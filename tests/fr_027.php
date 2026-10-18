<?php
require __DIR__ . '/product_fixture.php';

class TestInventoryRestockController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}
function restockRequest($id, $amount, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['amount' => $amount];
    http_response_code(200);
    try {
        (new TestInventoryRestockController())->restock($id);
        throw new LogicException('Restocking must return JSON.');
    } catch (JsonResponse $response) { return $response->data; }
}

$inventory = new Models\Inventory();
check($inventory->restock(1, '0.001'), 'The minimum fractional replenishment must be accepted.');
(new Models\Users())->save(['username' => 'inventory-staff', 'password' => 'password', 'role' => 'staff']);
$_SESSION['user'] = ['id' => 2, 'role' => 'staff'];
$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Inventory', 'restock');
foreach (['0.009', '1.234'] as $amount) {
    $result = restockRequest('1', $amount);
    check(($result['status'] ?? '') === 'success', 'Inventory staff must be able to replenish fractional quantities.');
}
check((int) round((float) $inventory->findByID(1)->amount * 1000) === 101244,
    'Sequential replenishments must add to existing stock rather than replace it.');
foreach (['0', '-0.001', '0.0001', '1000000000', '1e3', 'NaN', INF, [], null] as $amount) {
    $result = restockRequest(1, $amount);
    check(($result['status'] ?? '') === 'error' && !$inventory->restock(1, $amount)
        && (int) round((float) $inventory->findByID(1)->amount * 1000) === 101244,
        'Invalid replenishment quantities must fail API/model validation without changing stock.');
}
foreach (['', 'invalid', 0, [], 99999] as $id) {
    $result = restockRequest($id, '1');
    check(($result['status'] ?? '') === 'error' && (int) round((float) $inventory->findByID(1)->amount * 1000) === 101244,
        'Missing, malformed, or unknown inventory IDs must not change stock.');
}
check(!$inventory->restock(99999, '1'), 'A missing inventory row must not report successful replenishment.');
$result = restockRequest(1, '1', 'GET');
check(($result['status'] ?? '') === 'error' && http_response_code() === 405, 'GET must not replenish stock.');
$db->exec('UPDATE inventory SET amount = 999999999.998 WHERE inv_id = 1');
check(($result = restockRequest(1, '0.001'))['status'] === 'success'
    && (int) round((float) $inventory->findByID(1)->amount * 1000) === 999999999999,
    'Replenishment may reach the maximum supported stock quantity.');
$result = restockRequest(1, '0.001');
check(($result['status'] ?? '') === 'error' && !$inventory->restock(1, '0.001')
    && (int) round((float) $inventory->findByID(1)->amount * 1000) === 999999999999,
    'An overflowing replenishment must fail atomically without changing stock.');
check(count($inventory->find()) === 1 && $inventory->findByID(1)->inv_name === 'Beans'
    && $inventory->findByID(1)->unit === 'g', 'Replenishment must retain the item identity and unit.');
foreach (['owner' => true, 'staff' => true, 'admin' => false, 'cashier' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 1, 'role' => $role];
    check(Core\Auth::canAccess('Inventory', 'restock', 'Api') === $allowed, 'Incorrect replenishment permission for ' . $role . '.');
}

echo "FR-027 passed: fractional stock replenishment, staff access, strict validation and atomic overflow protection.\n";
