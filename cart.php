<?php
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('انتهت الجلسة، حاول مرة أخرى.', 'err');
        redirect('cart.php');
    }
    $action = (string)($_POST['action'] ?? '');
    $id = (string)($_POST['id'] ?? '');
    $p = find_product($id);
    $cart = cart();
    if ($action === 'add' && $p && !empty($p['active']) && $p['stock'] > 0) {
        $add = max(1, (int)($_POST['qty'] ?? 1));
        $cart[$id] = min((int)$p['stock'], ($cart[$id] ?? 0) + $add);
        $_SESSION['cart'] = $cart;
        flash('أضيف للسلة: ' . $p['name']);
        $ref = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $sameHost = !empty($ref['host']) && ($ref['host'] . (isset($ref['port']) ? ':' . $ref['port'] : '')) === ($_SERVER['HTTP_HOST'] ?? '');
        if ($sameHost && basename($ref['path'] ?? '') === 'index.php') {
            redirect('index.php' . (!empty($ref['query']) ? '?' . $ref['query'] : ''));
        }
        redirect('cart.php');
    }
    if ($action === 'set' && $p) {
        $q = (int)($_POST['qty'] ?? 0);
        if ($q <= 0) unset($cart[$id]); else $cart[$id] = min($q, (int)$p['stock']);
        $_SESSION['cart'] = $cart;
    }
    if ($action === 'remove') {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;
    }
    redirect('cart.php');
}

$t = cart_totals();
$cust = $_SESSION['cust'] ?? ['name' => '', 'phone' => '', 'addr' => '', 'note' => ''];
page_head('السلة | ' . cfg('store_name'));
?>
<h1 style="padding-top:16px;font-size:26px">سلة المشتريات</h1>
<?php if (!$t['lines']): ?>
  <div class="panel empty" style="grid-column:auto">السلة فارغة. <a href="index.php">تصفح المنتجات</a></div>
<?php else: ?>
<div class="panel">
  <?php foreach ($t['lines'] as $l): $p = $l['p']; ?>
  <div class="line">
    <div class="th"><?php if (!empty($p['img'])): ?><img src="uploads/<?= e($p['img']) ?>" alt=""><?php else: ?><?= e(mb_substr($p['name'], 0, 8)) ?><?php endif; ?></div>
    <div><b><?= e($p['name']) ?></b><div class="hint num"><?= money($p['price']) ?> للقطعة | <?= money($l['t']) ?></div></div>
    <div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <input type="number" name="qty" value="<?= (int)$l['q'] ?>" min="0" max="<?= (int)$p['stock'] ?>" aria-label="الكمية">
        <button class="btn sm" type="submit">تحديث</button>
      </form>
      <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button class="btn sm danger" type="submit">حذف</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="sum num">
    <div><span>المنتجات</span><span><?= money($t['sub']) ?></span></div>
    <div><span>الشحن</span><span><?= $t['ship'] ? money($t['ship']) : 'مجاني' ?></span></div>
    <div class="total"><span>الإجمالي</span><span><?= money($t['total']) ?></span></div>
  </div>
</div>

<form class="panel" method="post" action="checkout.php">
  <h2 style="font-size:20px;margin-bottom:10px">بيانات الشحن</h2>
  <?= csrf_field() ?>
  <label class="field">الاسم<input name="name" required autocomplete="name" value="<?= e($cust['name']) ?>"></label>
  <label class="field">رقم الهاتف<input name="phone" required inputmode="tel" autocomplete="tel" value="<?= e($cust['phone']) ?>"></label>
  <label class="field">العنوان بالتفصيل<textarea name="addr" required autocomplete="street-address"><?= e($cust['addr']) ?></textarea></label>
  <label class="field">ملاحظات (اختياري)<input name="note" value="<?= e($cust['note']) ?>"></label>
  <button class="btn primary block" type="submit">تأكيد الطلب وإرساله على واتساب</button>
  <p class="hint">الدفع عند الاستلام. نؤكد معك الطلب قبل الشحن.</p>
</form>
<?php endif;
page_foot();
