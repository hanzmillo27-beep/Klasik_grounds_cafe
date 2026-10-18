<?php
require __DIR__ . '/pos_fixture.php';

check((new Models\Products())->save(productPayload([
    'product_name' => 'Candy', 'product_price' => '0.10', 'ingredient_name' => [], 'ingredient_quantity' => [],
])), 'Could not create a fractional-price product.');
$pos = new Models\POS();
foreach ([[1, 1, '125.50', 12550, 0], [1, 1, '200.00', 12550, 7450], [2, 3, '1.00', 30, 70]] as [$id, $quantity, $paid, $totalCents, $changeCents]) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['items' => json_encode([['product_id' => $id, 'quantity' => $quantity]]),
        'amount_paid' => $paid, 'change_amount' => '99999999.99', 'checkout_key' => checkoutTestKey()];
    try {
        (new TestPOSController())->checkout();
        throw new LogicException('Checkout must return JSON.');
    } catch (JsonResponse $response) {
        $result = $response->data;
        check(($result['status'] ?? '') === 'success' && array_key_exists('change_amount', $result)
            && (int) round((float) $result['change_amount'] * 100) === $changeCents,
            'The API must calculate exact change and ignore browser-supplied change.');
        $sale = $pos->transaction($result['sale_id']);
        check($sale && property_exists($sale, 'change_amount')
            && (int) round((float) $sale->change_amount * 100) === $changeCents
            && (int) round((float) $sale->total_amount * 100) === $totalCents
            && (int) round((float) $sale->amount_paid * 100) === $totalCents + $changeCents,
            'The saved transaction must retain paid amount, total, and change in exact cents.');
        $stmt = $db->prepare('SELECT change_amount FROM sales WHERE sale_id = ?');
        $stmt->execute([$result['sale_id']]);
        check((int) round((float) $stmt->fetchColumn() * 100) === $changeCents,
            'Calculated change must be persisted in the sales record.');
    }
}
$rejected = false;
try { $pos->checkout(['items' => [['product_id' => 1, 'quantity' => 1]], 'amount_paid' => '125.49', 'change_amount' => '-0.01']); }
catch (PDOException $error) { throw new RuntimeException('Insufficient payment must fail validation safely.', 0, $error); }
catch (Exception $error) { $rejected = true; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 3
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 3
    && (int) $db->query('SELECT COUNT(*) FROM sales WHERE change_amount < 0')->fetchColumn() === 0
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
    'Insufficient payment must never save negative change or alter sales and stock.');

echo "FR-022 passed: exact and excess change in cents, persisted and returned change, forged and negative change prevented.\n";
