<?php
require __DIR__.'/app/bootstrap.php';
security_headers();
$c=content(true)['content'];
$magic=array_values(array_filter($c['products'],fn($p)=>$p['visible']&&$p['category']==='magic'));
$cards=array_values(array_filter($c['products'],fn($p)=>$p['visible']&&$p['category']==='cards'));
$suits=['♠','♥','♣','♦'];
?>
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sleightly Tricky | Magic Tricks &amp; Playing Cards in Sudbury</title>
<meta name="description" content="A little wonder in your hands. Discover magic tricks, collectable playing cards and your next great reveal at Sleightly Tricky, Sudbury.">
<meta property="og:title" content="Sleightly Tricky · A little wonder in your hands"><meta property="og:description" content="Magic to learn. Cards to collect. An independent magic stall in Sudbury, Suffolk."><meta property="og:type" content="website">
<?php site_assets(); ?></head><body class="public-site home-page">
<?php site_header(); ?>
<main id="main">
<section class="hero wrap" aria-labelledby="hero-title">
<div class="hero-copy"><p class="eyebrow hero-eyebrow"><span class="tiny-diamond" aria-hidden="true">♦</span> <?=h($c['eyebrow'])?></p>
<h1 id="hero-title"><?=h($c['heroTitle'])?><br><em><?=h($c['heroAccent'])?></em></h1>
<p class="hero-description"><?=h($c['heroDescription'])?></p>
<div class="hero-actions"><a class="button red" href="#magic">Explore the magic <?=icon('arrow')?></a><a class="text-link" href="#visit">Find our stall <?=icon('arrow-up')?></a></div>
<div class="hero-foot"><span class="suits" aria-hidden="true">♠ <i>♥</i> ♣ <i>♦</i></span><span>For the curious.<br>And the wonderfully curious.</span></div></div>
<div class="hero-visual"><figure class="hero-photo"><img src="<?=h($c['heroImage'])?>" alt="Playing cards, cups and rope on a burgundy magician’s table" width="1122" height="1402" fetchpriority="high"><span class="photo-outline" aria-hidden="true"></span><figcaption><span>THE ART OF A LITTLE WONDER</span><strong>“How did you do that?”</strong></figcaption></figure>
<div class="hero-note" aria-hidden="true"><span class="hero-note-suit">♦</span><span>A little less<br><em>ordinary.</em></span><span class="hero-note-line"></span><small>DISCOVER SOMETHING<br>WORTH SHARING</small></div>
<span class="hero-coordinate" aria-hidden="true">SLEIGHTLY TRICKY / SUDBURY</span></div>
<a class="scroll-cue" href="#story"><span class="scroll-line" aria-hidden="true"></span>Scroll for a little wonder</a>
</section>
<div class="ribbon"><div class="wrap ribbon-inner"><span>Magic to learn</span><span aria-hidden="true">✧</span><span>Cards to collect</span><span aria-hidden="true">✧</span><span>Secrets to keep</span></div></div>
<section class="about wrap section" id="story" aria-labelledby="story-title">
<div class="story-heading" data-reveal><p class="eyebrow section-kicker"><span>01</span> THE STORY BEHIND THE STALL</p><h2 id="story-title"><?=h($c['aboutTitle'])?></h2><div class="story-art"><?php card_art('♠'); ?><span class="story-art-caption">A shared curiosity. A little possibility.</span></div></div>
<div class="about-copy" data-reveal><span class="story-star" aria-hidden="true"><?=icon('spark')?></span><p class="large-copy"><?=h($c['aboutText'])?></p><p><?=h($c['aboutSecond'])?></p><a class="text-link" href="#visit">Meet us in Sudbury <?=icon('arrow-up')?></a><div class="story-signoff"><span aria-hidden="true">♦</span><p>Come for the curiosity.<br><em>Stay for the impossible.</em></p></div></div>
</section>
<section class="catalogue section" id="magic" aria-labelledby="magic-title"><div class="wrap">
<div class="section-head" data-reveal><div><p class="eyebrow section-kicker"><span>02</span> THE MAGIC COLLECTION</p><h2 id="magic-title"><?=h($c['magicTitle'])?></h2></div><p><?=h($c['magicIntro'])?></p></div>
<div class="collection-toolbar"><div class="collection-count"><span class="status-dot" aria-hidden="true"></span><span data-filter-count="magic-grid" role="status" aria-live="polite"><?=count($magic)?> tricks to discover</span></div><?php search_control('magic-search','Find a magic trick…'); ?></div>
<div class="product-grid" id="magic-grid" data-filter-grid="magic-search">
<?php foreach($magic as $i=>$p): ?>
<article class="product" data-reveal data-filter-item>
<div class="product-art"><?php if($p['image']): ?><img class="product-image" src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" loading="lazy" width="500" height="360"><?php else: magic_art($p,$suits[$i%4]); endif; ?><span class="product-index" aria-hidden="true"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span></div>
<div class="product-body"><p class="product-label"><?=h($p['label'])?></p><div class="product-title"><h3><?=h($p['name'])?></h3><span class="price"><?=money($p['price'])?></span></div><p class="product-description"><?=h($p['description'])?></p><div class="product-bottom"><span>Discover at the stall</span><span class="product-suit" aria-hidden="true"><?=$suits[$i%4]?></span></div></div>
</article>
<?php endforeach; ?>
</div><p class="filter-empty" data-filter-empty="magic-grid" hidden>No tricks found. Try a different name, or clear your search.</p>
<div class="collection-note"><span><?=icon('spark')?> Curious about a trick? Ask us at the stall.</span><span><?=h($c['notice'])?></span></div>
</div></section>
<section class="playing section" id="cards" aria-labelledby="cards-title"><div class="wrap">
<div class="section-head" data-reveal><div><p class="eyebrow section-kicker"><span>03</span> THE PLAYING CARD COLLECTION</p><h2 id="cards-title"><?=h($c['cardsTitle'])?></h2></div><p><?=h($c['cardsIntro'])?></p></div>
<div class="deck-grid">
<?php foreach($cards as $i=>$p): ?>
<article class="deck" data-reveal><div class="deck-art"><?php if($p['image']): ?><img class="product-image" src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" loading="lazy" width="500" height="360"><?php else: card_art($suits[$i%4],true); endif; ?></div><div class="deck-body"><div class="deck-top"><span class="deck-label"><?=h($p['label'])?></span><span aria-hidden="true" class="deck-suit"><?=$suits[$i%4]?></span></div><h3><?=h($p['name'])?></h3><p><?=h($p['description'])?></p><div class="deck-bottom"><span class="deck-price"><?=money($p['price'])?></span><span>Find your next favourite</span></div></div></article>
<?php endforeach; ?>
</div><p class="deck-note">A deck for every personality. Explore the designs in person — available editions may vary.</p>
</div></section>
<section class="instruction-callout section" aria-labelledby="learn-title"><div class="wrap learning-panel" data-reveal>
<div class="learning-art" aria-hidden="true"><div class="learning-orbit"></div><div class="learning-card"><span>THE NEXT CHAPTER</span><?=icon('play')?><em>Your next<br>great reveal.</em><span>LEARN · PRACTISE · AMAZE</span></div><span class="learning-spark">✧</span></div>
<div class="learning-copy"><p class="eyebrow">BOUGHT A TRICK? LET’S MAKE SOME MAGIC.</p><h2 id="learn-title">Your trick.<br><em>Your secret.</em></h2><p>Ready to learn? Find your trick’s instruction video and unlock it with the password supplied with your purchase.</p><a class="button red" href="/instructions.php">Watch your instructions <?=icon('play')?></a><span class="learning-hint"><?=icon('lock')?> Just you, your trick and a little practice.</span></div>
</div></section>
<section class="visit wrap section" id="visit" aria-labelledby="visit-title">
<div class="visit-copy" data-reveal><p class="eyebrow section-kicker"><span>04</span> FIND SLEIGHTLY TRICKY</p><h2 id="visit-title"><?=h($c['visitTitle'])?></h2><p><?=h($c['visitText'])?></p><div class="visit-details"><div class="location-line"><span class="location-icon"><?=icon('pin')?></span><div><span class="detail-label">FIND US AT THE MARKET</span><strong><?=h($c['location'])?></strong></div></div><?php if($c['tradingDays']): ?><p class="trading-days"><?=h($c['tradingDays'])?></p><?php endif; ?><?php if($c['email']): ?><a class="contact-email" href="mailto:<?=h($c['email'])?>"><?=h($c['email'])?> <?=icon('arrow-up')?></a><?php endif; ?></div><a class="button black" href="https://www.google.com/maps/search/?api=1&amp;query=Sudbury+Market+Suffolk" target="_blank" rel="noreferrer">Find Sudbury Market <?=icon('arrow-up')?></a><p class="visit-note">Browse here. Discover it in person.</p></div>
<div class="banner-frame" data-reveal><div class="banner-top"><p class="eyebrow">YOUR SIGN TO STOP AND SAY HELLO</p><span aria-hidden="true">♦</span></div><div class="banner-image"><img src="<?=h($c['bannerImage'])?>" alt="Look for the Sleightly Tricky playing-card banner at the market" width="1600" height="534" loading="lazy"></div><div class="banner-bottom"><span>A familiar face at the market.</span><span class="suits" aria-hidden="true">♠ <i>♥</i> ♣ <i>♦</i></span></div></div>
</section></main>
<?php site_footer(); ?></body></html>
