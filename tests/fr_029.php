<?php
require __DIR__ . '/pos_fixture.php';

class TestInventoryListController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}
$inventory = new Models\Inventory();
check($inventory->save(['inv_name' => 'Almond milk', 'amount' => '0.001', 'unit' => 'ml']),
    'Could not create fractional inventory.');
$db->exec('UPDATE inventory SET amount = 18.250 WHERE inv_id = 1');
(new Models\POS())->checkout(['checkout_key' => checkoutTestKey(), 'amount_paid' => '200.00',
    'items' => [['product_id' => 1, 'quantity' => 1]]]);
$listed = $inventory->find();
check(count($listed) === 2 && $listed[0]->inv_name === 'Almond milk' && $listed[1]->inv_name === 'Beans',
    'The inventory list must include all items in name order.');
foreach ($listed as $item) {
    foreach (['inv_id', 'inv_name', 'amount', 'unit'] as $field) {
        check(property_exists($item, $field), 'The inventory list omitted ' . $field . '.');
    }
}
check((int) round((float) $listed[0]->amount * 1000) === 1 && $listed[0]->unit === 'ml'
    && (float) $listed[1]->amount === 0.0 && $listed[1]->unit === 'g',
    'Current inventory must display 0.001 precisely and include stock reduced to zero by checkout.');
check($inventory->restock(1, '2.345'), 'Could not replenish stock.');
$listed = $inventory->find();
check((int) round((float) $listed[1]->amount * 1000) === 2345,
    'Refreshing inventory must return the current replenished quantity.');
$_SERVER['REQUEST_METHOD'] = 'GET';
Core\Auth::authorizeRoute('Api', 'Inventory', 'all');
$snapshot = json_encode($listed);
for ($read = 0; $read < 2; $read++) {
    try { (new TestInventoryListController())->all(); throw new LogicException('The inventory list must return JSON.'); }
    catch (JsonResponse $response) {
        check(json_encode($response->data['data']) === $snapshot, 'The inventory API must return current item amounts and units.');
    }
}
check(json_encode($inventory->find()) === $snapshot
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1,
    'Reading or refreshing inventory must not change stock or transaction history.');
foreach (['owner' => true, 'staff' => true, 'cashier' => true, 'admin' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 2, 'role' => $role];
    check(Core\Auth::canAccess('Inventory', 'all', 'Api') === $allowed, 'Incorrect inventory-list access for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('Inventory', 'all', 'Api'), 'Guests must not access protected inventory data.');

echo "FR-029 passed: current inventory amounts and units, zero/fractional stock, read-only refresh, role permissions.\n";
