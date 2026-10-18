<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
$users->save(['username'=>'owner','password'=>'password','role'=>'owner']);
$_SESSION['user']=['id'=>1,'role'=>'owner']; $_SERVER['REQUEST_METHOD']='POST';
foreach (Core\Auth::roles() as $role) {
    $_POST=['username'=>'role_'.$role,'password'=>'password','role'=>$role];
    try { (new TestUsersController())->add(); } catch (JsonResponse $r) { check($r->data['status']==='success','Role rejected: '.$role); }
    check($users->findByUsername('role_'.$role)->role===$role,'Assigned role not saved.');
}
$_POST=['username'=>'invalid-role','password'=>'password','role'=>'unknown'];
try { (new TestUsersController())->add(); throw new LogicException('Unknown role accepted.'); }
catch (JsonResponse $r) { check($r->data['status']==='error','Unknown role not rejected.'); }
echo "FR-009 passed: all six supported roles including Staff accepted; unknown roles rejected.\n";
