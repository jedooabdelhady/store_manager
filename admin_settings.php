<?php
session_start();
require_once 'config.php';

// مفترض أن الأدمن رقم 1 هو الرئيسي حالياً
$admin_id = 1; 
$msg = "";
$msg_type = "";

// 1. تحديث البيانات (اسم المستخدم وكلمة المرور)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // تحديث الاسم
    $sql_update = "UPDATE admins SET username = '$username' WHERE id = $admin_id";
    mysqli_query($conn, $sql_update);
    $msg = "تم تحديث البيانات بنجاح.";
    $msg_type = "success";

    // تحديث كلمة المرور إذا كتبت
    if (!empty($new_pass)) {
        if ($new_pass === $confirm_pass) {
             // التحقق من قوة كلمة المرور
             if (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_pass)) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $sql_pass = "UPDATE admins SET password = '$hashed' WHERE id = $admin_id";
                mysqli_query($conn, $sql_pass);
                $msg .= " وتم تغيير كلمة المرور.";
             } else {
                $msg = "كلمة المرور ضعيفة (يجب 8 خانات، حرف كبير، رقم).";
                $msg_type = "warning";
             }
        } else {
            $msg = "كلمة المرور غير متطابقة.";
            $msg_type = "danger";
        }
    }
}

// جلب بيانات الأدمن الحالية
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admins WHERE id = $admin_id"));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات المدير | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --light-bg: #f4f7f9; --glass: rgba(255, 255, 255, 0.95); }
        body { background-color: var(--light-bg); font-family: 'Segoe UI', sans-serif; }

        .sidebar { 
            background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh; color: white; padding: 30px 20px; 
            position: fixed; width: 260px;
        }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; border-radius: 10px; padding: 12px 15px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; }
        .main-content { margin-right: 260px; padding: 40px; }

        .settings-card {
            background: var(--glass); border-radius: 20px; padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); max-width: 800px; margin: auto;
        }
        .form-control { padding: 12px; border-radius: 10px; }
        .btn-save { background: var(--navy); color: white; padding: 12px 40px; border-radius: 10px; font-weight: bold; border: none; }
        .btn-save:hover { background: #003366; color: white; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <img src="logo.png" style="height: 45px; filter: brightness(0) invert(1);" alt="TaKeedPay">
        <div class="mt-3 badge bg-white bg-opacity-10 text-white px-3">Admin Panel</div>
    </div>
    <nav class="nav flex-column">
        <a href="admin_dashboard.php?tab=merchants" class="nav-link"><i class="fas fa-users-gear me-2"></i> إدارة التجار</a>
        <a href="admin_dashboard.php?tab=tickets" class="nav-link"><i class="fas fa-headset me-2"></i> تذاكر الدعم</a>
        <div class="mt-5 small text-uppercase opacity-50 mb-2 px-3">الإعدادات</div>
        <a href="admin_settings.php" class="nav-link active"><i class="fas fa-cog me-2"></i> إعدادات المنصة</a>
        <a href="logout.php" class="nav-link text-danger mt-auto"><i class="fas fa-power-off me-2"></i> خروج</a>
    </nav>
</div>

<div class="main-content">
    <h3 class="fw-bold mb-4" style="color: var(--navy);">إعدادات الحساب الإداري</h3>

    <?php if($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> shadow-sm rounded-3 mb-4 text-center"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="settings-card">
        <form method="POST">
            <div class="mb-4">
                <label class="form-label fw-bold">اسم المستخدم (للدخول)</label>
                <input type="text" name="username" class="form-control bg-light" value="<?php echo $admin['username']; ?>" required>
            </div>
            
            <hr class="my-4">
            
            <h5 class="fw-bold mb-3 text-danger"><i class="fas fa-lock me-2"></i> تغيير كلمة المرور</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="form-control" placeholder="اتركها فارغة إذا لم ترد التغيير">
                </div>
                <div class="col-md-6">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>
            </div>

            <div class="text-center mt-5">
                <button type="submit" class="btn btn-save shadow-lg">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>