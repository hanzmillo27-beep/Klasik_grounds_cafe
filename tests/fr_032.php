<?php
require __DIR__ . '/pos_fixture.php';

$insert = $db->prepare('INSERT INTO sales (user_id, total_amount, amount_paid, change_amount, sale_date) VALUES (2, ?, ?, 0, ?)');
foreach ([['2026-10-18 23:59:59.999', '1000'], ['2026-10-19 00:00:00', '10.01'],
    ['2026-10-25 23:59:59.999', '20.02'], ['2026-10-26 00:00:00', '1000'],
    ['2020-12-27 23:59:59.999', '1000'], ['2020-12-28 00:00:00', '1.01'],
    ['2021-01-03 23:59:59.999', '2.02'], ['2021-01-04 00:00:00', '1000']] as [$date, $total]) {
    $insert->execute([$total, $total, $date]);
}
$reports = new Models\Reports();
$weekly = $reports->sales('weekly', '2026-W43');
$ids = array_map(function ($row) { return (int) $row->sale_id; }, $weekly['rows']);
sort($ids);
check($weekly['period'] === 'weekly' && $weekly['start_date'] === '2026-10-19'
    && $weekly['end_date'] === '2026-10-25' && !empty($weekly['label']) && $ids === [2, 3]
    && (int) $weekly['transaction_count'] === 2 && (int) round((float) $weekly['total_amount'] * 100) === 3003,
    'A weekly report must include Monday through Sunday and exclude adjacent weeks.');
$crossYear = $reports->sales('weekly', '2020-W53');
$ids = array_map(function ($row) { return (int) $row->sale_id; }, $crossYear['rows']);
sort($ids);
check($crossYear['start_date'] === '2020-12-28' && $crossYear['end_date'] === '2021-01-03'
    && $ids === [6, 7] && (int) $crossYear['transaction_count'] === 2
    && (int) round((float) $crossYear['total_amount'] * 100) === 303,
    'An ISO week that crosses New Year must use its ISO week-year boundaries.');
$empty = $reports->sales('weekly', '2026-W45');
check($empty['rows'] === [] && (int) $empty['transaction_count'] === 0 && (float) $empty['total_amount'] === 0.0,
    'An empty week must return no transactions and zero totals.');

class TestWeeklySalesReportController extends Api\Reports\ReportsController {
    public function json($data): void { throw new JsonResponse($data); }
}
function weeklyRequest($week): array {
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['period' => 'weekly', 'date' => $week]; http_response_code(200);
    try { (new TestWeeklySalesReportController())->sales(); throw new LogicException('Weekly reports must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'Reports', 'sales');
$response = weeklyRequest('2026-W43');
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === json_encode($weekly),
    'The API must return the selected ISO weekly report.');
foreach (['2021-W53', '2026-W00', '2026-W54', '2026-W1', "2026-W43' OR 1=1 --", []] as $week) {
    $response = weeklyRequest($week);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400,
        'Invalid or nonexistent ISO weeks must return HTTP 400.');
}
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 8,
    'Generating weekly reports must not change transactions.');

echo "FR-032 passed: ISO weekly and cross-year boundaries, totals, empty reports and strict week validation.\n";
