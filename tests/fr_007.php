<?php
require __DIR__ . '/bootstrap.php';
$users=new Models\Users();
$users->save(['username'=>'admin','password'=>'admin-password','role'=>'admin']);
$users->save(['username'=>'cashier','password'=>'old-password','role'=>'cashier']);
$_SESSION['user']=['id'=>1,'username'=>'admin','role'=>'admin'];
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['id'=>1,'username'=>'edited-cashier','password'=>'','role'=>'user'];
try { (new TestUsersController())->edit(2); } catch (JsonResponse $r) { check($r->data['status']==='success','Valid update failed.'); }
check($users->findByID(1)->username==='admin','POST ID overrode the URL target.');
$user=$users->findByUsername('edited-cashier');
check($user && password_verify('old-password',$user->password),'Blank password must preserve the existing password.');
$_POST=['username'=>'edited-cashier','password'=>'new-password','role'=>'cashier'];
try { (new TestUsersController())->edit(2); } catch (JsonResponse $r) { check($r->data['status']==='success','Password update failed.'); }
check(password_verify('new-password',$users->findByUsername('edited-cashier')->password),'Password update was not hashed/saved.');
$_POST=['username'=>'admin','password'=>'','role'=>'cashier'];
try { (new TestUsersController())->edit(2); throw new LogicException('Duplicate username accepted.'); }
catch (JsonResponse $r) { check($r->data['status']==='error','Duplicate username must be rejected.'); }
echo "FR-007 passed: targeted account update, optional password retention, new hash, duplicate rejection.\n";
