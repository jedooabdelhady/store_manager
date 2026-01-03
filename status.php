<?php
session_start();
require_once 'config.php';

// يمكن الوصول بـ رقم الجوال أو البريد
$identifier = isset($_POST['identifier']) ? $_POST['identifier'] : (isset($_GET['identifier']) ? $_GET['identifier'] : '');
$identifier = htmlspecialchars($identifier, ENT_QUOTES);
$merchant = null;
$msg = "";

if (!empty($identifier)) {
    // البحث عن التاجر
    $stmt = $conn->prepare("SELECT id, status, first_name, store_name, rejection_reason, created_at, updated_at FROM merchants WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $merchant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$merchant) {
        $msg = "لم يتم العثور على حساب بهذه البيانات.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة حسابك | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --green: #28a745; --orange: #f59e0b; --red: #ef4444; }
        
        body {
            background: linear-gradient(135deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        .status-container {
            background: white;
            border-radius: 25px;
            padding: 50px 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .header-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-logo img {
            height: 60px;
            margin-bottom: 15px;
        }

        .status-box {
            text-align: center;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
        }

        /* حالات مختلفة */
        .status-pending {
            background: #fef3c7;
            border: 2px solid var(--orange);
        }

        .status-active {
            background: #d1fae5;
            border: 2px solid var(--green);
        }

        .status-rejected {
            background: #fee2e2;
            border: 2px solid var(--red);
        }

        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .status-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 15px 0;
        }

        .status-desc {
            font-size: 1rem;
            color: #666;
            margin: 15px 0;
            line-height: 1.6;
        }

        .merchant-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: right;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #666;
        }

        .info-value {
            color: var(--navy);
            font-weight: 600;
        }

        .support-contact {
            background: #f0f4f8;
            border-left: 4px solid var(--navy);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: right;
        }

        .support-contact h5 {
            color: var(--navy);
            margin-bottom: 15px;
        }

        .contact-item {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .contact-item i {
            color: var(--navy);
            font-size: 1.2rem;
            width: 25px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin: 30px 0;
        }

        .action-buttons .btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary-custom {
            background: var(--navy);
            color: white;
        }

        .btn-primary-custom:hover {
            background: #003366;
            color: white;
            transform: translateY(-2px);
        }

        .btn-secondary-custom {
            background: #e5e7eb;
            color: #333;
        }

        .btn-secondary-custom:hover {
            background: #d1d5db;
            color: #333;
        }

        .form-section {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            padding: 12px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            width: 100%;
            font-size: 1rem;
            transition: 0.3s;
        }

        .search-box input:focus {
            border-color: var(--navy);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 74, 135, 0.1);
        }

        .timeline {
            text-align: right;
            margin: 30px 0;
        }

        .timeline-item {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            margin: 15px 0;
        }

        .timeline-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--navy);
            order: 2;
        }

        .timeline-dot.pending {
            background: var(--orange);
        }

        .timeline-dot.completed {
            background: var(--green);
        }

        .timeline-text {
            text-align: right;
            order: 1;
        }

        .timeline-text strong {
            color: var(--navy);
        }

        .timeline-text small {
            color: #999;
        }
    </style>
</head>
<body>

<div class="status-container">
    <div class="header-logo">
        <img src="logo.png" alt="TaKeedPay">
        <h3 style="color: var(--navy); font-weight: bold;">حالة حسابك</h3>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-warning text-center py-3 rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <!-- إذا لم يتم العثور على حساب أو لم يتم البحث بعد -->
    <?php if (!$merchant): ?>
        <div class="form-section">
            <h5 class="text-center mb-4">تحقق من حالة حسابك</h5>
            <form method="POST">
                <div class="search-box">
                    <input type="text" name="identifier" placeholder="أدخل رقم الجوال أو البريد الإلكتروني" required value="<?php echo $identifier; ?>">
                </div>
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="fas fa-search me-2"></i> البحث
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- إذا وجدنا الحساب -->

        <!-- حالة: قيد المراجعة -->
        <?php if ($merchant['status'] === 'pending'): ?>
        <div class="status-box status-pending">
            <div class="status-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="status-title">قيد المراجعة</div>
            <div class="status-desc">
                نشكرك على انضمامك! بياناتك الآن تحت مراجعة فريقنا المتخصص.
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; margin-top: 15px;">
                <i class="fas fa-clock me-2 text-orange"></i>
                <strong>المدة المتوقعة:</strong> 2 - 5 أيام عمل
            </div>
        </div>

        <div class="timeline">
            <h6 class="mb-3 fw-bold">خطوات المراجعة:</h6>
            <div class="timeline-item">
                <div class="timeline-dot completed"></div>
                <div class="timeline-text">
                    <strong>الطلب المستلم</strong>
                    <br><small><?php echo date('d/m/Y H:i', strtotime($merchant['created_at'])); ?></small>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-dot pending"></div>
                <div class="timeline-text">
                    <strong>فحص المستندات</strong>
                    <br><small>جاري...</small>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-dot pending"></div>
                <div class="timeline-text">
                    <strong>التفعيل النهائي</strong>
                    <br><small>في انتظار فحص المستندات</small>
                </div>
            </div>
        </div>

        <!-- حالة: مفعل -->
        <?php elseif ($merchant['status'] === 'active'): ?>
        <div class="status-box status-active">
            <div class="status-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="status-title">تم تفعيل حسابك!</div>
            <div class="status-desc">
                مبروك! حسابك مفعل الآن وجاهز للاستخدام. يمكنك الآن البدء برفع المتجر وتوصيل API.
            </div>
        </div>

        <div class="action-buttons">
            <a href="login.php" class="btn btn-primary-custom">
                <i class="fas fa-sign-in-alt me-2"></i> دخول الحساب
            </a>
            <a href="index.php" class="btn btn-secondary-custom">
                <i class="fas fa-home me-2"></i> الرئيسية
            </a>
        </div>

        <!-- حالة: مرفوض -->
        <?php elseif ($merchant['status'] === 'rejected'): ?>
        <div class="status-box status-rejected">
            <div class="status-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="status-title">لم يتم قبول الطلب</div>
            <div class="status-desc">
                عذراً، لم نتمكن من الموافقة على طلب الانضمام في الوقت الحالي.
            </div>
            <?php if (!empty($merchant['rejection_reason'])): ?>
            <div style="background: white; padding: 15px; border-radius: 10px; margin-top: 15px; text-align: right;">
                <strong style="color: var(--red);">السبب:</strong><br>
                <?php echo htmlspecialchars($merchant['rejection_reason']); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <div class="merchant-info">
            <h6 class="mb-3 fw-bold"><i class="fas fa-user-circle me-2"></i> بيانات الحساب</h6>
            <div class="info-item">
                <span class="info-value"><?php echo htmlspecialchars($merchant['first_name']); ?></span>
                <span class="info-label">الاسم:</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?php echo htmlspecialchars($merchant['store_name']); ?></span>
                <span class="info-label">المتجر:</span>
            </div>
            <div class="info-item">
                <span class="info-value" style="text-transform: uppercase;">
                    <?php 
                    $status_text = [
                        'pending' => 'قيد المراجعة',
                        'active' => 'مفعل',
                        'rejected' => 'مرفوض'
                    ];
                    echo $status_text[$merchant['status']] ?? 'غير محدد';
                    ?>
                </span>
                <span class="info-label">الحالة:</span>
            </div>
        </div>

        <div class="support-contact">
            <h5><i class="fas fa-headset me-2"></i> تواصل معنا</h5>
            <div class="contact-item">
                <span>support@takeedpay.com</span>
                <i class="fas fa-envelope"></i>
            </div>
            <div class="contact-item">
                <span>+966501234567</span>
                <i class="fas fa-phone"></i>
            </div>
            <div class="contact-item">
                <span>WhatsApp: +966501234567</span>
                <i class="fab fa-whatsapp"></i>
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 0.9rem; color: #666;">
                <strong>ساعات العمل:</strong><br>
                السبت - الخميس: 9 صباحاً - 5 مساءً<br>
                الجمعة والأحد: مغلق
            </div>
        </div>

    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px; color: #999; font-size: 0.9rem;">
        <p>هل واجهت مشكلة؟ <a href="support.php" style="color: var(--navy); text-decoration: none;"><strong>تواصل مع الدعم الفني</strong></a></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>