<?php
require __DIR__ . '/pos_fixture.php';

function receiptRequest($id): array {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    try {
        (new TestPOSController())->transaction($id);
        throw new LogicException('A receipt request must return JSON.');
    } catch (JsonResponse $response) { return $response->data; }
}

$pos = new Models\POS();
$result = $pos->checkout(['checkout_key' => checkoutTestKey(), 'customer_name' => 'Receipt customer',
    'amount_paid' => '300.00', 'items' => [['product_id' => 1, 'quantity' => 2]]]);
$sale = $pos->transaction($result['sale_id']);
check($sale && (int) $sale->sale_id === (int) $result['sale_id'] && strtotime($sale->sale_date) !== false
    && $sale->user_name === 'test-cashier' && $sale->customer_name === 'Receipt customer',
    'A receipt must include its transaction ID, date, cashier, and customer.');
check(count($sale->items) === 1 && $sale->items[0]->product_name === 'Americano'
    && (int) $sale->items[0]->quantity === 2
    && (int) round((float) $sale->items[0]->unit_price * 100) === 12550
    && (int) round((float) $sale->items[0]->subtotal * 100) === 25100
    && (int) round((float) $sale->total_amount * 100) === 25100
    && (int) round((float) $sale->amount_paid * 100) === 30000
    && (int) round((float) $sale->change_amount * 100) === 4900,
    'A receipt must include item names, quantities, prices, subtotals, total, paid amount, and change.');
Core\Auth::authorizeRoute('Api', 'POS', 'transaction');
$response = receiptRequest($result['sale_id']);
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === json_encode($sale),
    'The receipt API must return the complete saved transaction.');
$snapshot = json_encode($sale);
$db->exec("UPDATE products SET product_name = 'Renamed catalog product', product_price = 999 WHERE product_id = 1");
check((new Models\Products())->setActive(1, false), 'Could not deactivate the sold product.');
$response = receiptRequest($result['sale_id']);
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === $snapshot,
    'Product renaming, repricing, and deactivation must preserve historical receipt details.');
foreach (['', 0, 99999] as $id) {
    $response = receiptRequest($id);
    check(($response['status'] ?? '') === 'error' && !empty($response['message']),
        'Missing or unknown receipt IDs must return an understandable error.');
}
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1,
    'Reading receipts must not change saved transactions.');

echo "FR-024 passed: complete paid receipt data, API access, historical snapshots, missing receipt errors.\n";
