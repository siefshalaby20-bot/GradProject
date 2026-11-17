<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
$m = load_menu(); $s = $m['site'];
$cats = array_values(array_filter($m['categories'], fn($c) => !empty($c['items'])));
$cur = $s['currency'];
header('Cache-Control: public, max-age=60');
$v = filemtime(__DIR__ . '/style.css');
function img(?string $p, int $size, string $cls = ''): string {
    return $p ? '<img' . ($cls ? ' class="' . $cls . '"' : '') . ' src="/' . esc($p) . '" alt="" width="' . $size . '" height="' . $size . '" loading="lazy" decoding="async">' : '';
}
?>
<!doctype html>
<html lang="<?= esc($s['lang']) ?>" data-theme="dark"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= esc($s['title']) ?></title>
<meta name="description" content="<?= esc($s['description']) ?>">
<meta name="theme-color" content="#1d130e">
<link rel="canonical" href="<?= esc($s['url']) ?>/">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<meta property="og:type" content="website"><meta property="og:title" content="<?= esc($s['title']) ?>">
<meta property="og:description" content="<?= esc($s['description']) ?>"><meta property="og:url" content="<?= esc($s['url']) ?>/">
<meta property="og:image" content="<?= esc($s['url']) ?>/<?= esc($s['ogImage']) ?>"><meta name="twitter:card" content="summary_large_image">
<script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Allura&family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/style.css?v=<?= $v ?>">
</head>
<body>
<button class="theme" id="theme" type="button" aria-label="Toggle light / dark mode"></button>
<header class="hero"><div class="wordmark" aria-hidden="true">Tre<b>III</b></div><h1 class="sr"><?= esc($s['name']) ?></h1><p class="tagline"><?= esc($s['tagline']) ?></p><p class="explore">Explore Our Menu</p></header>
<nav class="cats" aria-label="Menu categories"><ul>
<?php foreach ($cats as $n => $c): ?><li><a href="#<?= esc($c['id']) ?>"<?= $n === 0 ? ' class="active"' : '' ?>><?= esc($c['name']) ?></a></li>
<?php endforeach ?></ul></nav>
<main>
<?php if (!$cats): ?><p class="desc" style="padding-top:40px;text-align:center">Menu coming soon.</p><?php endif ?>
<?php foreach ($cats as $c): ?>
<section id="<?= esc($c['id']) ?>"><h2><?= img($c['image'] ?? null, 44, 'cat-img') ?><?= esc($c['name']) ?></h2><ul class="items">
<?php foreach ($c['items'] as $i): $off = ($i['available'] ?? true) === false; ?>
<li class="item<?= $off ? ' off' : '' ?>"><?= img($i['image'] ?? null, 64) ?><div class="info"><div class="name"><?= esc($i['name']) ?><?php if (!empty($i['badge'])): ?><span class="badge"><?= esc($i['badge']) ?></span><?php endif ?></div><?php if (!empty($i['description'])): ?><p class="desc"><?= esc($i['description']) ?></p><?php endif ?></div><div class="price"><?= $off ? 'Unavailable' : fmt_price($i['price'] ?? null, $cur) ?></div></li>
<?php endforeach ?></ul></section>
<?php endforeach ?>
</main>
<footer><div class="wordmark" aria-hidden="true">Tre<b>III</b></div><p>© <?= date('Y') ?> <?= esc($s['name']) ?></p></footer>
<script defer src="/app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script></body></html>
