<?php
require __DIR__ . '/loyalty_fixture.php';

function inventoryHistoryRows(PDO $db, ?int $id = null): array {
    $stmt = $db->prepare('SELECT * FROM inventory_history' . ($id === null ? '' : ' WHERE inv_id = ?') . ' ORDER BY history_id');
    $stmt->execute($id === null ? [] : [$id]);
    return $stmt->fetchAll();
}

function inventoryHistorySnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['inventory', 'inventory_history', 'sales', 'sale_items', 'loyal', 'loyalty_earnings', 'loyalty_redemptions'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function checkMovement($row, string $type, float $before, float $change, float $after): void {
    check($row->movement_type === $type
        && (int) round((float) $row->amount_before * 1000) === (int) round($before * 1000)
        && (int) round((float) $row->quantity_change * 1000) === (int) round($change * 1000)
        && (int) round((float) $row->amount_after * 1000) === (int) round($after * 1000),
        'Incorrect history type or before/change/after quantities for ' . $type . '.');
}

function failHistoryWrites(PDO $db): void {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        $db->exec('CREATE FUNCTION fail_inventory_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION \'Injected history failure\'; END; $$');
        $db->exec('CREATE TRIGGER fail_inventory_history BEFORE INSERT ON inventory_history
            FOR EACH ROW EXECUTE FUNCTION fail_inventory_history()');
    } else {
        $db->exec('CREATE TRIGGER fail_inventory_history BEFORE INSERT ON inventory_history
            BEGIN SELECT RAISE(ABORT, \'Injected history failure\'); END');
    }
}

function allowHistoryWrites(PDO $db): void {
    $postgres = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $db->exec('DROP TRIGGER fail_inventory_history' . ($postgres ? ' ON inventory_history' : ''));
    if ($postgres) $db->exec('DROP FUNCTION fail_inventory_history()');
}

class TestInventoryHistoryController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}

function failedHistoryRequest(string $action, int $id, array $payload): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    try {
        $controller = new TestInventoryHistoryController();
        if ($action === 'add') $controller->add();
        else $controller->$action($id);
        throw new LogicException('Inventory mutations must return JSON.');
    } catch (JsonResponse $response) { return $response->data; }
}

$inventory = new Models\Inventory();
$_SESSION['user'] = ['id' => 1, 'username' => 'test-owner', 'role' => 'owner'];
check(inventoryHistoryRows($db) === [], 'Existing inventory must not receive invented historical entries.');
$started = time();
check($inventory->save(['inv_name' => ' Milk ', 'amount' => '2.345', 'unit' => ' ml ', 'min_stock' => '0.250']),
    'Could not create inventory with history.');
$milkId = (int) $db->query("SELECT inv_id FROM inventory WHERE inv_name = 'Milk'")->fetchColumn();
$rows = inventoryHistoryRows($db, $milkId);
check(count($rows) === 1, 'Creating inventory must append exactly one history entry.');
checkMovement($rows[0], 'created', 0, 2.345, 2.345);
$generated = new DateTimeImmutable($rows[0]->created_at, new DateTimeZone('Asia/Manila'));
check($rows[0]->inv_name === 'Milk' && $rows[0]->unit === 'ml' && (float) $rows[0]->min_stock === 0.25
    && (int) $rows[0]->actor_id === 1 && $rows[0]->actor_name === 'test-owner'
    && $rows[0]->previous_inv_name === null && $rows[0]->sale_id === null
    && $generated->getTimestamp() >= $started - 1 && $generated->getTimestamp() <= time() + 1,
    'Creation history must retain metadata, actor and Manila timestamp snapshots.');
check((new Models\Users())->save(['username' => 'history-staff', 'password' => 'password', 'role' => 'staff']),
    'Could not create inventory staff.');
$_SESSION['user'] = ['id' => 3, 'role' => 'staff'];
Core\Auth::authorizeRoute('Api', 'Inventory', 'restock');
check($inventory->restock($milkId, '0.005'), 'Could not replenish inventory with history.');
$rows = inventoryHistoryRows($db, $milkId);
checkMovement($rows[1], 'restock', 2.345, 0.005, 2.350);
check((int) $rows[1]->actor_id === 3 && $rows[1]->actor_name === 'history-staff', 'Restocking must snapshot the signed-in staff account.');

$_SESSION['user'] = ['id' => 1, 'username' => 'test-owner', 'role' => 'owner'];
$edited = ['inv_id' => $milkId, 'inv_name' => 'Fresh milk', 'amount' => '2.350', 'unit' => 'litre', 'min_stock' => '0.500'];
check($inventory->save($edited), 'Could not edit inventory metadata.');
$rows = inventoryHistoryRows($db, $milkId);
checkMovement($rows[2], 'adjustment', 2.350, 0, 2.350);
check($rows[2]->inv_name === 'Fresh milk' && $rows[2]->previous_inv_name === 'Milk'
    && $rows[2]->unit === 'litre' && $rows[2]->previous_unit === 'ml'
    && (float) $rows[2]->previous_min_stock === 0.25 && (float) $rows[2]->min_stock === 0.5,
    'Metadata-only changes must record both old and new details without inventing a quantity change.');
check($inventory->save($edited) && count(inventoryHistoryRows($db, $milkId)) === 3,
    'Saving an unchanged item must succeed without adding history.');
$edited['amount'] = '1.001';
unset($edited['min_stock']);
check($inventory->save($edited), 'Could not adjust inventory quantity.');
$rows = inventoryHistoryRows($db, $milkId);
checkMovement($rows[3], 'adjustment', 2.350, -1.349, 1.001);
check((float) $rows[3]->min_stock === 0.5, 'An edit without a minimum must retain the locked current minimum.');
check($inventory->delete($milkId) && !$inventory->findByID($milkId), 'Could not delete an unused inventory item.');
$rows = inventoryHistoryRows($db, $milkId);
check(count($rows) === 5, 'Deleting an item must retain its complete existing history and append one movement.');
checkMovement($rows[4], 'deleted', 1.001, -1.001, 0);
check($rows[4]->inv_name === 'Fresh milk' && $rows[4]->unit === 'litre' && $rows[0]->inv_name === 'Milk',
    'Deleted and renamed items must remain understandable from their history snapshots.');
$snapshot = inventoryHistorySnapshot($db);
check(!$inventory->delete($milkId) && !$inventory->restock(999999, '1')
    && !$inventory->save(['inv_name' => 'Invalid', 'amount' => '-1', 'unit' => 'g'])
    && inventoryHistorySnapshot($db) === $snapshot, 'Missing or invalid mutations must not create history.');

check($inventory->save(['inv_name' => 'Rollback stock', 'amount' => '4.000', 'unit' => 'g']), 'Could not create rollback inventory.');
$rollbackId = (int) $db->query("SELECT inv_id FROM inventory WHERE inv_name = 'Rollback stock'")->fetchColumn();
$snapshot = inventoryHistorySnapshot($db);
failHistoryWrites($db);
foreach ([
    function () use ($inventory) { return $inventory->save(['inv_name' => 'Failed create', 'amount' => '1', 'unit' => 'g']); },
    function () use ($inventory, $rollbackId) { return $inventory->save(['inv_id' => $rollbackId, 'inv_name' => 'Rollback stock', 'amount' => '5', 'unit' => 'g']); },
    function () use ($inventory, $rollbackId) { return $inventory->restock($rollbackId, '1'); },
    function () use ($inventory, $rollbackId) { return $inventory->delete($rollbackId); },
] as $mutate) check(!$mutate() && !$db->inTransaction() && inventoryHistorySnapshot($db) === $snapshot,
    'A history insertion failure must roll back inventory creation, edit, restock or deletion.');
foreach ([
    ['add', ['inv_name' => 'API failed create', 'amount' => '1', 'unit' => 'g']],
    ['edit', ['inv_name' => 'Rollback stock', 'amount' => '5', 'unit' => 'g']],
    ['restock', ['amount' => '1']],
    ['delete', []],
] as [$action, $payload]) check(failedHistoryRequest($action, $rollbackId, $payload)['status'] === 'error'
    && inventoryHistorySnapshot($db) === $snapshot && !$db->inTransaction(),
    'History failures must give the normal inventory JSON error without partial stock changes.');
allowHistoryWrites($db);

check($inventory->save(['inv_name' => 'Milk', 'amount' => '50.000', 'unit' => 'ml', 'min_stock' => '5']),
    'Could not create the sale ingredient.');
$saleMilkId = (int) $db->query("SELECT inv_id FROM inventory WHERE inv_name = 'Milk'")->fetchColumn();
check((new Models\Products())->save(productPayload(['product_name' => 'History latte',
    'ingredient_name' => ['Beans', 'Milk'], 'ingredient_quantity' => ['10.125', '1.500']])),
    'Could not create the shared-ingredient sale product.');
$_SESSION['user'] = ['id' => 2, 'username' => 'test-cashier', 'role' => 'cashier'];
$pos = new Models\POS();
$payload = ['checkout_key' => 'inventory-history-sale', 'amount_paid' => '1000.00',
    'items' => [['product_id' => 1, 'quantity' => 2], ['product_id' => 2, 'quantity' => 2]]];
$sale = $pos->checkout($payload);
$saleId = (int) $sale['sale_id'];
$stmt = $db->prepare('SELECT * FROM inventory_history WHERE sale_id = ? ORDER BY inv_id');
$stmt->execute([$saleId]);
$rows = $stmt->fetchAll();
check(count($rows) === 2 && (int) $rows[0]->inv_id === 1 && (int) $rows[1]->inv_id === $saleMilkId,
    'A sale must record one aggregated movement for each ingredient, even when products share it.');
checkMovement($rows[0], 'sale', 100, -56.750, 43.250);
checkMovement($rows[1], 'sale', 50, -3, 47);
check((int) $rows[0]->actor_id === 2 && $rows[0]->actor_name === 'test-cashier' && $rows[0]->inv_name === 'Beans'
    && $rows[1]->unit === 'ml' && (float) $rows[1]->min_stock === 5.0,
    'Sale movements must retain ingredient, cashier and minimum snapshots with their transaction ID.');
$snapshot = inventoryHistorySnapshot($db);
check((int) $pos->checkout($payload)['sale_id'] === $saleId && inventoryHistorySnapshot($db) === $snapshot,
    'A checkout retry must not create duplicate stock movements or deductions.');
$failed = false;
try { $pos->checkout(['checkout_key' => 'history-stock-short', 'amount_paid' => '20000', 'items' => [['product_id' => 1, 'quantity' => 99]]]); }
catch (Exception $error) { $failed = str_contains($error->getMessage(), 'Insufficient inventory'); }
check($failed && !$db->inTransaction() && inventoryHistorySnapshot($db) === $snapshot,
    'Rejected stock shortages must not create sales or inventory history.');
failHistoryWrites($db);
$failed = false;
try { $pos->checkout(['checkout_key' => 'history-insert-failure', 'amount_paid' => '200', 'items' => [['product_id' => 2, 'quantity' => 1]]]); }
catch (Exception $error) { $failed = str_contains($error->getMessage(), 'Injected history failure'); }
check($failed && !$db->inTransaction() && inventoryHistorySnapshot($db) === $snapshot,
    'A sale history failure must roll back the sale, items and all stock deductions.');
allowHistoryWrites($db);

check((new Models\Users())->save(['username' => 'history-reward-member', 'password' => 'password', 'role' => 'loyal_customer']),
    'Could not create the reward customer.');
$db->exec("INSERT INTO loyalty_redemptions (user_id, reward_code, points_spent, product_id, product_name)
    VALUES (4, 'GC-1234ABCD', 10, 2, 'History latte')");
$rewardPayload = ['checkout_key' => 'history-reward-sale', 'reward_code' => 'GC-1234ABCD',
    'amount_paid' => '0.00', 'expected_total' => '0.00', 'items' => [['product_id' => 2, 'quantity' => 1]]];
$postgres = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
if ($postgres) {
    $db->exec('CREATE FUNCTION fail_history_reward() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION \'Injected later failure\'; END; $$');
    $db->exec('CREATE TRIGGER fail_history_reward BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        FOR EACH ROW EXECUTE FUNCTION fail_history_reward()');
} else {
    $db->exec('CREATE TRIGGER fail_history_reward BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        BEGIN SELECT RAISE(ABORT, \'Injected later failure\'); END');
}
$snapshot = inventoryHistorySnapshot($db);
$failed = false;
try { $pos->checkout($rewardPayload); }
catch (Exception $error) { $failed = str_contains($error->getMessage(), 'Injected later failure'); }
check($failed && !$db->inTransaction() && inventoryHistorySnapshot($db) === $snapshot,
    'A later checkout failure must also roll back history rows that were already inserted.');
$db->exec('DROP TRIGGER fail_history_reward' . ($postgres ? ' ON loyalty_redemptions' : ''));
if ($postgres) $db->exec('DROP FUNCTION fail_history_reward()');
$reward = $pos->checkout($rewardPayload);
$stmt->execute([$reward['sale_id']]);
$rows = $stmt->fetchAll();
check(count($rows) === 2 && (float) $reward['total_amount'] === 0.0, 'A free reward must record its normal ingredient deductions.');
checkMovement($rows[0], 'sale', 43.250, -10.125, 33.125);
checkMovement($rows[1], 'sale', 47, -1.5, 45.5);
$snapshot = inventoryHistorySnapshot($db);
check((int) $pos->checkout($rewardPayload)['sale_id'] === (int) $reward['sale_id']
    && inventoryHistorySnapshot($db) === $snapshot, 'Retrying a reward checkout must not duplicate its inventory history.');
$kiosk = new Models\Kiosk();
$submitted = $kiosk->submit(['customer_name' => 'Kiosk history', 'items' => [['product_id' => 1, 'quantity' => 1]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
$kioskPayload = ['checkout_key' => 'history-kiosk-sale', 'kiosk_order_id' => $claimed->kiosk_order_id,
    'customer_name' => 'Kiosk history', 'amount_paid' => '200.00', 'items' => [['product_id' => 1, 'quantity' => 1]]];
$kioskSale = $pos->checkout($kioskPayload);
$stmt->execute([$kioskSale['sale_id']]);
$rows = $stmt->fetchAll();
check(count($rows) === 1 && (int) $rows[0]->inv_id === 1
    && $kiosk->findByReference($submitted['reference_code'])->status === 'completed',
    'A completed kiosk order must retain its ingredient movement and sale link.');
checkMovement($rows[0], 'sale', 33.125, -18.250, 14.875);
$snapshot = inventoryHistorySnapshot($db);
check((int) $pos->checkout($kioskPayload)['sale_id'] === (int) $kioskSale['sale_id']
    && inventoryHistorySnapshot($db) === $snapshot, 'Retrying kiosk payment must not duplicate inventory history.');
$db->exec("UPDATE users SET username = 'renamed-owner' WHERE id = 1");
check(inventoryHistoryRows($db, $milkId)[0]->actor_name === 'test-owner', 'Account renaming must not change historical actor names.');

echo "Inventory history passed: precise create/restock/adjust/delete snapshots, metadata/no-op handling, actors, aggregated sales/rewards, safe retries and atomic rollback.\n";
