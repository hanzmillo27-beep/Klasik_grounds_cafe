<?php
require __DIR__ . '/bootstrap.php';
$_SESSION['user']=['id'=>1,'username'=>'cashier','role'=>'cashier'];
$_SERVER['REQUEST_METHOD']='GET';
(new TestLoginController())->logout();
check(http_response_code()===405 && Core\Auth::check(), 'A GET request must not log a user out.');
$_SERVER['REQUEST_METHOD']='POST';
try { (new TestLoginController())->logout(); throw new LogicException('Logout must redirect.'); }
catch (Redirected $redirect) { check($redirect->getMessage()===BASE_URL.'/Auth', 'Incorrect logout destination.'); }
check(!Core\Auth::check() && $_SESSION===[], 'Logout must clear authentication.');
check(session_status()===PHP_SESSION_NONE, 'Logout must destroy the session.');
echo "FR-005 passed: POST logout destroys the session; GET cannot trigger logout.\n";
