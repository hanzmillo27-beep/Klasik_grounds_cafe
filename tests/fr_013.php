<?php
require __DIR__ . '/product_fixture.php';

function productActive($product): bool {
    return in_array($product->is_active ?? null, [true, 1, '1', 't'], true);
}

function productStatusRequest($id, $active, string $action = 'status', string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['is_active' => $active];
    $_FILES = [];
    http_response_code(200);
    try {
        (new TestProductsController())->$action($id);
        throw new LogicException('Changing product status must return a JSON result.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

$products = new Models\Products();
check($products->save(productPayload(['product_image' => 'products/sold-product.jpg'])), 'Could not create a product.');
check(productActive($products->findByID(1)), 'New products must be active by default.');
$recipeSnapshot = json_encode($products->findByID(1)->ingredients);

// A historical sale retains a foreign key to the original product identity.
$db->exec('CREATE TABLE sales (sale_id INTEGER PRIMARY KEY, total_amount NUMERIC(10,2) NOT NULL)');
$db->exec('CREATE TABLE sale_items (
    sale_item_id INTEGER PRIMARY KEY, sale_id INTEGER NOT NULL REFERENCES sales(sale_id),
    product_id INTEGER NOT NULL REFERENCES products(product_id),
    quantity INTEGER NOT NULL, unit_price NUMERIC(10,2) NOT NULL
)');
$db->exec('INSERT INTO sales (sale_id, total_amount) VALUES (1, 251.00)');
$db->exec('INSERT INTO sale_items (sale_item_id, sale_id, product_id, quantity, unit_price) VALUES (1, 1, 1, 2, 125.50)');

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Products', 'status');
$result = productStatusRequest('1', '0');
check(($result['status'] ?? '') === 'success', 'A sold product must be deactivated successfully.');
$inactive = $products->findByID(1);
check($inactive && !productActive($inactive), 'Deactivation must retain an inactive product row.');
check($inactive->product_name === 'Americano' && $inactive->product_image === 'products/sold-product.jpg'
    && abs((float) $inactive->product_price - 125.50) < 0.00001,
    'Deactivation must preserve the product details and image.');
check(json_encode($inactive->ingredients) === $recipeSnapshot, 'Deactivation must preserve the complete recipe.');
check((int) $db->query('SELECT COUNT(*) FROM sale_items WHERE product_id = 1')->fetchColumn() === 1
    && (int) $db->query('SELECT quantity FROM sale_items WHERE product_id = 1')->fetchColumn() === 2,
    'Deactivation must preserve transaction history and its product reference.');

check($products->save(productPayload(['product_id' => 1, 'is_active' => true])), 'Ordinary product editing failed.');
check(!productActive($products->findByID(1)), 'Ordinary editing must not bypass product status changes.');
$result = productStatusRequest('1', '1');
check(($result['status'] ?? '') === 'success' && productActive($products->findByID(1)),
    'Reactivation must restore the same product identity.');

foreach ([['99999', '0'], ['not-an-id', '0'], ['0', '0'], ['1', 'invalid'], ['1', []], ['1', null]] as [$id, $active]) {
    $result = productStatusRequest($id, $active);
    check(($result['status'] ?? '') === 'error', 'Invalid product status input was accepted.');
    check(productActive($products->findByID(1)), 'Rejected status input changed the product.');
}
$result = productStatusRequest('1', '0', 'status', 'GET');
check(($result['status'] ?? '') === 'error' && http_response_code() === 405
    && productActive($products->findByID(1)), 'GET must not deactivate a product.');
$result = productStatusRequest('1', '0', 'delete', 'GET');
check(($result['status'] ?? '') === 'error' && productActive($products->findByID(1)),
    'The delete route must reject GET without changing the product.');
check($products->setActive(1, true) && productActive($products->findByID(1)), 'Model reactivation failed.');
check(!$products->setActive(99999, false), 'Status changes to nonexistent products must fail.');
check((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1
    && json_encode($products->findByID(1)->ingredients) === $recipeSnapshot,
    'Status changes must retain product, recipe, and sales history.');
check((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0,
    'Deactivating a product must not change ingredient stock.');

echo "FR-013 passed: reversible product deactivation, retained recipes and sales, validated status routes.\n";
