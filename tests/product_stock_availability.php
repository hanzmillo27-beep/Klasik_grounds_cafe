<?php
require __DIR__ . '/pos_fixture.php';

function checkAvailability(Models\Products $products, int $id, bool $stock, bool $orderable): void {
    $product = $products->findByID($id);
    check($product && property_exists($product, 'stock_available') && property_exists($product, 'can_order')
        && property_exists($product, 'availability_reason') && $product->stock_available === $stock
        && $product->can_order === $orderable && is_string($product->availability_reason)
        && ($orderable || $product->availability_reason !== ''),
        'Product ' . $id . ' has incorrect stock availability, ordering permission, or reason.');
}
function rejectUnavailableCart(callable $submit): void {
    $rejected = false;
    try { $submit(); }
    catch (PDOException | TypeError $error) { throw new RuntimeException('Unavailable carts must fail validation safely.', 0, $error); }
    catch (Exception $error) { $rejected = true; }
    $db = Core\Database::$connection;
    check($rejected && !$db->inTransaction()
        && (int) $db->query('SELECT COUNT(*) FROM kiosk_orders')->fetchColumn() === 0
        && (int) $db->query('SELECT COUNT(*) FROM kiosk_order_items')->fetchColumn() === 0
        && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0,
        'Rejected unavailable carts must not leave orders, sales, or an open transaction.');
}

$inventory = new Models\Inventory();
$products = new Models\Products();
$pos = new Models\POS();
$kiosk = new Models\Kiosk();
check($inventory->save(['inv_name' => 'Milk', 'amount' => '0.001', 'unit' => 'ml', 'min_stock' => '10']), 'Could not create Milk.');
check($inventory->save(['inv_name' => 'Unrelated salt', 'amount' => '0', 'unit' => 'g']), 'Could not create unrelated zero stock.');
check($products->save(productPayload(['product_name' => 'Tiny latte', 'product_price' => '50',
    'ingredient_name' => ['Beans', 'Milk'], 'ingredient_quantity' => ['0.001', '0.001']])), 'Could not create a mixed fractional recipe.');
check($products->save(productPayload(['product_name' => 'Black coffee', 'product_price' => '75',
    'ingredient_quantity' => ['10']])), 'Could not create another shared-ingredient recipe.');
foreach (['Packaged cookie', 'Manually off', 'Inactive'] as $name) {
    check($products->save(productPayload(['product_name' => $name, 'ingredient_name' => [], 'ingredient_quantity' => []])),
        'Could not create a product without a recipe.');
}
check($products->setAvailable(5, false) && $products->setActive(6, false), 'Could not configure manual product states.');
foreach ([1, 2, 3, 4] as $id) checkAvailability($products, $id, true, true);
checkAvailability($products, 5, true, false);
checkAvailability($products, 6, true, false);
check($inventory->findByID(2)->is_low && $products->findByID(2)->can_order,
    'Low-stock alerts must not disable a product when its complete recipe is still available.');

$db->exec('UPDATE inventory SET amount = 18.250, min_stock = 100 WHERE inv_id = 1');
checkAvailability($products, 1, true, true);
check($inventory->findByID(1)->is_low, 'The equality case must also demonstrate that low stock can remain orderable.');
$db->exec('UPDATE inventory SET amount = 18.249 WHERE inv_id = 1');
checkAvailability($products, 1, false, false);
checkAvailability($products, 2, true, true);
checkAvailability($products, 4, true, true);
check($pos->product(1) === null, 'POS product lookup must reject a recipe that lacks stock.');
foreach (['Products' => $products->find(), 'POS' => $pos->products(), 'Kiosk' => $kiosk->products()] as $source => $list) {
    $byId = [];
    foreach ($list as $product) $byId[(int) $product->product_id] = $product;
    check(isset($byId[1]) && $byId[1]->stock_available === false && $byId[1]->can_order === false
        && is_string($byId[1]->availability_reason) && $byId[1]->availability_reason !== '',
        $source . ' must retain a stock-short product with an explicit unavailable state.');
    if ($source === 'Products') check(count($list) === 6, 'Management must retain inactive and manually unavailable products.');
    else check(!isset($byId[5]) && !isset($byId[6]), $source . ' must retain existing manual/inactive filtering.');
}
$cart = ['items' => [['product_id' => 1, 'quantity' => 1]]];
rejectUnavailableCart(function () use ($kiosk, $cart) { $kiosk->submit($cart); });
rejectUnavailableCart(function () use ($pos, $cart) { $pos->checkout($cart + ['amount_paid' => '200.00', 'checkout_key' => checkoutTestKey()]); });
check((int) round((float) $inventory->findByID(1)->amount * 1000) === 18249,
    'Rejected out-of-stock orders must not consume stock.');
check($inventory->restock(1, '0.001'), 'Could not restore the exact recipe amount.');
checkAvailability($products, 1, true, true);
check($products->findByID(1)->is_available === true && $products->findByID(5)->is_available === false,
    'Automatic stock recovery must not rewrite manual availability flags.');

$cart = ['items' => [['product_id' => 2, 'quantity' => 2]]];
rejectUnavailableCart(function () use ($kiosk, $cart) { $kiosk->submit($cart); });
check((int) round((float) $inventory->findByID(2)->amount * 1000) === 1,
    'A cart requiring multiple portions must validate its complete ingredient demand.');
$db->exec('UPDATE inventory SET amount = 0 WHERE inv_id = 2');
checkAvailability($products, 2, false, false);
check($inventory->restock(2, '0.001'), 'Could not replenish a sub-unit ingredient.');
checkAvailability($products, 2, true, true);
$db->exec('UPDATE inventory SET amount = 20 WHERE inv_id = 1');
checkAvailability($products, 1, true, true);
checkAvailability($products, 3, true, true);
$sharedCart = ['items' => [['product_id' => 1, 'quantity' => 1], ['product_id' => 3, 'quantity' => 1]]];
rejectUnavailableCart(function () use ($kiosk, $sharedCart) { $kiosk->submit($sharedCart); });
check((float) $inventory->findByID(1)->amount === 20.0 && (int) round((float) $inventory->findByID(2)->amount * 1000) === 1,
    'Shared-ingredient shortages must reject the whole kiosk cart without consuming any stock.');

$db->exec('UPDATE inventory SET amount = 28.250 WHERE inv_id = 1');
$submitted = $kiosk->submit($sharedCart);
check(!empty($submitted['reference_code']) && (int) $db->query('SELECT COUNT(*) FROM kiosk_orders')->fetchColumn() === 1
    && (float) $inventory->findByID(1)->amount === 28.25,
    'A sufficient kiosk cart may be queued without reserving or consuming ingredients.');
$pos->checkout(['checkout_key' => checkoutTestKey(), 'amount_paid' => '200.00',
    'items' => [['product_id' => 1, 'quantity' => 1]]]);
checkAvailability($products, 1, false, false);
checkAvailability($products, 3, true, true);
checkAvailability($products, 4, true, true);
checkAvailability($products, 5, true, false);
checkAvailability($products, 6, true, false);
check((float) $inventory->findByID(1)->amount === 10.0
    && $products->findByID(1)->is_available === true,
    'Completed sales must update effective availability while retaining the manual setting.');

echo "Product stock availability passed: complete recipes, low-stock distinction, list flags, restock recovery and safe cart validation.\n";
