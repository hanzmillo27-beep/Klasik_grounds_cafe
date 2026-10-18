<?php
require __DIR__ . '/product_fixture.php';

function availabilityRequest($id, $available, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['is_available' => $available];
    http_response_code(200);
    try {
        (new TestProductsController())->availability($id);
        throw new LogicException('Availability changes must return JSON.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

function rejectUnavailableOrder(callable $submit, PDO $db): void {
    $rejected = false;
    try {
        $submit();
    } catch (PDOException $error) {
        throw new RuntimeException('An unavailable order must fail product validation, not a database/schema error.', 0, $error);
    } catch (Exception $error) {
        $rejected = true;
        check(stripos($error->getMessage(), 'availab') !== false || stripos($error->getMessage(), 'no longer exists') !== false,
            'An unavailable order must fail with an understandable product error.');
    }
    check($rejected && !$db->inTransaction(), 'An unavailable order must be rejected and its transaction rolled back.');
}

$products = new Models\Products();
check($products->save(productPayload()), 'Could not create the product.');
check($products->findByID(1)->is_available === true, 'New products must be available by default.');
$recipeSnapshot = json_encode($products->findByID(1)->ingredients);
$pos = new Models\POS();
$kiosk = new Models\Kiosk();
check($products->setAvailable(1, false), 'Model availability change failed.');
$unavailable = $products->findByID(1);
check($unavailable->is_active === true && $unavailable->is_available === false,
    'Manual availability must be independent of product deactivation.');
check($pos->products() === [] && $kiosk->products() === [] && $pos->product(1) === null,
    'Active but unavailable products must be excluded from order lists and lookup.');
check(count($products->find()) === 1 && $products->find()[0]->is_available === false,
    'Management must retain the product and show its availability.');
check($products->save(productPayload(['product_id' => 1, 'is_available' => true]))
    && $products->findByID(1)->is_available === false,
    'Ordinary product editing must preserve manual availability.');

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Products', 'availability');
$result = availabilityRequest('1', '1');
check(($result['status'] ?? '') === 'success' && $products->findByID(1)->is_available === true
    && count($pos->products()) === 1 && count($kiosk->products()) === 1 && $pos->product(1) !== null,
    'Making the product available must restore its order lists and lookup.');
foreach ([['99999', '0'], ['invalid-id', '0'], ['0', '0'], ['1', 'invalid'], ['1', []], ['1', null]] as [$id, $value]) {
    $result = availabilityRequest($id, $value);
    check(($result['status'] ?? '') === 'error' && $products->findByID(1)->is_available === true,
        'Invalid availability changes must be rejected without changing the product.');
}
$result = availabilityRequest('1', '0', 'GET');
check(($result['status'] ?? '') === 'error' && http_response_code() === 405
    && $products->findByID(1)->is_available === true, 'GET must not change product availability.');

$result = availabilityRequest('1', '0');
check(($result['status'] ?? '') === 'success', 'The API could not mark a product unavailable.');
$cart = ['items' => [['product_id' => 1, 'quantity' => 1]]];
rejectUnavailableOrder(function () use ($kiosk, $cart) { $kiosk->submit($cart); }, $db);
rejectUnavailableOrder(function () use ($pos, $cart) { $pos->checkout($cart); }, $db);
check($products->setAvailable(1, true) && $products->setActive(1, false), 'Could not set an inactive but available product.');
check($pos->products() === [] && $kiosk->products() === [] && $pos->product(1) === null,
    'Setting availability must not bypass deactivation.');
rejectUnavailableOrder(function () use ($kiosk, $cart) { $kiosk->submit($cart); }, $db);
rejectUnavailableOrder(function () use ($pos, $cart) { $pos->checkout($cart); }, $db);
check(!$products->setAvailable(99999, false), 'A nonexistent product cannot have its availability changed.');
check(count($products->find()) === 1 && json_encode($products->findByID(1)->ingredients) === $recipeSnapshot
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0,
    'Availability changes and rejected orders must preserve product, recipe, and stock.');

echo "FR-015 passed: manual availability, validated toggles, filtered lists, rejected unavailable orders.\n";
