<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
$users->save(['username'=>'owner','password'=>'password','role'=>'owner']);
$users->save(['username'=>'inactive-user','password'=>'password','role'=>'user']);
$users->setActive(2,false);
$_SESSION['user']=['id'=>1,'role'=>'owner'];
try { (new TestUsersController())->all(); } catch (JsonResponse $r) {
    check(count($r->data['data'])===2,'User list must include inactive accounts.');
    foreach ($r->data['data'] as $user) {
        check(!property_exists($user,'password'),'User list leaked password hashes.');
        check(property_exists($user,'is_active'),'User list omitted status.');
    }
}
foreach (['owner'=>true,'admin'=>true,'cashier'=>false,'staff'=>false,'user'=>false,'loyal_customer'=>false] as $role=>$allowed) {
    $_SESSION['user']=['id'=>1,'role'=>$role];
    check(Core\Auth::canAccess('Users','all','Api')===$allowed,'Incorrect user-list access for '.$role);
}
echo "FR-010 passed: active/inactive user listing, no password disclosure, owner/admin-only access.\n";
