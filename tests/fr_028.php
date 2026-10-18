<?php
require __DIR__ . '/pos_fixture.php';

$products = new Models\Products();
check($products->save(productPayload(['product_name' => 'Latte', 'product_price' => '150', 'ingredient_quantity' => ['10.125']])),
    'Could not create the shared-ingredient product.');
$pos = new Models\POS();
$payload = ['checkout_key' => checkoutTestKey(), 'amount_paid' => '600.00',
    'items' => [['product_id' => 1, 'quantity' => 2], ['product_id' => 2, 'quantity' => 2]]];
$db->exec('UPDATE inventory SET amount = 40 WHERE inv_id = 1');
$rejected = false;
try { $pos->checkout($payload); }
catch (PDOException $error) { throw new RuntimeException('Shared shortages must fail stock validation safely.', 0, $error); }
catch (Exception $error) { $rejected = stripos($error->getMessage(), 'inventory') !== false; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0
    && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 0
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 40.0,
    'Combined recipe demand must be checked before selling individually sufficient products.');

$db->exec('UPDATE inventory SET amount = 56.750 WHERE inv_id = 1');
$result = $pos->checkout($payload);
check((int) $result['sale_id'] > 0 && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 2
    && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 0.0,
    'Both products must deduct their complete shared recipe demand and may leave exactly zero stock.');
$db->exec('UPDATE inventory SET amount = 0.300 WHERE inv_id = 1');
$db->exec('UPDATE product_ingredients SET quantity_required = 0.100 WHERE product_id = 1');
$db->exec('UPDATE product_ingredients SET quantity_required = 0.200 WHERE product_id = 2');
$pos->checkout(['checkout_key' => checkoutTestKey(), 'amount_paid' => '300.00',
    'items' => [['product_id' => 1, 'quantity' => 1], ['product_id' => 2, 'quantity' => 1]]]);
check((float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 0.0,
    'Fractional 0.100 plus 0.200 ingredient demand must consume 0.300 without negative rounding residue.');

$db->exec('UPDATE inventory SET amount = 1 WHERE inv_id = 1');
foreach (['0', '-0.001', '0.0001'] as $quantity) {
    $db->prepare('UPDATE product_ingredients SET quantity_required = ? WHERE product_id = 1')->execute([$quantity]);
    $rejected = false;
    try { $pos->checkout(['checkout_key' => checkoutTestKey(), 'amount_paid' => '200.00',
        'items' => [['product_id' => 1, 'quantity' => 1]]]); }
    catch (PDOException $error) { throw new RuntimeException('Invalid recipes must fail validation safely.', 0, $error); }
    catch (Exception $error) { $rejected = true; }
    check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2
        && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 4
        && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 1.0,
        'Invalid legacy recipe quantities must not bypass deduction or create partial transactions.');
}
check(!$products->save(productPayload(['product_name' => 'Invalid tiny recipe', 'ingredient_quantity' => ['0.0001']])),
    'New recipes must not accept quantities below the supported 0.001 precision.');

echo "FR-028 passed: aggregate recipe demand, atomic shortage rollback, exact fractional deductions and zero stock.\n";
