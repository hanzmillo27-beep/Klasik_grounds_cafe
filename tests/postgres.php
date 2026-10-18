<?php
// Optional runner: php -d auto_prepend_file=tests/postgres.php tests/fr_011.php
// Enable pdo_pgsql in PHP when it is not enabled by default.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PostgreSQL tests require the PDO PostgreSQL extension.\n");
    exit(1);
}
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
require dirname(__DIR__) . '/app/config/init.php';

try {
    // Session mode preserves search_path between statements. Supabase's
    // transaction pooler (6543) cannot guarantee that isolation.
    // https://supabase.com/docs/guides/database/connecting-to-postgres
    $testPort = (string) DB_PORT;
    if ($testPort === '6543' && str_ends_with(DB_HOST, '.pooler.supabase.com')) $testPort = '5432';
    if ($testPort === '6543') throw new RuntimeException('Use a session-mode PostgreSQL connection for isolated tests.');
    $frTestDb = new PDO(
        'pgsql:host=' . DB_HOST . ';port=' . $testPort . ';dbname=' . DB_NAME . ';sslmode=require;connect_timeout=10',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
    );
    $frTestSchema = 'fr_test_' . bin2hex(random_bytes(8));
    if (!preg_match('/^fr_test_[a-f0-9]{16}$/', $frTestSchema)) {
        throw new RuntimeException('Invalid isolated schema name.');
    }
    $frTestDb->exec('CREATE SCHEMA "' . $frTestSchema . '"');
    register_shutdown_function(function () use ($frTestDb, $frTestSchema): void {
        try {
            if ($frTestDb->inTransaction()) $frTestDb->rollBack();
            // The identifier is generated here and validated before creation.
            if (!preg_match('/^fr_test_[a-f0-9]{16}$/', $frTestSchema)) {
                throw new RuntimeException('Invalid isolated schema name.');
            }
            $frTestDb->exec('DROP SCHEMA "' . $frTestSchema . '" CASCADE');
        } catch (Throwable $error) {
            fwrite(STDERR, 'Unable to remove isolated test schema ' . $frTestSchema . ".\n");
            exit(1);
        }
    });
    $frTestDb->exec('SET search_path TO "' . $frTestSchema . '"');
    if ($frTestDb->query('SELECT current_schema()')->fetchColumn() !== $frTestSchema) {
        throw new RuntimeException('Unable to select isolated schema.');
    }
} catch (Throwable $error) {
    // Do not expose configured credentials or connection details in test output.
    fwrite(STDERR, "Unable to prepare an isolated PostgreSQL test schema.\n");
    exit(1);
}
