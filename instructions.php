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
<?php site_header('instructions'); ?>
<main id="main" class="instruction-main">
<div class="wrap breadcrumb"><a href="/">Home</a><?=icon('chevron')?><?php if($page): ?><a href="/instructions.php">Instruction videos</a><?=icon('chevron')?><span><?=h($page['title'])?></span><?php else: ?><span>Instruction videos</span><?php endif; ?></div>
<?php if($page): ?>
<section class="wrap lesson-section <?=$unlocked?'lesson-unlocked':''?>">
<div class="lesson-intro" data-reveal><p class="eyebrow section-kicker"><span>♦</span> THE SECRET IS YOURS</p><h1><?=h($page['title'])?></h1><?php if($page['summary']): ?><p class="instruction-summary"><?=h($page['summary'])?></p><?php endif; ?>
<?php if(!$unlocked): ?>
<div class="lesson-preview" aria-hidden="true"><div class="preview-lines"></div><span class="preview-corner">ST / ♦</span><span class="preview-play"><?=icon('play')?></span><p>Every great reveal<br><em>starts with a little practice.</em></p><span class="preview-footer">LEARN IT. MAKE IT YOURS. PASS ON THE WONDER.</span></div>
<?php else: ?><span class="access-badge"><?=icon('check')?> Your secret is unlocked</span><?php endif; ?>
<a class="text-link back-to-library" href="/instructions.php">All instruction videos <?=icon('arrow')?></a>
</div>
<?php if(!$unlocked): ?>
<section class="instruction-gate" aria-labelledby="unlock-title" data-reveal><span class="gate-icon"><?=icon('lock')?></span><p class="eyebrow">A LITTLE WONDER AWAITS</p><h2 id="unlock-title">A little secret,<br><em>just for you.</em></h2><p>Enter the password that came with your trick to watch the instructions.</p>
<?php if($error): ?><p class="editor-error" role="alert" tabindex="-1"><?=h($error)?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><label class="field" for="trick-password"><span>Trick password</span></label><div class="password-input"><input id="trick-password" type="password" name="password" required maxlength="72" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="password-help"><button type="button" class="password-toggle" data-password-toggle="trick-password" aria-label="Show trick password" aria-pressed="false" hidden>Show</button></div><p id="password-help">Use the password exactly as printed, including capital letters. You do not need an account.</p><button class="button red" type="submit">Unlock instruction video <?=icon('arrow')?></button></form>
<div class="instruction-help"><?=icon('spark')?><p>Missing your password?<br><span>Bring your trick or proof of purchase to the stall and we’ll help.</span></p></div></section>
<?php else: ?>
<section class="instruction-watch" aria-label="Instruction video"><div class="watch-bar"><span><?=icon('play')?> YOUR PRIVATE SCREENING ROOM</span><span>LEARN AT YOUR OWN PACE</span></div>
<div class="watch-frame">
<?php $source=$page['video_source'];if(str_starts_with($source,'https://www.youtube-nocookie.com/embed/')||str_starts_with($source,'https://player.vimeo.com/video/')): ?>
<iframe class="instruction-player" src="<?=h($source)?>" title="<?=h($page['title'])?> instruction video" allow="fullscreen; picture-in-picture; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
<?php else: ?>
<video class="instruction-player" controls playsinline preload="metadata" src="<?=h(str_starts_with($source,'upload:')?'/instruction-video.php?id='.$page['id']:$source)?>">Your browser does not support this video. Please try a current browser.</video>
<?php endif; ?></div>
<div class="watch-details"><?php if($page['notes']): ?><div class="instruction-notes"><p class="eyebrow">THE FINISHING TOUCHES</p><h2>Before you perform</h2><p><?=nl2br(h($page['notes']))?></p></div><?php endif; ?>
<aside class="practice-note"><span aria-hidden="true">✧</span><h3>Make the magic yours.</h3><p>Take your time. Practise the little details. The best part is the first time you see someone smile.</p></aside></div>
<div class="watch-bottom"><p><?=icon('lock')?> Just for you. Access lasts for two hours in this browser.</p><form method="post" class="instruction-lock"><input type="hidden" name="_csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="lock"><button class="small-button">Lock this page <?=icon('lock')?></button></form></div>
</section>
<?php endif; ?></section>
<?php else: ?>
<section class="library-hero wrap"><div data-reveal><p class="eyebrow section-kicker"><span>♦</span> THE OTHER SIDE OF THE MAGIC</p><h1>Learn the secret.</h1><p class="instruction-summary">Bought a trick from us? Find it below, then use the password supplied with your trick to watch its instruction video.</p><div class="library-promise"><?=icon('lock')?> Your trick. Your password. Your next great reveal.</div></div><div class="library-art" aria-hidden="true"><?php card_art('♦'); ?><span class="library-stamp">A LITTLE PRACTICE.<br>A LOT OF POSSIBILITY.</span></div></section>
<section class="library-content section"><div class="wrap">
<?php if($error): ?><p class="editor-error" role="alert"><?=h($error)?></p><?php elseif(!$pages): ?>
<div class="instruction-empty" data-reveal><span class="empty-suit" aria-hidden="true">✧</span><p class="eyebrow">GOOD THINGS ARE IN THE MAKING</p><h2>A little patience,<br><em>a little magic.</em></h2><p>Instruction videos will appear here as they’re added. If you need help with a trick, please ask us at the stall.</p><a class="text-link" href="/#visit">Come and say hello <?=icon('arrow-up')?></a></div>
<?php else: ?>
<div class="collection-toolbar"><div class="collection-count"><span class="status-dot" aria-hidden="true"></span><span role="status" aria-live="polite" data-filter-count="instruction-grid"><?=count($pages)?> secrets waiting to be learned</span></div><?php search_control('instruction-search','Find your trick…'); ?></div>
<div class="product-grid instruction-grid" id="instruction-grid" data-filter-grid="instruction-search">
<?php foreach($pages as $i=>$item): ?><article class="lesson-card" data-reveal data-filter-item><div class="lesson-card-art" aria-hidden="true"><span class="lesson-card-number"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><div class="lesson-mini-card"><span>A</span><span>♦</span></div><span class="lesson-play"><?=icon('play')?></span><span class="lesson-protected"><?=icon('lock')?> PASSWORD REQUIRED</span></div><div class="lesson-card-body"><p class="eyebrow">A LITTLE PRACTICE. A LITTLE WONDER.</p><h2><?=h($item['title'])?></h2><?php if($item['summary']): ?><p class="lesson-summary"><?=h($item['summary'])?></p><?php endif; ?><a class="text-link" href="/instructions.php?trick=<?=h(rawurlencode($item['slug']))?>">View instructions<span class="sr-only"> for <?=h($item['title'])?></span><?=icon('arrow')?></a></div></article><?php endforeach; ?>
</div><p class="filter-empty" data-filter-empty="instruction-grid" hidden>No instruction pages found. Try another name, or clear your search.</p>
<?php endif; ?>
</div></section>
<section class="library-help wrap section"><div data-reveal><p class="eyebrow section-kicker"><span>?</span> A HELPING HAND</p><h2>A little less mystery.<br><em>A little more magic.</em></h2><p>Everything you need to get from your first practice to your first “wow”.</p></div><div class="faq-list" data-reveal><details><summary>Where do I find my password? <?=icon('chevron')?></summary><p>Your password comes with your trick. Enter it exactly as printed, including capital letters. If it’s missing, bring the trick or proof of purchase to our stall.</p></details><details><summary>Do I need to create an account? <?=icon('chevron')?></summary><p>No account needed. Choose your trick and enter its shared password. Your access lasts for two hours in the same browser, and you can unlock it again whenever you want to practise.</p></details><details><summary>Can I watch on my phone? <?=icon('chevron')?></summary><p>Absolutely. Watch on your phone, tablet or computer, and pause or replay the details as you practise. For the best experience, use a current browser and a reliable internet connection.</p></details><details><summary>Still need a little help? <?=icon('chevron')?></summary><p>Come and say hello at the market. Bring your trick along and we’ll help you get started. <a href="/#visit">Find the stall.</a></p></details></div></section>
<?php endif; ?>
</main><?php site_footer(); ?></body></html>
