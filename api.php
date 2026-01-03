<?php
// api.php - محرك استقبال الطلبات وحساب العربون
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid Request Method']);
    exit;
}

// استقبال البيانات (سواء Form Data أو JSON)
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true);
}

// 1. التحقق من البيانات المطلوبة
if (empty($input['api_key']) || empty($input['total_amount'])) {
    echo json_encode(['status' => false, 'message' => 'Missing required fields (api_key, total_amount)']);
    exit;
}

$api_key = mysqli_real_escape_string($conn, $input['api_key']);
$total_amount = floatval($input['total_amount']);
$external_order_id = isset($input['order_id']) ? mysqli_real_escape_string($conn, $input['order_id']) : uniqid();
$customer_name = isset($input['customer_name']) ? mysqli_real_escape_string($conn, $input['customer_name']) : 'Guest';
$customer_phone = isset($input['phone']) ? mysqli_real_escape_string($conn, $input['phone']) : '';

// 2. التحقق من التاجر
$sql = "SELECT id, status FROM merchants WHERE api_key = '$api_key' LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid API Key']);
    exit;
}

$merchant = mysqli_fetch_assoc($result);

if ($merchant['status'] !== 'active') {
    echo json_encode(['status' => false, 'message' => 'Merchant account is inactive']);
    exit;
}

// 3. حساب قيمة العربون (حسب الشرائح المعتمدة)
$deposit = 0;

if ($total_amount < 100) {
    $deposit = 20;
} elseif ($total_amount >= 100 && $total_amount < 300) {
    $deposit = 25;
} elseif ($total_amount >= 300 && $total_amount < 400) {
    $deposit = 30;
} elseif ($total_amount >= 400 && $total_amount < 500) {
    $deposit = 40;
} elseif ($total_amount >= 500 && $total_amount < 600) {
    $deposit = 50;
} elseif ($total_amount >= 600 && $total_amount < 700) {
    $deposit = 60;
} elseif ($total_amount >= 700 && $total_amount < 800) {
    $deposit = 70;
} elseif ($total_amount >= 800 && $total_amount < 900) {
    $deposit = 80;
} elseif ($total_amount >= 900 && $total_amount < 1000) {
    $deposit = 90;
} else {
    $deposit = 99; // 1000 وأكثر
}

// 4. حفظ الطلب في قاعدة البيانات
$merchant_id = $merchant['id'];
$insert_sql = "INSERT INTO orders (merchant_id, external_order_id, customer_name, customer_phone, total_amount, deposit_amount, status) 
               VALUES ('$merchant_id', '$external_order_id', '$customer_name', '$customer_phone', '$total_amount', '$deposit', 'pending_payment')";

if (mysqli_query($conn, $insert_sql)) {
    $order_db_id = mysqli_insert_id($conn);
    
    // بناء رابط الدفع (الذي سيتم توجيه العميل إليه)
    // ملاحظة: تأكد من تغيير الرابط أدناه لرابط موقعك الحقيقي
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']); // مسار المجلد الحالي
    
    // تصحيح المسار لضمان عدم وجود تكرار للشرطات المائلة
    $base_url = rtrim($protocol . "://" . $host . $path, '/');
    $payment_url = $base_url . "/payment.php?id=" . $order_db_id;

    // الرد الناجح
    echo json_encode([
        'status' => true,
        'message' => 'Order created successfully',
        'deposit_amount' => $deposit,
        'currency' => 'SAR',
        'payment_url' => $payment_url,
        'order_ref' => $order_db_id
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Database Error: ' . mysqli_error($conn)]);
}
?>