<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ملف اختبار محاكاة لمتجر سلة/زد
$url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/create_order.php';

// البيانات التي يرسلها المتجر عادةً
$data = [
    'api_key' => 'tkp_338188bacc307bc0fd420d8ef07c23d1faa1ef5969dc8310', // <--- كانت الفاصلة ناقصة هنا
    'order_id' => 'SALLA-1001',
    'customer_name' => 'خالد العميل',
    'customer_phone' => '0500000000',
    'total_amount' => 350 // نجرب مبلغ 350 لنتأكد أن العربون سيصبح 30
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true // هذا السطر مهم لرؤية أخطاء الـ API إن وجدت
    ],
];

$context  = stream_context_create($options);
// محاولة الاتصال
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    // إذا فشل الاتصال الداخلي (بسبب قيود الاستضافة المجانية)
    echo "<h3 style='color:red'>تنبيه: الاستضافة تمنع الاتصال الداخلي (Loopback)</h3>";
    echo "<p>وهذا طبيعي في InfinityFree. النظام يعمل، لكن لا يمكنك اختباره من نفس السيرفر.</p>";
    echo "<p>لتجربته، يجب استخدام موقع خارجي مثل <b>Postman</b> أو <b>ReqBin</b>.</p>";
    
    // محاولة عرض الخطأ الحقيقي إن وجد
    echo "Last Error: " . error_get_last()['message'];
} else {
    echo "<h3>رد النظام (Simulation Result):</h3>";
    echo "<pre>";
    print_r(json_decode($result, true));
    echo "</pre>";
    
    // عرض النص الخام للتأكد في حال كان هناك خطأ HTML
    if (json_decode($result) === null) {
        echo "<hr><b>Raw Response:</b><br>" . htmlspecialchars($result);
    }
}
?>