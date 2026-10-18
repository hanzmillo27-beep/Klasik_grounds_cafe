<?php
require __DIR__ . '/product_fixture.php';

class TestInventoryController extends Api\Inventory\InventoryController {
    public function json($data): void { throw new JsonResponse($data); }
}
function inventoryRequest(array $payload, string $action = 'add', $id = null, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $payload;
    http_response_code(200);
    try {
        $controller = new TestInventoryController();
        $id === null ? $controller->$action() : $controller->$action($id);
        throw new LogicException('Inventory writes must return JSON.');
    } catch (JsonResponse $response) { return $response->data; }
}

$_SERVER['REQUEST_METHOD'] = 'POST';
Core\Auth::authorizeRoute('Api', 'Inventory', 'add');
$inventory = new Models\Inventory();
$original = json_encode($inventory->findByID(1));
foreach ([['Sugar', '0', 'g'], ['Syrup', '12.345', 'ml'], ['Bulk stock', '999999999.999', 'g']] as [$name, $amount, $unit]) {
    $result = inventoryRequest(['inv_name' => '  ' . $name . '  ', 'amount' => $amount, 'unit' => ' ' . $unit . ' ']);
    check(($result['status'] ?? '') === 'success', 'A valid ingredient amount must be accepted.');
    $stmt = $db->prepare('SELECT inv_name, amount, unit FROM inventory WHERE inv_name = ?');
    $stmt->execute([$name]);
    $saved = $stmt->fetch();
    check($saved && $saved->inv_name === $name && $saved->unit === $unit
        && (int) round((float) $saved->amount * 1000) === (int) round((float) $amount * 1000),
        'Ingredient name/unit must be trimmed and the quantity stored to three decimals.');
}
$valid = ['inv_name' => 'Rejected ingredient', 'amount' => '1', 'unit' => 'g'];
$invalid = [['inv_name' => ''], ['inv_name' => str_repeat('x', 151)], ['inv_name' => []], ['inv_name' => null], ['inv_name' => 'beans'],
    ['unit' => ' '], ['unit' => str_repeat('x', 31)], ['unit' => []], ['unit' => 3],
    ['amount' => '-0.001'], ['amount' => 'NaN'], ['amount' => INF], ['amount' => []], ['amount' => null],
    ['amount' => '1000000000'], ['amount' => '0.0001'], ['amount' => '1e2'], ['inv_id' => 99999]];
foreach ($invalid as $changes) {
    $result = inventoryRequest(array_replace($valid, $changes));
    check(($result['status'] ?? '') === 'error' && (int) $db->query('SELECT COUNT(*) FROM inventory')->fetchColumn() === 4
        && json_encode($inventory->findByID(1)) === $original,
        'Invalid ingredient data must not create records or overwrite existing stock.');
}
check(!$inventory->save(array_replace($valid, ['amount' => '-1'])), 'The model must also reject invalid quantities.');
$result = inventoryRequest($valid, 'add', null, 'GET');
check(($result['status'] ?? '') === 'error' && http_response_code() === 405, 'GET must not create ingredients.');
$result = inventoryRequest($valid + ['inv_id' => 1], 'edit', 99999);
check(($result['status'] ?? '') === 'error' && json_encode($inventory->findByID(1)) === $original,
    'A nonexistent edit target must not fall back to a submitted ingredient ID.');
foreach (['owner' => true, 'admin' => false, 'staff' => false, 'cashier' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 1, 'role' => $role];
    check(Core\Auth::canAccess('Inventory', 'add', 'Api') === $allowed, 'Incorrect ingredient creation permission for ' . $role . '.');
    if ($role === 'staff') check(Core\Auth::canAccess('Inventory', 'restock', 'Api'), 'Staff must retain stock replenishment access.');
}

echo "FR-026 passed: valid zero/fractional ingredients, quantity bounds, safe validation, authorized creation.\n";
