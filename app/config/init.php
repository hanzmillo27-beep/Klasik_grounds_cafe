<?php

// Allow the Flutter web client to call this backend with its PHP session cookie.
$requestOrigin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$configuredOrigins = trim((string) getenv('CORS_ALLOWED_ORIGINS'));
$allowedOrigins = $configuredOrigins === ''
    ? []
    : array_map('trim', explode(',', $configuredOrigins));
$isLocalFlutterOrigin = $requestOrigin !== ''
    && preg_match('~^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?$~i', $requestOrigin) === 1;

if ($requestOrigin !== '' && ($isLocalFlutterOrigin || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin', false);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax',
]);
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


define('DB_PORT', '6543');
define('DB_HOST', 'aws-0-ap-northeast-2.pooler.supabase.com');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.nwvbeowsjqqlbeajcpsz');
define('DB_PASS', 'A*DsJnZ*2kWk@Ez');
define('DB_CHARSET', 'utf8mb4');

define('SUPABASE_URL', rtrim((string) getenv('SUPABASE_URL'), '/'));
define('SUPABASE_SERVICE_ROLE_KEY', (string) getenv('SUPABASE_SERVICE_ROLE_KEY'));
define('SUPABASE_STORAGE_BUCKET', (string) (getenv('SUPABASE_STORAGE_BUCKET') ?: 'product-images'));


/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'klasik_grounds_cafe');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
*/
date_default_timezone_set('Asia/Manila');
