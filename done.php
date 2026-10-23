<?php
require __DIR__ . '/lib.php';

$id = preg_replace('/[^a-f0-9]/', '', (string)($_GET['o'] ?? ''));
$order = null;
foreach (jread('orders') as $o) if ($o['id'] === $id) $order = $o;
if (!$order) redirect('index.php');

page_head('تم استلام طلبك | ' . cfg('store_name'));
?>
<section class="panel" style="text-align:center">
  <h1 style="font-size:26px">تم تسجيل طلبك</h1>
  <p class="hint">رقم الطلب <b class="num"><?= e(strtoupper(substr($order['id'], 0, 6))) ?></b>. أرسل التفاصيل الآن على واتساب لنؤكد الطلب بسرعة.</p>
  <a class="btn primary block" target="_blank" rel="noopener" href="<?= e(wa_link(order_text($order))) ?>">أرسل الطلب على واتساب</a>
</section>
<div class="panel">
  <?php foreach ($order['items'] as $i): ?>
    <div class="sum num"><div><span><?= e($i['name']) ?> × <?= (int)$i['qty'] ?></span><span><?= money($i['price'] * $i['qty']) ?></span></div></div>
  <?php endforeach; ?>
  <div class="sum num"><div><span>الشحن</span><span><?= $order['ship'] ? money($order['ship']) : 'مجاني' ?></span></div><div class="total"><span>الإجمالي</span><span><?= money($order['total']) ?></span></div></div>
</div>
<a class="btn block" href="index.php">متابعة التسوق</a>
<?php page_foot();
