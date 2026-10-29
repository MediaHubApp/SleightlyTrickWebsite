<?php
require __DIR__.'/app/bootstrap.php';
$error=null;$page=null;$pages=[];$unlocked=false;
try {
 session_boot();
 $slug=$_GET['trick']??null;
 if($slug!==null){
  if(!is_string($slug)||strlen($slug)>100)throw new DomainException('This instruction page is unavailable.',404);
  $page=instruction_by('slug',$slug);
  if(!$page||!$page['published'])throw new DomainException('This instruction page is unavailable.',404);
  if($_SERVER['REQUEST_METHOD']==='POST'){
   csrf_check();
   if(($_POST['action']??'')==='lock'){
    unset($_SESSION['instruction_access'][$page['id']]);
   }else{
    $ip=$_SERVER['REMOTE_ADDR']??'unknown';$scope='instruction:'.$page['id'].':'.$ip;$all='instruction-ip:'.$ip;
    rate_check($scope,8);rate_check($all,20);$password=$_POST['password']??'';
    if(!is_string($password)||strlen($password)>72||!password_verify($password,$page['password_hash'])){
     failed_attempt($scope);failed_attempt($all);throw new DomainException('That password does not match. Check the password supplied with your trick, including capital letters.',403);
    }
    session_regenerate_id(true);
    $_SESSION['instruction_access'][$page['id']]=['expires'=>time()+7200,'signature'=>$page['password_hash']];
   }
   header('Location: /instructions.php?trick='.rawurlencode($page['slug']),true,303);exit;
  }
  $unlocked=instruction_unlocked($page);
 }else{
  $pages=db()->query('SELECT slug,title,summary FROM instructions WHERE published=1 ORDER BY title COLLATE NOCASE')->fetchAll();
 }
}catch(DomainException $e){
 http_response_code(in_array($e->getCode(),[403,404,429],true)?$e->getCode():400);$error=$e->getMessage();
 if($e->getCode()===404)$page=null;
}catch(Throwable $e){error_log('Instruction page: '.$e->getMessage());http_response_code(503);$error='Instruction videos are temporarily unavailable. Please try again shortly.';$page=null;}
$title=$page?$page['title'].' instructions':'Instruction videos';
?>
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | Sleightly Tricky</title><link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/site.css"><script src="/assets/site.js" defer></script></head><body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><div class="wrap header-inner"><a href="/" class="wordmark" aria-label="Sleightly Tricky home">SLEIGHTLY<br>TRICKY<span class="logo-diamond">♦</span></a><nav class="main-nav" id="main-navigation" aria-label="Main navigation"><a href="/#magic">Magic tricks</a><a href="/instructions.php" aria-current="page">Instruction videos</a><a class="nav-visit" href="/#visit">Visit the stall</a></nav><button class="menu-toggle" aria-expanded="false" aria-controls="main-navigation" aria-label="Open menu"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button></div></header>
<main id="main" class="wrap section instruction-main">
<?php if($page): ?>
<a class="text-link" href="/instructions.php">All instruction videos</a>
<div class="instruction-heading"><p class="eyebrow">THE SECRET IS YOURS</p><h1><?=h($page['title'])?></h1><?php if($page['summary']): ?><p class="instruction-summary"><?=h($page['summary'])?></p><?php endif; ?></div>
<?php if($error): ?><p class="editor-error" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if($unlocked): ?>
<section class="instruction-watch" aria-label="Instruction video">
<?php $source=$page['video_source'];if(str_starts_with($source,'https://www.youtube-nocookie.com/embed/')||str_starts_with($source,'https://player.vimeo.com/video/')): ?>
<iframe class="instruction-player" src="<?=h($source)?>" title="<?=h($page['title'])?> instruction video" allow="fullscreen; picture-in-picture; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
<?php else: ?>
<video class="instruction-player" controls playsinline preload="metadata" src="<?=h(str_starts_with($source,'upload:')?'/instruction-video.php?id='.$page['id']:$source)?>">Your browser does not support this video. Please try a current browser.</video>
<?php endif; ?>
<?php if($page['notes']): ?><div class="instruction-notes"><h2>Before you perform</h2><p><?=nl2br(h($page['notes']))?></p></div><?php endif; ?>
<p class="admin-help">Enjoy learning your trick. Please keep its secret for fellow magicians. Access lasts for two hours in this browser.</p>
<form method="post" class="instruction-lock"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="lock"><button class="small-button">Lock this page</button></form>
</section>
<?php else: ?>
<section class="instruction-gate"><span class="instruction-diamond" aria-hidden="true">♦</span><h2>A little secret, just for you.</h2><p>Enter the password that came with your trick to watch the instructions.</p>
<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><label class="field"><span>Trick password</span><input type="password" name="password" required maxlength="72" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="password-help"></label><p id="password-help" class="admin-help">Use the password exactly as printed, including capital letters. You do not need an account.</p><button class="button red" type="submit">Unlock instruction video</button></form><p class="instruction-help">Missing your password? Bring your trick or proof of purchase to the stall and we’ll help.</p></section>
<?php endif; ?>
<?php else: ?>
<div class="instruction-heading"><p class="eyebrow">FROM YOUR FIRST PRACTICE TO YOUR FIRST “WOW”</p><h1>Learn the secret.</h1><p class="instruction-summary">Bought a trick from us? Find it below, then use the password supplied with your trick to watch its instruction video.</p></div>
<?php if($error): ?><p class="editor-error" role="alert"><?=h($error)?></p><?php elseif(!$pages): ?><div class="instruction-empty"><h2>A little patience, a little magic.</h2><p>Instruction videos will appear here as they’re added. If you need help with a trick, please ask us at the stall.</p></div><?php else: ?>
<div class="product-grid instruction-grid"><?php foreach($pages as $item): ?><article class="product"><p class="eyebrow">PASSWORD REQUIRED <span aria-hidden="true">♦</span></p><h2><?=h($item['title'])?></h2><?php if($item['summary']): ?><p><?=h($item['summary'])?></p><?php endif; ?><a class="text-link" href="/instructions.php?trick=<?=h(rawurlencode($item['slug']))?>">View instructions<span class="sr-only"> for <?=h($item['title'])?></span></a></article><?php endforeach; ?></div>
<?php endif; ?>
<?php endif; ?>
</main><footer><div class="wrap footer-bottom"><span>© <?=date('Y')?> Sleightly Tricky</span><a href="/">Back to the website</a><a href="/admin/">Stallholder login</a></div></footer></body></html>
