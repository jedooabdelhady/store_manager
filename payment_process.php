<?php
// payment_process.php - محاكاة معالجة الدفع
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id'])) {
    
    $order_id = intval($_POST['order_id']);
    
    // محاكاة رقم العملية من بوابة الدفع
    $payment_ref = "PAY-" . strtoupper(bin2hex(random_bytes(4))) . "-" . date('Ymd');
    
    // تحديث حالة الطلب إلى "مدفوع"
    $sql = "UPDATE orders SET status = 'paid', payment_ref = '$payment_ref' WHERE id = $order_id";
    
    if (mysqli_query($conn, $sql)) {
        // إعادة التوجيه لصفحة الدفع مع رسالة نجاح
        header("Location: payment.php?id=$order_id&success=1");
        exit;
    } else {
        die("حدث خطأ أثناء المعالجة: " . mysqli_error($conn));
    }
} else {
    header("Location: index.php");
    exit;
}
?>