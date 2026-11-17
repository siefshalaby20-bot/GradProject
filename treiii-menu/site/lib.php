<?php
declare(strict_types=1);
const ROOT = __DIR__;

function esc($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function load_menu(): array {
    $j = @file_get_contents(ROOT . '/data/menu.json');
    $m = $j ? json_decode($j, true) : null;
    if (!is_array($m)) $m = [];
    $m['site'] = ($m['site'] ?? []) + ['name' => 'TreIII', 'tagline' => '', 'title' => 'TreIII — Café Menu', 'description' => '', 'url' => 'https://treiii.online', 'lang' => 'en', 'currency' => 'EGP', 'ogImage' => 'assets/og.png'];
    $m['categories'] = $m['categories'] ?? [];
    return $m;
}

function save_menu(array $m): bool {
    $p = ROOT . '/data/menu.json';
    $lock = fopen(ROOT . '/data/.lock', 'c');
    if (!$lock) return false;
    flock($lock, LOCK_EX);
    $tmp = $p . '.tmp';
    $ok = file_put_contents($tmp, json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false && rename($tmp, $p);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

function new_id(string $prefix): string { return $prefix . bin2hex(random_bytes(4)); }

function fmt_price($p, string $cur): string {
    if ($p === null || $p === '') return '—';
    $n = (float)$p;
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') . '<small>' . esc($cur) . '</small>';
}

/** Validate, resize (max 900px) and store an uploaded image. Returns relative path or throws. */
function save_image(array $f): string {
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed (code ' . $f['error'] . ').');
    if ($f['size'] > 8 * 1024 * 1024) throw new RuntimeException('Image is larger than 8 MB.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Only JPG, PNG or WebP images are allowed.');
    $name = bin2hex(random_bytes(8));
    $dir = ROOT . '/uploads/';
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if (!$img) throw new RuntimeException('Could not read the image.');
        [$w, $h] = [imagesx($img), imagesy($img)];
        $max = 900;
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $img = $dst;
        }
        if (function_exists('imagewebp')) { imagesavealpha($img, true); $ok = imagewebp($img, $dir . $name . '.webp', 82); $rel = 'uploads/' . $name . '.webp'; }
        else { $ok = imagejpeg($img, $dir . $name . '.jpg', 85); $rel = 'uploads/' . $name . '.jpg'; }
        if (!$ok) throw new RuntimeException('Could not save the image.');
        return $rel;
    }
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name . '.' . $ext)) throw new RuntimeException('Could not save the image.');
    return 'uploads/' . $name . '.' . $ext;
}

function delete_image(?string $rel): void {
    if ($rel && preg_match('#^uploads/[a-f0-9]+\.(webp|jpg|png)$#', $rel)) @unlink(ROOT . '/' . $rel);
}
