<?php
require __DIR__ . '/product_fixture.php';

function addProduct(array $payload): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    $_FILES = [];
    http_response_code(200);
    try {
        (new TestProductsController())->add();
        throw new LogicException('Product creation must return a JSON result.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Products', 'add');
$result = addProduct(productPayload());
check(($result['status'] ?? '') === 'success', 'A valid product and recipe must be accepted.');
$product = $db->query("SELECT * FROM products WHERE product_name = 'Americano'")->fetch();
check($product && (int) $product->category_id === 1, 'Product category was not saved.');
check(abs((float) $product->product_price - 125.50) < 0.00001, 'Product price was not saved.');
$recipe = $db->query('SELECT * FROM product_ingredients')->fetchAll();
check(count($recipe) === 1 && (int) $recipe[0]->product_id === (int) $product->product_id
    && (int) $recipe[0]->inv_id === 1 && abs((float) $recipe[0]->quantity_required - 18.250) < 0.00001,
    'The selected ingredient and required quantity must be saved.');

$invalid = [
    'negative price' => ['product_price' => '-1'],
    'missing category' => ['category_id' => ''],
    'unknown category' => ['category_id' => '99999'],
    'unknown ingredient' => ['ingredient_name' => ['Missing ingredient']],
    'nonpositive ingredient quantity' => ['ingredient_quantity' => ['0']],
    'malformed ingredients' => ['ingredient_name' => 'Beans'],
];
foreach ($invalid as $label => $changes) {
    $result = addProduct(productPayload($changes + ['product_name' => 'Rejected ' . $label]));
    check(($result['status'] ?? '') === 'error', 'The API accepted ' . $label . '.');
    check((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 1,
        'Invalid product creation left an unwanted product.');
    check((int) $db->query('SELECT COUNT(*) FROM product_ingredients')->fetchColumn() === 1,
        'Invalid product creation changed existing recipes.');
}

$saved = (new Models\Products())->save(productPayload([
    'product_name' => 'Rollback invalid recipe',
    'ingredient_name' => ['Beans', 'Missing ingredient'],
    'ingredient_quantity' => ['2', '3'],
]));
check($saved === false, 'An invalid recipe must fail the model transaction.');
check((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM product_ingredients')->fetchColumn() === 1,
    'A failed recipe must roll back its product and ingredient inserts.');
check((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0,
    'Creating a recipe must not consume stock.');

echo "FR-011 passed: authorized product creation, persisted recipe, invalid input rejection, atomic rollback.\n";
