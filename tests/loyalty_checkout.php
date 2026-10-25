<?php
require __DIR__ . '/loyalty_fixture.php';

function rewardLookupRequest($code = null, string $method = 'GET'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try {
        (new TestPOSController())->reward($code);
        throw new LogicException('Reward retrieval must return JSON.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

function rewardCheckoutRequest(array $payload): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    $_POST['items'] = json_encode($payload['items']);
    http_response_code(200);
    try {
        (new TestPOSController())->checkout();
        throw new LogicException('Reward checkout must return JSON.');
    } catch (JsonResponse $response) {
        return $response->data;
    }
}

function rewardCheckoutSnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['sales', 'sale_items', 'inventory', 'loyal', 'loyalty_earnings', 'loyalty_redemptions', 'kiosk_orders'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function rejectRewardCheckout(Models\POS $pos, array $payload, string $message): void {
    $db = Core\Database::$connection;
    $snapshot = rewardCheckoutSnapshot($db);
    $rejected = false;
    try { $pos->checkout($payload); }
    catch (PDOException | TypeError $error) { throw new RuntimeException('Reward validation must fail safely.', 0, $error); }
    catch (Exception $error) { $rejected = str_contains($error->getMessage(), $message); }
    check($rejected && !$db->inTransaction() && rewardCheckoutSnapshot($db) === $snapshot,
        'Rejected reward checkout must explain the problem and preserve all data: ' . $message);
}

foreach (['Coffee', 'Coffee-based', ' SPECIALTY__COFFEE ', 'Hot coffee', 'Iced-coffee', 'cold_coffee', 'Espresso'] as $category) {
    check(Core\RewardEligibility::isCoffeeCategory($category), 'A recognized coffee category was rejected: ' . $category);
}
foreach (['Non-coffee', 'Non coffee', 'Coffee cake', 'Coffee-based desserts', 'Tea', '', null, []] as $category) {
    check(!Core\RewardEligibility::isCoffeeCategory($category), 'A non-coffee category was eligible for a free coffee.');
}

check((new Models\Users())->save(['username' => 'reward-member', 'password' => 'password', 'role' => 'loyal_customer']),
    'Could not create the reward customer.');
$db->exec('INSERT INTO loyal (user_id, loyalty_points) VALUES (3, 7)');
$db->exec("INSERT INTO categories (category_name) VALUES ('Non-coffee')");
$db->exec("INSERT INTO categories (category_name) VALUES ('Coffee_based')");
$products = new Models\Products();
foreach ([
    ['product_name' => 'Tea', 'category_id' => '2', 'product_price' => '25'],
    ['product_name' => 'Iced latte', 'category_id' => '3', 'product_price' => '50', 'ingredient_quantity' => ['1.000']],
    ['product_name' => 'Inactive coffee'],
    ['product_name' => 'Unavailable coffee'],
    ['product_name' => 'Stock-short coffee', 'ingredient_quantity' => ['200.000']],
] as $overrides) check($products->save(productPayload($overrides)), 'Could not create a reward eligibility fixture.');
check($products->setActive(4, false) && $products->setAvailable(5, false), 'Could not configure product availability.');
$insert = $db->prepare('INSERT INTO loyalty_redemptions (user_id, reward_code, points_spent, created_at) VALUES (3, ?, 10, ?)');
foreach (['GC-A1B2C3D4', 'GC-B2C3D4E5', 'GC-C3D4E5F6'] as $code) $insert->execute([$code, '2026-10-25 09:00:00']);
$db->prepare('UPDATE loyalty_redemptions SET used_at = ? WHERE reward_code = ?')
    ->execute(['2026-10-25 09:05:00', 'GC-C3D4E5F6']);

$pos = new Models\POS();
Core\Auth::authorizeRoute('Api', 'POS', 'reward');
$snapshot = rewardCheckoutSnapshot($db);
$response = rewardLookupRequest(' gc-a1b2c3d4 ');
$eligible = array_map(function ($product) { return (int) $product->product_id; }, $response['data']['products'] ?? []);
sort($eligible);
check($response['status'] === 'success' && $response['data']['reward_code'] === 'GC-A1B2C3D4'
    && $response['data']['customer_name'] === 'reward-member' && $response['data']['points_spent'] === 10
    && $response['data']['created_at'] === '2026-10-25 09:00:00' && $eligible === [1, 3],
    'Existing GC codes must retrieve customer/reward details and only eligible, active, available, stocked coffees.');
foreach ([null, [], '', 'KG-A1B2C3D4', 'GC-UNKNOWN', 'GC-FFFFFFFF', 'GC-C3D4E5F6'] as $code) {
    $response = rewardLookupRequest($code);
    check($response['status'] === 'error' && http_response_code() === 400 && !empty($response['message']),
        'Missing, malformed, unknown, or used reward codes must return a clear HTTP 400 response.');
}
check(rewardLookupRequest('GC-A1B2C3D4', 'POST')['status'] === 'error' && http_response_code() === 405,
    'Reward retrieval must require GET.');
check(rewardCheckoutSnapshot($db) === $snapshot, 'Reward lookup must not consume codes or change sales, points, or stock.');

$payload = ['checkout_key' => 'reward-checkout-existing', 'reward_code' => ' gc-a1b2c3d4 ',
    'customer_name' => 'reward-member', 'amount_paid' => '0.00', 'expected_total' => '0.00',
    'items' => [['product_id' => 1, 'quantity' => 1, 'product_name' => 'Forged name', 'unit_price' => 999]]];
foreach ([
    [['reward_code' => ''], 'valid reward code'],
    [['reward_code' => []], 'valid reward code'],
    [['reward_code' => 'KG-A1B2C3D4'], 'valid reward code'],
    [['reward_code' => 'GC-FFFFFFFF'], 'Reward code not found'],
    [['reward_code' => 'GC-C3D4E5F6'], 'already been used'],
    [['items' => [['product_id' => 1, 'quantity' => 2]]], 'exactly one coffee'],
    [['items' => [['product_id' => 1, 'quantity' => 1], ['product_id' => 3, 'quantity' => 1]]], 'exactly one coffee'],
    [['kiosk_order_id' => 999], 'separately from a kiosk order'],
    [['amount_paid' => '1.00'], 'requires no payment'],
    [['items' => [['product_id' => 2, 'quantity' => 1]]], 'eligible coffee'],
    [['items' => [['product_id' => 4, 'quantity' => 1]]], 'unavailable'],
    [['items' => [['product_id' => 5, 'quantity' => 1]]], 'unavailable'],
    [['items' => [['product_id' => 6, 'quantity' => 1]]], 'Insufficient inventory'],
] as [$changes, $message]) rejectRewardCheckout($pos, array_replace($payload, $changes), $message);
$withoutCode = $payload;
unset($withoutCode['reward_code'], $withoutCode['expected_total']);
rejectRewardCheckout($pos, $withoutCode, 'Amount paid must cover');

$response = rewardCheckoutRequest($payload);
check($response['status'] === 'success' && (float) $response['total_amount'] === 0.0
    && (float) $response['amount_paid'] === 0.0 && (float) $response['change_amount'] === 0.0
    && !$response['loyalty_point_earned'], 'A reward must complete as a zero-value sale without earning a loyalty point.');
$saleId = (int) $response['sale_id'];
$sale = $pos->transaction($saleId);
check((int) $sale->user_id === 2 && count($sale->items) === 1 && $sale->items[0]->product_name === 'Americano'
    && (int) $sale->items[0]->quantity === 1 && (float) $sale->items[0]->unit_price === 0.0
    && (float) $sale->items[0]->subtotal === 0.0, 'The free sale and receipt must retain the chosen coffee with trusted name and zero price.');
$stmt = $db->prepare('SELECT used_sale_id, used_at FROM loyalty_redemptions WHERE reward_code = ?');
$stmt->execute(['GC-A1B2C3D4']);
$used = $stmt->fetch();
check((int) $used->used_sale_id === $saleId && !empty($used->used_at)
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 81.75
    && (int) $db->query('SELECT loyalty_points FROM loyal WHERE user_id = 3')->fetchColumn() === 7
    && (int) $db->query('SELECT COUNT(*) FROM loyalty_earnings')->fetchColumn() === 0,
    'Reward usage, sale and ingredient deductions must commit together without spending or earning more points.');
$snapshot = rewardCheckoutSnapshot($db);
$replay = rewardCheckoutRequest(array_replace($payload, ['reward_code' => 'GC-A1B2C3D4']));
check($replay['status'] === 'success' && (int) $replay['sale_id'] === $saleId && rewardCheckoutSnapshot($db) === $snapshot,
    'Retrying the same key and normalized reward code must recover the original sale without another deduction.');
rejectRewardCheckout($pos, array_replace($payload, ['reward_code' => 'GC-B2C3D4E5']), 'already paid with different details');
rejectRewardCheckout($pos, array_replace($payload, ['checkout_key' => 'reward-different-checkout']), 'already been used');
$withoutCode = $payload;
unset($withoutCode['reward_code']);
rejectRewardCheckout($pos, $withoutCode, 'already paid with different details');
check(rewardLookupRequest('GC-A1B2C3D4')['status'] === 'error' && http_response_code() === 400,
    'A fulfilled reward must no longer be retrievable.');
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];
Core\Auth::authorizeRoute('Api', 'POS', 'reward');
rejectRewardCheckout($pos, $payload, 'completed by another cashier');
check(rewardLookupRequest('GC-B2C3D4E5')['status'] === 'success', 'Owners must also be able to retrieve unused rewards.');
$_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];

$second = array_replace($payload, ['checkout_key' => 'reward-checkout-rollback', 'reward_code' => 'GC-B2C3D4E5',
    'items' => [['product_id' => 3, 'quantity' => 1]]]);
$postgres = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
if ($postgres) {
    $db->exec('CREATE FUNCTION fail_reward_use() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN IF NEW.reward_code = \'GC-B2C3D4E5\' THEN RAISE EXCEPTION \'Injected reward failure\'; END IF;
        RETURN NEW; END; $$');
    $db->exec('CREATE TRIGGER fail_reward_use BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        FOR EACH ROW EXECUTE FUNCTION fail_reward_use()');
} else {
    $db->exec('CREATE TRIGGER fail_reward_use BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        WHEN NEW.reward_code = \'GC-B2C3D4E5\'
        BEGIN SELECT RAISE(ABORT, \'Injected reward failure\'); END');
}
$snapshot = rewardCheckoutSnapshot($db);
$response = rewardCheckoutRequest($second);
check($response['status'] === 'error' && str_contains($response['message'], 'Injected reward failure')
    && !$db->inTransaction() && rewardCheckoutSnapshot($db) === $snapshot,
    'Failure while consuming a reward must roll back its sale, items, stock and usage marker.');
$db->exec('DROP TRIGGER fail_reward_use' . ($postgres ? ' ON loyalty_redemptions' : ''));
if ($postgres) $db->exec('DROP FUNCTION fail_reward_use()');
check(rewardLookupRequest('GC-B2C3D4E5')['status'] === 'success', 'A failed checkout must leave the reward usable.');
$response = rewardCheckoutRequest($second);
check($response['status'] === 'success' && (int) $response['sale_id'] !== $saleId
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 80.75,
    'Retrying after rollback must fulfill the selected eligible coffee exactly once.');

$normal = ['checkout_key' => 'legacy-normal-checkout', 'customer_name' => 'Walk-in', 'amount_paid' => '50.00',
    'expected_total' => '50.00', 'items' => [['product_id' => 3, 'quantity' => 1]]];
$normalSale = $pos->checkout($normal);
$stmt = $db->prepare('SELECT checkout_payload_hash FROM sales WHERE sale_id = ?');
$stmt->execute([$normalSale['sale_id']]);
$legacyHash = hash('sha256', json_encode([[3 => 1], 'Walk-in', 5000, null, 5000]));
check($stmt->fetchColumn() === $legacyHash && (int) $pos->checkout($normal)['sale_id'] === (int) $normalSale['sale_id'],
    'Normal checkout fingerprints and retries must remain compatible with previously saved transactions.');

foreach (['owner' => true, 'cashier' => true, 'admin' => false, 'staff' => false, 'user' => false, 'loyal_customer' => false] as $role => $allowed) {
    $_SESSION['user'] = ['id' => 2, 'role' => $role];
    check(Core\Auth::canAccess('POS', 'reward', 'Api') === $allowed, 'Incorrect reward retrieval permission for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('POS', 'reward', 'Api'), 'Guests must not retrieve cashier rewards.');

echo "Loyalty checkout passed: existing GC lookup, eligible coffee selection, zero-price stock deduction, once-only usage, atomic rollback and safe retries.\n";
