<?php
require __DIR__ . '/product_fixture.php';

function editProduct($id, array $payload): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    $_FILES = [];
    http_response_code(200);
    try {
        (new TestProductsController())->edit($id);
        throw new LogicException('Product editing must return a JSON result.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

$db->exec("INSERT INTO categories (category_name) VALUES ('Specialty coffee')");
$db->exec("INSERT INTO inventory (inv_name, amount, unit) VALUES ('Milk', 200, 'ml')");
$products = new Models\Products();
check($products->save(productPayload(['product_image' => 'products/original.jpg'])), 'Could not create the original product.');
check($products->save(productPayload(['product_name' => 'Keep this product', 'product_image' => 'products/other.jpg'])),
    'Could not create the other product.');
$original = $products->findByID(1);
$otherSnapshot = json_encode($products->findByID(2));

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Products', 'edit');
$updatedPayload = productPayload([
    'product_name' => 'Cafe Latte',
    'category_id' => '2',
    'product_price' => '155.75',
    'ingredient_name' => ['Beans', 'Milk'],
    'ingredient_quantity' => ['20', '120'],
]);
$result = editProduct('1', $updatedPayload);
check(($result['status'] ?? '') === 'success', 'A valid product update was rejected.');
$updated = $products->findByID(1);
check($updated->product_name === 'Cafe Latte' && (int) $updated->category_id === 2
    && abs((float) $updated->product_price - 155.75) < 0.00001,
    'Updated product details were not saved.');
check($updated->product_image === 'products/original.jpg', 'Editing without an image must retain the current image.');
check($updated->created_at === $original->created_at, 'Editing must retain the product creation date.');
check(count($updated->ingredients) === 2 && $updated->ingredients[0]->inv_name === 'Beans'
    && abs((float) $updated->ingredients[0]->quantity_required - 20) < 0.00001
    && $updated->ingredients[1]->inv_name === 'Milk'
    && abs((float) $updated->ingredients[1]->quantity_required - 120) < 0.00001,
    'The replacement recipe must contain exactly the updated ingredients and quantities.');
check(json_encode($products->findByID(2)) === $otherSnapshot, 'Editing changed another product.');

$updatedSnapshot = json_encode($updated);
foreach ([
    ['product_name' => 'Reject unknown ingredient', 'ingredient_name' => ['Beans', 'Missing ingredient']],
    ['product_name' => 'Reject invalid quantity', 'ingredient_quantity' => ['20', '-1']],
    ['product_name' => 'Reject negative price', 'product_price' => '-1'],
] as $changes) {
    $result = editProduct('1', array_replace($updatedPayload, $changes));
    check(($result['status'] ?? '') === 'error', 'An invalid replacement was accepted.');
    check(json_encode($products->findByID(1)) === $updatedSnapshot,
        'A failed update must preserve the original details, image, and entire recipe.');
}

$saved = $products->save(array_replace($updatedPayload, [
    'product_id' => 1,
    'product_name' => 'Rollback partial replacement',
    'ingredient_name' => ['Beans', 'Missing ingredient'],
    'ingredient_quantity' => ['5', '3'],
]));
check($saved === false && json_encode($products->findByID(1)) === $updatedSnapshot,
    'The model must roll back details and recipe deletion when a replacement ingredient fails.');

foreach (['99999', 'not-a-number', '0'] as $id) {
    $result = editProduct($id, array_replace($updatedPayload, ['product_id' => 2]));
    check(($result['status'] ?? '') === 'error', 'A nonexistent or invalid target must be rejected.');
    check((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 2
        && json_encode($products->findByID(1)) === $updatedSnapshot
        && json_encode($products->findByID(2)) === $otherSnapshot,
        'Rejecting an invalid target must leave all products unchanged.');
}

$result = editProduct('1', array_replace($updatedPayload, ['product_id' => 2, 'product_name' => 'URL target updated']));
check(($result['status'] ?? '') === 'success' && $products->findByID(1)->product_name === 'URL target updated',
    'The edit URL must identify the product being updated.');
check(json_encode($products->findByID(2)) === $otherSnapshot,
    'A conflicting submitted product ID must not update another product.');
check((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 2')->fetchColumn() === 200.0,
    'Editing a recipe must not consume inventory.');

echo "FR-012 passed: product and recipe updates, image preservation, atomic rollback, target validation.\n";
