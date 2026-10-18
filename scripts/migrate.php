<?php
// Run one reviewed migration at a time: php scripts/migrate.php 008_user_status.sql
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('session.save_path', sys_get_temp_dir());
require dirname(__DIR__) . '/app/config/init.php';
require dirname(__DIR__) . '/app/autoload.php';
try {
    $name = $argv[1] ?? '';
    if (!preg_match('/^\d{3}_[a-z_]+\.sql$/', $name)) throw new RuntimeException('Specify a migration filename.');
    $path = dirname(__DIR__) . '/migrations/' . $name;
    if (!is_file($path)) throw new RuntimeException('Migration file not found.');
    $db = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=require', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') throw new RuntimeException('These migrations require PostgreSQL.');
    $db->beginTransaction();
    $db->exec('CREATE TABLE IF NOT EXISTS app_migrations (name VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('LOCK TABLE app_migrations IN EXCLUSIVE MODE');
    $check = $db->prepare('SELECT name FROM app_migrations WHERE name = ?');
    $check->execute([$name]);
    if (!$check->fetch()) {
        $db->exec(file_get_contents($path));
        $db->prepare('INSERT INTO app_migrations (name) VALUES (?)')->execute([$name]);
    }
    $db->commit();
    echo 'Migration applied: ', $name, PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Migration failed: ' . (isset($db) ? $error->getMessage() : 'Unable to connect to the configured PostgreSQL database.') . PHP_EOL);
    exit(1);
}
