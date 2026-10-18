<?php
require __DIR__ . '/pos_fixture.php';

$insert = $db->prepare('INSERT INTO sales (user_id, customer_name, total_amount, amount_paid, change_amount, sale_date)
    VALUES (?, ?, ?, ?, ?, ?)');
foreach ([['2026-10-18 23:59:59.999', '1000', '1000', '0'], ['2026-10-19 00:00:00', '10.10', '20', '9.90'],
    ['2026-10-19 23:59:59.999', '20.20', null, null], ['2026-10-20 00:00:00', '1000', '1000', '0']] as [$date, $total, $paid, $change]) {
    $insert->execute([2, 'Report customer', $total, $paid, $change, $date]);
}
$reports = new Models\Reports();
$daily = $reports->sales('daily', '2026-10-19');
check($daily['period'] === 'daily' && $daily['start_date'] === '2026-10-19' && $daily['end_date'] === '2026-10-19'
    && !empty($daily['label']) && (int) $daily['transaction_count'] === 2
    && (int) round((float) $daily['total_amount'] * 100) === 3030,
    'Daily reporting must include exactly the selected Manila calendar day and its sales total.');
$ids = [];
$legacyFound = false;
foreach ($daily['rows'] as $row) {
    $ids[] = (int) $row->sale_id;
    check(str_starts_with($row->sale_date, '2026-10-19') && $row->user_name === 'test-cashier'
        && $row->customer_name === 'Report customer', 'Report rows must include the selected date, cashier, and customer.');
    if ((int) $row->sale_id === 2) check((float) $row->amount_paid === 20.0 && (float) $row->change_amount === 9.9,
        'Paid amount and change must be included in report rows.');
    if ((int) $row->sale_id === 3) $legacyFound = $row->amount_paid === null && $row->change_amount === null;
}
sort($ids);
check($ids === [2, 3] && $legacyFound, 'Midnight boundaries and legacy nullable payment records must be handled correctly.');
$empty = $reports->sales('daily', '2026-10-21');
check($empty['rows'] === [] && (int) $empty['transaction_count'] === 0 && (float) $empty['total_amount'] === 0.0,
    'A day without sales must return an empty report and zero totals.');

class TestSalesReportController extends Api\Reports\ReportsController {
    public function json($data): void { throw new JsonResponse($data); }
}
function dailyReportRequest(array $query): array {
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = $query; http_response_code(200);
    try { (new TestSalesReportController())->sales(); throw new LogicException('Report requests must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'Reports', 'sales');
$response = dailyReportRequest(['period' => 'daily', 'date' => '2026-10-19']);
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === json_encode($daily),
    'The reporting API must return the selected daily report.');
foreach (['2026-02-30', '2026-1-1', "2026-10-19' OR 1=1 --", []] as $date) {
    $response = dailyReportRequest(['period' => 'daily', 'date' => $date]);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400, 'Malformed report dates must return HTTP 400.');
}
foreach (['invalid', []] as $period) {
    $response = dailyReportRequest(['period' => $period, 'date' => '2026-10-19']);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400, 'Malformed periods must return HTTP 400.');
}
foreach (['owner' => true, 'admin' => false, 'staff' => false, 'cashier' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 1, 'role' => $role];
    check(Core\Auth::canAccess('Reports', 'sales', 'Api') === $allowed, 'Incorrect reporting permission for ' . $role . '.');
}
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 4, 'Reports must not change sales records.');

echo "FR-031 passed: daily date boundaries, totals, cashier/payment data, empty reports and strict owner-only requests.\n";
