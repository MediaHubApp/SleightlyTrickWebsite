<?php
require __DIR__.'/app/bootstrap.php';
$error=null;
try{
 session_boot();if(installed()){header('Location: /admin/login.php',true,303);exit;}setup_key();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();$scope='setup:'.($_SERVER['REMOTE_ADDR']??'unknown');rate_check($scope,8);
  $key=$_POST['setup_key']??'';
  if(!is_string($key)||!hash_equals(setup_key(),$key)){failed_attempt($scope);throw new DomainException('The setup code does not match. See INSTALL-PLESK.txt for where to find your private code.');}
  $email=valid_email($_POST['email']??'');$pass=$_POST['password']??'';if(!is_string($pass))throw new DomainException('Invalid password.');validate_password($pass);
  if($pass!==($_POST['confirm']??null))throw new DomainException('The passwords do not match.');
  $pdo=db();$pdo->exec('BEGIN IMMEDIATE');
  try{if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)throw new DomainException('Setup is already complete.');
   $pdo->prepare('INSERT INTO users(email,password_hash,role) VALUES(?,?,?)')->execute([$email,password_hash($pass,PASSWORD_DEFAULT),'owner']);$id=(int)$pdo->lastInsertId();
   $pdo->prepare('INSERT INTO content(id,body,version,updated_at) VALUES(1,?,1,?)')->execute([safe_json(defaults()),gmdate('c')]);$pdo->exec('COMMIT');
  }catch(Throwable $e){$pdo->exec('ROLLBACK');throw $e;}
  login(['id'=>$id,'session_version'=>1]);header('Location: /admin/',true,303);exit;
 }
}catch(DomainException $e){$error=$e->getMessage();}catch(Throwable $e){error_log($e->getMessage());http_response_code(503);auth_page('Almost there.','The hosting needs a small adjustment.','<p>Use PHP 8.2 or later with PDO SQLite enabled. The PHP user must be able to create the private folder beside httpdocs. See INSTALL-PLESK.txt for the exact settings.</p>');exit;}
$body='<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><label class="field"><span>One-time setup code</span><input name="setup_key" type="password" required autocomplete="off"></label><label class="field"><span>Your email address</span><input name="email" type="email" required autocomplete="username" maxlength="200"></label><label class="field"><span>Create a password</span><input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"><small>Use at least 12 characters.</small></label><label class="field"><span>Confirm password</span><input name="confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="button red" type="submit">Create your owner account</button></form>';
auth_page('Make it yours.','Set up your private stallholder login. You will find the setup code in the installation guide supplied with this website.',$body,$error);
