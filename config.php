<?php
// config.php - TaKeedPay Core Connection
$host = "sql100.infinityfree.com";
$user = "if0_40804426";
$pass = "kXJKVVLac0";
$db_name = "if0_40804426_store";

// الاتصال بنظام MySQLi
$conn = mysqli_connect($host, $user, $pass, $db_name);

// فحص الاتصال
if (!$conn) {
    die("خطأ في الاتصال بقاعدة البيانات: " . mysqli_connect_error());
}

// ضبط الترميز لدعم العربية 100%
mysqli_set_charset($conn, "utf8mb4");

// ملاحظة: لا نضع علامة إغلاق PHP هنا لتجنب أخطاء Header