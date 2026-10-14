<?php

session_start();

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');


$configuredBaseUrl = trim((string) getenv('APP_BASE_URL'));
if ($configuredBaseUrl !== '') {
    define('BASE_URL', rtrim($configuredBaseUrl, '/'));
} else {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https';
    $scheme = $isHttps ? 'https' : 'http';

    $scriptName = parse_url($_SERVER['SCRIPT_NAME'] ?? '/index.php', PHP_URL_PATH) ?: '/index.php';
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if (str_ends_with($basePath, '/public')) {
        $basePath = substr($basePath, 0, -strlen('/public'));
    }
    $basePath = ($basePath === '.' || $basePath === '/') ? '' : rtrim($basePath, '/');

    define('BASE_URL', $scheme . '://' . $host . $basePath);
}
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST']);


define('DB_PORT', '6543');
define('DB_HOST', 'aws-0-ap-northeast-2.pooler.supabase.com');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.nwvbeowsjqqlbeajcpsz');
define('DB_PASS', 'A*DsJnZ*2kWk@Ez');
define('DB_CHARSET', 'utf8mb4');


/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'klasik_grounds_cafe');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
*/
date_default_timezone_set('Asia/Manila');
