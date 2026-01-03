<?php
session_start();
require_once 'config.php';
require_once 'notifications.php';

// =====================================================
// التحقق من دخول الأدمن
// =====================================================
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// إنشاء instance من نظام الإشعارات
$notificationManager = new NotificationManager($conn);

// =====================================================
// 0. معالجة تحديث الإعدادات (التبويب الجديد)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $admin_id = $_SESSION['admin_id'];
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_pass = $_POST['password'];
    
    $update_query = "UPDATE admins SET email = '$new_email' WHERE id = $admin_id";
    mysqli_query($conn, $update_query);
    
    if (!empty($new_pass)) {
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE admins SET password = '$hashed_pass' WHERE id = $admin_id");
    }
    
    header("Location: admin_dashboard.php?tab=settings&msg=settings_saved");
    exit;
}

// =====================================================
// 1. معالجة الرد على التذاكر
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket_id'])) {
    $ticket_id = intval($_POST['reply_ticket_id']);
    $admin_reply = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
    
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE tickets SET admin_reply = ?, status = 'closed', updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $admin_reply, $ticket_id);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_dashboard.php?tab=tickets&msg=replied");
            exit;
        }
    }
}

// =====================================================
// 2. معالجة قبول/رفض التجار
// =====================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $merchant_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    $check_stmt = $conn->prepare("SELECT * FROM merchants WHERE id = ? LIMIT 1");
    $check_stmt->bind_param("i", $merchant_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $merchant = $result->fetch_assoc();
        
        // الموافقة
        if ($action == 'approve' && $merchant['status'] == 'pending') {
            $api_key = "tkp_" . bin2hex(random_bytes(24));
            $stmt = $conn->prepare("UPDATE merchants SET status = 'active', api_key = ? WHERE id = ?");
            $stmt->bind_param("si", $api_key, $merchant_id);
            if ($stmt->execute()) {
                $notificationManager->sendNotification($merchant_id, 'approved', ['api_key' => $api_key]);
                logAdminAction($conn, $_SESSION['admin_id'], 'approved_merchant', 'merchants', $merchant_id, ['status' => 'active']);
                header("Location: admin_dashboard.php?msg=approved");
                exit;
            }
        }
        
        // الرفض
        if ($action == 'reject' && $merchant['status'] == 'pending') {
            $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : 'عدم اكتمال البيانات';
            $stmt = $conn->prepare("UPDATE merchants SET status = 'rejected', rejection_reason = ? WHERE id = ?");
            $stmt->bind_param("si", $reason, $merchant_id);
            if ($stmt->execute()) {
                $notificationManager->sendNotification($merchant_id, 'rejected', ['reason' => $reason]);
                logAdminAction($conn, $_SESSION['admin_id'], 'rejected_merchant', 'merchants', $merchant_id, ['reason' => $reason]);
                header("Location: admin_dashboard.php?msg=rejected");
                exit;
            }
        }
    }
}

// =====================================================
// 3. جلب البيانات وتجهيز الإحصائيات
// =====================================================
$tab = $_GET['tab'] ?? 'dashboard';
$data_result = null;

// إحصائيات عامة
$stats = [
    'total_merchants' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM merchants"))['c'],
    'pending_merchants' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM merchants WHERE status='pending'"))['c'],
    'total_orders' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders"))['c'],
    'active_tickets' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tickets WHERE status='open'"))['c'],
    'total_revenue' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(deposit_amount) s FROM orders WHERE status='paid'"))['s'] ?? 0
];

// منطق التبويبات
if ($tab == 'merchants') {
    $data_result = mysqli_query($conn, "SELECT * FROM merchants ORDER BY created_at DESC");
} 
elseif ($tab == 'orders') {
    $data_result = mysqli_query($conn, "SELECT orders.*, merchants.store_name, merchants.phone as merchant_phone 
                                        FROM orders 
                                        JOIN merchants ON orders.merchant_id = merchants.id 
                                        ORDER BY orders.created_at DESC");
}
elseif ($tab == 'financials') {
    $fin_transferred = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c, SUM(deposit_amount) s FROM orders WHERE transfer_status='paid'"));
    $fin_processing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c, SUM(deposit_amount) s FROM orders WHERE transfer_status='processing'"));
    $fin_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c, SUM(deposit_amount) s FROM orders WHERE status='paid' AND transfer_status='pending'"));
    $fin_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c, SUM(deposit_amount) s FROM orders WHERE status='paid'"));
}
elseif ($tab == 'tickets') {
    $data_result = mysqli_query($conn, "SELECT tickets.*, merchants.store_name FROM tickets JOIN merchants ON tickets.merchant_id = merchants.id ORDER BY status DESC, created_at DESC");
}
// إضافة بيانات الصفحة الرئيسية (Dashboard Data)
elseif ($tab == 'dashboard') {
    $latest_orders = mysqli_query($conn, "SELECT orders.*, merchants.store_name FROM orders JOIN merchants ON orders.merchant_id = merchants.id ORDER BY orders.created_at DESC LIMIT 5");
    $latest_merchants = mysqli_query($conn, "SELECT * FROM merchants ORDER BY created_at DESC LIMIT 5");
}
// جلب بيانات الأدمن للإعدادات
elseif ($tab == 'settings') {
    $admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admins WHERE id = " . $_SESSION['admin_id']));
}

// دالة اللوج
function logAdminAction($conn, $u_id, $act, $tbl, $r_id, $chg) {
    $ip = $_SERVER['REMOTE_ADDR']; $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 255); $jsn = json_encode($chg);
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, user_type, action, table_name, record_id, changes, ip_address, user_agent) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiss", $u_id, $act, $tbl, $r_id, $jsn, $ip, $ua);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة الإدارة | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --navy: #004a87; --green: #28a745; --bg: #f4f7f9; }
        body { background: var(--bg); font-family: 'Segoe UI', Tahoma, sans-serif; overflow-x: hidden; }
        
        .sidebar { background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%); width: 260px; height: 100vh; position: fixed; right: 0; top: 0; color: white; padding: 20px; z-index: 1000; overflow-y: auto; }
        .main-content { margin-right: 260px; padding: 30px; transition: 0.3s; }
        .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 8px; border-radius: 10px; padding: 12px 15px; font-weight: 500; display: flex; align-items: center; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.15); color: white; transform: translateX(-5px); }
        .nav-link i { width: 25px; font-size: 1.1rem; text-align: center; margin-left: 10px; }
        
        .stat-card { background: white; border-radius: 15px; padding: 25px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 5px 15px rgba(0,0,0,0.02); transition: 0.3s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        
        /* ألوان الكروت */
        .card-fin { color: white; border: none; }
        .bg-fin-green { background: linear-gradient(45deg, #198754, #20c997); }
        .bg-fin-orange { background: linear-gradient(45deg, #fd7e14, #ffc107); }
        .bg-fin-red { background: linear-gradient(45deg, #dc3545, #ef4444); }
        .bg-fin-gray { background: linear-gradient(45deg, #6c757d, #adb5bd); }

        .table-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.02); }
        .table thead th { background: #f8f9fa; color: #555; border-bottom: 2px solid #eee; padding: 15px; font-size: 0.9rem; }
        .table tbody td { padding: 15px; vertical-align: middle; color: #444; }
        
        /* مودال */
        .modal-content { border-radius: 20px; border: none; overflow: hidden; }
        .modal-header { background: #f8f9fa; border-bottom: 1px solid #eee; }
        .doc-img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; cursor: pointer; transition: 0.2s; }
        .doc-img:hover { transform: scale(1.02); border-color: var(--navy); }
        .info-label { font-size: 0.85rem; color: #888; margin-bottom: 2px; }
        .info-value { font-weight: 600; color: var(--navy); margin-bottom: 15px; font-size: 1.05rem; }
        .section-title { font-weight: bold; border-right: 4px solid var(--green); padding-right: 10px; margin: 20px 0 15px 0; color: #333; }

        .badge-status { padding: 6px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: bold; }
        .bg-st-paid { background: #d1e7dd; color: #0f5132; }
        .bg-st-pending { background: #fff3cd; color: #856404; }
        .bg-st-cancel { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <img src="logo.png" style="height: 50px; filter: brightness(0) invert(1);" alt="LOGO">
        <div class="mt-2 small opacity-75">لوحة الإدارة</div>
    </div>
    
    <nav class="nav flex-column">
        <a href="?tab=dashboard" class="nav-link <?php echo $tab=='dashboard'?'active':''; ?>">
            <i class="fas fa-chart-pie"></i> الرئيسية
        </a>
        <a href="?tab=orders" class="nav-link <?php echo $tab=='orders'?'active':''; ?>">
            <i class="fas fa-receipt"></i> الطلبات
        </a>
        <a href="?tab=financials" class="nav-link <?php echo $tab=='financials'?'active':''; ?>">
            <i class="fas fa-wallet"></i> المالية
        </a>
        <a href="?tab=invoices" class="nav-link <?php echo $tab=='invoices'?'active':''; ?>">
            <i class="fas fa-file-invoice-dollar"></i> الفواتير
        </a>
        <a href="?tab=merchants" class="nav-link <?php echo $tab=='merchants'?'active':''; ?>">
            <i class="fas fa-users"></i> التجار
            <?php if($stats['pending_merchants']>0) echo "<span class='badge bg-warning text-dark ms-auto'>{$stats['pending_merchants']}</span>"; ?>
        </a>
        <a href="?tab=tickets" class="nav-link <?php echo $tab=='tickets'?'active':''; ?>">
            <i class="fas fa-headset"></i> الدعم الفني
            <?php if($stats['active_tickets']>0) echo "<span class='badge bg-danger ms-auto'>{$stats['active_tickets']}</span>"; ?>
        </a>
        <a href="?tab=settings" class="nav-link <?php echo $tab=='settings'?'active':''; ?>">
            <i class="fas fa-cog"></i> الإعدادات
        </a>
        
        <div class="mt-5 pt-5 border-top border-white border-opacity-10">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success shadow-sm rounded-3 mb-4"><i class="fas fa-check-circle me-2"></i> تم تنفيذ العملية بنجاح</div>
    <?php endif; ?>

    <?php if($tab == 'dashboard'): ?>
        <h4 class="mb-4 fw-bold text-secondary">لوحة القيادة</h4>
        
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">إجمالي التجار</h6>
                    <h2 class="fw-bold text-primary mb-0"><?php echo $stats['total_merchants']; ?></h2>
                    <i class="fas fa-users position-absolute end-0 bottom-0 m-3 fs-1 text-primary opacity-25"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">طلبات الانضمام</h6>
                    <h2 class="fw-bold text-warning mb-0"><?php echo $stats['pending_merchants']; ?></h2>
                    <i class="fas fa-user-clock position-absolute end-0 bottom-0 m-3 fs-1 text-warning opacity-25"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">إجمالي الطلبات</h6>
                    <h2 class="fw-bold text-success mb-0"><?php echo $stats['total_orders']; ?></h2>
                    <i class="fas fa-shopping-bag position-absolute end-0 bottom-0 m-3 fs-1 text-success opacity-25"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">الإيرادات (العربون)</h6>
                    <h2 class="fw-bold text-info mb-0"><?php echo number_format($stats['total_revenue']); ?> <small class="fs-6">ر.س</small></h2>
                    <i class="fas fa-coins position-absolute end-0 bottom-0 m-3 fs-1 text-info opacity-25"></i>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0 text-primary">آخر العمليات المالية</h5>
                        <a href="?tab=orders" class="btn btn-sm btn-light">عرض الكل</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>الطلب</th><th>المتجر</th><th>المبلغ</th><th>الحالة</th></tr></thead>
                            <tbody>
                                <?php while($lo = mysqli_fetch_assoc($latest_orders)): ?>
                                <tr>
                                    <td>#<?php echo $lo['id']; ?></td>
                                    <td><?php echo htmlspecialchars($lo['store_name']); ?></td>
                                    <td class="fw-bold text-success"><?php echo $lo['deposit_amount']; ?> ر.س</td>
                                    <td>
                                        <?php if($lo['status']=='paid') echo '<span class="badge bg-success">مدفوع</span>';
                                              else echo '<span class="badge bg-warning text-dark">انتظار</span>'; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0 text-primary">انضم حديثاً</h5>
                        <a href="?tab=merchants" class="btn btn-sm btn-light">عرض</a>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php while($lm = mysqli_fetch_assoc($latest_merchants)): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($lm['store_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($lm['first_name']); ?></small>
                            </div>
                            <?php if($lm['status']=='pending') echo '<span class="badge bg-warning text-dark">جديد</span>';
                                  elseif($lm['status']=='active') echo '<span class="badge bg-success">نشط</span>'; ?>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>

    <?php elseif($tab == 'orders'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold"><i class="fas fa-receipt me-2 text-primary"></i> سجل الطلبات</h4>
            <button class="btn btn-success btn-sm rounded-3"><i class="fas fa-file-excel me-2"></i> تصدير إكسل</button>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>رقم الطلب</th><th>المتجر</th><th>تاريخ الطلب</th><th>القيمة الكلية</th><th>العربون</th><th>حالة الربط</th><th>الملاحظات</th></tr></thead>
                    <tbody>
                    <?php while($ord = mysqli_fetch_assoc($data_result)): ?>
                        <tr>
                            <td class="font-monospace fw-bold">#<?php echo $ord['id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($ord['store_name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($ord['merchant_phone']); ?></div>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($ord['created_at'])); ?></td>
                            <td><?php echo number_format($ord['total_amount'], 2); ?> ر.س</td>
                            <td class="fw-bold text-success"><?php echo number_format($ord['deposit_amount'], 2); ?> ر.س</td>
                            <td>
                                <?php 
                                if($ord['status'] == 'paid') echo '<span class="badge-status bg-st-paid">مؤكد (تم الدفع)</span>';
                                elseif($ord['status'] == 'pending_payment') echo '<span class="badge-status bg-st-pending">بانتظار الدفع</span>';
                                else echo '<span class="badge-status bg-st-cancel">ملغي</span>';
                                ?>
                            </td>
                            <td><span class="text-muted small">-</span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif($tab == 'financials'): ?>
        <h4 class="fw-bold mb-4"><i class="fas fa-wallet me-2 text-primary"></i> الإدارة المالية</h4>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card card-fin bg-fin-green">
                    <h5>تم التحويل</h5>
                    <h3 class="fw-bold mt-2"><?php echo number_format($fin_transferred['s'] ?? 0, 2); ?> ر.س</h3>
                    <div class="small opacity-75"><?php echo $fin_transferred['c'] ?? 0; ?> عملية</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card-fin bg-fin-orange">
                    <h5>جاري التحويل</h5>
                    <h3 class="fw-bold mt-2"><?php echo number_format($fin_processing['s'] ?? 0, 2); ?> ر.س</h3>
                    <div class="small opacity-75"><?php echo $fin_processing['c'] ?? 0; ?> عملية</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card-fin bg-fin-red">
                    <h5>مبالغ معلقة</h5>
                    <h3 class="fw-bold mt-2"><?php echo number_format($fin_pending['s'] ?? 0, 2); ?> ر.س</h3>
                    <div class="small opacity-75"><?php echo $fin_pending['c'] ?? 0; ?> عملية</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card-fin bg-fin-gray">
                    <h5>إجمالي العمليات</h5>
                    <h3 class="fw-bold mt-2"><?php echo number_format($fin_all['s'] ?? 0, 2); ?> ر.س</h3>
                    <div class="small opacity-75"><?php echo $fin_all['c'] ?? 0; ?> عملية</div>
                </div>
            </div>
        </div>

    <?php elseif($tab == 'invoices'): ?>
        <h4 class="fw-bold mb-4"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i> الفواتير الشهرية</h4>
        <div class="table-card">
            <table class="table table-hover mb-0">
                <thead><tr><th>الشهر</th><th>عدد العمليات</th><th>إجمالي المبالغ</th><th>الحالة</th><th>تحميل</th></tr></thead>
                <tbody>
                    <tr>
                        <td>يناير 2026</td>
                        <td><?php echo $stats['total_orders']; ?></td>
                        <td><?php echo number_format($stats['total_revenue'], 2); ?> ر.س</td>
                        <td><span class="badge bg-success">تم الإصدار</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> PDF</button>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    <?php elseif($tab == 'merchants'): ?>
        <h4 class="fw-bold mb-4"><i class="fas fa-users me-2 text-primary"></i> إدارة التجار</h4>
        <div class="table-card">
            <table class="table table-hover mb-0">
                <thead><tr><th>التاجر</th><th>الكيان</th><th>الحالة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php while($m = mysqli_fetch_assoc($data_result)): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($m['store_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($m['first_name'].' '.$m['last_name']); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?php echo $m['doc_type']=='commercial'?'سجل تجاري':'عمل حر'; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if($m['status']=='pending') echo '<span class="badge bg-warning text-dark">قيد المراجعة</span>';
                            elseif($m['status']=='active') echo '<span class="badge bg-success">نشط</span>';
                            else echo '<span class="badge bg-danger">مرفوض</span>';
                            ?>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $m['id']; ?>">
                                <i class="fas fa-eye me-1"></i> الملف
                            </button>

                            <div class="modal fade" id="viewModal<?php echo $m['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold text-primary"><i class="fas fa-user-check me-2"></i> ملف التحقق</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row">
                                                <div class="col-lg-7 ps-4 border-end">
                                                    <div class="section-title mt-0">البيانات الشخصية</div>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="info-label">الاسم</div>
                                                            <div class="info-value"><?php echo htmlspecialchars($m['first_name'].' '.$m['last_name']); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="info-label">الجوال</div>
                                                            <div class="info-value" dir="ltr"><?php echo htmlspecialchars($m['phone']); ?></div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="info-label">البريد الإلكتروني</div>
                                                            <div class="info-value"><?php echo htmlspecialchars($m['email']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="section-title">البيانات البنكية</div>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="info-label">البنك</div>
                                                            <div class="info-value"><?php echo htmlspecialchars($m['bank_name']); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="info-label">المستفيد</div>
                                                            <div class="info-value"><?php echo htmlspecialchars($m['beneficiary_name']); ?></div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="info-label">IBAN</div>
                                                            <div class="info-value font-monospace bg-light p-2 rounded text-center border">
                                                                <?php echo htmlspecialchars($m['iban']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-5">
                                                    <div class="section-title mt-0">الوثائق الثبوتية</div>
                                                    <div class="mb-4">
                                                        <p class="small text-muted mb-1">صورة السجل / الوثيقة:</p>
                                                        <?php if($m['doc_file']): ?>
                                                            <a href="<?php echo $m['doc_file']; ?>" target="_blank">
                                                                <img src="<?php echo $m['doc_file']; ?>" class="doc-img">
                                                            </a>
                                                            <div class="mt-1 d-flex justify-content-between small">
                                                                <span>رقم: <b><?php echo $m['doc_number']; ?></b></span>
                                                                <span class="<?php echo (strtotime($m['doc_expiry']) < time())?'text-danger':'text-success'; ?>">
                                                                    ينتهي: <?php echo $m['doc_expiry']; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-2">
                                                        <p class="small text-muted mb-1">شهادة الآيبان:</p>
                                                        <?php if($m['iban_file']): ?>
                                                            <a href="<?php echo $m['iban_file']; ?>" target="_blank">
                                                                <img src="<?php echo $m['iban_file']; ?>" class="doc-img">
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light p-3">
                                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إغلاق</button>
                                            <?php if($m['status'] == 'pending'): ?>
                                                <div class="ms-auto d-flex gap-2">
                                                    <button class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $m['id']; ?>" data-bs-dismiss="modal">رفض</button>
                                                    <a href="?action=approve&id=<?php echo $m['id']; ?>" class="btn btn-success px-4" onclick="return confirm('تأكيد تفعيل الحساب؟')">قبول وتفعيل</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="rejectModal<?php echo $m['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form method="POST" action="?action=reject&id=<?php echo $m['id']; ?>" class="w-100">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">رفض طلب الانضمام</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="mb-2 fw-bold">سبب الرفض:</label>
                                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-danger w-100">تأكيد الرفض</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($tab == 'tickets'): ?>
        <h4 class="fw-bold mb-4"><i class="fas fa-headset me-2 text-primary"></i> تذاكر الدعم الفني</h4>
        <div class="table-card">
            <table class="table table-hover mb-0">
                <thead><tr><th>#</th><th>التاجر</th><th>الموضوع</th><th>الحالة</th><th>التاريخ</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php while($t = mysqli_fetch_assoc($data_result)): ?>
                    <tr>
                        <td><?php echo $t['id']; ?></td>
                        <td><?php echo htmlspecialchars($t['store_name']); ?></td>
                        <td><?php echo htmlspecialchars($t['subject']); ?></td>
                        <td><?php echo $t['status']=='open'?'<span class="badge bg-danger">مفتوحة</span>':'<span class="badge bg-success">مغلقة</span>'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($t['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $t['id']; ?>">
                                <i class="fas fa-reply"></i> رد
                            </button>
                            <div class="modal fade" id="ticketModal<?php echo $t['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">تذكرة #<?php echo $t['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="bg-light p-3 rounded mb-3">
                                                    <strong>الرسالة:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($t['message'])); ?>
                                                </div>
                                                <label class="fw-bold mb-2">الرد:</label>
                                                <textarea name="admin_reply" class="form-control" rows="4" required><?php echo htmlspecialchars($t['admin_reply']); ?></textarea>
                                                <input type="hidden" name="reply_ticket_id" value="<?php echo $t['id']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-success">إرسال الرد</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($tab == 'settings'): ?>
        <h4 class="fw-bold mb-4"><i class="fas fa-cog me-2 text-primary"></i> إعدادات المدير</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="stat-card p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الجديدة (اتركها فارغة إذا لم ترد التغيير)</label>
                            <input type="password" name="password" class="form-control" placeholder="******">
                        </div>
                        <button type="submit" name="save_settings" class="btn btn-primary w-100">حفظ التغييرات</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>