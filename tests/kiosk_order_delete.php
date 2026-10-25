<?php
require __DIR__ . '/pos_fixture.php';

function deleteKioskOrderRequest($id = null, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try {
        (new TestPOSController())->deleteKioskOrder($id);
        throw new LogicException('Deleting a kiosk order must return JSON.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

function deleteOrderFixture(Models\Kiosk $kiosk, string $name) {
    $submitted = $kiosk->submit(['customer_name' => $name, 'items' => [['product_id' => 1, 'quantity' => 1]]]);
    return $kiosk->findByReference($submitted['reference_code']);
}

function deleteOrderSnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['kiosk_orders', 'kiosk_order_items', 'sales', 'sale_items', 'inventory', 'loyal', 'loyalty_earnings'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function deleteOrderQueueIds(Models\Kiosk $kiosk, int $userId): array {
    return array_map(function ($order) { return (int) $order->kiosk_order_id; }, $kiosk->queue($userId));
}

$kiosk = new Models\Kiosk();
Core\Auth::authorizeRoute('Api', 'POS', 'deleteKioskOrder');
check((new Models\Users())->save(['username' => 'delete-other-cashier', 'password' => 'password', 'role' => 'cashier']),
    'Could not create another cashier.');
$pending = deleteOrderFixture($kiosk, 'Delete pending');
$own = deleteOrderFixture($kiosk, 'Delete own claim');
$kiosk->claim($own->reference_code, 2);
$other = deleteOrderFixture($kiosk, 'Keep another claim');
$kiosk->claim($other->reference_code, 3);
$ownerPending = deleteOrderFixture($kiosk, 'Owner pending');
$ownerClaim = deleteOrderFixture($kiosk, 'Owner claim');
$kiosk->claim($ownerClaim->reference_code, 1);
$completed = deleteOrderFixture($kiosk, 'Keep completed');
$db->prepare('UPDATE kiosk_orders SET status = ? WHERE kiosk_order_id = ?')->execute(['completed', $completed->kiosk_order_id]);
$failed = deleteOrderFixture($kiosk, 'Rollback order');
$itemsBefore = json_encode($db->query('SELECT * FROM kiosk_order_items ORDER BY kiosk_item_id')->fetchAll());
$orderCount = (int) $db->query('SELECT COUNT(*) FROM kiosk_orders')->fetchColumn();

foreach ([$pending, $own] as $order) {
    $response = deleteKioskOrderRequest($order->kiosk_order_id);
    check($response === ['status' => 'success', 'message' => 'Kiosk order deleted.'] && http_response_code() === 200,
        'The cashier must be able to delete a pending order or their own claimed order.');
    $saved = $kiosk->findByReference($order->reference_code);
    check($saved->status === 'cancelled' && count($saved->items) === 1 && $saved->sale_id === null
        && !in_array((int) $order->kiosk_order_id, deleteOrderQueueIds($kiosk, 2), true)
        && !in_array((int) $order->kiosk_order_id, deleteOrderQueueIds($kiosk, 3), true),
        'Deleting an unpaid order must preserve its history and items while removing it from every queue.');
}
$snapshot = deleteOrderSnapshot($db);
foreach ([$pending->kiosk_order_id, $other->kiosk_order_id, $ownerClaim->kiosk_order_id, $completed->kiosk_order_id, 999999] as $id) {
    $response = deleteKioskOrderRequest($id);
    check($response['status'] === 'error' && http_response_code() === 409 && !$db->inTransaction(),
        'Already deleted, missing, completed or another cashier\'s claimed orders must reject deletion and close the transaction.');
}
check(deleteOrderSnapshot($db) === $snapshot, 'Rejected deletions must preserve every order, sale, item and inventory amount.');

$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'POS', 'deleteKioskOrder');
$response = deleteKioskOrderRequest($other->kiosk_order_id);
check($response['status'] === 'error' && http_response_code() === 409
    && $kiosk->findByReference($other->reference_code)->status === 'claimed',
    'An owner must not delete an order claimed by another cashier.');
foreach ([$ownerPending, $ownerClaim] as $order) {
    check(deleteKioskOrderRequest($order->kiosk_order_id)['status'] === 'success'
        && $kiosk->findByReference($order->reference_code)->status === 'cancelled',
        'Owners must be able to delete pending orders and their own claims.');
}
$_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];
Core\Auth::authorizeRoute('Api', 'POS', 'deleteKioskOrder');
$snapshot = deleteOrderSnapshot($db);
foreach ([null, '', 'abc', '1.5', '1 OR 1=1', 0, -1, []] as $id) {
    $response = deleteKioskOrderRequest($id);
    check($response['status'] === 'error' && http_response_code() === 400, 'Invalid or missing kiosk order IDs must return HTTP 400.');
}
foreach (['GET', 'PUT', 'DELETE'] as $method) {
    $response = deleteKioskOrderRequest($failed->kiosk_order_id, $method);
    check($response['status'] === 'error' && http_response_code() === 405, 'Kiosk order deletion must require POST.');
}
check(deleteOrderSnapshot($db) === $snapshot && !$db->inTransaction(), 'Invalid deletion requests must not change stored data.');

$db->exec('CREATE TRIGGER fail_order_delete BEFORE UPDATE OF status ON kiosk_orders
    WHEN NEW.status = \'cancelled\' AND OLD.kiosk_order_id = ' . (int) $failed->kiosk_order_id . '
    BEGIN UPDATE inventory SET amount = amount - 1 WHERE inv_id = 1;
        SELECT RAISE(FAIL, \'Injected delete failure\'); END');
$response = deleteKioskOrderRequest($failed->kiosk_order_id);
check($response['status'] === 'error' && http_response_code() === 409 && !$db->inTransaction()
    && deleteOrderSnapshot($db) === $snapshot, 'A failed cancellation must roll back the order and all transaction side effects.');
$db->exec('DROP TRIGGER fail_order_delete');

check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 0
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0
    && (int) $db->query('SELECT COUNT(*) FROM kiosk_orders')->fetchColumn() === $orderCount
    && json_encode($db->query('SELECT * FROM kiosk_order_items ORDER BY kiosk_item_id')->fetchAll()) === $itemsBefore,
    'Deleting kiosk orders must never create sales, deduct stock, or erase order/item history.');

$db->prepare('INSERT INTO sales (user_id, total_amount, sale_date) VALUES (2, 125.50, ?)')->execute(['2026-10-25 12:00:00']);
$saleId = (int) $db->lastInsertId();
$db->prepare('UPDATE kiosk_orders SET sale_id = ? WHERE kiosk_order_id = ?')->execute([$saleId, $failed->kiosk_order_id]);
$snapshot = deleteOrderSnapshot($db);
$response = deleteKioskOrderRequest($failed->kiosk_order_id);
check($response['status'] === 'error' && http_response_code() === 409 && !$db->inTransaction()
    && deleteOrderSnapshot($db) === $snapshot, 'An order linked to a sale must reject deletion even if its status still says pending.');

foreach (['owner' => true, 'cashier' => true, 'admin' => false, 'staff' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 2, 'role' => $role];
    check(Core\Auth::canAccess('POS', 'deleteKioskOrder', 'Api') === $allowed, 'Incorrect kiosk order deletion permission for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('POS', 'deleteKioskOrder', 'Api'), 'Guests must not delete kiosk orders.');

echo "Kiosk order deletion passed: pending/own-claim cancellation, history preservation, access/status/method guards and rollback without sale or stock changes.\n";
