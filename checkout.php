<?php
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    flash('انتهت الجلسة، حاول مرة أخرى.', 'err');
    redirect('cart.php');
}

$cust = [
    'name'  => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80),
    'phone' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 20),
    'addr'  => mb_substr(trim((string)($_POST['addr'] ?? '')), 0, 300),
    'note'  => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200),
];
$_SESSION['cust'] = $cust;

if ($cust['name'] === '' || $cust['addr'] === '' || strlen(preg_replace('/\D/', '', $cust['phone'])) < 10) {
    flash('اكتب الاسم ورقم هاتف صحيح والعنوان.', 'err');
    redirect('cart.php');
}

$t = cart_totals();
if (!$t['lines']) {
    flash('السلة فارغة.', 'err');
    redirect('cart.php');
}

$order = [
    'id' => bin2hex(random_bytes(8)),
    'at' => date('c'),
    'status' => 'new',
    'name' => $cust['name'], 'phone' => $cust['phone'], 'addr' => $cust['addr'], 'note' => $cust['note'],
    'items' => array_map(fn($l) => ['pid' => $l['p']['id'], 'name' => $l['p']['name'], 'price' => $l['p']['price'], 'qty' => $l['q']], $t['lines']),
    'sub' => $t['sub'], 'ship' => $t['ship'], 'total' => $t['total'],
];
jmodify('orders', function (array &$d) use ($order) { $d[] = $order; });
$_SESSION['cart'] = [];
redirect('done.php?o=' . $order['id']);
