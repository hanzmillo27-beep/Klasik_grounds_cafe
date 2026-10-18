<?php
require __DIR__ . '/pos_fixture.php';

check((new Models\Products())->save(productPayload(['product_name' => 'Latte', 'product_price' => '150'])),
    'Could not create the second catalog product.');
$saleInsert = $db->prepare('INSERT INTO sales (user_id, total_amount, amount_paid, change_amount, sale_date) VALUES (2, ?, ?, 0, ?)');
$itemInsert = $db->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
$sales = [
    ['2026-10-19 10:00:00', '30.30', [[1, 'Americano old', 2, '10.10', '20.20'], [2, 'Latte original', 1, '10.10', '10.10']]],
    ['2026-10-19 11:00:00', '35.35', [[1, 'Americano new', 3, '10.10', '30.30'], [2, 'Latte original', 1, '5.05', '5.05']]],
    ['2026-10-19 12:00:00', '5.00', [[null, 'Deleted cookie', 2, '1.00', '2.00'], [null, 'Deleted muffin', 1, '3.00', '3.00']]],
    ['2026-09-30 23:59:59.999', '100', [[1, 'Americano old', 10, '10', '100']]],
    ['2026-11-01 00:00:00', '200', [[2, 'Latte original', 20, '10', '200']]],
];
foreach ($sales as [$date, $total, $items]) {
    $saleInsert->execute([$total, $total, $date]);
    $saleId = $db->lastInsertId();
    foreach ($items as $item) $itemInsert->execute(array_merge([$saleId], $item));
}
$reports = new Models\Reports();
$daily = $reports->products('daily', '2026-10-19');
check($daily['report_type'] === 'products' && count($daily['rows']) === 4
    && (int) $daily['transaction_count'] === 3 && (int) $daily['total_quantity'] === 10
    && (int) round((float) $daily['total_amount'] * 100) === 7065,
    'Product reports must aggregate selected historical quantities and revenue, including deleted products.');
$rows = [];
foreach ($daily['rows'] as $row) $rows[$row->product_id === null ? $row->product_name : (string) $row->product_id] = $row;
check((int) $rows['1']->quantity_sold === 5 && (int) round((float) $rows['1']->total_sales * 100) === 5050
    && $rows['1']->product_name === 'Americano old'
    && (int) $rows['2']->quantity_sold === 2 && (int) round((float) $rows['2']->total_sales * 100) === 1515,
    'A product ID must remain one group across saved name changes and use historical subtotals.');
check((int) $rows['Deleted cookie']->quantity_sold === 2 && (int) $rows['Deleted muffin']->quantity_sold === 1,
    'Distinct nullable legacy products must be grouped by their saved names.');
foreach ([['weekly', '2026-W43'], ['monthly', '2026-10']] as [$period, $date]) {
    $report = $reports->products($period, $date);
    check((int) $report['transaction_count'] === 3 && (int) $report['total_quantity'] === 10
        && (int) round((float) $report['total_amount'] * 100) === 7065,
        'Product reporting must apply the selected weekly or monthly range.');
}
$snapshot = json_encode($daily);
$db->exec("UPDATE products SET product_name = 'Current catalog name', product_price = 999");
foreach ([1, 2] as $id) check((new Models\Products())->setActive($id, false), 'Could not deactivate a catalog product.');
check(json_encode($reports->products('daily', '2026-10-19')) === $snapshot,
    'Catalog renaming, repricing, or deactivation must not change historical product reports.');
$empty = $reports->products('daily', '2026-10-21');
check($empty['rows'] === [] && (int) $empty['transaction_count'] === 0 && (int) $empty['total_quantity'] === 0
    && (float) $empty['total_amount'] === 0.0, 'Empty product reports must return zero quantities and revenue.');

class TestProductSalesReportController extends Api\Reports\ReportsController {
    public function json($data): void { throw new JsonResponse($data); }
}
function productReportRequest($period, $date): array {
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['period' => $period, 'date' => $date]; http_response_code(200);
    try { (new TestProductSalesReportController())->products(); throw new LogicException('Product reports must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'Reports', 'products');
$response = productReportRequest('daily', '2026-10-19');
check(($response['status'] ?? '') === 'success' && json_encode($response['data']) === $snapshot,
    'The API must return the selected historical product report.');
foreach ([['daily', '2026-02-30'], ['daily', []], ['unknown', '2026-10-19'], [[], '2026-10-19']] as [$period, $date]) {
    $response = productReportRequest($period, $date);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400, 'Invalid product-report requests must return HTTP 400.');
}
foreach (['owner' => true, 'admin' => false, 'staff' => false, 'cashier' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 1, 'role' => $role];
    check(Core\Auth::canAccess('Reports', 'products', 'Api') === $allowed, 'Incorrect product-report permission for ' . $role . '.');
}

echo "FR-034 passed: product quantities/revenue, stable historical groups, date ranges and owner-only API.\n";
