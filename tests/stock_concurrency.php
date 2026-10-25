<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

if (($argv[1] ?? '') === '--worker') {
    $stage = 'setup';
    try {
        $schema = $argv[2] ?? '';
        $worker = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT);
        if (!preg_match('/^fr_test_[a-f0-9]{16}$/', $schema) || !in_array($worker, [1, 2], true)) {
            throw new RuntimeException('Invalid isolated worker arguments.');
        }
        ini_set('session.save_path', sys_get_temp_dir());
        ini_set('session.use_cookies', '0');
        require dirname(__DIR__) . '/app/config/init.php';
        require dirname(__DIR__) . '/app/autoload.php';
        $port = (string) DB_PORT;
        if ($port === '6543' && str_ends_with(DB_HOST, '.pooler.supabase.com')) $port = '5432';
        if ($port !== '5432') throw new RuntimeException('Workers require the session-mode PostgreSQL port.');
        $stage = 'connection';
        $workerDb = new PDO('pgsql:host=' . DB_HOST . ';port=5432;dbname=' . DB_NAME . ';sslmode=require;connect_timeout=10',
            DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
        $stage = 'schema';
        $owner = $workerDb->prepare('SELECT nspname FROM pg_namespace WHERE nspname = ?
            AND nspowner = (SELECT oid FROM pg_roles WHERE rolname = CURRENT_USER)');
        $owner->execute([$schema]);
        if ($owner->fetchColumn() !== $schema) throw new RuntimeException('The isolated schema must belong to the test connection.');
        $workerDb->exec('SET search_path TO "' . $schema . '"');
        if ($workerDb->query('SELECT current_schema()')->fetchColumn() !== $schema) {
            throw new RuntimeException('Unable to select the isolated worker schema.');
        }
        $_SESSION['user'] = ['id' => 2, 'role' => 'cashier'];
        $pos = (new ReflectionClass(Models\POS::class))->newInstanceWithoutConstructor();
        $property = new ReflectionProperty(Core\Model::class, 'db');
        $property->setAccessible(true);
        $property->setValue($pos, $workerDb);
        $stage = 'barrier';
        $workerDb->prepare('INSERT INTO fr_checkout_barrier (worker_id) VALUES (?)')->execute([$worker]);
        $deadline = microtime(true) + 10;
        while ((int) $workerDb->query('SELECT COUNT(*) FROM fr_checkout_barrier')->fetchColumn() < 2) {
            if (microtime(true) > $deadline) throw new RuntimeException('Checkout workers did not synchronize.');
            usleep(10000);
        }
        $stage = 'checkout';
        try {
            $result = $pos->checkout(['checkout_key' => 'fr028-concurrent-' . $worker, 'amount_paid' => '200.00',
                'items' => [['product_id' => $worker, 'quantity' => 1]]]);
            echo json_encode(['status' => 'success', 'sale_id' => $result['sale_id']]);
        } catch (Exception $error) {
            if ($error instanceof PDOException || stripos($error->getMessage(), 'insufficient inventory') === false) throw $error;
            echo json_encode(['status' => 'insufficient']);
        }
        exit;
    } catch (Throwable $error) {
        fwrite(STDERR, 'Worker failed during ' . $stage . ($stage === 'connection' ? '.' : ': ' . $error->getMessage()) . PHP_EOL);
        echo json_encode(['status' => 'error', 'stage' => $stage]);
        exit(1);
    }
}

require __DIR__ . '/pos_fixture.php';
check($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' && isset($frTestSchema)
    && preg_match('/^fr_test_[a-f0-9]{16}$/', $frTestSchema)
    && $db->query('SELECT current_schema()')->fetchColumn() === $frTestSchema,
    'Run this concurrency test with the isolated PostgreSQL auto-prepend runner.');
$db->exec('UPDATE inventory SET amount = 1.000 WHERE inv_id = 1');
$db->exec('UPDATE product_ingredients SET quantity_required = 1.000 WHERE product_id = 1');
check((new Models\Products())->save(productPayload(['product_name'=>'Second shared-stock product',
    'ingredient_quantity'=>['1.000']])), 'Could not create the second shared-stock product.');
$db->exec('CREATE TABLE fr_checkout_barrier (worker_id INTEGER PRIMARY KEY)');
$workers = [];
try {
    foreach ([1, 2] as $number) {
        $command = [PHP_BINARY, '-d', 'display_errors=stderr'];
        if (PHP_OS_FAMILY === 'Windows') array_push($command, '-d', 'extension=php_pdo_pgsql.dll');
        array_push($command, __FILE__, '--worker', $frTestSchema, (string) $number);
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes,
            dirname(__DIR__), null, ['bypass_shell' => true]);
        check(is_resource($process), 'Unable to start a checkout worker.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'output' => '', 'error' => ''];
    }
    $deadline = microtime(true) + 25;
    do {
        $running = false;
        foreach ($workers as &$worker) {
            $worker['output'] .= stream_get_contents($worker['out']);
            $worker['error'] .= stream_get_contents($worker['err']);
            $running = proc_get_status($worker['process'])['running'] || $running;
        }
        unset($worker);
        check(microtime(true) <= $deadline, 'Concurrent checkout workers timed out.');
        if ($running) usleep(20000);
    } while ($running);
    $statuses = [];
    foreach ($workers as &$worker) {
        $worker['output'] .= stream_get_contents($worker['out']);
        $worker['error'] .= stream_get_contents($worker['err']);
        $output = json_decode($worker['output'], true, 512, JSON_THROW_ON_ERROR);
        check(($output['status'] ?? '') !== 'error', 'Concurrent worker failed: ' . $worker['error']);
        $statuses[] = $output['status'];
    }
    unset($worker);
    sort($statuses);
    check($statuses === ['insufficient', 'success']
        && (int) $db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1
        && (int) $db->query('SELECT COUNT(*) FROM sale_items')->fetchColumn() === 1
        && (float) $db->query('SELECT amount FROM inventory WHERE inv_id = 1')->fetchColumn() === 0.0,
        'Concurrent checkouts must commit one sale, reject the other, and never oversell stock.');
    $movements = $db->query("SELECT * FROM inventory_history WHERE movement_type = 'sale'")->fetchAll();
    check(count($movements) === 1 && (int) $movements[0]->inv_id === 1
        && (float) $movements[0]->amount_before === 1.0
        && (float) $movements[0]->quantity_change === -1.0
        && (float) $movements[0]->amount_after === 0.0
        && (int) $movements[0]->sale_id === (int) $db->query('SELECT sale_id FROM sales')->fetchColumn(),
        'Concurrent checkouts must record exactly one sale movement with the committed stock change and sale reference.');
} finally {
    // Finish every child before the PostgreSQL runner drops the isolated schema.
    foreach ($workers as $worker) {
        if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
        fclose($worker['out']);
        fclose($worker['err']);
        proc_close($worker['process']);
    }
}
echo "FR-028 concurrency passed: two PostgreSQL checkout workers, one sale and movement, and one stock rejection.\n";
