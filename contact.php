<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/contact.php';
$values=['name'=>'','email'=>'','topic'=>'General enquiry','message'=>''];$errors=[];$error=null;$settings=null;$sent=false;
$c=content(true)['content'];
try{
 session_boot();
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
 $settings=contact_mail_config();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $values=contact_values($_POST);
  $token=$_POST['_csrf']??'';$formId=$_POST['_form_id']??'';
  if(!is_string($token)||!hash_equals(csrf(),$token)||!is_string($formId)||!isset($_SESSION['contact_form_id'])||!hash_equals($_SESSION['contact_form_id'],$formId))throw new DomainException('Your form has expired. Please check your message and send it again.',403);
  contact_rate_limit();
  $trap=$_POST['website']??'';
  if(!is_string($trap)||$trap!==''){
   unset($_SESSION['contact_form_id']);$_SESSION['contact_sent']=true;header('Location: /contact.php',true,303);exit;
  }
  $errors=contact_errors($values);
  if($errors)http_response_code(400);
 }
 if($_SERVER['REQUEST_METHOD']==='POST'&&!$errors){
  if(!$settings||!function_exists('mail')||!contact_send($settings,$values))throw new RuntimeException('Mail handoff unavailable.');
  unset($_SESSION['contact_form_id']);$_SESSION['contact_sent']=true;
  header('Location: /contact.php',true,303);exit;
 }
 $sent=!empty($_SESSION['contact_sent']);unset($_SESSION['contact_sent']);
}catch(DomainException $e){http_response_code(in_array($e->getCode(),[403,429],true)?$e->getCode():400);$error=$e->getMessage();}
catch(Throwable $e){error_log('Contact form: mail configuration or handoff unavailable.');http_response_code(503);$error='We couldn’t send your message just now. Please try again later, or contact us at the stall.';}
if(session_status()===PHP_SESSION_ACTIVE&&!isset($_SESSION['contact_form_id']))$_SESSION['contact_form_id']=bin2hex(random_bytes(24));
$ready=$settings!==null&&function_exists('mail');
function contact_field_error(string $key,array $errors): void {if(isset($errors[$key]))echo '<span class="contact-field-error" id="contact-'.h($key).'-error">'.h($errors[$key]).'</span>';}
function contact_field_attributes(string $key,array $errors): string {return isset($errors[$key])?' aria-invalid="true" aria-describedby="contact-'.h($key).'-error"':'';}
?>
<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contact us | Sleightly Tricky</title><meta name="description" content="Ask Sleightly Tricky about our magic tricks, playing cards or instruction videos."><?php site_assets(); ?></head><body class="public-site contact-page">
<?php site_header('contact'); ?><main id="main" class="wrap contact-main">
<div class="contact-intro"><h1>Let’s talk<br> <span>magic.</span></h1><p>A question about a trick, a deck or your instructions? Send us a message and we’ll get back to you by email.</p><div class="contact-visit"><h2>Or come and see us.</h2><p><?=h($c['location'])?></p><?php if($c['tradingDays']): ?><p><?=h($c['tradingDays'])?></p><?php endif; ?><a class="text-link" href="/#visit">Find the stall <?=icon('arrow-up')?></a></div><?php if($c['email']): ?><a class="contact-direct" href="mailto:<?=h($c['email'])?>"><?=h($c['email'])?></a><?php endif; ?><div class="contact-art" aria-hidden="true"><?php card_back(); ?></div></div>
<section class="contact-card" aria-labelledby="contact-form-title">
<?php if($sent): ?>
<div class="contact-sent" role="status"><?=icon('check')?><h2 id="contact-form-title">Message sent.</h2><p>Thanks for getting in touch. We’ll reply to the email address you gave us.</p><a class="button red" href="/contact.php">Send another message <?=icon('arrow')?></a></div>
<?php else: ?>
<h2 id="contact-form-title">Send us a message.</h2><p class="contact-card-intro">Tell us a little about what you need help with.</p>
<?php if($error): ?><p class="contact-feedback" role="alert"><?=h($error)?></p><?php elseif(!$ready): ?><p class="contact-feedback" role="status">The contact form is temporarily unavailable. Please try again later or come and see us at the stall.</p><?php endif; ?>
<?php if($errors): ?><div class="contact-feedback" role="alert"><p>Please check the highlighted fields.</p><ul><?php foreach($errors as $key=>$message): ?><li><a href="#contact-<?=h($key)?>"><?=h($message)?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/contact.php" class="contact-form">
<input type="hidden" name="_csrf" value="<?=h($_SESSION['csrf']??'')?>"><input type="hidden" name="_form_id" value="<?=h($_SESSION['contact_form_id']??'')?>">
<div class="contact-trap" aria-hidden="true"><label for="contact-website">Leave this field blank</label><input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="contact-field-pair"><label class="contact-field" for="contact-name"><span>Your name</span><input id="contact-name" name="name" autocomplete="name" maxlength="100" required value="<?=h($values['name'])?>"<?=contact_field_attributes('name',$errors)?>><?php contact_field_error('name',$errors); ?></label><label class="contact-field" for="contact-email"><span>Email address</span><input type="email" id="contact-email" name="email" autocomplete="email" maxlength="254" required value="<?=h($values['email'])?>"<?=contact_field_attributes('email',$errors)?>><?php contact_field_error('email',$errors); ?></label></div>
<label class="contact-field" for="contact-topic"><span id="contact-topic-label">What’s it about?</span><select id="contact-topic" name="topic" aria-labelledby="contact-topic-label" required<?=contact_field_attributes('topic',$errors)?>><?php foreach(CONTACT_TOPICS as $topic): ?><option<?=$values['topic']===$topic?' selected':''?>><?=h($topic)?></option><?php endforeach; ?></select><?php contact_field_error('topic',$errors); ?></label>
<label class="contact-field" for="contact-message"><span>Your message</span><textarea id="contact-message" name="message" rows="6" maxlength="5000" required<?=contact_field_attributes('message',$errors)?>><?=h($values['message'])?></textarea><?php contact_field_error('message',$errors); ?></label>
<p class="contact-privacy">We’ll use your name, email address and message to respond to your enquiry.</p><button class="button red" type="submit"<?=!$ready?' disabled':''?>>Send message <?=icon('arrow')?></button>
</form><?php endif; ?></section></main><?php site_footer(); ?></body></html>
