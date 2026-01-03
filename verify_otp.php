<?php
session_start();
require_once 'config.php';

// حماية: ممنوع الدخول إلا لمن لديه جلسة تحقق
if (!isset($_SESSION['otp_identifier'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$identifier = $_SESSION['otp_identifier'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = mysqli_real_escape_string($conn, $_POST['otp_code']);
    
    // فحص الكود في قاعدة البيانات
    $check = mysqli_query($conn, "SELECT * FROM otp_codes WHERE identifier = '$identifier' AND code = '$code'");
    
    if (mysqli_num_rows($check) > 0) {
        // كود صحيح! مسحه وتسجيل الدخول
        mysqli_query($conn, "DELETE FROM otp_codes WHERE identifier = '$identifier'");
        
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM merchants WHERE phone = '$identifier' OR email = '$identifier'"));
        $_SESSION['merchant_id'] = $user['id'];
        $_SESSION['user_type'] = 'merchant';
        
        header("Location: merchant_dashboard.php");
        exit;
    } else {
        $error = "الكود خاطئ!";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>التحقق | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="bg-white p-5 rounded shadow text-center" style="width: 400px;">
        <h4>رمز التحقق</h4>
        <p class="text-muted">أرسلنا كود إلى: <?php echo $identifier; ?></p>
        <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="otp_code" class="form-control text-center fs-3 mb-3" maxlength="6" placeholder="XXXXXX" required>
            <button class="btn btn-primary w-100">تأكيد</button>
        </form>
    </div>
</body>
</html>