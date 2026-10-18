<?php
namespace Core {
    // SQLite by default; postgres.php may supply an isolated PostgreSQL schema.
    class Database {
        public static \PDO $connection;
        public static function connect() { return self::$connection; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require_once dirname(__DIR__) . '/app/autoload.php';
    if (!defined('BASE_URL')) define('BASE_URL', 'http://test.local');
    if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
    if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', BASE_PATH . '/public');
    if (!defined('SUPABASE_URL')) define('SUPABASE_URL', '');
    if (!defined('SUPABASE_SERVICE_ROLE_KEY')) define('SUPABASE_SERVICE_ROLE_KEY', '');
    if (!defined('SUPABASE_STORAGE_BUCKET')) define('SUPABASE_STORAGE_BUCKET', 'product-images');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.save_path', sys_get_temp_dir());
        ini_set('session.use_cookies', '0');
        session_start();
    }
    if (array_key_exists('frTestDb', $GLOBALS)) {
        $testDb = $GLOBALS['frTestDb'];
        $testSchema = $GLOBALS['frTestSchema'] ?? '';
        if (!$testDb instanceof \PDO || $testDb->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'pgsql'
            || !is_string($testSchema) || !preg_match('/^fr_test_[a-f0-9]{16}$/', $testSchema)
            || $testDb->query('SELECT current_schema()')->fetchColumn() !== $testSchema) {
            throw new \RuntimeException('PostgreSQL tests require their own isolated schema.');
        }
        \Core\Database::$connection = $testDb;
    } else {
        \Core\Database::$connection = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        ]);
        \Core\Database::$connection->exec('PRAGMA foreign_keys = ON');
    }
    $postgres = \Core\Database::$connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $identity = $postgres ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $activeColumn = $postgres ? 'BOOLEAN NOT NULL DEFAULT TRUE' : 'INTEGER NOT NULL DEFAULT 1';
    \Core\Database::$connection->exec('CREATE TABLE users (
        id ' . $identity . ', username VARCHAR(100) UNIQUE NOT NULL,
        password TEXT NOT NULL, role TEXT NOT NULL, created TEXT, modified TEXT,
        is_active ' . $activeColumn . '
    )');
    function check(bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    }
    class Redirected extends \RuntimeException {}
    class JsonResponse extends \RuntimeException {
        public $data;
        public function __construct($data) { parent::__construct(); $this->data=$data; }
    }
    class TestUsersController extends \Api\Users\UsersController {
        public function json($data): void { throw new JsonResponse($data); }
    }
    class TestLoginController extends \Controllers\AuthController {
        public function redirect($url): void { throw new Redirected($url); }
    }
}
