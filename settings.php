<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['merchant_id'])) {
    header("Location: login.php");
    exit;
}

$m_id = $_SESSION['merchant_id'];
$msg = "";
$msg_type = "";

// 1. معالجة تحديث البيانات العامة (بما فيها اللغة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $store_link = mysqli_real_escape_string($conn, $_POST['store_link']);
    $language = mysqli_real_escape_string($conn, $_POST['language']); // استقبال اللغة
    
    // معالجة رفع الشعار
    $logo_sql_part = "";
    if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] == 0) {
        $ext = pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION);
        $new_name = "LOGO_" . $m_id . "_" . time() . "." . $ext;
        $target = "uploads/logos/" . $new_name;
        if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $target)) {
            $logo_sql_part = ", store_logo = '$target'";
        }
    }

    // تحديث البيانات
    $sql = "UPDATE merchants SET 
            store_name = '$store_name', 
            phone = '$phone', 
            store_link = '$store_link',
            language = '$language' 
            $logo_sql_part 
            WHERE id = $m_id";
    
    if (mysqli_query($conn, $sql)) {
        $msg = "تم حفظ الإعدادات بنجاح!";
        $msg_type = "success";
        $_SESSION['store_name'] = $store_name;
    } else {
        $msg = "حدث خطأ في التحديث.";
        $msg_type = "danger";
    }
}

// 2. معالجة تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pass'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM merchants WHERE id = $m_id"));
    
    if (password_verify($old_pass, $row['password'])) {
        if ($new_pass === $confirm_pass) {
            if (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{10,}$/', $new_pass)) {
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE merchants SET password = '$hashed_pass' WHERE id = $m_id");
                $msg = "تم تغيير كلمة المرور بنجاح.";
                $msg_type = "success";
            } else {
                $msg = "كلمة المرور ضعيفة (يجب 10 خانات، حرف كبير، رقم).";
                $msg_type = "warning";
            }
        } else {
            $msg = "كلمة المرور الجديدة غير متطابقة.";
            $msg_type = "danger";
        }
    } else {
        $msg = "كلمة المرور الحالية غير صحيحة.";
        $msg_type = "danger";
    }
}

// جلب بيانات التاجر الحالية
$merchant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM merchants WHERE id = $m_id"));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الحساب | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --green: #28a745; --light-bg: #f4f7f9; --glass: rgba(255, 255, 255, 0.95); }
        body { background-color: var(--light-bg); font-family: 'Segoe UI', Tahoma, sans-serif; }

        .sidebar { 
            background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh; color: white; padding: 30px 20px; 
            position: fixed; width: 260px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 100;
        }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; border-radius: 10px; padding: 12px 15px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; }
        .main-content { margin-right: 260px; padding: 40px; }

        .settings-card {
            background: var(--glass); border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); margin-bottom: 30px;
        }
        .section-title { color: var(--navy); font-weight: 700; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .logo-preview {
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
            border: 3px solid #f0f0f0; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .form-label { font-weight: 600; color: #555; font-size: 0.9rem; }
        .form-control, .form-select { border-radius: 10px; padding: 10px 15px; border: 1px solid #dee2e6; }
        .form-control:focus, .form-select:focus { border-color: var(--navy); box-shadow: 0 0 0 0.2rem rgba(0, 74, 135, 0.15); }
        .btn-save { background: var(--navy); color: white; border: none; padding: 10px 30px; border-radius: 10px; font-weight: bold; }
        .btn-save:hover { background: #003366; color: white; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <img src="logo.png" style="height: 45px; filter: brightness(0) invert(1);" alt="TaKeedPay">
    </div>
    <nav class="nav flex-column">
        <a href="merchant_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> الرئيسية</a>
        <a href="merchant_dashboard.php" class="nav-link"><i class="fas fa-box-open me-2"></i> الطلبات</a>
        <a href="#" class="nav-link"><i class="fas fa-wallet me-2"></i> المحفظة</a>
        <a href="#" class="nav-link"><i class="fas fa-code me-2"></i> الربط (API)</a>
        <a href="support.php" class="nav-link"><i class="fas fa-headset me-2"></i> الدعم الفني</a>
        <a href="settings.php" class="nav-link active"><i class="fas fa-cog me-2"></i> الإعدادات</a>
        <div class="mt-5">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold" style="color: var(--navy);">إعدادات الحساب</h3>
            <p class="text-muted">تخصيص الحساب وتحديث البيانات.</p>
        </div>
    </div>

    <?php if($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?> shadow-sm rounded-3 mb-4">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="settings-card">
                <h5 class="section-title"><i class="fas fa-store me-2"></i> بيانات المتجر</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-3 text-center">
                            <div class="mb-2">
                                <?php $logo_src = !empty($merchant['store_logo']) ? $merchant['store_logo'] : 'https://via.placeholder.com/100?text=Logo'; ?>
                                <img src="<?php echo $logo_src; ?>" class="logo-preview" id="previewImg">
                            </div>
                            <label class="btn btn-sm btn-outline-secondary rounded-pill cursor-pointer">
                                <i class="fas fa-camera me-1"></i> رفع شعار
                                <input type="file" name="store_logo" class="d-none" accept="image/*" onchange="previewFile()">
                            </label>
                        </div>
                        <div class="col-md-9">
                            <div class="alert alert-light border small text-muted">
                                <i class="fas fa-language me-1"></i> اختر لغة لوحة التحكم المفضلة لديك.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">اسم المتجر</label>
                            <input type="text" name="store_name" class="form-control" value="<?php echo $merchant['store_name']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم الجوال</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $merchant['phone']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رابط المتجر</label>
                            <input type="url" name="store_link" class="form-control" value="<?php echo $merchant['store_link']; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-primary"><i class="fas fa-globe me-1"></i> لغة لوحة التحكم</label>
                            <select name="language" class="form-select bg-light">
                                <option value="ar" <?php if($merchant['language'] == 'ar') echo 'selected'; ?>>🇸🇦 العربية</option>
                                <option value="en" <?php if($merchant['language'] == 'en') echo 'selected'; ?>>🇺🇸 English</option>
                            </select>
                        </div>

                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" name="update_info" class="btn btn-save">
                            <i class="fas fa-save me-2"></i> حفظ الإعدادات
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="settings-card">
                <h5 class="section-title"><i class="fas fa-lock me-2"></i> الأمان</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور الحالية</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الجديدة</label>
                        <input type="password" name="new_password" class="form-control" placeholder="10 خانات..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تأكيد الجديدة</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="update_pass" class="btn btn-outline-danger fw-bold rounded-3">تحديث</button>
                    </div>
                </form>
            </div>
            
            <div class="settings-card bg-light border-0">
                <h6 class="fw-bold text-muted mb-3">حالة الحساب</h6>
                <div class="d-flex justify-content-between mb-2 small">
                    <span>الحالة:</span><span class="text-success fw-bold">نشط</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span>اللغة الحالية:</span>
                    <span class="fw-bold"><?php echo ($merchant['language']=='ar') ? 'العربية' : 'English'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewFile() {
    const preview = document.getElementById('previewImg');
    const file = document.querySelector('input[type=file]').files[0];
    const reader = new FileReader();
    reader.addEventListener("load", function () { preview.src = reader.result; }, false);
    if (file) reader.readAsDataURL(file);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>