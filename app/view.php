<?php
declare(strict_types=1);

function icon(string $name, string $class = ''): string {
 $paths = [
  'arrow' => '<path d="M4 12h15M13 5l7 7-7 7"/>',
  'arrow-up' => '<path d="M6 18 18 6M6 6h12v12"/>',
  'chevron' => '<path d="m9 5 7 7-7 7"/>',
  'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  'play' => '<path d="m9 5 11 7-11 7Z"/>',
  'lock' => '<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
  'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  'spark' => '<path d="m12 2 2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5Z"/>',
  'menu' => '<path d="M4 8h16M4 16h16"/>',
  'check' => '<path d="m5 12 4 4L19 6"/>',
 ];
 return '<svg class="icon '.h($class).'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['spark']).'</svg>';
}
function site_assets(): void { ?>
<link rel="icon" href="/favicon.svg">
<link rel="preload" href="/assets/fonts/InstrumentSerif-Regular.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/assets/fonts/Manrope-Variable.ttf" as="font" type="font/ttf" crossorigin>
<link rel="stylesheet" href="/assets/site.css?v=<?=h(APP_VERSION)?>">
<noscript><link rel="stylesheet" href="/assets/nojs.css?v=<?=h(APP_VERSION)?>"></noscript>
<script src="/assets/site.js?v=<?=h(APP_VERSION)?>" defer></script>
<?php }
function brand(string $class=''): void { ?>
<a class="wordmark <?=h($class)?>" href="/" aria-label="Sleightly Tricky home"><span class="brand-symbol" aria-hidden="true">♦</span><span class="brand-name">Sleightly<br>Tricky<span class="brand-dot">.</span></span></a>
<?php }
function site_header(string $active=''): void { ?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="site-topline"><div class="wrap"><span>Independent magic. Extraordinary moments.</span><a href="/#visit"><?=icon('pin')?> Sudbury, Suffolk</a></div></div>
<header class="site-header"><div class="wrap header-inner">
<?php brand(); ?>
<nav class="main-nav" id="main-navigation" aria-label="Main navigation">
<a class="nav-link" href="/#story">Our story</a><a class="nav-link" href="/#magic">Magic tricks</a><a class="nav-link" href="/#cards">Playing cards</a><a class="nav-link" href="/instructions.php" <?=$active==='instructions'?'aria-current="page"':''?>>Instruction videos</a><a class="nav-visit" href="/#visit">Visit the stall <?=icon('arrow-up')?></a>
</nav>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Open menu"><span class="menu-lines" aria-hidden="true"></span><span class="menu-label">Menu</span></button>
</div></header>
<?php }
function site_footer(): void { ?>
<footer class="site-footer"><div class="wrap">
<div class="footer-invitation"><p class="eyebrow">A LITTLE LESS ORDINARY</p><p>Stay curious.<br><em>Wonderful things happen.</em></p><a class="footer-round-link" href="/#visit" aria-label="Come and visit the stall"><?=icon('arrow-up')?></a></div>
<div class="footer-main"><?php brand('footer-brand'); ?><p>Magic tricks to learn. Playing cards to collect.<br>A little wonder in Sudbury, Suffolk.</p><nav aria-label="Footer navigation"><a href="/#magic">The collection</a><a href="/instructions.php">Instruction videos</a><a href="/#visit">Find the stall</a></nav></div>
<div class="footer-bottom"><span>© <?=date('Y')?> Sleightly Tricky</span><span>Made for the curious.</span><a href="/admin/">Stallholder login <?=icon('arrow-up')?></a></div>
</div></footer>
<?php }
/** Decorative artwork only; real product photos always take precedence. */
function card_art(string $suit='♦', bool $dark=false): void { ?>
<div class="card-art <?=$dark?'card-art-dark':''?>" aria-hidden="true"><span class="art-orbit"></span><span class="art-card art-card-back"></span><span class="art-card art-card-face"><span class="card-corner">A<br><?=h($suit)?></span><span class="card-centre"><?=h($suit)?></span><span class="card-corner card-corner-bottom">A<br><?=h($suit)?></span></span><span class="art-spark">✧</span></div>
<?php }
function search_control(string $id, string $placeholder): void { ?>
<div class="collection-search" data-enhance hidden><label for="<?=h($id)?>" class="sr-only"><?=h($placeholder)?></label><?=icon('search')?><input type="search" id="<?=h($id)?>" placeholder="<?=h($placeholder)?>" autocomplete="off"><button type="button" class="search-clear" data-search-clear="<?=h($id)?>" aria-label="Clear search" hidden>×</button></div>
<?php }
function magic_art(array $product, string $suit): void {
 $kind = ['cups'=>'cups','rope'=>'rope','prisma'=>'light'][$product['id']]??null;
 if(!$kind){card_art($suit);return;}
 ?>
<div class="prop-art" aria-hidden="true"><span class="art-orbit"></span>
<svg viewBox="0 0 340 210" fill="none" xmlns="http://www.w3.org/2000/svg">
<?php if($kind==='cups'): ?>
<g stroke="#a18c6a" stroke-width="1.5" fill="#fffaf0"><path d="M58 69 46 140Q85 158 124 140L112 69Z"/><ellipse cx="85" cy="69" rx="27" ry="7"/><path d="M228 69 216 140Q255 158 294 140L282 69Z"/><ellipse cx="255" cy="69" rx="27" ry="7"/><path d="M143 89 131 161Q170 179 209 161L197 89Z"/><ellipse cx="170" cy="89" rx="27" ry="7"/><path d="m58 135 7-51m80 72 6-51m78 30 6-51" opacity=".4"/></g><g fill="#af2433"><circle cx="114" cy="169" r="8"/><circle cx="225" cy="169" r="8"/><circle cx="170" cy="47" r="8"/></g>
<?php elseif($kind==='rope'): ?>
<path d="M56 148C55 74 119 42 185 56c66 14 98 66 54 91-44 25-137 1-123-37s104-21 96 23-80 48-103 25" stroke="#bda67f" stroke-width="9" stroke-linecap="round"/><path d="M56 148C55 74 119 42 185 56c66 14 98 66 54 91-44 25-137 1-123-37s104-21 96 23-80 48-103 25" stroke="#fff8df" stroke-width="5" stroke-linecap="round"/><path d="m53 152-4 16m61-7 9 13" stroke="#af2433" stroke-width="5" stroke-linecap="round"/>
<?php else: ?>
<circle cx="170" cy="106" r="60" stroke="#cbbda2" stroke-dasharray="2 7"/><circle cx="170" cy="106" r="38" stroke="#af2433" opacity=".2"/><path d="m170 69 9 28 28 9-28 9-9 28-9-28-28-9 28-9Z" fill="#af2433" fill-opacity=".08" stroke="#af2433" stroke-width="1.4"/><path d="m102 47 3 10 10 3-10 3-3 10-3-10-10-3 10-3Zm132 85 3 10 10 3-10 3-3 10-3-10-10-3 10-3Z" stroke="#aa8d68"/>
<?php endif; ?>
</svg><span class="prop-caption">A LITTLE IMPOSSIBILITY.</span></div>
<?php }
