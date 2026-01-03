<?php
session_start();
require_once 'config.php';

// 1. التوجيه التلقائي إذا كان مسجلاً مسبقاً
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}
if (isset($_SESSION['merchant_id'])) {
    header("Location: merchant_dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    // ---------------------------------------------------------
    // الخطوة الأولى: البحث في جدول المشرفين (Admins)
    // ---------------------------------------------------------
    $query_admin = "SELECT * FROM admins WHERE email = '$email' LIMIT 1";
    $result_admin = mysqli_query($conn, $query_admin);

    if ($row_admin = mysqli_fetch_assoc($result_admin)) {
        // وجدنا إيميلاً في جدول الأدمن، نتحقق من الباسورد
        if (password_verify($password, $row_admin['password'])) {
            $_SESSION['user_type'] = 'admin'; // لتمييز نوع المستخدم
            $_SESSION['admin_id'] = $row_admin['id'];
            $_SESSION['admin_name'] = $row_admin['username'];
            
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = "كلمة المرور غير صحيحة (حساب مشرف)";
        }
    } 
    // ---------------------------------------------------------
    // الخطوة الثانية: إذا لم يكن مشرفاً، نبحث في جدول التجار
    // ---------------------------------------------------------
    else {
        $query_merchant = "SELECT * FROM merchants WHERE email = '$email' LIMIT 1";
        $result_merchant = mysqli_query($conn, $query_merchant);

        if ($row_merchant = mysqli_fetch_assoc($result_merchant)) {
            // وجدنا إيميلاً في جدول التجار
            if (password_verify($password, $row_merchant['password'])) {
                
                // التحقق من حالة التاجر
                if ($row_merchant['status'] == 'active') {
                    $_SESSION['user_type'] = 'merchant';
                    $_SESSION['merchant_id'] = $row_merchant['id'];
                    $_SESSION['store_name'] = $row_merchant['store_name'];
                    
                    header("Location: merchant_dashboard.php");
                    exit;
                } elseif ($row_merchant['status'] == 'pending') {
                    $error = "حسابك لا يزال قيد المراجعة من الإدارة.";
                } else {
                    $error = "عذراً، تم رفض الحساب. تواصل مع الدعم.";
                }

            } else {
                $error = "كلمة المرور غير صحيحة";
            }
        } else {
            // لم نجده لا في الأدمن ولا في التجار
            $error = "البريد الإلكتروني غير مسجل في النظام";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول الموحد | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --navy: #004a87; 
            --navy-dark: #002d52;
            --glass: rgba(255, 255, 255, 0.95);
        }

        body {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            overflow: hidden;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            z-index: 0;
        }
        .c1 { width: 300px; height: 300px; top: -50px; right: -50px; }
        .c2 { width: 200px; height: 200px; bottom: 50px; left: -50px; }

        .login-card {
            background: var(--glass);
            border-radius: 25px;
            padding: 45px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .brand-logo {
            width: 90px;
            height: 90px;
            background: var(--navy);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -90px auto 20px;
            border: 6px solid var(--navy-dark);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            padding: 15px;
            overflow: hidden;
        }

        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .form-control {
            background: #f8f9fa;
            border: 2px solid #eef0f2;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: 0.3s;
        }
        .form-control:focus {
            background: #fff;
            border-color: var(--navy);
            box-shadow: 0 0 0 4px rgba(0, 74, 135, 0.1);
        }

        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #eef0f2;
            border-left: none;
            border-radius: 0 12px 12px 0;
            color: var(--navy);
        }
        .form-control { border-right: none; border-radius: 12px 0 0 12px !important; }

        .btn-login {
            background: linear-gradient(90deg, var(--navy) 0%, #003666 100%);
            color: white;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: 0.3s;
            box-shadow: 0 8px 20px rgba(0, 74, 135, 0.25);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(0, 74, 135, 0.35);
            color: white;
        }

        .login-title { color: var(--navy); font-weight: 800; letter-spacing: -0.5px; }
    </style>
</head>
<body>

<div class="bg-circle c1"></div>
<div class="bg-circle c2"></div>

<div class="login-card">
    <div class="brand-logo">
        <img src="logo.png" alt="Logo">
    </div>
    
    <div class="text-center mb-4">
        <h3 class="login-title">تسجيل الدخول</h3>
    </div>
    
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger text-center py-2 rounded-3 small border-0 bg-danger bg-opacity-10 text-danger fw-bold mb-4">
            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold ms-1">البريد الإلكتروني</label>
            <div class="input-group dir-ltr">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" class="form-control" placeholder="example@domain.com" required>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="form-label text-muted small fw-bold ms-1">كلمة المرور</label>
            <div class="input-group dir-ltr">
                <span class="input-group-text"><i class="fas fa-lock-open"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-login w-100 border-0 mb-3">
            تسجيل الدخول <i class="fas fa-arrow-left ms-2"></i>
        </button>
        
        <div class="text-center d-flex justify-content-between align-items-center mt-3 px-2">
            <a href="register.php" class="btn btn-primary-custom rounded-3">انضم كتاجر</a>
            <a href="#" class="small text-secondary fw-semibold text-decoration-none opacity-75">نسيت كلمة المرور؟</a>
        </div>
    </form>
</div>

</body>
</html>