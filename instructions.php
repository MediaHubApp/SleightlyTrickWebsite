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
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | Sleightly Tricky</title><?php site_assets(); ?></head><body class="public-site instructions-page">
<?php site_header('instructions'); ?><main id="main" class="instruction-main">
<?php if($page): ?>
<div class="wrap breadcrumb"><a href="/instructions.php">Instruction videos</a><?=icon('chevron')?><span><?=h($page['title'])?></span></div>
<section class="wrap lesson-section <?=$unlocked?'lesson-unlocked':''?>">
<div class="lesson-intro"><h1><?=h($page['title'])?></h1><?php if($page['summary']): ?><p class="instruction-summary"><?=h($page['summary'])?></p><?php endif; ?></div>
<?php if(!$unlocked): ?>
<div class="lesson-access"><div class="lesson-art" aria-hidden="true"><?php card_study(); ?></div>
<section class="instruction-gate" aria-labelledby="unlock-title"><h2 id="unlock-title">Watch the instructions.</h2><p>Enter the password supplied with your trick.</p>
<?php if($error): ?><p class="editor-error" role="alert" tabindex="-1"><?=h($error)?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><label class="field" for="trick-password"><span>Trick password</span></label><div class="password-input"><input id="trick-password" type="password" name="password" required maxlength="72" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="password-help"><button type="button" class="password-toggle" data-password-toggle="trick-password" aria-label="Show trick password" aria-pressed="false" hidden>Show</button></div><p id="password-help">Passwords are case-sensitive. No account is needed.</p><button class="button red" type="submit">Unlock instruction video <?=icon('arrow')?></button></form>
<p class="instruction-help">Can’t find the password? Bring your trick or proof of purchase to the stall and we’ll help.</p></section></div>
<?php else: ?>
<section class="instruction-watch" aria-label="Instruction video"><div class="watch-frame">
<?php $source=$page['video_source'];if(str_starts_with($source,'https://www.youtube-nocookie.com/embed/')||str_starts_with($source,'https://player.vimeo.com/video/')): ?>
<iframe class="instruction-player" src="<?=h($source)?>" title="<?=h($page['title'])?> instruction video" allow="fullscreen; picture-in-picture; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
<?php else: ?><video class="instruction-player" controls playsinline preload="metadata" src="<?=h(str_starts_with($source,'upload:')?'/instruction-video.php?id='.$page['id']:$source)?>">Your browser does not support this video. Please try a current browser.</video><?php endif; ?></div>
<div class="watch-bottom"><p><?=icon('lock')?> This page locks after two hours. Enter your password again to continue watching.</p><form method="post" class="instruction-lock"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="lock"><button class="small-button">Lock this page <?=icon('lock')?></button></form></div>
<?php if($page['notes']): ?><div class="instruction-notes"><h2>Before you perform</h2><p><?=nl2br(h($page['notes']))?></p></div><?php endif; ?>
</section>
<?php endif; ?><a class="text-link back-to-library" href="/instructions.php">All instruction videos <?=icon('arrow')?></a></section>
<?php else: ?>
<section class="library-hero wrap"><h1>Instruction videos.</h1><p>Find your trick and enter the password supplied with it.</p></section>
<section class="library-content wrap">
<?php if($error): ?><p class="editor-error" role="alert"><?=h($error)?></p><?php elseif(!$pages): ?><div class="instruction-empty"><h2>No videos yet.</h2><p>We’re adding instruction videos here. For help with your trick, please ask us at the stall.</p><a class="text-link" href="/#visit">Find the stall <?=icon('arrow-up')?></a></div>
<?php else: ?>
<div class="collection-toolbar"><?php search_control('instruction-search','Find your trick…'); ?><span class="collection-count" role="status" aria-live="polite" data-filter-count="instruction-grid"><?=count($pages)?> videos</span></div>
<div class="instruction-grid" id="instruction-grid" data-filter-grid="instruction-search"><?php foreach($pages as $item): ?><article class="lesson-card" data-reveal data-filter-item><span class="lesson-symbol" aria-hidden="true"><?=icon('play')?></span><div class="lesson-card-body"><h2><?=h($item['title'])?></h2><?php if($item['summary']): ?><p class="lesson-summary"><?=h($item['summary'])?></p><?php endif; ?></div><a class="lesson-link" href="/instructions.php?trick=<?=h(rawurlencode($item['slug']))?>"><span>View instructions<span class="sr-only"> for <?=h($item['title'])?></span></span><?=icon('arrow')?></a></article><?php endforeach; ?></div><p class="filter-empty" data-filter-empty="instruction-grid" hidden>No instruction pages found. Try another name, or clear your search.</p>
<?php endif; ?></section>
<section class="library-help wrap section"><h2>A few questions.</h2><div class="faq-list"><details><summary>Where do I find my password? <?=icon('chevron')?></summary><p>Your password comes with your trick. Enter it exactly as printed, including capital letters. If it’s missing, bring the trick or proof of purchase to our stall.</p></details><details><summary>Do I need an account? <?=icon('chevron')?></summary><p>No. Choose your trick and enter its shared password. Access lasts for two hours in the same browser. You can unlock it again whenever you want to practise.</p></details><details><summary>Can I watch on my phone? <?=icon('chevron')?></summary><p>Yes. The videos work on phones, tablets and computers. You can pause and replay as you practise.</p></details><details><summary>What if I need more help? <?=icon('chevron')?></summary><p>Come and see us at the market with your trick. <a href="/#visit">Find the stall.</a></p></details></div></section>
<?php endif; ?></main><?php site_footer(); ?></body></html>
