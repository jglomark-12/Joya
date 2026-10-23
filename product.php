<?php
require __DIR__ . '/lib.php';

$id = (string)($_GET['id'] ?? '');
$p = find_product($id);
if (!$p || empty($p['active'])) {
    http_response_code(404);
    page_head('المنتج غير موجود');
    echo '<div class="empty">هذا المنتج غير متاح. <a href="index.php">العودة للمتجر</a></div>';
    page_foot();
    exit;
}
$idx = 0;
foreach (products() as $i => $x) if ($x['id'] === $id) $idx = $i;
$out = $p['stock'] <= 0;

page_head($p['name'] . ' | ' . cfg('store_name'));
?>
<div class="crumb"><a href="index.php">المتجر</a> / <a href="index.php?cat=<?= urlencode($p['cat']) ?>"><?= e($p['cat']) ?></a></div>
<article class="pp" style="--hue:<?= hue_for($idx) ?>">
  <div class="pic card" style="animation:none">
    <?php if (!empty($p['img'])): ?><img src="uploads/<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>" width="800" height="800"><?php else: ?><span class="noimg"><?= e($p['name']) ?></span><?php endif; ?>
  </div>
  <div>
    <h1><?= e($p['name']) ?></h1>
    <div class="price num"><?= money($p['price']) ?><?php if (!empty($p['was'])): ?><s><?= money($p['was']) ?></s><?php endif; ?></div>
    <p><?= nl2br(e($p['desc'])) ?></p>
    <p class="hint"><?= $out ? 'نفد من المخزون' : ($p['stock'] <= 3 ? 'باقي ' . (int)$p['stock'] . ' فقط' : 'متوفر') ?></p>
    <form method="post" action="cart.php" class="qtyrow">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>" aria-label="الكمية"<?= $out ? ' disabled' : '' ?>>
      <button class="btn primary" type="submit" style="flex:1"<?= $out ? ' disabled' : '' ?>><?= $out ? 'غير متاح' : 'أضف للسلة' ?></button>
    </form>
  </div>
</article>
<?php page_foot();
