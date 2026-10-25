<?php
require __DIR__ . '/pos_fixture.php';

function kioskQueueRequest(string $method = 'GET'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try {
        (new TestPOSController())->kioskOrders();
        throw new LogicException('The kiosk queue must return JSON.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

function kioskQueueIds(array $orders): array {
    return array_map(function ($order) { return (int) $order->kiosk_order_id; }, $orders);
}

function kioskQueueSnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['kiosk_orders', 'kiosk_order_items', 'sales', 'sale_items', 'inventory'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function kioskQueueOrder(PDO $db, string $reference, string $created, string $status, ?int $claimedBy, array $quantities): int {
    $total = 0;
    foreach ($quantities as $productId => $quantity) $total += ($productId === 1 ? 125.50 : 12.25) * $quantity;
    $stmt = $db->prepare('INSERT INTO kiosk_orders
        (reference_code, customer_name, total_amount, created_at, status, claimed_by)
        VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$reference, 'Customer ' . $reference, $total, $created, $status, $claimedBy]);
    $orderId = (int) $db->lastInsertId();
    $stmt = $db->prepare('INSERT INTO kiosk_order_items
        (kiosk_order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($quantities as $productId => $quantity) {
        $price = $productId === 1 ? 125.50 : 12.25;
        $stmt->execute([$orderId, $productId, 'Product ' . $productId, $quantity, $price, $price * $quantity]);
    }
    return $orderId;
}

$kiosk = new Models\Kiosk();
Core\Auth::authorizeRoute('Api', 'POS', 'kioskOrders');
check($kiosk->queue(2) === [] && kioskQueueRequest() === ['status' => 'success', 'data' => []],
    'An empty kiosk queue must return a successful empty JSON list.');
check((new Models\Users())->save(['username' => 'queue-other-cashier', 'password' => 'password', 'role' => 'cashier']),
    'Could not create the other cashier.');
check((new Models\Products())->save(productPayload([
    'product_name' => 'Queue tea', 'product_price' => '12.25', 'ingredient_quantity' => ['1.000'],
])), 'Could not create the second queue product.');

$later = kioskQueueOrder($db, 'KG-LATER', '2026-10-25 10:03:00', 'pending', null, [1 => 2, 2 => 3]);
$own = kioskQueueOrder($db, 'KG-OWN', '2026-10-25 10:01:00', 'claimed', 2, [1 => 1]);
$other = kioskQueueOrder($db, 'KG-OTHER', '2026-10-25 09:59:00', 'claimed', 3, [1 => 2]);
kioskQueueOrder($db, 'KG-COMPLETED', '2026-10-25 09:58:00', 'completed', 2, [1 => 1]);
kioskQueueOrder($db, 'KG-CANCELLED', '2026-10-25 09:57:00', 'cancelled', 2, [1 => 1]);
$tie = kioskQueueOrder($db, 'KG-TIE', '2026-10-25 10:03:00', 'pending', null, [2 => 1]);
$earlier = kioskQueueOrder($db, 'KG-EARLIER', '2026-10-25 10:00:00', 'pending', null, [1 => 1]);
$snapshot = kioskQueueSnapshot($db);
$queue = $kiosk->queue(2);
check(kioskQueueIds($queue) === [$earlier, $own, $later, $tie],
    'The queue must include pending and own claimed orders, oldest first with stable ID ordering for tied times.');
check(array_keys(get_object_vars($queue[2])) === [
    'kiosk_order_id', 'reference_code', 'customer_name', 'total_amount', 'created_at', 'status', 'item_count',
] && $queue[2]->reference_code === 'KG-LATER' && $queue[2]->customer_name === 'Customer KG-LATER'
    && (float) $queue[2]->total_amount === 287.75 && $queue[2]->item_count === 5
    && $queue[1]->status === 'claimed' && $queue[1]->item_count === 1,
    'Queue summaries must expose only their display fields and sum quantities across all order lines.');

$_GET = ['user_id' => 3];
$response = kioskQueueRequest();
check($response['status'] === 'success' && json_encode($response['data']) === json_encode($queue)
    && http_response_code() === 200, 'The queue API must return JSON for the signed-in cashier, ignoring a requested user ID.');
$_GET = [];
foreach (['POST', 'PUT', 'DELETE'] as $method) {
    $response = kioskQueueRequest($method);
    check($response['status'] === 'error' && http_response_code() === 405, 'Queue requests must use GET.');
}
check(kioskQueueSnapshot($db) === $snapshot && !$db->inTransaction(),
    'Reading the queue or rejecting a method must not change orders, sales, items, or inventory.');

$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'POS', 'kioskOrders');
check(kioskQueueIds(kioskQueueRequest()['data']) === [$earlier, $later, $tie],
    'Owners may read pending orders without exposing another cashier\'s claims.');
$_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];
Core\Auth::authorizeRoute('Api', 'POS', 'kioskOrders');
$claimed = $kiosk->claim('KG-EARLIER', 2);
check($claimed->status === 'claimed' && kioskQueueIds($kiosk->queue(2)) === [$earlier, $own, $later, $tie]
    && kioskQueueIds($kiosk->queue(3)) === [$other, $later, $tie],
    'Claiming an order must retain it for its cashier and remove it from other cashiers\' queues.');
$recovered = $kiosk->claim('KG-EARLIER', 2);
check((int) $recovered->kiosk_order_id === $earlier && $recovered->status === 'claimed',
    'An own claimed queue order must remain recoverable after a reload.');
$sale = (new Models\POS())->checkout([
    'checkout_key' => 'kiosk-queue-complete', 'kiosk_order_id' => $earlier,
    'amount_paid' => '200.00', 'items' => [['product_id' => 1, 'quantity' => 1]],
]);
check($kiosk->findByReference('KG-EARLIER')->status === 'completed' && (int) $sale['sale_id'] > 0
    && kioskQueueIds($kiosk->queue(2)) === [$own, $later, $tie]
    && kioskQueueIds($kiosk->queue(3)) === [$other, $later, $tie],
    'A completed kiosk order must disappear from every cashier queue.');

foreach (['owner' => true, 'cashier' => true, 'admin' => false, 'staff' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 2, 'role' => $role];
    check(Core\Auth::canAccess('POS', 'kioskOrders', 'Api') === $allowed, 'Incorrect kiosk queue permission for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('POS', 'kioskOrders', 'Api'), 'Guests must not access the cashier queue.');

echo "Kiosk queue passed: scoped access, oldest-first summaries, quantity totals, read-only GET API, claim recovery and completion removal.\n";
