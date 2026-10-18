<?php
require __DIR__ . '/pos_fixture.php';

$cases = [
    ['2026-10', '2026-10-01', '2026-10-31', ['2026-09-30 23:59:59.999', '2026-10-01 00:00:00', '2026-10-31 23:59:59.999', '2026-11-01 00:00:00']],
    ['2024-02', '2024-02-01', '2024-02-29', ['2024-01-31 23:59:59.999', '2024-02-01 00:00:00', '2024-02-29 23:59:59.999', '2024-03-01 00:00:00']],
    ['2026-02', '2026-02-01', '2026-02-28', ['2026-01-31 23:59:59.999', '2026-02-01 00:00:00', '2026-02-28 23:59:59.999', '2026-03-01 00:00:00']],
    ['2026-12', '2026-12-01', '2026-12-31', ['2026-11-30 23:59:59.999', '2026-12-01 00:00:00', '2026-12-31 23:59:59.999', '2027-01-01 00:00:00']],
];
$insert = $db->prepare('INSERT INTO sales (user_id, total_amount, amount_paid, change_amount, sale_date) VALUES (2, ?, ?, 0, ?)');
foreach ($cases as $case) {
    foreach ($case[3] as $index => $date) {
        $total = ['1000', '10.01', '20.02', '1000'][$index];
        $insert->execute([$total, $total, $date]);
    }
}
$reports = new Models\Reports();
foreach ($cases as $index => [$month, $start, $end]) {
    $report = $reports->sales('monthly', $month);
    $ids = array_map(function ($row) { return (int) $row->sale_id; }, $report['rows']);
    sort($ids);
    check($report['period'] === 'monthly' && $report['start_date'] === $start && $report['end_date'] === $end
        && !empty($report['label']) && $ids === [$index * 4 + 2, $index * 4 + 3]
        && (int) $report['transaction_count'] === 2 && (int) round((float) $report['total_amount'] * 100) === 3003,
        'Monthly reports must use exact calendar boundaries, including leap days and December rollover.');
}
$empty = $reports->sales('monthly', '2026-08');
check($empty['rows'] === [] && (int) $empty['transaction_count'] === 0 && (float) $empty['total_amount'] === 0.0,
    'A month without transactions must return no rows and zero totals.');

class TestMonthlySalesReportController extends Api\Reports\ReportsController {
    public function json($data): void { throw new JsonResponse($data); }
}
function monthlyRequest($month): array {
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['period' => 'monthly', 'date' => $month]; http_response_code(200);
    try { (new TestMonthlySalesReportController())->sales(); throw new LogicException('Monthly reports must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'Reports', 'sales');
$response = monthlyRequest('2026-10');
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === json_encode($reports->sales('monthly', '2026-10')),
    'The API must return the selected calendar month report.');
foreach (['2026-00', '2026-13', '2026-10-01', '2026-1', '0999-10', '9999-10', "2026-10' OR 1=1 --", []] as $month) {
    $response = monthlyRequest($month);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400,
        'Invalid month formats, years, and request types must return HTTP 400.');
}
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 16,
    'Generating monthly reports must not change transactions.');

echo "FR-033 passed: calendar-month totals, leap and normal February, December rollover and strict month validation.\n";
