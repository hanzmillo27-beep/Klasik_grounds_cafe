<?php
require __DIR__ . '/bootstrap.php';
(new Models\Users())->save(['username'=>'registered', 'password'=>'correct-password', 'role'=>'cashier']);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.202';
$limiter = new Core\RateLimiter();
$key = 'login:'.Core\RateLimiter::clientIp();
try {
    foreach ([['',''], ['registered','wrong'], ['unknown','correct-password'], ["' OR 1=1 --",'correct-password'], [['invalid'],'password'], ['registered',['invalid']]] as [$username,$password]) {
        $limiter->reset($key);
        $_SESSION = [];
        $_POST = ['username'=>$username, 'password'=>$password];
        try { (new TestLoginController())->login(); throw new LogicException('Invalid credentials must redirect.'); }
        catch (Redirected $redirect) {
            check($redirect->getMessage() === BASE_URL.'/Auth', 'Invalid credentials must return to sign in.');
            check(empty($_SESSION['user']), 'Invalid credentials established a session.');
            check(!empty($_SESSION['error']), 'Validation error is missing.');
        }
    }
} finally { $limiter->reset($key); }
echo "FR-002 passed: empty, unknown, wrong, injection-shaped, and array credentials rejected.\n";
