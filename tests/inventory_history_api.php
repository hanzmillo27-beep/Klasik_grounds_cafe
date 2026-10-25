<?php
require __DIR__ . '/product_fixture.php';

$history = new Models\InventoryHistory($db);
$db->beginTransaction();
for ($index = 1; $index <= 25; $index++) {
    $history->record([
        'inv_id'=>$index % 2 ? 1 : 2, 'inv_name'=>$index === 1 ? 'Beans 100%_special' : 'Beans',
        'unit'=>'g', 'movement_type'=>$index % 3 ? 'restock' : 'adjustment',
        'amount_before'=>'1.000', 'quantity_change'=>'0.001', 'amount_after'=>'1.001', 'min_stock'=>'0.500',
        'previous_inv_name'=>$index === 1 ? 'Original beans' : null,
    ]);
}
$db->commit();
$db->exec("UPDATE inventory_history SET created_at = '2026-10-25 08:00:00'");
$page = $history->page(['draw'=>'2', 'start'=>'0', 'length'=>'10']);
check($page['draw'] === 2 && $page['recordsTotal'] === 25 && $page['recordsFiltered'] === 25
    && count($page['data']) === 10 && (int) $page['data'][0]->history_id === 25
    && (int) $page['data'][9]->history_id === 16,
    'History must have bounded, deterministic newest-first pages and integer draw/counts.');
$second = $history->page(['start'=>10, 'length'=>10]);
check((int) $second['data'][0]->history_id === 15 && count($second['data']) === 10,
    'The second history page must not repeat the first page.');
$item = $history->page(['inv_id'=>1, 'length'=>100]);
check($item['recordsTotal'] === 13 && count($item['data']) === 13
    && array_unique(array_map(fn($row)=>(int) $row->inv_id, $item['data'])) === [1],
    'An item filter must scope history and its total count.');
$filtered = $history->page(['inv_id'=>1, 'movement_type'=>'adjustment', 'length'=>100]);
check($filtered['recordsTotal'] === 13 && $filtered['recordsFiltered'] === 4,
    'History action filters must compose with item scope.');
foreach (['100%_', 'ORIGINAL BEANS'] as $term) {
    $result = $history->page(['search'=>['value'=>$term]]);
    check($result['recordsFiltered'] === 1 && (int) $result['data'][0]->history_id === 1,
        'Search must support saved previous names, case-insensitive text, and literal SQL wildcards.');
}
check($history->page(['search'=>['value'=>"' OR 1=1 --"]])['recordsFiltered'] === 0,
    'Search values must be bound as data.');
check($history->page(['inv_id'=>999])['data'] === [], 'Unknown or deleted item history may be empty.');
$before = json_encode($history->page(['length'=>100]));
$db->exec('DELETE FROM inventory WHERE inv_id = 1');
$db->exec("UPDATE users SET username = 'Renamed user' WHERE id = 1");
check(json_encode($history->page(['length'=>100])) === $before,
    'Reading history must use saved item and actor snapshots after entity deletion or renaming.');

class TestInventoryHistoryController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}
function historyRequest(array $query, string $method = 'GET'): array {
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try { (new TestInventoryHistoryController())->history(); throw new LogicException('History must return JSON.'); }
    catch (JsonResponse $response) { return $response->data; }
}
$response = historyRequest(['draw'=>'9', 'length'=>'25']);
check($response['draw'] === 9 && count($response['data']) === 25 && http_response_code() === 200,
    'The inventory history API must return DataTables JSON.');
foreach ([['draw'=>'<script>'], ['start'=>-1], ['length'=>-1], ['length'=>101], ['inv_id'=>[]],
    ['movement_type'=>'invalid'], ['search'=>'invalid'], ['search'=>['value'=>[]]],
    ['search'=>['value'=>str_repeat('a', 151)]]] as $query) {
    $response = historyRequest($query);
    check(($response['status'] ?? '') === 'error' && http_response_code() === 400,
        'Malformed history pages and filters must return HTTP 400.');
}
$response = historyRequest([], 'POST');
check(($response['status'] ?? '') === 'error' && http_response_code() === 405,
    'Inventory history must be read using GET.');
foreach (['owner'=>true, 'cashier'=>true, 'staff'=>true, 'admin'=>false, 'user'=>false, 'loyal_customer'=>false] as $role=>$allowed) {
    $_SESSION['user'] = ['id'=>1, 'role'=>$role];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    check(Core\Auth::canAccess('Inventory', 'history', 'Api') === $allowed,
        'Incorrect history permission for ' . $role);
    if ($role !== 'owner') check(!Core\Auth::canAccess('Products', 'history', 'Api'),
        'Inventory history access must not add new product permissions.');
}
echo "Inventory history API passed: pagination, item/action filters, literal search, preserved snapshots, validation and role access.\n";
