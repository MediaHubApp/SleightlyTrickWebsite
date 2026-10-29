<?php
require dirname(__DIR__).'/app/bootstrap.php';
$error=null;
try{
 session_boot();if(!installed()){header('Location: /setup.php',true,303);exit;}if(user()){header('Location: /admin/',true,303);exit;}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();$email=valid_email($_POST['email']??'');$password=$_POST['password']??'';if(!is_string($password))throw new DomainException('Please check your email and password.');
  $ip='login:'.($_SERVER['REMOTE_ADDR']??'unknown');$account='account:'.$email;rate_check($ip,15);rate_check($account,10);
  $q=db()->prepare('SELECT * FROM users WHERE email=?');$q->execute([$email]);$u=$q->fetch();
  $hash=$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
  $verified=password_verify($password,$hash);
  if(!$u||!$verified){failed_attempt($ip);failed_attempt($account);throw new DomainException('Please check your email and password.');}
  if(password_needs_rehash($hash,PASSWORD_DEFAULT))db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);
  login($u);header('Location: /admin/',true,303);exit;
 }
}catch(DomainException $e){$error=$e->getMessage();if($e->getCode()===429)http_response_code(429);}catch(Throwable $e){error_log($e->getMessage());http_response_code(503);auth_page('Please try again.','The editor is temporarily unavailable.','<p>Check the PHP and folder settings in your installation guide.</p>');exit;}
$body='<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><label class="field"><span>Email address</span><input type="email" name="email" required autocomplete="username"></label><label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label><button class="button red" type="submit">Sign in</button></form>';
auth_page('Stallholder entrance.','Sign in to update your collection, prices and stall details.',$body,$error);
