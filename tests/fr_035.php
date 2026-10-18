<?php
require __DIR__ . '/product_fixture.php';

$inventory = new Models\Inventory();
foreach ([['Low flour', '0.001', '0.002'], ['Equal milk', '5', '5'], ['Above syrup', '5.001', '5'], ['Empty salt', '0', '0']] as [$name, $amount, $minimum]) {
    check($inventory->save(['inv_name' => $name, 'amount' => $amount, 'unit' => 'g', 'min_stock' => $minimum]),
        'Could not create the report inventory.');
}
$reports = new Models\Reports();
$before = time();
$report = $reports->inventory();
$generated = new DateTimeImmutable($report['generated_at'], new DateTimeZone('Asia/Manila'));
check($report['report_type'] === 'inventory' && !empty($report['label'])
    && $generated->getTimestamp() >= $before - 1 && $generated->getTimestamp() <= time() + 1
    && (int) $report['total_items'] === 5 && (int) $report['low_stock_count'] === 3,
    'Inventory reports must identify their current Manila generation time and correct stock counts.');
$rows = [];
foreach ($report['rows'] as $item) {
    foreach (['inv_id', 'inv_name', 'amount', 'unit', 'min_stock', 'is_low'] as $field) {
        check(property_exists($item, $field), 'The inventory report omitted ' . $field . '.');
    }
    check(is_bool($item->is_low), 'The low-stock flag must be a boolean.');
    $rows[$item->inv_name] = $item;
}
check((int) round((float) $rows['Low flour']->amount * 1000) === 1 && $rows['Low flour']->is_low
    && $rows['Equal milk']->is_low && !$rows['Above syrup']->is_low
    && (float) $rows['Empty salt']->amount === 0.0 && $rows['Empty salt']->is_low
    && !$rows['Beans']->is_low, 'Reports must retain exact fractional and zero amounts with per-item minimum flags.');
check($inventory->restock($rows['Low flour']->inv_id, '0.002'), 'Could not replenish the low-stock item.');
$report = $reports->inventory();
check((int) $report['low_stock_count'] === 2, 'Inventory reports must reflect replenishment immediately.');
$snapshot = json_encode($inventory->find());

class TestInventoryReportController extends Api\Reports\ReportsController {
    public function json($data): void { throw new JsonResponse($data); }
}
function inventoryReportRequest(string $method = 'GET'): array {
    $_SERVER['REQUEST_METHOD'] = $method; http_response_code(200);
    try { (new TestInventoryReportController())->inventory(); throw new LogicException('Inventory reports must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'Reports', 'inventory');
$response = inventoryReportRequest();
check(($response['status'] ?? '') === 'success' && json_encode($response['data']['rows']) === $snapshot
    && (int) $response['data']['total_items'] === 5 && (int) $response['data']['low_stock_count'] === 2,
    'The inventory API must return the current rows, quantities, and counts.');
$response = inventoryReportRequest('POST');
check(($response['status'] ?? '') === 'error' && http_response_code() === 405,
    'Inventory reports must reject methods other than GET.');
check(json_encode($inventory->find()) === $snapshot, 'Generating inventory reports must not change stock.');

(new Models\Users())->save(['username' => 'inventory-reader', 'password' => 'password', 'role' => 'staff']);
$_SESSION['user'] = ['id' => 2, 'role' => 'staff'];
Core\Auth::authorizeRoute('Controllers', 'Reports', 'index');
Core\Auth::authorizeRoute('Api', 'Reports', 'inventory');
foreach (['owner', 'staff', 'admin', 'cashier', 'user', 'loyal_customer'] as $role) {
    $_SESSION['user'] = ['id' => 1, 'role' => $role];
    $inventoryAllowed = in_array($role, ['owner', 'staff'], true);
    check(Core\Auth::canAccess('Reports', 'index', 'Controllers') === $inventoryAllowed
        && Core\Auth::canAccess('Reports', 'inventory', 'Api') === $inventoryAllowed
        && Core\Auth::canAccess('Reports', 'sales', 'Api') === ($role === 'owner')
        && Core\Auth::canAccess('Reports', 'products', 'Api') === ($role === 'owner'),
        'Reporting must allow Staff inventory access while keeping financial reports owner-only.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('Reports', 'inventory', 'Api'), 'Guests must not access inventory reports.');

echo "FR-035 passed: current inventory report, accurate minimum flags, read-only API and scoped Staff permissions.\n";
