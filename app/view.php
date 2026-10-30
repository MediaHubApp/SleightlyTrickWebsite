<?php
declare(strict_types=1);
function icon(string $name, string $class=''): string {
 $paths=[
  'close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
  'arrow'=>'<path d="M4 12h15M13 5l7 7-7 7"/>',
  'arrow-up'=>'<path d="M6 18 18 6M6 6h12v12"/>',
  'chevron'=>'<path d="m9 5 7 7-7 7"/>',
  'pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  'play'=>'<path d="m9 5 11 7-11 7Z"/>',
  'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
  'check'=>'<path d="m5 12 4 4L19 6"/>',
  'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  'website'=>'<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 13h8M8 16h5"/>',
  'collection'=>'<rect x="3" y="5" width="12" height="16" rx="2"/><path d="M8 2h11a2 2 0 0 1 2 2v12M9 10l2 3-2 3-2-3Z"/>',
  'image'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 16 5-5 4 4 3-3 6 6"/>',
  'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>',
  'account'=>'<circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
  'logout'=>'<path d="M9 4H4v16h5M12 12h9m-4-4 4 4-4 4"/>',
 ];
 return '<svg class="icon '.h($class).'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['arrow']).'</svg>';
}
function site_assets(bool $admin=false): void { ?>
<link rel="icon" href="/favicon.svg">
<link rel="preload" href="/assets/fonts/Manrope-Variable.ttf" as="font" type="font/ttf" crossorigin>
<link rel="stylesheet" href="/assets/site.css?v=<?=h(APP_VERSION)?>">
<?php if($admin): ?><link rel="stylesheet" href="/assets/admin.css?v=<?=h(APP_VERSION)?>"><?php endif; ?>
<noscript><link rel="stylesheet" href="/assets/nojs.css?v=<?=h(APP_VERSION)?>"></noscript>
<script src="/assets/site.js?v=<?=h(APP_VERSION)?>" defer></script>
<?php }
function brand(string $class=''): void { ?>
<a class="wordmark <?=h($class)?>" href="/" aria-label="Sleightly Tricky home"><span class="brand-mark" aria-hidden="true">♦</span><span class="brand-name">Sleightly<br>Tricky<span class="brand-stop">.</span></span></a>
<?php }
function site_header(string $active=''): void { ?>
<a class="skip-link" href="#main">Skip to content</a><div class="scroll-progress" aria-hidden="true"></div>
<header class="site-header"><div class="header-inner"><?php brand(); ?>
<nav class="main-nav" id="main-navigation" aria-label="Main navigation"><a href="/#magic">Magic tricks</a><a href="/#cards">Playing cards</a><a href="/instructions.php" <?=$active==='instructions'?'aria-current="page"':''?>>Instruction videos</a><a class="nav-visit" href="/#visit">Visit the stall <?=icon('arrow-up')?></a></nav>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Open menu"><span class="menu-lines" aria-hidden="true"></span><span>Menu</span></button>
</div></header>
<?php }
function site_footer(): void { ?>
<footer class="site-footer"><div class="wrap"><div class="footer-top"><h2>See you<br>at the stall.</h2><a class="footer-visit" href="/#visit" aria-label="Find the stall"><?=icon('arrow-up')?></a></div><div class="footer-main"><div><?php brand('footer-brand'); ?><p>Magic tricks &amp; playing cards.<br>Sudbury, Suffolk.</p></div><nav aria-label="Footer navigation"><a href="/#magic">Magic tricks</a><a href="/#cards">Playing cards</a><a href="/instructions.php">Instruction videos</a><a href="/#story">About us</a><a href="/#visit">Visit the stall</a></nav></div><div class="footer-bottom"><span>© <?=date('Y')?> Sleightly Tricky</span><a href="/credits.php">Image credits</a><a href="/admin/">Stallholder login <?=icon('arrow-up')?></a></div></div></footer>
<?php }
function search_control(string $id,string $placeholder): void { ?>
<div class="collection-search" data-enhance hidden><label for="<?=h($id)?>" class="sr-only"><?=h($placeholder)?></label><?=icon('search')?><input type="search" id="<?=h($id)?>" placeholder="<?=h($placeholder)?>" autocomplete="off"><button type="button" class="search-clear" data-search-clear="<?=h($id)?>" aria-label="Clear search" hidden>×</button></div>
<?php }
/** Established, non-generated card artwork; source and licences ship with the SVGs. */
function playing_card(string $suit='spades',int $rank=1): void {
 $suits=['spades'=>'S','clubs'=>'C','hearts'=>'H','diamonds'=>'D'];
 if(!isset($suits[$suit])||!in_array($rank,[1,2,7],true))throw new LogicException('Unsupported card artwork.');
 $code=($rank===1?'A':$rank).$suits[$suit];
 echo '<img class="playing-card card-face" data-suit="'.h($suit).'" data-rank="'.$rank.'" src="/assets/cards/faces/'.$code.'.svg" alt="" width="179" height="250" loading="lazy" decoding="async" aria-hidden="true">';
}
function card_study(): void { ?>
<div class="card-study" aria-hidden="true"><div class="study-card study-spade"><img class="playing-card card-face" src="/assets/cards/faces/KS.svg" alt="" width="179" height="250" loading="lazy" aria-hidden="true"></div><div class="study-card study-heart"><?php playing_card('hearts',7); ?></div><div class="study-card study-club"><?php card_back(true); ?></div></div>
<?php }
/** Refresh only unchanged stock copy; preserve custom text, products and photos. */
function design_content(array $content): array {
 $previous=require __DIR__.'/previous-copy.php';$new=defaults();
 if(in_array($content['heroImage']??'',['/images/hero.png','/images/still-life.png'],true))$content['heroImage']=$new['heroImage'];
 $content['storyImage']=$content['storyImage']??$new['storyImage'];
 foreach($previous as $key=>$value){if($key!=='products'&&array_key_exists($key,$content)&&$content[$key]===$value)$content[$key]=$new[$key];}
 $oldProducts=array_column($previous['products'],'description','id');$newProducts=array_column($new['products'],'description','id');
 if(isset($content['products'])&&is_array($content['products']))foreach($content['products'] as &$product){
  $id=$product['id']??'';
  if(isset($oldProducts[$id],$newProducts[$id])&&($product['description']??null)===$oldProducts[$id])$product['description']=$newProducts[$id];
 }
 unset($product);
 return $content;
}

/** The actual Rider Back design, with a restrained red presentation variant. */
function card_back(bool $blue=false): void {
 echo '<img class="playing-card card-back '.($blue?'rider-blue':'rider-red').'" src="/assets/cards/rider-back.svg" alt="" width="800" height="1130" loading="lazy" decoding="async" aria-hidden="true">';
}
function product_visual(array $p,int $index=0,bool $deck=false): void {
 if($p['image']){echo '<img class="product-image" src="'.h($p['image']).'" alt="'.h($p['name']).'" loading="lazy" width="600" height="500">';return;}
 $kind=['cups'=>'cups','rope'=>'rope','prisma'=>'light','ghost'=>'blank','bwave'=>'packet'][$p['id']]??'cards';
 $suits=['spades','hearts','clubs','diamonds'];
 ?>
<div class="product-scene scene-<?=h($kind)?> tone-<?=$index%4?> <?=$deck?'deck-scene':''?>" aria-hidden="true"><span class="scene-shadow"></span>
<?php if($kind==='cups'): ?><svg class="cups-art" viewBox="0 0 400 300" fill="none"><g stroke="#6f6a61" stroke-width="2"><?php foreach([[90,139],[200,100],[305,145]] as [$x,$y]): ?><path d="M<?=$x-31?> <?=$y?>L<?=$x-44?> <?=$y+91?>Q<?=$x?> <?=$y+109?> <?=$x+44?> <?=$y+91?>L<?=$x+31?> <?=$y?>" fill="#d8d4c9"/><ellipse cx="<?=$x?>" cy="<?=$y?>" rx="31" ry="9" fill="#ebe7dc"/><path d="M<?=$x-42?> <?=$y+86?>Q<?=$x?> <?=$y+101?> <?=$x+42?> <?=$y+86?>"/></g><g stroke="#6f6a61" stroke-width="2"><?php endforeach; ?></g><circle cx="131" cy="246" r="15" fill="#af2433"/></svg>
<?php elseif($kind==='rope'): ?>
<svg class="rope-art" viewBox="0 0 400 300" fill="none"><path d="M70 210C40 90 128 45 217 71S329 208 249 232 118 181 145 129 284 126 267 196 174 252 127 213" stroke="#958163" stroke-width="17" stroke-linecap="round"/><path d="M70 210C40 90 128 45 217 71S329 208 249 232 118 181 145 129 284 126 267 196 174 252 127 213" stroke="#faf0d4" stroke-width="13" stroke-linecap="round"/><path d="M70 210C40 90 128 45 217 71S329 208 249 232 118 181 145 129 284 126 267 196 174 252 127 213" stroke="#d6c5a3" stroke-width="13" stroke-dasharray="1 5"/><path d="m69 211-1 14m60-10 13 12" stroke="#af2433" stroke-width="11" stroke-linecap="round"/></svg>
<?php elseif($kind==='light'): ?><span class="light-orbit"></span><span class="light-orbit light-orbit-two"></span><span class="light-dot"></span><span class="light-dot light-dot-two"></span>
<?php else: ?>
<div class="scene-card scene-card-one"><?php card_back($index%2===1); ?></div>
<div class="scene-card scene-card-two"><?php if($kind==='blank'): ?><svg class="playing-card" viewBox="0 0 250 350"><rect x="1" y="1" width="248" height="348" rx="12" fill="#fffcf3" stroke="#d7d1c5"/></svg><?php else: playing_card($suits[$index%4],$index%3===0?7:1); endif; ?></div>
<?php if($kind==='packet'): ?><div class="scene-card scene-card-three"><?php card_back(); ?></div><?php endif; ?>
<?php endif; ?></div>
<?php }
function product_dialog(): void { ?>
<dialog class="product-dialog" id="product-dialog" aria-labelledby="dialog-name"><button class="dialog-close" type="button" aria-label="Close product details"><?=icon('close')?></button><div class="dialog-art" id="dialog-art"></div><div class="dialog-copy"><div class="dialog-heading"><h2 id="dialog-name"></h2><span id="dialog-price"></span></div><p id="dialog-description"></p><div class="dialog-browse" aria-label="Browse products"><span id="dialog-position" role="status" aria-live="polite"></span><div><button type="button" data-dialog-step="-1" aria-label="Previous product"><?=icon('arrow')?></button><button type="button" data-dialog-step="1" aria-label="Next product"><?=icon('arrow')?></button></div></div><p class="dialog-note">Visit the stall to see the product and check availability.</p><a class="button red" href="/#visit" id="dialog-visit">Find the stall <?=icon('arrow-up')?></a></div></dialog>
<?php }
