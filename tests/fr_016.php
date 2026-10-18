<?php
require __DIR__ . '/pos_fixture.php';

function checkoutRequest(array $payload, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $payload;
    http_response_code(200);
    try {
        (new TestPOSController())->checkout();
        throw new LogicException('Checkout must return a JSON result.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Controllers', 'POS', 'index');
Core\Auth::authorizeRoute('Api', 'POS', 'checkout');
check(Core\Auth::user()['role'] === 'cashier', 'The cashier session must be authorized to create an order.');
$result = checkoutRequest([
    'customer_name' => 'Walk-in customer',
    'items' => json_encode([['product_id' => 1, 'quantity' => 2]]),
]);
check(($result['status'] ?? '') === 'success' && (int) ($result['sale_id'] ?? 0) > 0,
    'The cashier could not create a new customer order.');
$sale = (new Models\POS())->transaction($result['sale_id']);
check($sale && (int) $sale->user_id === 2 && $sale->customer_name === 'Walk-in customer',
    'The order must retain its cashier and customer.');
check(abs((float) $sale->total_amount - 251) < 0.00001 && count($sale->items) === 1
    && (int) $sale->items[0]->product_id === 1 && (int) $sale->items[0]->quantity === 2
    && abs((float) $sale->items[0]->unit_price - 125.50) < 0.00001,
    'The order must retain its selected product, quantity, and database price.');
check(abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
    'The completed order must deduct the required ingredient quantity.');

foreach ([[], ['items' => '[]'], ['items' => 'not-json'], ['items' => ['invalid']]] as $payload) {
    $result = checkoutRequest($payload);
    check(($result['status'] ?? '') === 'error', 'Empty or malformed order data must be rejected.');
    check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
        && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1,
        'Rejected order data must not create sales or items.');
}
$result = checkoutRequest(['items' => json_encode([['product_id' => 1, 'quantity' => 1]])], 'GET');
check(($result['status'] ?? '') === 'error' && http_response_code() === 405,
    'GET must not create an order.');
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
    'Rejected orders must preserve sales and inventory.');

foreach (['owner' => true, 'cashier' => true, 'admin' => false, 'staff' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 2, 'role' => $role];
    check(Core\Auth::canAccess('POS', 'checkout', 'Api') === $allowed, 'Incorrect order-creation access for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('POS', 'checkout', 'Api'), 'Guests must not create cashier orders.');

echo "FR-016 passed: cashier order creation, stored items and inventory deduction, empty/malformed/method rejection.\n";
