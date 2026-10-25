<?php
require __DIR__ . '/loyalty_fixture.php';

function loyaltyRewardFailure(callable $action, string $message): void {
    try {
        $action();
    } catch (Exception $error) {
        check(str_contains($error->getMessage(), $message), 'Unexpected reward error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('The invalid reward operation unexpectedly succeeded.');
}

check((new Models\Users())->save(['username' => 'reward-customer', 'password' => 'password', 'role' => 'loyal_customer']),
    'Could not create the loyal customer.');
$customerId = (int) (new Models\Users())->findByUsername('reward-customer')->id;
$db->prepare('INSERT INTO loyal (user_id, loyalty_points) VALUES (?, ?)')->execute([$customerId, 23]);
$loyalty = new Models\Loyalty();
$before = $loyalty->summary($customerId);
check($before['points'] === 23 && $before['rewards_available'] === 2 && $before['activity'] === [],
    'A balance without activity must remain readable without a loyalty_id column.');

$issued = $loyalty->redeem($customerId);
$code = $issued['reward_code'];
check(preg_match('/^GC-[A-F0-9]{8}$/D', $code) === 1 && $issued['points'] === 13,
    'Redeeming must generate the supported GC code and subtract exactly ten points.');
check((int) $db->query('SELECT COUNT(*) FROM loyalty_redemptions')->fetchColumn() === 1 && !$db->inTransaction(),
    'Issuing a reward must commit one redemption.');
$stored = $db->query('SELECT * FROM loyalty_redemptions')->fetch();
check($stored->reward_code === $code && (int) $stored->user_id === $customerId && (int) $stored->points_spent === 10
    && $stored->used_sale_id === null && $stored->used_at === null,
    'New and legacy unused codes must not be marked used at issuance.');
$snapshot = json_encode($stored);
$reward = $loyalty->reward($code);
check($reward === [
    'reward_code' => $code, 'customer_name' => 'reward-customer', 'user_id' => $customerId,
    'created_at' => $stored->created_at, 'points_spent' => 10,
] && $loyalty->reward('  ' . strtolower($code) . "\n") === $reward,
    'Lookup must return the exact issued reward, accepting surrounding whitespace and lowercase.');
loyaltyRewardFailure(function () use ($loyalty) { $loyalty->reward('GC-'); }, 'valid free coffee');
loyaltyRewardFailure(function () use ($loyalty, $code) { $loyalty->reward($code . 'suffix'); }, 'valid free coffee');
loyaltyRewardFailure(function () use ($loyalty) { $loyalty->reward('KG-12345678'); }, 'valid free coffee');
$unknown = $code === 'GC-00000000' ? 'GC-FFFFFFFF' : 'GC-00000000';
loyaltyRewardFailure(function () use ($loyalty, $unknown) { $loyalty->reward($unknown); }, 'not found');
check(json_encode($db->query('SELECT * FROM loyalty_redemptions')->fetch()) === $snapshot
    && $loyalty->summary($customerId)['points'] === 13 && !$db->inTransaction(),
    'Valid, normalized, invalid, and unknown lookups must not consume the reward or deduct points.');

$available = $loyalty->summary($customerId)['activity'][0];
check($available->activity_type === 'redeemed' && $available->reward_code === $code
    && (int) $available->points === -10 && $available->status === 'available'
    && $available->used_sale_id === null && $available->used_at === null,
    'Unused reward activity must show that the code is available.');
$db->prepare('INSERT INTO sales (user_id, customer_name, total_amount, amount_paid, change_amount, sale_date)
    VALUES (?, ?, 0, 0, 0, ?)')->execute([2, 'reward-customer', '2026-10-25 12:00:00']);
$saleId = (int) $db->lastInsertId();
$db->prepare('UPDATE loyalty_redemptions SET used_sale_id = ?, used_at = ? WHERE reward_code = ?')
    ->execute([$saleId, '2026-10-25 12:00:00', $code]);
loyaltyRewardFailure(function () use ($loyalty, $code) { $loyalty->reward($code); }, 'already been used');
$db->prepare('INSERT INTO loyalty_earnings (user_id, sale_id, point_date, earned_at) VALUES (?, ?, ?, ?)')
    ->execute([$customerId, $saleId, '2026-10-25', '2026-10-25 13:00:00']);
$summary = $loyalty->summary($customerId);
$used = array_values(array_filter($summary['activity'], function ($row) { return $row->activity_type === 'redeemed'; }))[0];
$earned = array_values(array_filter($summary['activity'], function ($row) { return $row->activity_type === 'earned'; }))[0];
check($used->status === 'used' && (int) $used->used_sale_id === $saleId && $used->used_at === '2026-10-25 12:00:00'
    && $earned->used_sale_id === null && $earned->used_at === null && $earned->status === null
    && $summary['points'] === 13,
    'Activity must distinguish used rewards and ordinary earnings without changing the balance.');

$db->prepare('UPDATE loyal SET loyalty_points = 9 WHERE user_id = ?')->execute([$customerId]);
$redemptions = (int) $db->query('SELECT COUNT(*) FROM loyalty_redemptions')->fetchColumn();
loyaltyRewardFailure(function () use ($loyalty, $customerId) { $loyalty->redeem($customerId); }, 'need 10 points');
check($loyalty->summary($customerId)['points'] === 9
    && (int) $db->query('SELECT COUNT(*) FROM loyalty_redemptions')->fetchColumn() === $redemptions
    && !$db->inTransaction(), 'Insufficient points must roll back without issuing a code or changing the balance.');
loyaltyRewardFailure(function () use ($loyalty) { $loyalty->redeem(999999); }, 'need 10 points');
check(!$db->inTransaction() && $loyalty->summary(999999)['points'] === 0,
    'A missing loyalty account must have no points and must not leave an open transaction.');

echo "Loyalty rewards passed: generated GC lookup, normalization, unchanged points, usage status, and insufficient-points rollback.\n";
