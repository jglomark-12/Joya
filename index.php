<?php
require __DIR__ . '/lib.php';

$all = products();
$cats = array_values(array_unique(array_column($all, 'cat')));
$cat = (string)($_GET['cat'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));

$list = [];
foreach ($all as $i => $p) {
    if ($cat !== '' && $p['cat'] !== $cat) continue;
    if ($q !== '' && mb_stripos($p['name'] . ' ' . $p['desc'], $q) === false) continue;
    $list[] = [$p, $i];
}

page_head(cfg('store_name'));
?>
<section class="hero">
  <h1>هدايا وإكسسوارات تصلك لحد الباب</h1>
  <p>اختر منتجاتك وأرسل الطلب على واتساب، والدفع عند الاستلام.</p>
  <div class="perks">
    <span class="perk">الدفع عند الاستلام</span>
    <span class="perk num">شحن <?= money(cfg('shipping')) ?></span>
    <span class="perk num">شحن مجاني فوق <?= money(cfg('free_from')) ?></span>
  </div>
</section>

<div class="tools">
  <form method="get" action="index.php">
    <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="ابحث عن منتج" aria-label="ابحث عن منتج">
    <button class="btn" type="submit">بحث</button>
  </form>
  <nav class="chips" aria-label="الأقسام">
    <a class="chip <?= $cat === '' ? 'on' : '' ?>" href="index.php">الكل</a>
    <?php foreach ($cats as $c): ?><a class="chip <?= $cat === $c ? 'on' : '' ?>" href="index.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a><?php endforeach; ?>
  </nav>
</div>

<div class="grid">
  <?php if (!$list): ?><div class="empty">لا توجد منتجات مطابقة.</div><?php endif; ?>
  <?php foreach ($list as [$p, $i]) product_card($p, $i); ?>
</div>

<section class="steps" aria-label="طريقة الطلب">
  <div class="step"><b>1. اختر</b><span>أضف المنتجات للسلة.</span></div>
  <div class="step"><b>2. أرسل الطلب</b><span>اكتب بياناتك وتصلنا التفاصيل على واتساب.</span></div>
  <div class="step"><b>3. استلم وادفع</b><span>نؤكد الطلب ونشحنه، والدفع عند الاستلام.</span></div>
</section>

<?php $url = site_url(); $tx = rawurlencode('تسوق من ' . cfg('store_name')); ?>
<div class="share">
  <b>شارك المتجر</b>
  <a class="btn sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $tx ?>%20<?= rawurlencode($url) ?>">واتساب</a>
  <a class="btn sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($url) ?>">فيسبوك</a>
  <a class="btn sm" target="_blank" rel="noopener" href="https://t.me/share/url?url=<?= rawurlencode($url) ?>&text=<?= $tx ?>">تيليجرام</a>
</div>
<?php page_foot();
