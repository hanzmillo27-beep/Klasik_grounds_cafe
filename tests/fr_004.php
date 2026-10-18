<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
foreach (['owner','admin','staff','cashier','user','loyal_customer'] as $role) {
    $users->save(['username'=>$role, 'password'=>'password', 'role'=>$role]);
}
$_SERVER['REQUEST_METHOD']='POST';
foreach (['owner'=>true,'admin'=>false,'cashier'=>false,'staff'=>false,'user'=>false] as $role=>$allowed) {
    $_SESSION['user']=['id'=>1,'role'=>$role];
    check(Core\Auth::canAccess('Products','add','Api')===$allowed, 'Wrong product-write permission for '.$role);
}
$_SESSION['user']=['id'=>3,'role'=>'staff'];
check(Core\Auth::canAccess('Inventory','restock','Api'), 'Inventory staff must replenish stock.');
check(!Core\Auth::canAccess('Inventory','delete','Api'), 'Staff must not delete inventory.');
$_SESSION['user']=['id'=>4,'role'=>'cashier'];
check(Core\Auth::canAccess('POS','checkout','Api'), 'Cashier checkout denied.');
check(!Core\Auth::canAccess('Users','add','Api'), 'Cashier user administration allowed.');
$_SESSION['user']=['id'=>2,'role'=>'admin'];
foreach ([['id'=>1,'username'=>'changed-owner','role'=>'admin'], ['username'=>'new-owner','password'=>'password','role'=>'owner']] as $payload) {
    $_POST=$payload; http_response_code(200);
    try { (new TestUsersController())->add(); throw new LogicException('Unauthorized save was not rejected.'); }
    catch (JsonResponse $response) { check($response->data['status']==='error', 'Unauthorized create/update succeeded.'); }
}
check($users->findByID(1)->username==='owner', 'Owner was modified through add.');
$_POST=['username'=>'admin','role'=>'owner'];
try { (new TestUsersController())->edit(2); throw new LogicException('Admin promoted itself.'); }
catch (JsonResponse $response) { check(http_response_code()===403, 'Owner-role assignment must be forbidden.'); }
Core\Database::$connection->exec("UPDATE users SET role='cashier' WHERE id=2");
Core\Auth::refresh();
check(Core\Auth::user()['role']==='cashier' && !Core\Auth::canAccess('Users'), 'Role changes must take effect in existing sessions.');
Core\Database::$connection->exec('DELETE FROM users WHERE id=2');
Core\Auth::refresh();
check(!Core\Auth::check(), 'Deleted user session must be revoked.');
Core\Auth::authorizeRoute('Controllers','auth','index');
echo "FR-004 passed: role matrix, owner guard, alternate update prevention, live role refresh, revoked sessions.\n";
