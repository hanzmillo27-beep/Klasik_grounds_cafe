<?php
require __DIR__ . '/pos_fixture.php';

check((new Models\Products())->save(productPayload([
    'product_name' => 'Latte', 'product_price' => '150', 'ingredient_quantity' => ['5'],
])), 'Could not create a second product.');
$pos = new Models\POS();
$kiosk = new Models\Kiosk();
$result = $pos->checkout(['items' => [
    ['product_id' => 1, 'quantity' => 2, 'unit_price' => 1],
    ['product_id' => 2, 'quantity' => 3, 'unit_price' => 1],
]]);
$sale = $pos->transaction($result['sale_id']);
check(count($sale->items) === 2 && abs((float) $sale->total_amount - 701) < 0.00001,
    'An order must retain multiple products at their database prices.');
check((int) $sale->items[0]->product_id === 1 && (int) $sale->items[0]->quantity === 2
    && (int) $sale->items[1]->product_id === 2 && (int) $sale->items[1]->quantity === 3,
    'Each selected product and its quantity must be saved.');
check(abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 48.5) < 0.00001,
    'The order must deduct ingredients for every selected quantity.');

$invalidCarts = [
    [], 'not-an-array', ['invalid-row'], [true],
    [['product_id' => 1.5, 'quantity' => 1]],
    [['product_id' => 0, 'quantity' => 1]],
    [['product_id' => 99999, 'quantity' => 1]],
    [['product_id' => 1, 'quantity' => 0]],
    [['product_id' => 1, 'quantity' => -1]],
    [['product_id' => 1, 'quantity' => 1.5]],
    [['product_id' => 1, 'quantity' => 100]],
    [['product_id' => 1, 'quantity' => '1e1']],
    [['product_id' => [], 'quantity' => 1]],
    [['product_id' => 1, 'quantity' => []]],
    [['product_id' => 1, 'quantity' => 1], ['product_id' => 1, 'quantity' => 2]],
    array_fill(0, 101, ['product_id' => 1, 'quantity' => 1]),
];
foreach ($invalidCarts as $index => $items) {
    foreach (['POS' => $pos, 'Kiosk' => $kiosk] as $name => $model) {
        $rejected = false;
        try {
            $name === 'POS' ? $model->checkout(['items' => $items]) : $model->submit(['items' => $items]);
        } catch (PDOException | TypeError $error) {
            throw new RuntimeException($name . ' invalid cart ' . $index . ' must fail validation safely.', 0, $error);
        } catch (Exception $error) {
            $rejected = true;
        }
        check($rejected && !$db->inTransaction(), $name . ' accepted invalid cart ' . $index . ' or left a transaction open.');
        check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
            && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 2
            && (int) $db->query('SELECT COUNT(*) FROM kiosk_orders')->fetchColumn() === 0
            && (int) $db->query('SELECT COUNT(*) FROM kiosk_order_items')->fetchColumn() === 0
            && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 48.5) < 0.00001,
            'Rejected items must not create partial orders or deduct stock.');
    }
}

echo "FR-017 passed: multiple products and quantities, strict item validation, duplicate rejection and rollback.\n";
