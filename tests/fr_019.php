<?php
require __DIR__ . '/pos_fixture.php';

function rejectEmptyOrder(Models\POS $pos, array $payload): void {
    $rejected = false;
    try { $pos->checkout($payload + ['amount_paid' => '1000.00']); }
    catch (PDOException $error) { throw new RuntimeException('An empty cart must fail validation safely.', 0, $error); }
    catch (Exception $error) { $rejected = stripos($error->getMessage(), 'empty') !== false; }
    check($rejected && !Core\Database::$connection->inTransaction(), 'Removing every item must reject checkout and roll back.');
}

$db->exec("INSERT INTO inventory (inv_name, amount, unit) VALUES ('Milk', 200, 'ml')");
check((new Models\Products())->save(productPayload([
    'product_name' => 'Latte', 'product_price' => '150',
    'ingredient_name' => ['Milk'], 'ingredient_quantity' => ['10'],
])), 'Could not create the second product.');
$pos = new Models\POS();
$kiosk = new Models\Kiosk();
$cart = [['product_id' => 1, 'quantity' => 1], ['product_id' => 2, 'quantity' => 2]];
array_pop($cart);
$normal = $pos->checkout(['amount_paid' => '1000.00', 'items' => $cart]);
$sale = $pos->transaction($normal['sale_id']);
check(count($sale->items) === 1 && (int) $sale->items[0]->product_id === 1
    && abs((float) $sale->total_amount - 125.50) < 0.00001,
    'A removed normal-cart item must not reappear in the sale.');
check((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 2')->fetchColumn() === 200.0,
    'Removing a product must not consume its ingredients.');
rejectEmptyOrder($pos, ['items' => []]);

$submitted = $kiosk->submit(['items' => [['product_id' => 1, 'quantity' => 1], ['product_id' => 2, 'quantity' => 2]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
$remaining = [['product_id' => 2, 'quantity' => 2]];
$result = $pos->checkout(['amount_paid' => '1000.00', 'kiosk_order_id' => $claimed->kiosk_order_id, 'items' => $remaining]);
$sale = $pos->transaction($result['sale_id']);
check(count($sale->items) === 1 && (int) $sale->items[0]->product_id === 2
    && (int) $sale->items[0]->quantity === 2 && abs((float) $sale->total_amount - 300) < 0.00001,
    'A removed kiosk item must remain removed when the claimed order is saved.');
check(abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 81.75) < 0.00001
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 2')->fetchColumn() === 180.0,
    'Only the remaining kiosk items may consume stock.');

$submitted = $kiosk->submit(['items' => [['product_id' => 1, 'quantity' => 1]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
rejectEmptyOrder($pos, ['kiosk_order_id' => $claimed->kiosk_order_id, 'items' => []]);
$recovered = $kiosk->claim($submitted['reference_code'], 2);
check($recovered->status === 'claimed' && (int) $recovered->kiosk_order_id === (int) $claimed->kiosk_order_id
    && $recovered->sale_id === null, 'An emptied kiosk order must remain recoverable by its cashier.');
check((int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 2
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 81.75) < 0.00001
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 2')->fetchColumn() === 180.0,
    'Removing every item must leave completed sales and inventory unchanged.');

echo "FR-019 passed: normal and kiosk item removal persists, removed stock untouched, empty claimed orders recoverable.\n";
