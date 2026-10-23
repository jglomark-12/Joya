<?php
declare(strict_types=1);

define('ROOT', __DIR__);
define('DATA', ROOT . '/data');
define('UPLOADS', ROOT . '/uploads');
$GLOBALS['CFG'] = require ROOT . '/config.php';

function cfg(string $k)
{
    return $GLOBALS['CFG'][$k] ?? null;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($n): string
{
    return number_format((float)$n, 0, '.', ',') . ' ' . cfg('currency');
}

/* ---------- JSON storage with locking ---------- */
function jread(string $name): array
{
    $f = DATA . "/$name.json";
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function jmodify(string $name, callable $fn)
{
    $f = DATA . "/$name.json";
    $h = fopen($f, 'c+');
    if (!$h) throw new RuntimeException('cannot open data file');
    flock($h, LOCK_EX);
    $raw = stream_get_contents($h);
    $d = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
    if (!is_array($d)) $d = [];
    $res = $fn($d);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $res;
}

function products(bool $onlyActive = true): array
{
    $all = jread('products');
    return $onlyActive ? array_values(array_filter($all, fn($p) => !empty($p['active']))) : $all;
}

function find_product(string $id): ?array
{
    foreach (jread('products') as $p) if ($p['id'] === $id) return $p;
    return null;
}

function hue_for(int $i): int
{
    return [350, 32, 150, 200, 265, 310][$i % 6];
}

function new_id(string $prefix = ''): string
{
    return $prefix . bin2hex(random_bytes(5));
}

/* ---------- CSRF, flash ---------- */
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_ok(): bool
{
    return !empty($_SESSION['csrf']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['m' => $msg, 't' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function site_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

/* ---------- cart ---------- */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_lines(): array
{
    $lines = [];
    foreach (cart() as $id => $q) {
        $p = find_product((string)$id);
        if ($p && !empty($p['active']) && $p['stock'] > 0) {
            $q = min((int)$q, (int)$p['stock']);
            if ($q > 0) $lines[] = ['p' => $p, 'q' => $q, 't' => $p['price'] * $q];
        }
    }
    return $lines;
}

function cart_totals(): array
{
    $lines = cart_lines();
    $sub = array_sum(array_column($lines, 't'));
    $ship = $sub <= 0 ? 0 : ($sub >= cfg('free_from') ? 0 : cfg('shipping'));
    return ['lines' => $lines, 'sub' => $sub, 'ship' => $ship, 'total' => $sub + $ship,
            'count' => array_sum(array_column($lines, 'q'))];
}

function order_text(array $o): string
{
    $l = ['طلب جديد رقم ' . strtoupper(substr($o['id'], 0, 6)) . ' من ' . cfg('store_name'), ''];
    foreach ($o['items'] as $i) $l[] = '- ' . $i['name'] . ' × ' . $i['qty'] . ' = ' . money($i['price'] * $i['qty']);
    $l[] = '';
    $l[] = 'المنتجات: ' . money($o['sub']);
    $l[] = 'الشحن: ' . ($o['ship'] ? money($o['ship']) : 'مجاني');
    $l[] = 'الإجمالي: ' . money($o['total']);
    $l[] = '';
    $l[] = 'الاسم: ' . $o['name'];
    $l[] = 'الهاتف: ' . $o['phone'];
    $l[] = 'العنوان: ' . $o['addr'];
    if (!empty($o['note'])) $l[] = 'ملاحظات: ' . $o['note'];
    $l[] = 'الدفع: عند الاستلام';
    return implode("\n", $l);
}

function wa_link(string $text): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', (string)cfg('whatsapp')) . '?text=' . rawurlencode($text);
}

/* ---------- uploads ---------- */
function save_upload(array $f): ?string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('تعذر رفع الصورة، جرّب صورة أصغر.');
    if ($f['size'] > 10 * 1024 * 1024) throw new RuntimeException('الصورة أكبر من 10 ميجابايت.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info) throw new RuntimeException('الملف ليس صورة.');
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($types[$info[2]])) throw new RuntimeException('الصيغ المسموحة: JPG وPNG وWEBP.');
    if (!is_dir(UPLOADS)) mkdir(UPLOADS, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.jpg';
    $dest = UPLOADS . '/' . $name;
    $src = null;
    if (function_exists('imagecreatetruecolor')) {
        $src = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($f['tmp_name'])
            : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromwebp($f['tmp_name']));
    }
    if ($src) {
        $w = imagesx($src); $h = imagesy($src);
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $ex = @exif_read_data($f['tmp_name']);
            $o = $ex['Orientation'] ?? 1;
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) { $src = imagerotate($src, $rot, 0); $w = imagesx($src); $h = imagesy($src); }
        }
        $max = 1000; $s = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($dst, $dest, 82);
        return $name;
    }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], UPLOADS . '/' . $name)) throw new RuntimeException('تعذر حفظ الصورة.');
    return $name;
}

function delete_upload(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $name)) @unlink(UPLOADS . '/' . $name);
}

/* ---------- storefront layout ---------- */
function page_head(string $title, string $root = ''): void
{
    $count = cart_totals()['count'];
    ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= $root ?>assets/style.css">
</head>
<body>
<div class="wrap">
<header class="top">
  <a class="logo" href="<?= $root ?>index.php"><b>Joya</b> store <small><?= e(cfg('store_ar')) ?></small></a>
  <a class="btn" href="<?= $root ?>cart.php">السلة <span class="badge num"><?= (int)$count ?></span></a>
</header>
<?php if ($f = flash()): ?><div class="flash <?= $f['t'] === 'err' ? 'err' : '' ?>" role="status"><?= e($f['m']) ?></div><?php endif;
}

function page_foot(): void
{
    ?>
<div class="foot"><span>© <?= e(cfg('store_name')) ?> | الدفع عند الاستلام</span><span>الأسعار بالجنيه المصري</span></div>
</div>
</body>
</html>
<?php
}

function product_card(array $p, int $i): void
{
    $out = $p['stock'] <= 0;
    ?>
<article class="card" style="--hue:<?= hue_for($i) ?>">
  <a class="pic" href="product.php?id=<?= e($p['id']) ?>">
    <?php if (!empty($p['img'])): ?><img src="uploads/<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="300" height="300"><?php else: ?><span class="noimg"><?= e($p['name']) ?></span><?php endif; ?>
    <?php if ($out): ?><span class="tag out">نفد</span><?php elseif ($p['stock'] <= 3): ?><span class="tag">باقي <?= (int)$p['stock'] ?></span><?php endif; ?>
  </a>
  <div class="cb">
    <h3><a href="product.php?id=<?= e($p['id']) ?>"><?= e($p['name']) ?></a></h3>
    <div class="price num"><?= money($p['price']) ?><?php if (!empty($p['was'])): ?><s><?= money($p['was']) ?></s><?php endif; ?></div>
    <form method="post" action="cart.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <button class="btn sm primary" type="submit"<?= $out ? ' disabled' : '' ?>><?= $out ? 'غير متاح' : 'أضف للسلة' ?></button>
    </form>
  </div>
</article>
<?php
}
