<?php
require __DIR__ . '/pos_fixture.php';

$pos = new Models\POS();
$kiosk = new Models\Kiosk();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['items' => json_encode([['product_id' => 1, 'quantity' => 1]]), 'amount_paid' => '200.00'];
try { (new TestPOSController())->checkout(); throw new LogicException('Missing checkout key was accepted.'); }
catch (JsonResponse $response) { check(($response->data['status'] ?? '') === 'error', 'The API must require a checkout key.'); }
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0, 'A missing checkout key created a sale.');

(new Models\Users())->save(['username' => 'loyal-customer', 'password' => 'password', 'role' => 'loyal_customer']);
$submitted = $kiosk->submit(['customer_user_id' => 3, 'items' => [['product_id' => 1, 'quantity' => 1]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
$payload = ['checkout_key' => 'fr023-atomic-order', 'kiosk_order_id' => $claimed->kiosk_order_id,
    'amount_paid' => '200.00', 'items' => [['product_id' => 1, 'quantity' => 1]]];
$postgres = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
if ($postgres) {
    $db->exec('CREATE FUNCTION fail_kiosk_completion() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN IF NEW.status = \'completed\' THEN RAISE EXCEPTION \'Injected checkout failure\'; END IF; RETURN NEW; END; $$');
    $db->exec('CREATE TRIGGER fail_kiosk_completion BEFORE UPDATE ON kiosk_orders
        FOR EACH ROW EXECUTE FUNCTION fail_kiosk_completion()');
} else {
    $db->exec('CREATE TRIGGER fail_kiosk_completion BEFORE UPDATE OF status ON kiosk_orders
        WHEN NEW.status = \'completed\' BEGIN SELECT RAISE(ABORT, \'Injected checkout failure\'); END');
}
$failed = false;
try { $pos->checkout($payload); }
catch (Exception $error) { $failed = str_contains($error->getMessage(), 'Injected checkout failure'); }
$order = $kiosk->findByReference($submitted['reference_code']);
check($failed && !$db->inTransaction() && $order->status === 'claimed' && $order->sale_id === null
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM loyalty_earnings')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM loyal')->fetchColumn() === 0
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 100.0,
    'A failure after sale/item/stock writes must roll back every checkout change.');
$db->exec('DROP TRIGGER fail_kiosk_completion' . ($postgres ? ' ON kiosk_orders' : ''));
if ($postgres) $db->exec('DROP FUNCTION fail_kiosk_completion()');

$result = $pos->checkout($payload);
$completed = $kiosk->findByReference($submitted['reference_code']);
check($completed->status === 'completed' && (int) $completed->sale_id === (int) $result['sale_id']
    && $result['loyalty_point_earned'] === true
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1
    && (int) $db->query('SELECT loyalty_points FROM loyal WHERE user_id = 3')->fetchColumn() === 1
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 81.75) < 0.00001,
    'A paid kiosk sale, items, inventory, completion, and loyalty point must commit together.');
$replay = $pos->checkout($payload);
check((int) $replay['sale_id'] === (int) $result['sale_id']
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 81.75) < 0.00001,
    'Replaying a checkout key must return the saved sale without another deduction.');
foreach ([['items' => [['product_id' => 1, 'quantity' => 2]], 'amount_paid' => '300.00'], ['amount_paid' => '250.00']] as $changes) {
    $rejected = false;
    try { $pos->checkout(array_replace($payload, $changes)); }
    catch (Exception $error) { $rejected = str_contains($error->getMessage(), 'already paid with different details'); }
    check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
        && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 81.75) < 0.00001,
        'A checkout key must not replay a sale when its cart or paid amount has changed.');
}
(new Models\Users())->save(['username' => 'other-cashier', 'password' => 'password', 'role' => 'cashier']);
$_SESSION['user'] = ['id' => 4, 'role' => 'cashier'];
$rejected = false;
try { $pos->checkout($payload); } catch (Exception $error) { $rejected = true; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1,
    'A different cashier must not reuse another cashier checkout key.');

$_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];
$submitted = $kiosk->submit(['customer_user_id' => 3, 'items' => [['product_id' => 1, 'quantity' => 1]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
$second = $pos->checkout(['checkout_key' => 'fr023-second-order', 'kiosk_order_id' => $claimed->kiosk_order_id,
    'amount_paid' => '125.50', 'items' => [['product_id' => 1, 'quantity' => 1]]]);
check($second['loyalty_point_earned'] === false
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2
    && (int) $db->query('SELECT COUNT(*) FROM loyalty_earnings')->fetchColumn() === 1
    && (int) $db->query('SELECT loyalty_points FROM loyal WHERE user_id = 3')->fetchColumn() === 1
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
    'A second paid order must complete without awarding a second daily loyalty point.');

echo "FR-023 passed: atomic checkout and rollback, loyalty persistence, replay protection, daily point limit.\n";
