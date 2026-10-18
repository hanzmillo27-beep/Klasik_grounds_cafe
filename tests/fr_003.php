<?php
require __DIR__ . '/bootstrap.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.203';
$_POST = ['username'=>'unknown', 'password'=>'wrong'];
$limiter = new Core\RateLimiter();
$key = 'login:'.Core\RateLimiter::clientIp();
$limiter->reset($key);
try {
    for ($i=0; $i<4; $i++) {
        try { (new TestLoginController())->login(); } catch (Redirected $expected) {}
        check(($_SESSION['error'] ?? '') === 'Invalid username or password.', 'Invalid-login message missing.');
    }
    ob_start();
    (new TestLoginController())->login();
    $html = ob_get_clean();
    check(http_response_code() === 429, 'Fifth failure must return HTTP 429.');
    check(strpos($html, 'Too many failed sign-ins.') !== false, 'Cooldown notification missing.');
    check(strpos($html, 'data-auto-open="true"') !== false, 'Error must reopen the popup.');
    check(strpos($html, 'id="login-error"') !== false, 'Accessible error target missing.');
    $_SESSION['error'] = '<script>alert(1)</script>';
    ob_start(); (new Core\BaseController())->view('Auth/index', ['title'=>'Login'], 'auth'); $html=ob_get_clean();
    check(strpos($html, '&lt;script&gt;') !== false, 'Error must be escaped.');
    check(!isset($_SESSION['error']), 'Flash error must be consumed once.');
} finally { $limiter->reset($key); }
echo "FR-003 passed: invalid-login notification, fifth-failure cooldown popup, accessible escaped messages.\n";
