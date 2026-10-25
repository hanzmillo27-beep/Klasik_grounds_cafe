<?php
// Uses only the isolated test database created by loyalty_fixture.php.
require __DIR__ . '/loyalty_fixture.php';

function itemRewardSnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['sales', 'sale_items', 'inventory', 'loyal', 'loyalty_earnings', 'loyalty_redemptions'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function rejectItemReward(callable $action, PDO $db, string $reason): void {
    $before = itemRewardSnapshot($db);
    $failed = false;
    try { $action(); }
    catch (PDOException | TypeError $error) {
        throw new RuntimeException($reason . ' must fail through validation, not a database/type error.', 0, $error);
    }
    catch (Exception $error) { $failed = $error->getMessage() !== ''; }
    check($failed && !$db->inTransaction() && itemRewardSnapshot($db) === $before,
        $reason . ' must preserve points, vouchers, sales and stock.');
}

function itemRewardLookup(string $code): array {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    http_response_code(200);
    try { (new TestPOSController())->reward($code); }
    catch (JsonResponse $response) { return $response->data; }
    throw new RuntimeException('Reward lookup must return JSON.');
}

check((new Models\Users())->save([
    'username' => 'item-reward-member', 'password' => 'password', 'role' => 'loyal_customer',
]), 'Could not create the item reward customer.');
$customerId = (int) (new Models\Users())->findByUsername('item-reward-member')->id;
$db->prepare('INSERT INTO loyal (user_id, loyalty_points) VALUES (?, 25)')->execute([$customerId]);
$db->exec("INSERT INTO categories (category_name) VALUES ('Foods')");
$products = new Models\Products();
foreach ([
    ['product_name' => 'Buffalo Wings', 'category_id' => '2', 'ingredient_quantity' => ['2.500']],
    ['product_name' => 'Other food', 'category_id' => '2', 'ingredient_quantity' => ['1.000']],
    ['product_name' => 'Inactive food', 'category_id' => '2'],
    ['product_name' => 'Unavailable food', 'category_id' => '2'],
    ['product_name' => 'Stock-short food', 'category_id' => '2', 'ingredient_quantity' => ['200.000']],
] as $overrides) check($products->save(productPayload($overrides)), 'Could not create the reward product.');
check($products->setActive(4, false) && $products->setAvailable(5, false), 'Could not set product availability.');
$loyalty = new Models\Loyalty();
$issued = $loyalty->redeemItem($customerId, 2);
$code = $issued['reward_code'];
check($issued['product_id'] === 2 && $issued['product_name'] === 'Buffalo Wings'
    && $issued['points'] === 15 && $issued['points_spent'] === 10
    && preg_match('/^GC-[A-F0-9]{8}$/D', $code) === 1,
    'The selected food must cost exactly ten points and return a complete cashier receipt.');
$stored = $db->query('SELECT * FROM loyalty_redemptions')->fetch();
check((int) $stored->product_id === 2 && $stored->product_name === 'Buffalo Wings'
    && $stored->reward_code === $code && $stored->used_sale_id === null && $stored->used_at === null
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0,
    'Issuance must save the selected product without consuming the voucher or ingredient stock.');
foreach ([0, -1, 999, 4, 5, 6] as $id) {
    rejectItemReward(fn() => $loyalty->redeemItem($customerId, $id), $db, 'Invalid/unavailable product ' . $id);
}
rejectItemReward(fn() => $loyalty->redeemItem(99999, 2), $db, 'Missing loyalty account');
$second = $loyalty->redeemItem($customerId, 1);
check($second['points'] === 5 && $second['points_spent'] === 10 && $second['reward_code'] !== $code,
    'Every further ten points must allow another selected item with a new code and preserve the remainder.');
rejectItemReward(fn() => $loyalty->redeemItem($customerId, 2), $db, 'Insufficient points');
$db->prepare('UPDATE products SET product_name = ? WHERE product_id = 2')->execute(['Renamed wings']);
$history = array_values(array_filter($loyalty->summary($customerId)['activity'], fn($row) => $row->reward_code === $code))[0];
check($history->description === 'Buffalo Wings' && $history->status === 'available'
    && $history->used_sale_id === null && $history->used_at === null,
    'Activity must preserve the selected name and existing available/used status fields.');
$metadata = $loyalty->reward($code);
check($metadata['product_id'] === 2 && $metadata['product_name'] === 'Buffalo Wings'
    && $metadata['points_spent'] === 10,
    'Cashier reward metadata must identify the saved chosen item.');
$lookup = itemRewardLookup($code);
check($lookup['status'] === 'success'
    && array_map(fn($product) => (int) $product->product_id, $lookup['data']['products']) === [2],
    'The cashier lookup must offer only the bound food, regardless of its non-coffee category.');

$pos = new Models\POS();
$payload = [
    'checkout_key' => 'chosen-item-checkout', 'reward_code' => $code,
    'customer_name' => 'item-reward-member', 'amount_paid' => '0.00', 'expected_total' => '0.00',
    'items' => [['product_id' => 2, 'quantity' => 1, 'unit_price' => 900, 'product_name' => 'Forged']],
];
foreach ([1, 3] as $id) {
    rejectItemReward(fn() => $pos->checkout(array_replace($payload, [
        'items' => [['product_id' => $id, 'quantity' => 1]],
    ])), $db, 'Replacing the selected item with product ' . $id);
}
rejectItemReward(fn() => $pos->checkout(array_replace($payload, ['amount_paid' => '1.00'])), $db, 'Charging for a free item');
$completed = $pos->checkout($payload);
$sale = $pos->transaction((int) $completed['sale_id']);
check((float) $completed['total_amount'] === 0.0 && (float) $completed['amount_paid'] === 0.0
    && !$completed['loyalty_point_earned'] && count($sale->items) === 1
    && (int) $sale->items[0]->product_id === 2 && (int) $sale->items[0]->quantity === 1
    && (float) $sale->items[0]->unit_price === 0.0 && (float) $sale->items[0]->subtotal === 0.0
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 97.5
    && $loyalty->summary($customerId)['points'] === 5,
    'The cashier must fulfill the selected food at zero price, consuming stock once without more point changes.');
$used = array_values(array_filter($loyalty->summary($customerId)['activity'], fn($row) => $row->reward_code === $code))[0];
check($used->status === 'used' && (int) $used->used_sale_id === (int) $completed['sale_id'] && !empty($used->used_at),
    'Fulfillment must preserve the used-sale history and mark the voucher used.');
$snapshot = itemRewardSnapshot($db);
$replay = $pos->checkout($payload);
check((int) $replay['sale_id'] === (int) $completed['sale_id'] && itemRewardSnapshot($db) === $snapshot,
    'Retrying the same checkout must recover its receipt without another inventory deduction.');
rejectItemReward(fn() => $pos->checkout(array_replace($payload, ['checkout_key' => 'chosen-item-reuse'])), $db, 'Reusing a fulfilled code');
check(itemRewardLookup($code)['status'] === 'error' && http_response_code() === 400,
    'A fulfilled chosen-item voucher must no longer be retrievable.');

// A failed consumption must roll back the free sale and inventory together.
$rollbackPayload = array_replace($payload, [
    'checkout_key' => 'chosen-item-rollback', 'reward_code' => $second['reward_code'],
    'items' => [['product_id' => 1, 'quantity' => 1]],
]);
if ($postgres) {
    $db->exec('CREATE FUNCTION fail_chosen_reward_use() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION \'Injected chosen reward failure\'; END; $$');
    $db->exec('CREATE TRIGGER fail_chosen_reward_use BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        FOR EACH ROW EXECUTE FUNCTION fail_chosen_reward_use()');
} else {
    $db->exec('CREATE TRIGGER fail_chosen_reward_use BEFORE UPDATE OF used_sale_id ON loyalty_redemptions
        BEGIN SELECT RAISE(ABORT, \'Injected chosen reward failure\'); END');
}
$snapshot = itemRewardSnapshot($db);
$failed = false;
try { $pos->checkout($rollbackPayload); }
catch (Throwable $error) { $failed = str_contains($error->getMessage(), 'Injected chosen reward failure'); }
check($failed && !$db->inTransaction() && itemRewardSnapshot($db) === $snapshot,
    'A failed chosen-voucher consumption must roll back the sale, stock and usage marker.');
$db->exec('DROP TRIGGER fail_chosen_reward_use' . ($postgres ? ' ON loyalty_redemptions' : ''));
if ($postgres) $db->exec('DROP FUNCTION fail_chosen_reward_use()');
check(itemRewardLookup($second['reward_code'])['status'] === 'success', 'Failed fulfillment must leave its reward available.');

// Exercise JSON input validation without a server or production credentials.
class TestItemLoyaltyController extends Api\Loyalty\LoyaltyController {
    public function json($data): void { throw new JsonResponse($data); }
}
class ItemJsonInput {
    public $context;
    public static string $body = '';
    private int $position = 0;
    public function stream_open($path, $mode, $options, &$openedPath): bool { return $path === 'php://input'; }
    public function stream_read($count): string {
        $result = substr(self::$body, $this->position, $count);
        $this->position += strlen($result);
        return $result;
    }
    public function stream_eof(): bool { return $this->position >= strlen(self::$body); }
    public function stream_stat(): array { return []; }
}
function itemJsonRequest(string $body, string $method = 'POST'): array {
    ItemJsonInput::$body = $body;
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try { (new TestItemLoyaltyController())->redeemItem(); }
    catch (JsonResponse $response) { return $response->data; }
    throw new RuntimeException('Item redemption must return JSON.');
}
$_SESSION['user'] = ['id' => $customerId, 'username' => 'item-reward-member', 'role' => 'loyal_customer'];
$db->prepare('UPDATE loyal SET loyalty_points = 20 WHERE user_id = ?')->execute([$customerId]);
stream_wrapper_unregister('php');
stream_wrapper_register('php', ItemJsonInput::class);
try {
    foreach (['', '{', 'null', '[]', '{}', '{"product_id":0}', '{"product_id":"2"}',
        '{"product_id":2.5}', '{"product_id":true}', '{"product_id":[2]}'] as $body) {
        $before = itemRewardSnapshot($db);
        $response = itemJsonRequest($body);
        check($response['status'] === 'error' && http_response_code() === 400
            && !empty($response['message']) && itemRewardSnapshot($db) === $before,
            'Invalid JSON/IDs must not spend points or issue a voucher.');
    }
    $before = itemRewardSnapshot($db);
    check(itemJsonRequest('{"product_id":2}', 'GET')['status'] === 'error' && http_response_code() === 405
        && itemRewardSnapshot($db) === $before, 'Only POST may redeem a chosen item.');
    $response = itemJsonRequest('{"product_id":2,"product_name":"Forged","points_spent":0}');
    check($response['status'] === 'success' && http_response_code() === 200
        && $response['data']['product_id'] === 2 && $response['data']['product_name'] === 'Renamed wings'
        && $response['data']['points_spent'] === 10 && $response['data']['points'] === 10,
        'The endpoint must return authoritative item/points data and ignore forged names or prices.');
} finally { stream_wrapper_restore('php'); }

echo "Chosen-item rewards passed: food selection, 10-point spending, bound cashier fulfillment, usage status, stock rollback and JSON validation.\n";
