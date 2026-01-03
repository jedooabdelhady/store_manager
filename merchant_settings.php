<?php
session_start();
require_once 'config.php';

// 1. التحقق من الصلاحيات
if (!isset($_SESSION['merchant_id']) || $_SESSION['user_type'] != 'merchant') {
    header("Location: login.php");
    exit;
}

$m_id = $_SESSION['merchant_id'];
$msg = "";
$msg_type = "";

// 2. معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // استقبال البيانات وتنظيفها
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    // تحديث البيانات الأساسية
    $sql = "UPDATE merchants SET first_name='$first_name', last_name='$last_name', store_name='$store_name', phone='$phone' WHERE id=$m_id";
    mysqli_query($conn, $sql);

    // تحديث كلمة المرور (فقط إذا كتب شيئاً في الخانة)
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE merchants SET password='$password' WHERE id=$m_id");
    }

    $msg = "تم حفظ التغييرات بنجاح!";
    $msg_type = "success";
}

// 3. جلب البيانات الحالية للعرض
$merchant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM merchants WHERE id = $m_id"));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الحساب | <?php echo $merchant['store_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --glass: rgba(255, 255, 255, 0.95); }
        body { background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, sans-serif; }

        .sidebar { 
            background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh; color: white; padding: 30px 20px; 
            position: fixed; width: 260px; z-index: 100;
        }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; border-radius: 10px; padding: 12px 15px; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; transform: translateX(-5px); }
        
        .main-content { margin-right: 260px; padding: 40px; }

        .settings-card {
            background: var(--glass); border-radius: 20px; padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: none;
        }
        .form-control {
            border-radius: 10px; padding: 12px; border: 1px solid #e0e0e0; background: #fdfdfd;
        }
        .form-control:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(0, 74, 135, 0.1); }
        .section-title { border-right: 4px solid var(--navy); padding-right: 15px; margin-bottom: 25px; color: var(--navy); font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <h4 class="fw-bold"><i class="fas fa-store me-2"></i> لوحة التاجر</h4>
        <div class="small opacity-50"><?php echo mb_substr($merchant['store_name'], 0, 20); ?></div>
    </div>
    <nav class="nav flex-column">
        <a href="merchant_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> الرئيسية</a>
        <a href="merchant_settings.php" class="nav-link active"><i class="fas fa-cog me-2"></i> الإعدادات</a>
        <div class="mt-5 pt-5 border-top border-white border-opacity-10">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    <h3 class="fw-bold mb-4" style="color: var(--navy);">إعدادات الحساب</h3>

    <?php if($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> shadow-sm border-0 rounded-3 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="settings-card">
        <form method="POST">
            <h5 class="section-title">البيانات الشخصية والمتجر</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">الاسم الأول</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo $merchant['first_name']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">اسم العائلة</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo $merchant['last_name']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">اسم المتجر</label>
                    <input type="text" name="store_name" class="form-control" value="<?php echo $merchant['store_name']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">رقم الجوال</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $merchant['phone']; ?>" required>
                </div>
            </div>

            <h5 class="section-title">الأمان وتغيير كلمة المرور</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">البريد الإلكتروني (لا يمكن تغييره)</label>
                    <input type="email" class="form-control bg-light" value="<?php echo $merchant['email']; ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">كلمة المرور الجديدة</label>
                    <input type="password" name="password" class="form-control" placeholder="اتركها فارغة إذا كنت لا تريد التغيير">
                </div>
            </div>

            <hr class="my-4 text-muted opacity-25">

            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded-3 shadow-sm" style="background: var(--navy); border: none;">
                    <i class="fas fa-save me-2"></i> حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>