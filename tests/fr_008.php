<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
foreach (['owner','admin','cashier'] as $role) $users->save(['username'=>$role,'password'=>'password','role'=>$role]);
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['REMOTE_ADDR']='192.0.2.208';
$_SESSION['user']=['id'=>2,'role'=>'admin'];
$_POST=['is_active'=>'0'];
try { (new TestUsersController())->status(3); } catch (JsonResponse $r) { check($r->data['status']==='success','Deactivation failed.'); }
check(count($users->find())===3 && !UsersActive($users->findByID(3)), 'Account was deleted or not deactivated.');
$users->save(['id'=>3,'username'=>'cashier','password'=>'','role'=>'cashier','is_active'=>true]);
check(!UsersActive($users->findByID(3)), 'Ordinary edits must not bypass the status action.');
$_SESSION=['user'=>['id'=>3,'role'=>'cashier']]; Core\Auth::refresh();
check(!Core\Auth::check(),'Inactive account retained its session.');
$_POST=['username'=>'cashier','password'=>'password'];
$limiter=new Core\RateLimiter(); $key='login:'.Core\RateLimiter::clientIp(); $limiter->reset($key);
try { (new TestLoginController())->login(); throw new LogicException('Inactive account signed in.'); }
catch (Redirected $r) { check(empty($_SESSION['user']),'Inactive login authenticated.'); }
$limiter->reset($key);
$_SESSION['user']=['id'=>2,'role'=>'admin']; $_POST=['is_active'=>'1'];
try { (new TestUsersController())->status(3); } catch (JsonResponse $r) { check($r->data['status']==='success','Reactivation failed.'); }
check(UsersActive($users->findByID(3)), 'Account did not reactivate.');
$_SESSION=[]; $_POST=['username'=>'cashier','password'=>'password'];
try { (new TestLoginController())->login(); } catch (Redirected $r) { check($r->getMessage()===BASE_URL.'/POS','Reactivated account could not sign in.'); }
$_SESSION['user']=['id'=>2,'role'=>'admin'];
$_POST=['is_active'=>'0'];
foreach ([1,2] as $id) {
    try { (new TestUsersController())->status($id); throw new LogicException('Owner/self deactivation was accepted.'); }
    catch (JsonResponse $r) { check($r->data['status']==='error','Owner/self protection failed.'); }
}
check(password_verify('password',$users->findByUsername('cashier')->password),'Deactivation changed password/history identity.');
echo "FR-008 passed: reversible deactivation, inactive login/session rejection, owner/self protection, retained account.\n";
function UsersActive($user): bool { return Models\Users::isActive($user); }
