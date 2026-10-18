<?php
require __DIR__ . '/pos_fixture.php';

$items = [['product_id' => 1, 'quantity' => 1]];
foreach (['125.50', '200.00'] as $paid) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['items' => json_encode($items), 'amount_paid' => $paid];
    try {
        (new TestPOSController())->checkout();
        throw new LogicException('Payment checkout must return JSON.');
    } catch (JsonResponse $response) {
        check(($response->data['status'] ?? '') === 'success', 'A sufficient valid payment must be accepted.');
        $stmt = $db->prepare('SELECT amount_paid, total_amount FROM sales WHERE sale_id = ?');
        $stmt->execute([$response->data['sale_id']]);
        $sale = $stmt->fetch();
        check($sale && (int) round((float) $sale->amount_paid * 100) === (int) round((float) $paid * 100)
            && (int) round((float) $sale->total_amount * 100) === 12550,
            'The completed transaction must store the actual amount paid.');
    }
}

$invalidPayments = [[]];
foreach (['', null, [], 'invalid', 'NaN', 'INF', INF, NAN, '-1', '0', '125.49', '125.501', '1e3', '100000000.00'] as $paid) {
    $invalidPayments[] = ['amount_paid' => $paid];
}
$pos = new Models\POS();
foreach ($invalidPayments as $index => $payment) {
    $rejected = false;
    try { $pos->checkout(['items' => $items] + $payment); }
    catch (PDOException | TypeError $error) {
        throw new RuntimeException('Invalid payment ' . $index . ' must fail validation safely.', 0, $error);
    } catch (Exception $error) { $rejected = true; }
    check($rejected && !$db->inTransaction()
        && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2
        && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 2
        && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
        'Missing, malformed, or insufficient payment must not create a transaction or consume stock.');
}

echo "FR-021 passed: exact and excess payments stored; missing, malformed, and insufficient payments rejected atomically.\n";
