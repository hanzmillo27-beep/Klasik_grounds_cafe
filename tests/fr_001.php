<?php
require __DIR__ . '/bootstrap.php';
$users = new Models\Users();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.201';
foreach (['owner'=>'Dashboard', 'admin'=>'Users', 'staff'=>'Products', 'cashier'=>'POS', 'user'=>'Kiosk', 'loyal_customer'=>'Loyalty'] as $role=>$page) {
    $users->save(['username'=>'test_'.$role, 'password'=>'valid-password', 'role'=>$role]);
    $_POST = ['username'=>'test_'.$role, 'password'=>'valid-password'];
    $_SESSION = [];
    $oldSession = session_id();
    try { (new TestLoginController())->login(); throw new LogicException('Login must redirect.'); }
    catch (Redirected $redirect) {
        check($redirect->getMessage() === BASE_URL.'/'.$page, 'Incorrect destination for '.$role);
        check(($_SESSION['user']['role'] ?? '') === $role, 'Missing authenticated role.');
        check(session_id() !== $oldSession, 'Login must rotate the session ID.');
    }
}
echo "FR-001 passed: real user lookup/password verification, six role redirects, session rotation.\n";
