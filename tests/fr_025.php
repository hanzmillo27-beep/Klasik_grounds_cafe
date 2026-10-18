<?php
require __DIR__ . '/pos_fixture.php';

$pos = new Models\POS();
$payload = ['checkout_key' => checkoutTestKey(), 'sale_id' => 99999, 'amount_paid' => '200.00',
    'items' => [['product_id' => 1, 'quantity' => 1]]];
$first = $pos->checkout($payload);
$second = $pos->checkout(array_replace($payload, ['checkout_key' => checkoutTestKey()]));
check((int) $first['sale_id'] > 0 && (int) $second['sale_id'] > (int) $first['sale_id']
    && (int) $first['sale_id'] !== 99999 && (int) $second['sale_id'] !== 99999,
    'Paid transactions must receive distinct positive database-generated IDs and ignore submitted IDs.');
$firstSnapshot = json_encode($pos->transaction($first['sale_id']));
$secondSnapshot = json_encode($pos->transaction($second['sale_id']));
$replayed = $pos->checkout($payload);
check((int) $replayed['sale_id'] === (int) $first['sale_id']
    && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2,
    'A checkout replay must retain its original transaction number.');

$rejected = false;
try { $pos->checkout(array_replace($payload, ['checkout_key' => checkoutTestKey(), 'amount_paid' => '125.49'])); }
catch (PDOException $error) { throw new RuntimeException('Insufficient payment must fail validation safely.', 0, $error); }
catch (Exception $error) { $rejected = true; }
check($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 2,
    'An unsuccessful checkout must not create a committed transaction number.');
$third = $pos->checkout(array_replace($payload, ['checkout_key' => checkoutTestKey()]));
check((int) $third['sale_id'] > (int) $second['sale_id']
    && json_encode($pos->transaction($first['sale_id'])) === $firstSnapshot
    && json_encode($pos->transaction($second['sale_id'])) === $secondSnapshot,
    'Later transactions and failures must never reuse committed IDs or change existing receipts.');

$duplicateRejected = false;
try {
    $db->prepare('INSERT INTO sales (sale_id, user_id, total_amount, amount_paid, change_amount, sale_date)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)')->execute([$first['sale_id'], 2, 0, 0, 0]);
} catch (PDOException $error) {
    $duplicateRejected = in_array((string) $error->getCode(), ['23000', '23505'], true);
}
check($duplicateRejected && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 3
    && count($pos->transactions()) === 3
    && json_encode($pos->transaction($first['sale_id'])) === $firstSnapshot,
    'The database primary key must reject duplicate transaction numbers and retain history.');

echo "FR-025 passed: distinct generated transaction IDs, stable receipts, replay and primary-key uniqueness.\n";
