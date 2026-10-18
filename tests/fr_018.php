<?php
require __DIR__ . '/pos_fixture.php';

$pos = new Models\POS();
$kiosk = new Models\Kiosk();
check((new Models\Products())->save(productPayload([
    'product_name' => 'Latte', 'product_price' => '150', 'ingredient_quantity' => ['5'],
])), 'Could not create a product to add to an order.');
$normalCart = [['product_id' => 1, 'quantity' => 1]];
$normalCart[0]['quantity'] = 2;
$normal = $pos->checkout(['amount_paid' => '1000.00', 'customer_name' => 'Edited customer', 'items' => $normalCart]);
$sale = $pos->transaction($normal['sale_id']);
check(count($sale->items) === 1 && (int) $sale->items[0]->quantity === 2
    && abs((float) $sale->total_amount - 251) < 0.00001,
    'A normal order must save the modified cart quantity.');

$submitted = $kiosk->submit(['customer_name' => 'Original customer', 'items' => [['product_id' => 1, 'quantity' => 1]]]);
$claimed = $kiosk->claim($submitted['reference_code'], 2);
check($claimed->status === 'claimed', 'The cashier could not claim the kiosk order.');
$db->exec("UPDATE products SET product_name = 'Current Americano', product_price = 999 WHERE product_id = 1");
$db->exec("UPDATE products SET product_name = 'Current Latte', product_price = 160 WHERE product_id = 2");
$edited = ['amount_paid' => '1000.00', 'kiosk_order_id' => $claimed->kiosk_order_id, 'customer_name' => 'Updated kiosk customer', 'items' => [
    ['product_id' => 1, 'quantity' => 2, 'product_name' => 'Forged name', 'unit_price' => 1],
    ['product_id' => 2, 'quantity' => 1, 'product_name' => 'Forged extra', 'unit_price' => 1],
]];
(new Models\Users())->save(['username' => 'other-cashier', 'password' => 'password', 'role' => 'cashier']);
$_SESSION['user'] = ['id' => 3, 'role' => 'cashier'];
$rejected = false;
try { $pos->checkout($edited); }
catch (PDOException $error) { throw new RuntimeException('Claim rejection must not be a schema error.', 0, $error); }
catch (Exception $error) { $rejected = true; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
    && $kiosk->findByReference($submitted['reference_code'])->status === 'claimed'
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 63.5) < 0.00001,
    'An unauthorized cashier must not change the claim, sales, or inventory.');

$_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];
$result = $pos->checkout($edited);
$sale = $pos->transaction($result['sale_id']);
check(count($sale->items) === 2 && (int) $sale->items[0]->product_id === 1
    && (int) $sale->items[0]->quantity === 2 && $sale->items[0]->product_name === 'Americano'
    && abs((float) $sale->items[0]->unit_price - 125.50) < 0.00001,
    'The modified kiosk quantity must retain its original saved product name and price.');
check((int) $sale->items[1]->product_id === 2 && (int) $sale->items[1]->quantity === 1
    && $sale->items[1]->product_name === 'Current Latte' && abs((float) $sale->items[1]->unit_price - 160) < 0.00001
    && abs((float) $sale->total_amount - 411) < 0.00001 && $sale->customer_name === 'Updated kiosk customer',
    'Added products and the customer name must match the edited cart using trusted prices.');
$completed = $kiosk->findByReference($submitted['reference_code']);
check($completed->status === 'completed' && (int) $completed->sale_id === (int) $result['sale_id']
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 22) < 0.00001,
    'The edited kiosk order must complete with matching ingredient deductions.');
$rejected = false;
try { $pos->checkout($edited); }
catch (PDOException $error) { throw new RuntimeException('Completed-order rejection must not be a schema error.', 0, $error); }
catch (Exception $error) { $rejected = true; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2
    && abs((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() - 22) < 0.00001,
    'A completed kiosk order must not create another sale or deduct stock twice.');

echo "FR-018 passed: edited normal and kiosk quantities, added products, trusted prices, claim and completion guards.\n";
