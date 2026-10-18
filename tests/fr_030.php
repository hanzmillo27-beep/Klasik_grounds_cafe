<?php
require __DIR__ . '/product_fixture.php';

class TestInventoryMinimumController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}
function minimumRequest(array $payload): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    try { (new TestInventoryMinimumController())->add(); throw new LogicException('Minimum stock changes must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}

$inventory = new Models\Inventory();
check((float) $inventory->findByID(1)->min_stock === 10.0, 'Existing ingredients must start with the default minimum.');
$cases = [['Below', '4', '5', true], ['Equal', '5', '5', true], ['Above', '5.001', '5', false],
    ['Empty', '0', '0', true], ['Fractional', '0.001', '0.001', true]];
foreach ($cases as [$name, $amount, $minimum, $low]) {
    $result = minimumRequest(['inv_name' => $name, 'amount' => $amount, 'unit' => 'g', 'min_stock' => $minimum]);
    check(($result['status'] ?? '') === 'success', 'A valid per-item minimum must be saved.');
}
$byName = [];
foreach ($inventory->find() as $item) $byName[$item->inv_name] = $item;
foreach ($cases as [$name, $amount, $minimum, $low]) {
    check($byName[$name]->is_low === $low
        && (int) round((float) $byName[$name]->min_stock * 1000) === (int) round((float) $minimum * 1000),
        'Low stock must compare each amount to its saved minimum, including equality and zero.');
}
$dashboard = new Models\Dashboard();
$summary = $dashboard->inventorySummary();
check((int) $summary->total_items === 6 && (int) $summary->low_stock === 4,
    'The dashboard count must use per-item minima instead of a fixed threshold.');
$lowNames = [];
foreach ($dashboard->inventory() as $item) if ($item->stock_status === 'Low Stock') $lowNames[] = $item->inv_name;
check($lowNames === ['Below', 'Empty', 'Equal', 'Fractional'], 'Dashboard low-stock items must match inventory flags.');

$existing = ['inv_id' => 1, 'inv_name' => 'Beans', 'amount' => '100', 'unit' => 'g'];
check($inventory->save($existing + ['min_stock' => '150']) && $inventory->findByID(1)->is_low === true,
    'Updating an ingredient minimum must immediately change its low-stock flag.');
check($inventory->save($existing) && (float) $inventory->findByID(1)->min_stock === 150.0,
    'Editing without a minimum must preserve its current threshold.');
$snapshot = json_encode($inventory->find());
foreach (['-1', 'NaN', INF, [], null, '0.0001', '1000000000'] as $minimum) {
    $result = minimumRequest($existing + ['min_stock' => $minimum]);
    check(($result['status'] ?? '') === 'error' && !$inventory->save($existing + ['min_stock' => $minimum])
        && json_encode($inventory->find()) === $snapshot,
        'Invalid minima must fail API/model validation without changing inventory.');
}
check((int) $dashboard->inventorySummary()->low_stock === 5, 'The dashboard must reflect updated minimum levels.');

echo "FR-030 passed: configurable minimum stock, exact low-stock boundaries, validation and dashboard consistency.\n";
