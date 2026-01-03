<?php
session_start();
require_once 'config.php';

// التحقق من الدخول
if (!isset($_SESSION['merchant_id'])) {
    header("Location: login.php");
    exit;
}

$m_id = $_SESSION['merchant_id'];
$msg = "";

// 1. معالجة إنشاء تذكرة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_ticket'])) {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    if (!empty($subject) && !empty($message)) {
        $sql = "INSERT INTO tickets (merchant_id, subject, message, status) VALUES ('$m_id', '$subject', '$message', 'open')";
        if (mysqli_query($conn, $sql)) {
            $msg = "success";
        } else {
            $msg = "error";
        }
    }
}

// 2. جلب تذاكر التاجر
$tickets = mysqli_query($conn, "SELECT * FROM tickets WHERE merchant_id = $m_id ORDER BY created_at DESC");

// جلب بيانات التاجر للاسم
$merchant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM merchants WHERE id = $m_id"));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدعم الفني | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --green: #28a745; --light-bg: #f4f7f9; --glass: rgba(255, 255, 255, 0.95); }
        body { background-color: var(--light-bg); font-family: 'Segoe UI', Tahoma, sans-serif; }

        /* Sidebar - نفس تصميم الداشبورد */
        .sidebar { 
            background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh; color: white; padding: 30px 20px; 
            position: fixed; width: 260px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 100;
        }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; border-radius: 10px; padding: 12px 15px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; }
        .main-content { margin-right: 260px; padding: 40px; }

        /* البطاقات */
        .ticket-card {
            background: var(--glass); border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: bold; }
        .bg-open { background: #e0f2fe; color: #0369a1; }
        .bg-closed { background: #dcfce7; color: #166534; }

        .btn-new-ticket {
            background: var(--navy); color: white; border-radius: 10px; padding: 10px 25px;
            font-weight: bold; border: none; transition: 0.3s;
        }
        .btn-new-ticket:hover { background: #003366; color: white; transform: translateY(-2px); }

        /* تنسيق المحادثة */
        .msg-box { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: 1px solid #eee; }
        .msg-header { font-weight: bold; color: var(--navy); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .admin-reply-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 15px; padding: 20px; }
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
        <a href="support.php" class="nav-link active"><i class="fas fa-headset me-2"></i> الدعم الفني</a>
        <div class="mt-5">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold" style="color: var(--navy);">الدعم والمساعدة</h3>
            <p class="text-muted">نحن هنا لمساعدتك في أي وقت.</p>
        </div>
        <button class="btn btn-new-ticket shadow-sm" data-bs-toggle="modal" data-bs-target="#newTicketModal">
            <i class="fas fa-plus me-2"></i> فتح تذكرة جديدة
        </button>
    </div>

    <?php if($msg === 'success'): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
        <i class="fas fa-check-circle me-2"></i> تم إرسال تذكرتك بنجاح، سيتم الرد عليك قريباً.
    </div>
    <?php endif; ?>

    <div class="ticket-card">
        <h5 class="fw-bold mb-4 text-secondary">تذاكرك السابقة</h5>
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>رقم التذكرة</th>
                        <th>الموضوع</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th class="text-center">عرض</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($tickets) > 0): ?>
                        <?php while($t = mysqli_fetch_assoc($tickets)): ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?php echo $t['id']; ?></td>
                            <td class="fw-bold text-dark"><?php echo $t['subject']; ?></td>
                            <td>
                                <?php 
                                    if($t['status'] == 'open') echo '<span class="status-badge bg-open">مفتوحة</span>';
                                    else echo '<span class="status-badge bg-closed">تم الرد</span>';
                                ?>
                            </td>
                            <td class="text-muted small"><?php echo date('Y-m-d', strtotime($t['created_at'])); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#viewTicket<?php echo $t['id']; ?>">
                                    <i class="fas fa-eye"></i> التفاصيل
                                </button>
                            </td>
                        </tr>

                        <div class="modal fade" id="viewTicket<?php echo $t['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <div class="modal-header border-0 bg-light">
                                        <h5 class="fw-bold text-primary">تذكرة #<?php echo $t['id']; ?>: <?php echo $t['subject']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="msg-box">
                                            <div class="msg-header"><i class="fas fa-user me-2"></i> رسالتك:</div>
                                            <p class="mb-0 text-dark"><?php echo nl2br($t['message']); ?></p>
                                        </div>

                                        <?php if(!empty($t['admin_reply'])): ?>
                                            <div class="admin-reply-box">
                                                <div class="msg-header text-success"><i class="fas fa-headset me-2"></i> رد الدعم الفني:</div>
                                                <p class="mb-0 text-dark"><?php echo nl2br($t['admin_reply']); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-muted py-3">
                                                <i class="fas fa-clock mb-2"></i><br>
                                                بانتظار رد فريق الدعم...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إغلاق</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">لا توجد تذاكر حالياً.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newTicketModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="w-100">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 bg-navy text-white" style="background: var(--navy);">
                    <h5 class="fw-bold m-0 text-white"><i class="fas fa-pen me-2"></i> فتح تذكرة جديدة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الموضوع</label>
                        <input type="text" name="subject" class="form-control" placeholder="مثال: مشكلة في رفع الإيصال" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">تفاصيل المشكلة</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="اشرح مشكلتك بالتفصيل..." required></textarea>
                    </div>
                    <input type="hidden" name="new_ticket" value="1">
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">إرسال التذكرة</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>