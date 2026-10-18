<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
$users->save(['username'=>'administrator','password'=>'password','role'=>'admin']);
$_SESSION['user']=['id'=>1,'username'=>'administrator','role'=>'admin'];
$_SERVER['REQUEST_METHOD']='POST';
Core\Auth::authorizeRoute('Api','Users','add');
$_POST=['username'=>'  new-cashier  ','password'=>'secret-password','role'=>'cashier'];
try { (new TestUsersController())->add(); } catch (JsonResponse $r) { check($r->data['status']==='success','Valid account creation failed.'); }
$user=$users->findByUsername('new-cashier');
check($user && password_verify('secret-password',$user->password),'Password was not safely saved.');
check($user->role==='cashier','Role was not saved.');
foreach ([['username'=>'new-cashier','password'=>'password','role'=>'user'], ['username'=>'short','password'=>'123','role'=>'user'], ['username'=>['bad'],'password'=>'password','role'=>'user']] as $payload) {
    $_POST=$payload;
    try { (new TestUsersController())->add(); throw new LogicException('Invalid user creation was accepted.'); }
    catch (JsonResponse $r) { check($r->data['status']==='error','Invalid account creation succeeded.'); }
}
check(count($users->find())===2,'Invalid submissions created accounts.');
echo "FR-006 passed: administrator creates hashed accounts; duplicate and malformed submissions rejected.\n";
