<?php
require dirname(__DIR__).'/app/bootstrap.php';
try{
 session_boot();$u=require_user(true);$action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD'];
 if($method==='GET'&&$action==='load')json_response(content());
 if($method==='GET'&&$action==='contact-form')json_response(contact_form_definition());
 if($method==='GET'&&$action==='instructions')json_response(['instructions'=>array_map('instruction_admin',db()->query('SELECT * FROM instructions ORDER BY title COLLATE NOCASE')->fetchAll())]);
 if($method==='GET'&&$action==='users'){require_owner($u);json_response(['users'=>db()->query('SELECT id,email,role FROM users ORDER BY id')->fetchAll()]);}
 if($method!=='POST')throw new DomainException('This action requires a POST request.',405);
 csrf_check();
 if($action==='upload')json_response(['url'=>upload_image()],201);
 if($action==='upload-video')json_response(['source'=>upload_instruction_video()],201);
 $raw=file_get_contents('php://input',false,null,0,500001);if(strlen($raw)>500000)throw new DomainException('This update is too large.',413);
 try{$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new DomainException('Invalid request.',400);}if(!is_array($data))throw new DomainException('Invalid request.');
 if($action==='save-instruction')json_response(['instruction'=>save_instruction($data)]);
 if($action==='save-contact-form')json_response(save_contact_form($data));
 if($action==='delete-instruction'){
  if(!is_string($data['id']??null)||!is_int($data['version']??null))throw new DomainException('Invalid instruction page.');
  $q=db()->prepare('DELETE FROM instructions WHERE id=? AND version=?');$q->execute([$data['id'],$data['version']]);
  if($q->rowCount()!==1)throw new DomainException('This page was changed or removed. Reload before deleting.',409);
  json_response(['success'=>true]);
 }
 if($action==='save'){
  $c=validate_content($data['content']??null);$v=$data['version']??null;if(!is_int($v)||$v<1)throw new DomainException('Invalid content version.');
  $q=db()->prepare('UPDATE content SET body=?, version=version+1, updated_at=? WHERE id=1 AND version=?');$q->execute([safe_json($c),gmdate('c'),$v]);
  if($q->rowCount()!==1)throw new DomainException('Someone else updated the website. Open a fresh editor in another tab before saving over their changes.',409);
  json_response(['saved'=>true,'version'=>$v+1,'content'=>$c]);
 }
 if($action==='add-user'){
  require_owner($u);$email=valid_email($data['email']??null);$password=$data['password']??null;if(!is_string($password))throw new DomainException('Please enter a password.');validate_password($password);
  $q=db()->prepare('SELECT id FROM users WHERE email=?');$q->execute([$email]);if($q->fetch())throw new DomainException('This email already has an account.');
  if((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn()>=10)throw new DomainException('This site supports up to 10 stallholder accounts.');
  db()->prepare('INSERT INTO users(email,password_hash,role) VALUES(?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),'editor']);json_response(['success'=>true],201);
 }
 if($action==='remove-user'){
  require_owner($u);$id=filter_var($data['id']??null,FILTER_VALIDATE_INT);if(!$id)throw new DomainException('Select a valid editor.');
  $q=db()->prepare("DELETE FROM users WHERE id=? AND role='editor'");$q->execute([$id]);if($q->rowCount()!==1)throw new DomainException('This editor does not exist. The owner cannot be removed.');json_response(['success'=>true]);
 }
 if($action==='password'){
  $current=$data['current']??null;$new=$data['password']??null;if(!is_string($current)||!is_string($new))throw new DomainException('Please fill in both password fields.');validate_password($new);
  $scope='password:'.$u['id'];rate_check($scope,8);$q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$u['id']]);
  if(!password_verify($current,$q->fetchColumn())){failed_attempt($scope);throw new DomainException('Your current password does not match.');}
  db()->prepare('UPDATE users SET password_hash=?, session_version=session_version+1 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);
  $u['session_version']=(int)$u['session_version']+1;login($u);json_response(['success'=>true,'csrf'=>csrf()]);
 }
 throw new DomainException('Unknown action.',404);
}catch(DomainException $e){$code=$e->getCode();json_response(['error'=>$e->getMessage()],in_array($code,[401,403,404,405,409,413,429],true)?$code:400);}catch(Throwable $e){error_log('Sleightly Tricky API: '.$e->getMessage());json_response(['error'=>'The update could not be completed. Your changes are still in the editor. Please try again.'],503);}
