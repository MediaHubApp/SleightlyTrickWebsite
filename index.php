<?php
require __DIR__.'/app/bootstrap.php';
security_headers();
$c=content(true)['content'];
$magic=array_values(array_filter($c['products'],fn($p)=>$p['visible']&&$p['category']==='magic'));
$cards=array_values(array_filter($c['products'],fn($p)=>$p['visible']&&$p['category']==='cards'));
?>
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sleightly Tricky | Magic Tricks &amp; Playing Cards in Sudbury</title><meta name="description" content="Browse magic tricks and playing cards at Sleightly Tricky. Visit our independent stall at Sudbury Market, Suffolk."><meta property="og:title" content="Sleightly Tricky"><meta property="og:description" content="Magic tricks, playing cards and advice from two friends in Sudbury."><meta property="og:type" content="website"><?php site_assets(); ?></head>
<body class="public-site home-page"><?php site_header(); ?>
<main id="main">
<section class="hero" aria-labelledby="hero-title">
<figure class="hero-image"><img src="<?=h($c['heroImage'])?>" alt="Magic props at Sleightly Tricky" width="1536" height="1024" fetchpriority="high"></figure>
<div class="wrap hero-layout"><div class="hero-copy"><h1 id="hero-title"><?=h($c['heroTitle'])?><br><?=h($c['heroAccent'])?></h1><p><?=h($c['heroDescription'])?></p><div class="hero-actions"><a class="button light" href="#magic">Browse the collection <?=icon('arrow')?></a><a class="hero-visit" href="#visit">Visit the stall <?=icon('arrow-up')?></a></div></div></div>
</section>
<section class="catalogue wrap section" id="magic" aria-labelledby="magic-title">
<div class="section-head" data-reveal><h2 id="magic-title"><?=h($c['magicTitle'])?></h2><p><?=h($c['magicIntro'])?></p></div>
<div class="collection-toolbar"><?php search_control('magic-search','Find a magic trick…'); ?><span data-filter-count="magic-grid" class="collection-count" role="status" aria-live="polite"><?=count($magic)?> tricks</span></div>
<div class="product-grid" id="magic-grid" data-filter-grid="magic-search">
<?php foreach($magic as $p): ?><article class="product" data-reveal data-filter-item><div class="product-title"><h3><?=h($p['name'])?></h3><span class="price"><?=money($p['price'])?></span></div><?php if($p['image']): ?><img class="product-image" src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" width="500" height="360" loading="lazy"><?php endif; ?><p class="product-description"><?=h($p['description'])?></p></article><?php endforeach; ?>
</div><p class="filter-empty" data-filter-empty="magic-grid" hidden>No tricks found. Try another name, or clear your search.</p><div class="collection-note"><span>Ask us for a demonstration at the stall.</span><span><?=h($c['notice'])?></span></div>
</section>
<section class="playing section" id="cards" aria-labelledby="cards-title"><div class="wrap">
<div class="section-head" data-reveal><h2 id="cards-title"><?=h($c['cardsTitle'])?></h2><p><?=h($c['cardsIntro'])?></p></div>
<div class="cards-layout"><div class="cards-art" data-reveal><?php card_study(); ?></div><div class="deck-grid"><?php foreach($cards as $p): ?><article class="deck" data-reveal><div class="deck-top"><h3><?=h($p['name'])?></h3><span class="deck-price"><?=money($p['price'])?></span></div><?php if($p['image']): ?><img class="product-image" src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" width="500" height="360" loading="lazy"><?php endif; ?><p><?=h($p['description'])?></p></article><?php endforeach; ?></div></div>
<p class="deck-note">Browse the decks in person. Available editions may vary.</p></div></section>
<section class="instruction-callout wrap section" aria-labelledby="learn-title" data-reveal><h2 id="learn-title">Your next step.</h2><div><p>Already bought a trick? Find the video and learn it at your own pace. You’ll need the password supplied with your purchase.</p><a class="button red" href="/instructions.php">Watch your instructions <?=icon('play')?></a></div></section>
<section class="about section" id="story" aria-labelledby="story-title"><div class="wrap about-layout"><h2 id="story-title" data-reveal><?=h($c['aboutTitle'])?></h2><div data-reveal><p class="large-copy"><?=h($c['aboutText'])?></p><p><?=h($c['aboutSecond'])?></p><a class="text-link" href="#visit">Come and meet us <?=icon('arrow-up')?></a></div></div></section>
<section class="visit wrap section" id="visit" aria-labelledby="visit-title"><div data-reveal><h2 id="visit-title"><?=h($c['visitTitle'])?></h2><p><?=h($c['visitText'])?></p><div class="visit-details"><p class="location-line"><?=icon('pin')?> <?=h($c['location'])?></p><?php if($c['tradingDays']): ?><p class="trading-days"><?=h($c['tradingDays'])?></p><?php endif; ?><?php if($c['email']): ?><a class="contact-email" href="mailto:<?=h($c['email'])?>"><?=h($c['email'])?></a><?php endif; ?></div><a class="button red" href="https://www.google.com/maps/search/?api=1&amp;query=Sudbury+Market+Suffolk" target="_blank" rel="noreferrer">Get directions <?=icon('arrow-up')?></a></div><figure class="banner-frame" data-reveal><img src="<?=h($c['bannerImage'])?>" alt="The Sleightly Tricky banner to look for at Sudbury Market" width="1600" height="534" loading="lazy"></figure></section>
</main><?php site_footer(); ?></body></html>
