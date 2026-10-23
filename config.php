<?php
// إعدادات المتجر. عدّل هذا الملف فقط.
return [
    'store_name'  => 'Joya store',
    'store_ar'    => 'جويا ستور',
    'whatsapp'    => '201111741062',   // رقم الواتساب بصيغة دولية بدون +
    'currency'    => 'ج.م',
    'shipping'    => 50,               // رسوم الشحن
    'free_from'   => 1000,             // شحن مجاني للطلبات فوق هذا المبلغ
    // كلمة سر لوحة التحكم (مشفرة). لتغييرها شغّل: php -r "echo password_hash('كلمتك', PASSWORD_DEFAULT);"
    'admin_hash'  => '$2y$10$aLZleIxiTweoqmpMNfitN.Pn7SDx34mJGcgGomNgsh6xE73aMTX6e',
];
