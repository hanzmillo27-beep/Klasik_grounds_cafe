<?php
namespace Core {
    // Isolated tests never load application database credentials.
    class Database {
        public static \PDO $connection;
        public static function connect() { return self::$connection; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require_once dirname(__DIR__) . '/app/autoload.php';
    define('BASE_URL', 'http://test.local');
    define('BASE_PATH', dirname(__DIR__));
    define('PUBLIC_PATH', BASE_PATH . '/public');
    ini_set('session.save_path', sys_get_temp_dir());
    ini_set('session.use_cookies', '0');
    session_start();
    \Core\Database::$connection = new \PDO('sqlite::memory:', null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
    ]);
    \Core\Database::$connection->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL, role TEXT NOT NULL, created TEXT, modified TEXT
    )');
    function check(bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    }
    class Redirected extends \RuntimeException {}
    class TestLoginController extends \Controllers\AuthController {
        public function redirect($url): void { throw new Redirected($url); }
    }
}
