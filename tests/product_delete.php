<?php
require __DIR__ . '/loyalty_fixture.php';

function deleteProductRequest($id = null, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['is_active' => '1'];
    http_response_code(200);
    try {
        (new TestProductsController())->delete($id);
        throw new LogicException('Product deletion must return JSON.');
    } catch (JsonResponse $response) { return $response->data; }
}

function productDeleteSnapshot(PDO $db): string {
    $snapshot = [];
    foreach (['products', 'product_ingredients', 'inventory', 'inventory_history', 'sales', 'sale_items',
        'kiosk_orders', 'kiosk_order_items', 'loyal', 'loyalty_earnings', 'loyalty_redemptions'] as $table) {
        $snapshot[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
    }
    return json_encode($snapshot);
}

function deleteProductFixture(Models\Products $products, string $name): int {
    check($products->save(productPayload(['product_name'=>$name, 'ingredient_quantity'=>['0.001'],
        'product_image'=>'products/retained-image.jpg'])), 'Could not create a product deletion fixture.');
    $stmt = Core\Database::$connection->prepare('SELECT product_id FROM products WHERE product_name = ?');
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn();
}

$products = new Models\Products();
$unsoldId = deleteProductFixture($products, 'Unsold product');
$inactiveId = deleteProductFixture($products, 'Inactive product');
$pendingId = deleteProductFixture($products, 'Pending kiosk product');
$claimedId = deleteProductFixture($products, 'Claimed kiosk product');
$rewardId = deleteProductFixture($products, 'Unused reward product');
$rollbackId = deleteProductFixture($products, 'Rollback product');
$cancelledId = deleteProductFixture($products, 'Cancelled kiosk product');
check($products->setActive($inactiveId, false), 'Could not deactivate the deletion fixture.');
check((new Models\Users())->save(['username'=>'delete-reward-member', 'password'=>'password', 'role'=>'loyal_customer']),
    'Could not create the reward member.');
$db->exec('INSERT INTO loyal (user_id, loyalty_points) VALUES (3, 20)');
$db->exec("INSERT INTO loyalty_redemptions (user_id, reward_code, points_spent)
    VALUES (3, 'GC-AABBCCDD', 10)");
$stmt = $db->prepare('INSERT INTO loyalty_redemptions (user_id, reward_code, points_spent, product_id, product_name)
    VALUES (3, ?, 10, ?, ?)');
$stmt->execute(['GC-11223344', $rewardId, 'Unused reward product']);
$kiosk = new Models\Kiosk();
$pending = $kiosk->submit(['items'=>[['product_id'=>$pendingId, 'quantity'=>1]]]);
$claimed = $kiosk->submit(['items'=>[['product_id'=>$claimedId, 'quantity'=>1]]]);
$kiosk->claim($claimed['reference_code'], 2);
$cancelled = $kiosk->submit(['items'=>[['product_id'=>$cancelledId, 'quantity'=>1]]]);
$cancelledOrder = $kiosk->findByReference($cancelled['reference_code']);
$kiosk->deleteOrder((int) $cancelledOrder->kiosk_order_id, 2);

$_SESSION['user'] = ['id'=>1, 'username'=>'test-owner', 'role'=>'owner'];
Core\Auth::authorizeRoute('Api', 'Products', 'delete');
$snapshot = productDeleteSnapshot($db);
foreach ([null, '', [], true, 'abc', '1.5', '1 OR 1=1', 0, -1] as $id) {
    $response = deleteProductRequest($id);
    check($response['status'] === 'error' && http_response_code() === 400,
        'Invalid or missing product IDs must return HTTP 400.');
    check(!$products->delete($id), 'The model must also reject invalid product IDs.');
}
foreach (['GET', 'PUT', 'DELETE'] as $method) {
    $response = deleteProductRequest($unsoldId, $method);
    check($response['status'] === 'error' && http_response_code() === 405 && $_POST['is_active'] === '1',
        'Product deletion must require POST without mutating status input.');
}
check(deleteProductRequest(999999)['status'] === 'error' && http_response_code() === 404
    && !$products->delete(999999) && !$db->inTransaction() && productDeleteSnapshot($db) === $snapshot,
    'Invalid, missing, or wrong-method deletion requests must not change catalog or historical data.');
foreach ([$pendingId, $claimedId, $rewardId] as $id) {
    $response = deleteProductRequest($id);
    check($response['status'] === 'error' && http_response_code() === 409 && !empty($response['message'])
        && !$db->inTransaction() && productDeleteSnapshot($db) === $snapshot,
        'Open kiosk orders and unused item-bound rewards must prevent product deletion with a clear conflict.');
}
$response = deleteProductRequest($unsoldId);
check($response === ['status'=>'success', 'message'=>'Product deleted successfully.'] && http_response_code() === 200
    && !$products->findByID($unsoldId), 'Deleting an active unsold product must physically remove its catalog row.');
check(!$products->findByID($unsoldId) && (int) $db->query('SELECT COUNT(*) FROM product_ingredients WHERE product_id = ' . $unsoldId)->fetchColumn() === 0
    && (new Models\Loyalty())->reward('GC-AABBCCDD')['reward_code'] === 'GC-AABBCCDD',
    'Deletion must remove recipe links while leaving legacy unbound reward codes usable.');
check(deleteProductRequest($inactiveId)['status'] === 'success' && !$products->findByID($inactiveId),
    'An inactive product must also support physical deletion.');
check(deleteProductRequest($unsoldId)['status'] === 'error' && http_response_code() === 404,
    'Deleting the same product again must report that it is missing.');
check(deleteProductRequest($cancelledId)['status'] === 'success', 'A cancelled kiosk order must not block product deletion.');
$savedCancelled = $kiosk->findByReference($cancelled['reference_code']);
check($savedCancelled->status === 'cancelled' && $savedCancelled->items[0]->product_id === null
    && $savedCancelled->items[0]->product_name === 'Cancelled kiosk product' && (int) $savedCancelled->items[0]->quantity === 1,
    'Cancelled kiosk item history must retain its saved details with a nullable catalog reference.');

$db->prepare('INSERT INTO sales (user_id, total_amount, amount_paid, change_amount, sale_date) VALUES (2, 10, 10, 0, ?)')
    ->execute(['2026-10-25 10:00:00']);
$legacySaleId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, NULL, 1, 10, 10)')
    ->execute([$legacySaleId, $rollbackId]);
$legacyReceiptName = (new Models\POS())->transaction($legacySaleId)->items[0]->product_name;
check($legacyReceiptName === 'Rollback product', 'A legacy NULL name must initially display its existing catalog fallback.');
$postgres = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
if ($postgres) {
    $db->exec('CREATE FUNCTION fail_product_delete() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION \'Injected product delete failure\'; END; $$');
    $db->exec('CREATE TRIGGER fail_product_delete BEFORE DELETE ON products
        FOR EACH ROW EXECUTE FUNCTION fail_product_delete()');
} else {
    $db->exec('CREATE TRIGGER fail_product_delete BEFORE DELETE ON products
        BEGIN SELECT RAISE(ABORT, \'Injected product delete failure\'); END');
}
$snapshot = productDeleteSnapshot($db);
$response = deleteProductRequest($rollbackId);
check($response['status'] === 'error' && http_response_code() === 500
    && !str_contains($response['message'], 'Injected') && !$db->inTransaction()
    && productDeleteSnapshot($db) === $snapshot, 'A failed product deletion must roll back missing-name preservation and recipe removal, returning a generic database error.');
$db->exec('DROP TRIGGER fail_product_delete' . ($postgres ? ' ON products' : ''));
if ($postgres) $db->exec('DROP FUNCTION fail_product_delete()');
check(deleteProductRequest($rollbackId)['status'] === 'success', 'Product deletion must recover after a rolled-back failure.');
$legacyReceipt = (new Models\POS())->transaction($legacySaleId);
check($legacyReceipt->items[0]->product_id === null && $legacyReceipt->items[0]->product_name === $legacyReceiptName
    && (float) $legacyReceipt->items[0]->unit_price === 10.0 && (int) $legacyReceipt->items[0]->quantity === 1,
    'Deleting a legacy product must preserve the receipt name it already displayed without changing price or quantity.');

$_SESSION['user'] = ['id'=>2, 'username'=>'test-cashier', 'role'=>'cashier'];
$pos = new Models\POS();
$normalPayload = ['checkout_key'=>'delete-sold-checkout', 'customer_name'=>'Saved customer', 'amount_paid'=>'200.00',
    'items'=>[['product_id'=>1, 'quantity'=>1]]];
$normalSale = $pos->checkout($normalPayload);
$completed = $kiosk->submit(['items'=>[['product_id'=>1, 'quantity'=>1]]]);
$completedOrder = $kiosk->claim($completed['reference_code'], 2);
$kioskPayload = ['checkout_key'=>'delete-completed-kiosk', 'kiosk_order_id'=>$completedOrder->kiosk_order_id,
    'amount_paid'=>'200.00', 'items'=>[['product_id'=>1, 'quantity'=>1]]];
$kioskSale = $pos->checkout($kioskPayload);
$reward = (new Models\Loyalty())->redeemItem(3, 1);
$rewardPayload = ['checkout_key'=>'delete-used-reward', 'reward_code'=>$reward['reward_code'],
    'amount_paid'=>'0.00', 'expected_total'=>'0.00', 'items'=>[['product_id'=>1, 'quantity'=>1]]];
$rewardSale = $pos->checkout($rewardPayload);
$db->exec("UPDATE products SET product_name = 'Current catalog name' WHERE product_id = 1");
$salesBefore = json_encode($db->query('SELECT * FROM sales ORDER BY sale_id')->fetchAll());
$stockBefore = json_encode($db->query('SELECT * FROM inventory ORDER BY inv_id')->fetchAll());
$historyBefore = json_encode($db->query('SELECT * FROM inventory_history ORDER BY history_id')->fetchAll());
$pointsBefore = json_encode($db->query('SELECT * FROM loyal ORDER BY user_id')->fetchAll());
$earningsBefore = json_encode($db->query('SELECT * FROM loyalty_earnings ORDER BY 1')->fetchAll());
$rewardsBefore = json_encode($db->query('SELECT * FROM loyalty_redemptions ORDER BY loyalty_redemption_id')->fetchAll());
$kioskBefore = json_encode($db->query('SELECT * FROM kiosk_orders ORDER BY kiosk_order_id')->fetchAll());
$items = $db->query('SELECT * FROM sale_items ORDER BY sale_item_id')->fetchAll();
foreach ($items as $item) if ((int) $item->product_id === 1) $item->product_id = null;
$expectedItems = json_encode($items);
$_SESSION['user'] = ['id'=>1, 'username'=>'test-owner', 'role'=>'owner'];
$response = deleteProductRequest(1);
check($response['status'] === 'success' && !$products->findByID(1)
    && (int) $db->query('SELECT COUNT(*) FROM product_ingredients WHERE product_id = 1')->fetchColumn() === 0,
    'A sold product with completed orders and used rewards must be deleted from the catalog and recipe links.');
check(json_encode($db->query('SELECT * FROM sale_items ORDER BY sale_item_id')->fetchAll()) === $expectedItems
    && json_encode($db->query('SELECT * FROM sales ORDER BY sale_id')->fetchAll()) === $salesBefore
    && json_encode($db->query('SELECT * FROM inventory ORDER BY inv_id')->fetchAll()) === $stockBefore
    && json_encode($db->query('SELECT * FROM inventory_history ORDER BY history_id')->fetchAll()) === $historyBefore
    && json_encode($db->query('SELECT * FROM loyal ORDER BY user_id')->fetchAll()) === $pointsBefore
    && json_encode($db->query('SELECT * FROM loyalty_earnings ORDER BY 1')->fetchAll()) === $earningsBefore
    && json_encode($db->query('SELECT * FROM loyalty_redemptions ORDER BY loyalty_redemption_id')->fetchAll()) === $rewardsBefore
    && json_encode($db->query('SELECT * FROM kiosk_orders ORDER BY kiosk_order_id')->fetchAll()) === $kioskBefore,
    'Deletion must only null historical item references, preserving saved names/prices, sales, orders, rewards, points and stock.');
$savedCompleted = $kiosk->findByReference($completed['reference_code']);
check($savedCompleted->status === 'completed' && $savedCompleted->items[0]->product_id === null
    && $savedCompleted->items[0]->product_name === 'Americano', 'Completed kiosk items must preserve their saved names after catalog deletion.');
$receipt = $pos->transaction($normalSale['sale_id']);
check($receipt->items[0]->product_id === null && $receipt->items[0]->product_name === 'Americano'
    && (float) $receipt->items[0]->unit_price === 125.50, 'Old receipts must retain saved product details after deletion.');
$_SESSION['user'] = ['id'=>2, 'username'=>'test-cashier', 'role'=>'cashier'];
$snapshot = productDeleteSnapshot($db);
foreach ([[$normalPayload, $normalSale], [$kioskPayload, $kioskSale], [$rewardPayload, $rewardSale]] as [$payload, $saved]) {
    check((int) $pos->checkout($payload)['sale_id'] === (int) $saved['sale_id'],
        'Completed checkout retries must recover saved sales after product deletion.');
}
$rejected = false;
try { $pos->checkout(array_replace($normalPayload, ['checkout_key'=>'deleted-product-old-cart'])); }
catch (Exception $error) { $rejected = str_contains($error->getMessage(), 'unavailable'); }
check($rejected && !$db->inTransaction() && productDeleteSnapshot($db) === $snapshot,
    'A fresh checkout of an old cart must reject deleted products without changing sales or stock.');
foreach (['owner'=>true, 'admin'=>false, 'cashier'=>false, 'staff'=>false, 'user'=>false, 'loyal_customer'=>false] as $role=>$allowed) {
    $_SESSION['user'] = ['id'=>1, 'role'=>$role];
    check(Core\Auth::canAccess('Products', 'delete', 'Api') === $allowed, 'Incorrect product deletion permission for ' . $role . '.');
}
$_SESSION = [];
check(!Core\Auth::canAccess('Products', 'delete', 'Api'), 'Guests must not delete catalog products.');

echo "Product deletion passed: physical active/inactive/sold deletion, open-order/reward guards, preserved history and stock, rollback, POST validation and owner access.\n";
