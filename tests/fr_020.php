<?php
require __DIR__ . '/pos_fixture.php';

check((new Models\Products())->save(productPayload([
    'product_name' => 'Candy', 'product_price' => '0.10',
    'ingredient_name' => [], 'ingredient_quantity' => [],
])), 'Could not create a fractional-price product.');
$pos = new Models\POS();
$cart = [
    ['product_id' => 1, 'quantity' => 2, 'unit_price' => '0.01', 'subtotal' => '0.02'],
    ['product_id' => 2, 'quantity' => 3, 'unit_price' => '999', 'subtotal' => '2997'],
];
$result = $pos->checkout(['amount_paid' => '1000.00', 'items' => $cart, 'expected_total' => '251.30', 'total_amount' => '0.01']);
$sale = $pos->transaction($result['sale_id']);
check((int) round((float) $result['total_amount'] * 100) === 25130
    && (int) round((float) $sale->total_amount * 100) === 25130,
    'The total must equal 125.50 times 2 plus 0.10 times 3 in exact cents.');
check((int) round((float) $sale->items[0]->subtotal * 100) === 25100
    && (int) round((float) $sale->items[1]->subtotal * 100) === 30
    && (int) round((float) $sale->items[0]->unit_price * 100) === 12550
    && (int) round((float) $sale->items[1]->unit_price * 100) === 10,
    'Line subtotals and unit prices must use trusted database prices.');

function rejectInvalidTotal(Models\POS $pos, array $payload, ?string $message = null): void {
    $rejected = false;
    try { $pos->checkout($payload + ['amount_paid' => '99999999.99']); }
    catch (PDOException $error) { throw new RuntimeException('Invalid totals must fail validation before database writes.', 0, $error); }
    catch (Exception $error) {
        $rejected = true;
        if ($message !== null) check($error->getMessage() === $message, 'A changed total must ask the cashier to review prices.');
    }
    $db = Core\Database::$connection;
    check($rejected && !$db->inTransaction()
        && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
        && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 2
        && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
        'Rejected totals must not create sales, items, or stock deductions.');
}

rejectInvalidTotal($pos, ['items' => $cart, 'expected_total' => '251.29'],
    'Order total changed. Review the current prices before checkout.');
foreach (['-0.01', 'NaN'] as $price) {
    $db->prepare('UPDATE products SET product_price = ? WHERE product_id = 2')->execute([$price]);
    rejectInvalidTotal($pos, ['items' => [['product_id' => 2, 'quantity' => 1]]]);
}
$db->exec('UPDATE products SET product_price = 99999999.99 WHERE product_id = 2');
rejectInvalidTotal($pos, ['items' => [['product_id' => 2, 'quantity' => 2]]]);

echo "FR-020 passed: totals in cents, trusted prices, stale-total check, invalid and overflowing amounts rejected.\n";
