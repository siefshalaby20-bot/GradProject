<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
$authFile = ROOT . '/data/auth.json';
$hash = is_file($authFile) ? (json_decode((string)file_get_contents($authFile), true)['hash'] ?? null) : null;

function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function back(string $q = ''): never { header('Location: /admin/' . $q); exit; }
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'login') {
        if ($hash && password_verify((string)($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true); $_SESSION['auth'] = true; $_SESSION['csrf'] = bin2hex(random_bytes(16)); back();
        }
        usleep(900000); $err = 'Wrong password.';
    } elseif (!empty($_SESSION['auth']) && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        try {
            $m = load_menu();
            $find = function (string $id) use (&$m) { foreach ($m['categories'] as $ci => $c) foreach ($c['items'] as $ii => $i) if ($i['id'] === $id) return [$ci, $ii]; return null; };
            $cidx = function (string $id) use (&$m) { foreach ($m['categories'] as $ci => $c) if ($c['id'] === $id) return $ci; return null; };
            $upload = fn() => (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) ? save_image($_FILES['image']) : null;
            switch ($act) {
                case 'logout': session_destroy(); back();
                case 'save_category':
                    $name = trim((string)($_POST['name'] ?? '')); if ($name === '') throw new RuntimeException('Category name is required.');
                    $id = (string)($_POST['id'] ?? ''); $ci = $id !== '' ? $cidx($id) : null; $new = $upload();
                    if ($ci === null) { $m['categories'][] = ['id' => new_id('c'), 'name' => $name, 'image' => $new, 'items' => []]; flash('ok', 'Category added.'); }
                    else {
                        $c = &$m['categories'][$ci]; $c['name'] = $name;
                        if ($new || !empty($_POST['remove_image'])) { delete_image($c['image'] ?? null); $c['image'] = $new; }
                        flash('ok', 'Category saved.');
                    }
                    save_menu($m) || throw new RuntimeException('Could not write menu data.'); back('?tab=categories');
                case 'delete_category':
                    $ci = $cidx((string)$_POST['id']); if ($ci !== null) {
                        foreach ($m['categories'][$ci]['items'] as $i) delete_image($i['image'] ?? null);
                        delete_image($m['categories'][$ci]['image'] ?? null); array_splice($m['categories'], $ci, 1); save_menu($m); flash('ok', 'Category deleted.');
                    } back('?tab=categories');
                case 'move_category':
                    $ci = $cidx((string)$_POST['id']); $to = $ci + ($_POST['dir'] === 'up' ? -1 : 1);
                    if ($ci !== null && isset($m['categories'][$to])) { [$m['categories'][$ci], $m['categories'][$to]] = [$m['categories'][$to], $m['categories'][$ci]]; save_menu($m); } back('?tab=categories');
                case 'save_product':
                    $name = trim((string)($_POST['name'] ?? '')); if ($name === '') throw new RuntimeException('Product name is required.');
                    $pr = trim(str_replace(',', '.', (string)($_POST['price'] ?? '')));
                    if ($pr !== '' && (!is_numeric($pr) || (float)$pr < 0)) throw new RuntimeException('Price must be a number.');
                    $cat = $cidx((string)($_POST['category'] ?? '')); if ($cat === null) throw new RuntimeException('Choose a category (add one first).');
                    $item = ['name' => $name, 'description' => trim((string)($_POST['description'] ?? '')), 'price' => $pr === '' ? null : (float)$pr + 0, 'available' => !empty($_POST['available']), 'badge' => ($_POST['badge'] ?? '') ?: null];
                    $id = (string)($_POST['id'] ?? ''); $new = $upload(); $at = $id !== '' ? $find($id) : null;
                    if ($at === null) { $item['id'] = new_id('p'); $item['image'] = $new; $m['categories'][$cat]['items'][] = $item; flash('ok', 'Product added.'); }
                    else {
                        $old = $m['categories'][$at[0]]['items'][$at[1]]; $item['id'] = $id; $item['image'] = $old['image'] ?? null;
                        if ($new || !empty($_POST['remove_image'])) { delete_image($item['image']); $item['image'] = $new; }
                        if ($at[0] === $cat) $m['categories'][$cat]['items'][$at[1]] = $item;
                        else { array_splice($m['categories'][$at[0]]['items'], $at[1], 1); $m['categories'][$cat]['items'][] = $item; }
                        flash('ok', 'Product saved.');
                    }
                    save_menu($m) || throw new RuntimeException('Could not write menu data.'); back('?cat=' . urlencode($m['categories'][$cat]['id']));
                case 'delete_product':
                    $at = $find((string)$_POST['id']); if ($at) { delete_image($m['categories'][$at[0]]['items'][$at[1]]['image'] ?? null); array_splice($m['categories'][$at[0]]['items'], $at[1], 1); save_menu($m); flash('ok', 'Product deleted.'); } back();
                case 'password':
                    $n = (string)($_POST['new'] ?? '');
                    if (!password_verify((string)($_POST['current'] ?? ''), (string)$hash)) throw new RuntimeException('Current password is wrong.');
                    if (strlen($n) < 8 || $n !== ($_POST['confirm'] ?? '')) throw new RuntimeException('New password must be 8+ characters and match the confirmation.');
                    file_put_contents($authFile, json_encode(['hash' => password_hash($n, PASSWORD_DEFAULT)])); flash('ok', 'Password changed.'); back('?tab=settings');
            }
        } catch (RuntimeException $e) { flash('err', $e->getMessage()); back($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''); }
    } else { flash('err', 'Session expired. Please log in again.'); session_destroy(); back(); }
}
$authed = !empty($_SESSION['auth']);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$csrf = $_SESSION['csrf'];
?>
<!doctype html><html lang="en" data-theme="auto"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>TreIII Dashboard</title><link rel="icon" href="/favicon.svg">
<script>try{var t=localStorage.getItem('adm-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>"></head><body>
<?php if (!$authed): ?>
<main class="login"><form method="post" class="card"><h1>TreIII</h1><p class="muted">Dashboard login</p>
<?php if ($flash || $err): ?><div class="msg err"><?= esc($err ?? $flash[1]) ?></div><?php endif ?>
<?php if (!$hash): ?><div class="msg err">Admin password is not set up on the server yet.</div><?php endif ?>
<input type="hidden" name="action" value="login"><label>Password<input type="password" name="password" autocomplete="current-password" required autofocus></label>
<button class="btn primary" type="submit">Log in</button></form></main>
<?php else:
$m = load_menu(); $cats = $m['categories']; $cur = $m['site']['currency'];
$tab = in_array($_GET['tab'] ?? '', ['categories', 'settings'], true) ? $_GET['tab'] : 'products';
$editP = null; $editPCat = null; $editC = null;
if (!empty($_GET['edit_p'])) foreach ($cats as $c) foreach ($c['items'] as $i) if ($i['id'] === $_GET['edit_p']) { $editP = $i; $editPCat = $c['id']; }
if (!empty($_GET['edit_c'])) foreach ($cats as $c) if ($c['id'] === $_GET['edit_c']) $editC = $c;
$selCat = $editPCat ?? ($_GET['cat'] ?? ($cats[0]['id'] ?? ''));
$thumb = fn($p) => $p ? '<img class="thumb" src="/' . esc($p) . '" alt="">' : '<span class="thumb ph"></span>';
$tok = '<input type="hidden" name="csrf" value="' . esc($csrf) . '">';
?>
<header class="bar"><strong>TreIII <span class="muted">Dashboard</span></strong><span class="sp"></span>
<a class="btn ghost" href="/" target="_blank" rel="noopener">View menu ↗</a>
<button class="btn ghost" id="theme" type="button" aria-label="Toggle theme">Dark / Light</button>
<form method="post"><?= $tok ?><input type="hidden" name="action" value="logout"><button class="btn ghost">Log out</button></form></header>
<nav class="tabs"><a href="?tab=products" class="<?= $tab === 'products' ? 'on' : '' ?>">Products</a><a href="?tab=categories" class="<?= $tab === 'categories' ? 'on' : '' ?>">Categories</a><a href="?tab=settings" class="<?= $tab === 'settings' ? 'on' : '' ?>">Password</a></nav>
<main class="wrap">
<?php if ($flash): ?><div class="msg <?= esc($flash[0]) ?>"><?= esc($flash[1]) ?></div><?php endif ?>

<?php if ($tab === 'products'): ?>
<form method="post" enctype="multipart/form-data" class="card" id="pform"><?= $tok ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= esc($editP['id'] ?? '') ?>">
<h2><?= $editP ? 'Edit product' : 'Add product' ?></h2>
<?php if (!$cats): ?><p class="msg err">Add a category first (Categories tab).</p><?php endif ?>
<div class="grid">
<label>Product name<input name="name" required maxlength="80" value="<?= esc($editP['name'] ?? '') ?>"></label>
<label>Price (<?= esc($cur) ?>)<input name="price" inputmode="decimal" placeholder="e.g. 65" value="<?= esc($editP['price'] ?? '') ?>"></label>
<label>Category<select name="category"><?php foreach ($cats as $c): ?><option value="<?= esc($c['id']) ?>" <?= $c['id'] === $selCat ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?></select></label>
<label>Badge<select name="badge"><?php foreach (['' => 'None', 'Best Seller' => 'Best Seller', 'New' => 'New', 'Recommended' => 'Recommended'] as $k => $v): ?><option value="<?= esc($k) ?>" <?= ($editP['badge'] ?? '') === $k ? 'selected' : '' ?>><?= esc($v) ?></option><?php endforeach ?></select></label>
</div>
<label>Description (optional)<input name="description" maxlength="160" value="<?= esc($editP['description'] ?? '') ?>"></label>
<div class="imgrow"><div id="prev"><?= $thumb($editP['image'] ?? null) ?></div>
<label>Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-prev="prev"></label></div>
<?php if (!empty($editP['image'])): ?><label class="chk"><input type="checkbox" name="remove_image" value="1"> Remove current image</label><?php endif ?>
<label class="chk"><input type="checkbox" name="available" value="1" <?= ($editP['available'] ?? true) ? 'checked' : '' ?>> Available</label>
<div class="row"><button class="btn primary" type="submit"><?= $editP ? 'Save changes' : 'Add product' ?></button><?php if ($editP): ?><a class="btn ghost" href="?tab=products">Cancel</a><?php endif ?></div>
</form>
<?php foreach ($cats as $c): ?><section class="card"><h3><?= esc($c['name']) ?> <span class="muted">(<?= count($c['items']) ?>)</span></h3>
<?php if (!$c['items']): ?><p class="muted">No products yet.</p><?php endif ?>
<?php foreach ($c['items'] as $i): ?><div class="li<?= ($i['available'] ?? true) ? '' : ' off' ?>"><?= $thumb($i['image'] ?? null) ?><div class="grow"><b><?= esc($i['name']) ?></b><div class="muted"><?= ($i['price'] ?? null) === null ? 'No price' : esc($i['price'] . ' ' . $cur) ?><?= ($i['available'] ?? true) ? '' : ' · Unavailable' ?></div></div>
<a class="btn" href="?edit_p=<?= esc($i['id']) ?>#pform">Edit</a>
<form method="post" data-confirm="Delete “<?= esc($i['name']) ?>”?"><?= $tok ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= esc($i['id']) ?>"><button class="btn danger">Delete</button></form></div>
<?php endforeach ?></section><?php endforeach ?>

<?php elseif ($tab === 'categories'): ?>
<form method="post" enctype="multipart/form-data" class="card"><?= $tok ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= esc($editC['id'] ?? '') ?>">
<h2><?= $editC ? 'Edit category' : 'Add category' ?></h2>
<label>Category name<input name="name" required maxlength="60" value="<?= esc($editC['name'] ?? '') ?>"></label>
<div class="imgrow"><div id="prev"><?= $thumb($editC['image'] ?? null) ?></div><label>Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-prev="prev"></label></div>
<?php if (!empty($editC['image'])): ?><label class="chk"><input type="checkbox" name="remove_image" value="1"> Remove current image</label><?php endif ?>
<div class="row"><button class="btn primary"><?= $editC ? 'Save changes' : 'Add category' ?></button><?php if ($editC): ?><a class="btn ghost" href="?tab=categories">Cancel</a><?php endif ?></div></form>
<section class="card"><h3>Categories <span class="muted">(order = order on the menu)</span></h3>
<?php foreach ($cats as $n => $c): ?><div class="li"><?= $thumb($c['image'] ?? null) ?><div class="grow"><b><?= esc($c['name']) ?></b><div class="muted"><?= count($c['items']) ?> products</div></div>
<form method="post"><?= $tok ?><input type="hidden" name="action" value="move_category"><input type="hidden" name="id" value="<?= esc($c['id']) ?>"><button class="btn" name="dir" value="up" <?= $n === 0 ? 'disabled' : '' ?> aria-label="Move up">↑</button><button class="btn" name="dir" value="down" <?= $n === count($cats) - 1 ? 'disabled' : '' ?> aria-label="Move down">↓</button></form>
<a class="btn" href="?tab=categories&edit_c=<?= esc($c['id']) ?>">Edit</a>
<form method="post" data-confirm="Delete “<?= esc($c['name']) ?>” and its <?= count($c['items']) ?> products?"><?= $tok ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= esc($c['id']) ?>"><button class="btn danger">Delete</button></form></div>
<?php endforeach ?></section>

<?php else: ?>
<form method="post" class="card"><?= $tok ?><input type="hidden" name="action" value="password"><h2>Change password</h2>
<label>Current password<input type="password" name="current" autocomplete="current-password" required></label>
<label>New password (8+ characters)<input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
<label>Confirm new password<input type="password" name="confirm" autocomplete="new-password" minlength="8" required></label>
<button class="btn primary">Change password</button></form>
<?php endif ?>
</main>
<script>
document.getElementById('theme').onclick=function(){var d=document.documentElement,dark=d.dataset.theme==='dark'||(d.dataset.theme==='auto'&&matchMedia('(prefers-color-scheme:dark)').matches),n=dark?'light':'dark';d.dataset.theme=n;try{localStorage.setItem('adm-theme',n)}catch(e){}};
document.querySelectorAll('form[data-confirm]').forEach(function(f){f.onsubmit=function(e){if(!confirm(f.dataset.confirm))e.preventDefault()}});
document.querySelectorAll('input[type=file][data-prev]').forEach(function(i){i.onchange=function(){var f=i.files[0];if(f)document.getElementById(i.dataset.prev).innerHTML='<img class="thumb" src="'+URL.createObjectURL(f)+'" alt="">'}});
</script>
<?php endif ?>
</body></html>
