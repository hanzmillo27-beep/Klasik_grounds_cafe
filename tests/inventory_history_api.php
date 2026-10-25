<?php
require __DIR__ . '/pos_fixture.php';

$history = new Models\Inventory($db);
$_SESSION['user'] = ['id'=>1, 'username'=>'test-owner', 'role'=>'owner'];
$db->beginTransaction();
for ($index = 1; $index <= 25; $index++) {
    $history->recordHistory([
        'inv_id'=>$index % 2 ? 1 : 2, 'inv_name'=>$index === 1 ? 'Beans 100%_special' : 'Beans',
        'unit'=>'g', 'movement_type'=>$index % 3 ? 'restock' : 'adjustment',
        'amount_before'=>'1.000', 'quantity_change'=>'0.001', 'amount_after'=>'1.001', 'min_stock'=>'0.500',
        'previous_inv_name'=>$index === 1 ? 'Original beans' : null,
    ]);
}
$db->commit();
$db->exec("UPDATE inventory_history SET created_at = '2026-10-25 08:00:00'");
$page = $history->history(['draw'=>'2', 'start'=>'0', 'length'=>'10']);
check($page['draw'] === 2 && $page['recordsTotal'] === 25 && $page['recordsFiltered'] === 25
    && count($page['data']) === 10 && (int) $page['data'][0]->history_id === 25
    && (int) $page['data'][9]->history_id === 16,
    'History must have bounded, deterministic newest-first pages and integer draw/counts.');
$second = $history->history(['start'=>10, 'length'=>10]);
check((int) $second['data'][0]->history_id === 15 && count($second['data']) === 10,
    'The second history page must not repeat the first page.');
$item = $history->history(['inv_id'=>1, 'length'=>100]);
check($item['recordsTotal'] === 13 && count($item['data']) === 13
    && array_unique(array_map(fn($row)=>(int) $row->inv_id, $item['data'])) === [1],
    'An item filter must scope history and its total count.');
$filtered = $history->history(['inv_id'=>1, 'movement_type'=>'adjustment', 'length'=>100]);
check($filtered['recordsTotal'] === 13 && $filtered['recordsFiltered'] === 4,
    'History action filters must compose with item scope.');
foreach (['100%_', 'ORIGINAL BEANS'] as $term) {
    $result = $history->history(['search'=>['value'=>$term]]);
    check($result['recordsFiltered'] === 1 && (int) $result['data'][0]->history_id === 1,
        'Search must support saved previous names, case-insensitive text, and literal SQL wildcards.');
}
check($history->history(['search'=>['value'=>"' OR 1=1 --"]])['recordsFiltered'] === 0,
    'Search values must be bound as data.');
check($history->history(['inv_id'=>999])['data'] === [], 'Unknown or deleted item history may be empty.');
$before = json_encode($history->history(['length'=>100]));
$db->exec('DELETE FROM product_ingredients WHERE inv_id = 1');
$db->exec('DELETE FROM inventory WHERE inv_id = 1');
$db->exec("UPDATE users SET username = 'Renamed user' WHERE id = 1");
check(json_encode($history->history(['length'=>100])) === $before,
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

$saleInsert = $db->prepare('INSERT INTO sales (user_id, customer_name, total_amount, amount_paid, change_amount, sale_date)
    VALUES (2, ?, ?, ?, 0, ?)');
$itemInsert = $db->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal)
    VALUES (?, ?, ?, ?, ?, ?)');
$saleInsert->execute(['Legacy customer', '63.00', '63.00', '2026-10-24 12:00:00']);
$legacySaleId = (int) $db->lastInsertId();
$itemInsert->execute([$legacySaleId, 1, 'Saved Americano name', 2, '25.00', '50.00']);
$legacyFirstItem = (int) $db->lastInsertId();
$itemInsert->execute([$legacySaleId, null, 'Deleted cookie 100%_saved', 1, '13.00', '13.00']);
$legacyDeletedItem = (int) $db->lastInsertId();
$_SESSION['user'] = ['id'=>2, 'username'=>'test-cashier', 'role'=>'cashier'];
$pos = new Models\POS();
$untracked = $pos->checkout(['checkout_key'=>'history-untracked-sale', 'amount_paid'=>'500.00',
    'items'=>[['product_id'=>1, 'quantity'=>3]]]);
$untrackedId = (int) $untracked['sale_id'];
$db->prepare('UPDATE sales SET sale_date = ? WHERE sale_id = ?')->execute(['2026-10-26 12:00:00', $untrackedId]);
check((int) $db->query('SELECT COUNT(*) FROM inventory_history')->fetchColumn() === 25,
    'Selling products without a recipe must not fabricate ingredient movements.');

$db->exec("INSERT INTO inventory (inv_id, inv_name, amount, unit, min_stock) VALUES (99, 'Tracked beans', 100, 'g', 5)");
check((new Models\Products())->save(productPayload(['product_name'=>'Tracked coffee', 'product_price'=>'30.00',
    'ingredient_name'=>['Tracked beans'], 'ingredient_quantity'=>['2.000']])), 'Could not create the tracked sale product.');
$tracked = $pos->checkout(['checkout_key'=>'history-tracked-sale', 'amount_paid'=>'100.00',
    'items'=>[['product_id'=>2, 'quantity'=>2]]]);
$trackedId = (int) $tracked['sale_id'];
$db->prepare('UPDATE inventory_history SET created_at = ? WHERE sale_id = ?')->execute(['2026-10-26 12:00:00', $trackedId]);
$all = $history->history(['length'=>100]);
check($all['recordsTotal'] === 29 && $all['recordsFiltered'] === 29 && count($all['data']) === 29,
    'Global history must combine 26 saved stock movements with three fallback sale lines.');
$fallback = array_values(array_filter($all['data'], fn($row)=>$row->entry_kind === 'sale_record'));
check(count($fallback) === 3 && count(array_filter($all['data'], fn($row)=>(int) $row->sale_id === $trackedId)) === 1,
    'Sales already captured as ingredient movements must not receive duplicate sale-record entries.');
foreach ($fallback as $row) {
    check($row->movement_type === 'sale' && (int) $row->actor_id === 2 && $row->actor_name === 'test-cashier',
        'Fallback sale rows must retain the recorded cashier ID, current username and sale action.');
    foreach (['inv_id', 'unit', 'amount_before', 'quantity_change', 'amount_after', 'min_stock',
        'previous_inv_name', 'previous_unit', 'previous_min_stock'] as $field) {
        check($row->$field === null, 'Fallback sales must never invent stock quantities, units or ingredient associations.');
    }
}
$legacyRows = array_values(array_filter($fallback, fn($row)=>(int) $row->sale_id === $legacySaleId));
check(count($legacyRows) === 2
    && (int) $legacyRows[0]->history_id === $legacyDeletedItem && $legacyRows[0]->inv_name === 'Deleted cookie 100%_saved'
    && (int) $legacyRows[0]->sold_quantity === 1 && $legacyRows[0]->created_at === '2026-10-24 12:00:00'
    && (int) $legacyRows[1]->history_id === $legacyFirstItem && $legacyRows[1]->inv_name === 'Saved Americano name'
    && (int) $legacyRows[1]->sold_quantity === 2,
    'Legacy sales and deleted products must show their saved names, quantities and transaction dates.');
check($all['data'][0]->entry_kind === 'sale_record' && (int) $all['data'][0]->sale_id === $untrackedId
    && (int) $all['data'][0]->sold_quantity === 3 && $all['data'][1]->entry_kind === 'stock',
    'Unified history must order equal timestamps by entry kind before descending entry ID.');
$saleFilter = $history->history(['movement_type'=>'sale', 'length'=>100]);
check($saleFilter['recordsTotal'] === 29 && $saleFilter['recordsFiltered'] === 4 && count($saleFilter['data']) === 4,
    'The Sale action filter must include both saved ingredient movements and fallback sale lines.');
$scoped = $history->history(['inv_id'=>99, 'length'=>100]);
check($scoped['recordsTotal'] === 1 && $scoped['recordsFiltered'] === 1 && count($scoped['data']) === 1
    && $scoped['data'][0]->entry_kind === 'stock' && (int) $scoped['data'][0]->sale_id === $trackedId,
    'An ingredient-specific history must exclude all fallback sale records.');
foreach (['saved americano', '100%_saved'] as $term) {
    $found = $history->history(['search'=>['value'=>$term]]);
    check($found['recordsTotal'] === 29 && $found['recordsFiltered'] === 1 && $found['data'][0]->entry_kind === 'sale_record',
        'Unified search must match historical sale names and treat wildcard characters literally.');
}
$pageOne = $history->history(['length'=>2]);
$pageTwo = $history->history(['start'=>2, 'length'=>2]);
$rowKeys = fn($page)=>array_map(fn($row)=>$row->entry_kind . ':' . $row->history_id, $page['data']);
check(count($pageOne['data']) === 2 && count($pageTwo['data']) === 2
    && array_intersect($rowKeys($pageOne), $rowKeys($pageTwo)) === [], 'Unified paging must not duplicate rows across stock and sale-record sources.');
$before = json_encode($history->history(['movement_type'=>'sale', 'length'=>100]));
$db->exec("UPDATE products SET product_name = 'Current catalog name' WHERE product_id = 1");
$db->exec('INSERT INTO product_ingredients (product_id, inv_id, quantity_required) VALUES (1, 99, 3.000)');
check(json_encode($history->history(['movement_type'=>'sale', 'length'=>100])) === $before,
    'Editing today\'s product names or recipes must not change saved sale records or invent past stock deductions.');
$stockBefore = json_encode($db->query('SELECT * FROM inventory ORDER BY inv_id')->fetchAll());
$historyBefore = json_encode($db->query('SELECT * FROM inventory_history ORDER BY history_id')->fetchAll());
$response = historyRequest(['draw'=>'15', 'movement_type'=>'sale', 'length'=>'100']);
check($response['draw'] === 15 && $response['recordsTotal'] === 29 && $response['recordsFiltered'] === 4
    && count($response['data']) === 4 && http_response_code() === 200,
    'The API must return the unified saved-sale and ingredient history with correct pagination counts.');
check(json_encode($db->query('SELECT * FROM inventory ORDER BY inv_id')->fetchAll()) === $stockBefore
    && json_encode($db->query('SELECT * FROM inventory_history ORDER BY history_id')->fetchAll()) === $historyBefore,
    'Reading fallback sales must never write backfill records or alter stock.');

echo "Inventory history API passed: unified saved-sale/stock history, recipe-free and legacy sales, no duplicates or stock inference, pagination, filters, search and role access.\n";
