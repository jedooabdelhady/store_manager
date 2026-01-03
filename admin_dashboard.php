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

// إنشاء instance من النظام
$notificationManager = new NotificationManager($conn);

// =====================================================
// 1. معالجة الرد على التذاكر (بـ Prepared Statements)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket_id'])) {
    $ticket_id = intval($_POST['reply_ticket_id']);
    $admin_reply = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
    
    if (!empty($admin_reply) && strlen($admin_reply) > 0) {
        // استخدام Prepared Statements
        $stmt = $conn->prepare("UPDATE tickets SET admin_reply = ?, status = 'closed', updated_at = NOW() WHERE id = ?");
        
        if ($stmt) {
            $stmt->bind_param("si", $admin_reply, $ticket_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin_dashboard.php?tab=tickets&msg=replied");
                exit;
            }
            $stmt->close();
        }
    }
}

// =====================================================
// 2. معالجة قبول/رفض التجار (محسّنة)
// =====================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $merchant_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // التحقق من وجود التاجر
    $check_stmt = $conn->prepare("SELECT id, email, phone, first_name, store_name, status FROM merchants WHERE id = ? LIMIT 1");
    
    if ($check_stmt) {
        $check_stmt->bind_param("i", $merchant_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: admin_dashboard.php?msg=error&error=merchant_not_found");
            $check_stmt->close();
            exit;
        }
        
        $merchant = $result->fetch_assoc();
        $check_stmt->close();
        
        // =====================================================
        // معالجة الموافقة
        // =====================================================
        if ($action == 'approve' && $merchant['status'] == 'pending') {
            // توليد API Key
            $api_key = "tkp_" . bin2hex(random_bytes(24));
            
            // تحديث قاعدة البيانات
            $stmt = $conn->prepare("UPDATE merchants SET status = 'active', api_key = ? WHERE id = ?");
            
            if ($stmt) {
                $stmt->bind_param("si", $api_key, $merchant_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // إرسال الإشعار
                    $notificationManager->sendNotification(
                        $merchant_id, 
                        'approved', 
                        ['api_key' => $api_key]
                    );
                    
                    // تسجيل في Audit Log
                    logAdminAction($conn, $_SESSION['admin_id'], 'merchant_approved', 'merchants', $merchant_id, 
                        ['status' => 'pending → active', 'api_key_generated' => true]
                    );
                    
                    header("Location: admin_dashboard.php?msg=approved");
                    exit;
                }
                $stmt->close();
            }
        }
        
        // =====================================================
        // معالجة الرفض
        // =====================================================
        if ($action == 'reject' && $merchant['status'] == 'pending') {
            // التحقق من سبب الرفض
            if (!isset($_POST['rejection_reason']) || empty(trim($_POST['rejection_reason']))) {
                header("Location: admin_dashboard.php?msg=error&error=no_reason");
                exit;
            }
            
            $rejection_reason = trim($_POST['rejection_reason']);
            
            // تحديث قاعدة البيانات
            $stmt = $conn->prepare("UPDATE merchants SET status = 'rejected', rejection_reason = ? WHERE id = ?");
            
            if ($stmt) {
                $stmt->bind_param("si", $rejection_reason, $merchant_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // إرسال الإشعار
                    $notificationManager->sendNotification(
                        $merchant_id, 
                        'rejected', 
                        ['reason' => $rejection_reason]
                    );
                    
                    // تسجيل في Audit Log
                    logAdminAction($conn, $_SESSION['admin_id'], 'merchant_rejected', 'merchants', $merchant_id, 
                        ['status' => 'pending → rejected', 'reason' => $rejection_reason]
                    );
                    
                    header("Location: admin_dashboard.php?msg=rejected");
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// =====================================================
// 3. الإحصائيات (محسّنة)
// =====================================================
$stats = [
    'total_merchants' => 0,
    'pending_merchants' => 0,
    'active_merchants' => 0,
    'rejected_merchants' => 0,
    'open_tickets' => 0,
    'total_revenue' => 0
];

// إجمالي التجار
$total_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants");
$stats['total_merchants'] = mysqli_fetch_assoc($total_result)['c'] ?? 0;

// التجار المعلقون
$pending_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants WHERE status = 'pending'");
$stats['pending_merchants'] = mysqli_fetch_assoc($pending_result)['c'] ?? 0;

// التجار النشطون
$active_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants WHERE status = 'active'");
$stats['active_merchants'] = mysqli_fetch_assoc($active_result)['c'] ?? 0;

// التجار المرفوضون
$rejected_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants WHERE status = 'rejected'");
$stats['rejected_merchants'] = mysqli_fetch_assoc($rejected_result)['c'] ?? 0;

// التذاكر المفتوحة
$tickets_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE status = 'open'");
$stats['open_tickets'] = mysqli_fetch_assoc($tickets_result)['c'] ?? 0;

// إجمالي الأرباح
$revenue_result = mysqli_query($conn, "SELECT SUM(deposit_amount) as total FROM orders WHERE status = 'paid'");
$revenue_data = mysqli_fetch_assoc($revenue_result);
$stats['total_revenue'] = $revenue_data['total'] ?? 0;

// =====================================================
// 4. تحديد التبويب والبيانات
// =====================================================
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'merchants';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($current_tab == 'tickets') {
    $data_query = "SELECT tickets.*, merchants.store_name, merchants.first_name, merchants.last_name 
                   FROM tickets 
                   JOIN merchants ON tickets.merchant_id = merchants.id 
                   ORDER BY FIELD(tickets.status, 'open', 'closed'), tickets.created_at DESC
                   LIMIT 50";
} else {
    $data_query = "SELECT * FROM merchants ORDER BY created_at DESC LIMIT 100";
}

$result_data = mysqli_query($conn, $data_query);

// =====================================================
// دالة تسجيل الإجراءات
// =====================================================
function logAdminAction($conn, $admin_id, $action, $table, $record_id, $changes) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
    $changes_json = json_encode($changes);
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, user_type, action, table_name, record_id, changes, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $user_type = 'admin';
        $stmt->bind_param("isssisss", $admin_id, $user_type, $action, $table, $record_id, $changes_json, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة القيادة العليا | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --navy: #004a87; 
            --green: #28a745; 
            --light-bg: #f4f7f9;
            --glass: rgba(255, 255, 255, 0.95);
        }

        body { background-color: var(--light-bg); font-family: 'Segoe UI', Tahoma, sans-serif; }

        .sidebar { 
            background: linear-gradient(180deg, var(--navy) 0%, #002d52 100%);
            min-height: 100vh; color: white; padding: 30px 20px; 
            position: fixed; width: 260px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 100;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7); padding: 12px 15px; border-radius: 10px;
            margin-bottom: 10px; transition: 0.3s; font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1); color: white; transform: translateX(-5px);
        }
        .sidebar .nav-link i { width: 25px; font-size: 1.1rem; }

        .main-content { margin-right: 260px; padding: 40px; }

        .stat-card {
            border: none; border-radius: 20px; padding: 25px;
            background: var(--glass); box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: 0.3s; position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-7px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        .stat-icon {
            position: absolute; left: -10px; bottom: -10px; font-size: 5rem;
            opacity: 0.05; color: var(--navy);
        }
        .stat-val { font-size: 2.2rem; font-weight: 800; color: var(--navy); }

        .table-container {
            background: var(--glass); border-radius: 25px; padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.02); border: 1px solid rgba(255,255,255,0.5);
        }
        .table thead th {
            background-color: transparent; border-bottom: 2px solid #f0f0f0;
            color: #888; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;
        }
        .table tbody tr { transition: 0.2s; }
        .table tbody tr:hover { background-color: #fcfdfe; }

        .badge-pill { padding: 6px 14px; border-radius: 50px; font-weight: 600; font-size: 0.75rem; }
        .status-pending { background: #fff7ed; color: #c2410c; }
        .status-active { background: #ecfdf5; color: #047857; }
        .status-rejected { background: #fef2f2; color: #b91c1c; }
        .status-open { background: #fee2e2; color: #b91c1c; animation: pulse 2s infinite; }
        .status-closed { background: #dcfce7; color: #15803d; }
        
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        .doc-preview { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; border: 2px solid #f0f0f0; cursor: pointer; }
        .doc-preview:hover { transform: scale(1.02); border-color: var(--navy); }
        .section-header { font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: 15px; border-right: 4px solid var(--green); padding-right: 10px; }
        
        .alert-success { background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-danger { background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <img src="logo.png" style="height: 45px; filter: brightness(0) invert(1);" alt="TaKeedPay">
        <div class="mt-3 badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 px-3">نظام الأدمن v2.0</div>
    </div>
    <nav class="nav flex-column">
        <a href="admin_dashboard.php?tab=merchants" class="nav-link <?php echo ($current_tab == 'merchants') ? 'active' : ''; ?>">
            <i class="fas fa-users-gear me-2"></i> إدارة التجار
        </a>
        <a href="admin_dashboard.php?tab=tickets" class="nav-link <?php echo ($current_tab == 'tickets') ? 'active' : ''; ?>">
            <i class="fas fa-headset me-2"></i> تذاكر الدعم
            <?php if($stats['open_tickets'] > 0): ?>
                <span class="badge bg-danger ms-auto float-end rounded-pill"><?php echo $stats['open_tickets']; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_settings.php" class="nav-link">
            <i class="fas fa-cog me-2"></i> الإعدادات
        </a>
        <div class="mt-auto pt-5">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-power-off me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <!-- الرسائل -->
    <?php if($msg === 'approved'): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> تم تفعيل الحساب وإرسال الإشعار بنجاح
        </div>
    <?php elseif($msg === 'rejected'): ?>
        <div class="alert alert-danger border-0 rounded-3 mb-4 shadow-sm">
            <i class="fas fa-times-circle me-2"></i> تم رفض الطلب وإرسال الإشعار بنجاح
        </div>
    <?php elseif($msg === 'replied'): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> تم الرد على التذكرة بنجاح
        </div>
    <?php endif; ?>

    <!-- الإحصائيات -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <p class="text-muted fw-semibold mb-1">إجمالي التجار</p>
                <div class="stat-val"><?php echo $stats['total_merchants']; ?></div>
                <small class="text-muted">منهم <?php echo $stats['active_merchants']; ?> نشط</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon" style="color: #f59e0b;"></i>
                <p class="text-muted fw-semibold mb-1">طلبات معلقة</p>
                <div class="stat-val" style="color: #f59e0b;"><?php echo $stats['pending_merchants']; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-ticket-alt stat-icon" style="color: #dc3545;"></i>
                <p class="text-muted fw-semibold mb-1">تذاكر مفتوحة</p>
                <div class="stat-val text-danger"><?php echo $stats['open_tickets']; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-money-bill stat-icon" style="color: #10b981;"></i>
                <p class="text-muted fw-semibold mb-1">الأرباح</p>
                <div class="stat-val" style="color: #10b981;"><?php echo number_format($stats['total_revenue'], 2); ?> ر.س</div>
            </div>
        </div>
    </div>

    <div class="table-container">
        
        <?php if ($current_tab == 'merchants'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold" style="color: var(--navy);"><i class="fas fa-file-invoice me-2"></i> طلبات الانضمام</h5>
                <button class="btn btn-outline-success btn-sm rounded-3" onclick="window.print()">
                    <i class="fas fa-file-excel me-1"></i> طباعة
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>التاجر والمتجر</th><th>نوع الكيان</th><th>الحالة</th><th class="text-center">الإجراءات</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result_data) > 0): ?>
                            <?php while($m = mysqli_fetch_assoc($result_data)): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="merchant-avatar me-3 text-center bg-light rounded p-2 fw-bold text-primary" style="width:45px;">
                                            <?php echo mb_substr($m['store_name'], 0, 1, 'utf-8'); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
                                            <div class="small text-muted">
                                                <?php echo htmlspecialchars($m['store_name']); ?>
                                                <a href="https://wa.me/<?php echo str_replace('+', '', $m['phone']); ?>" target="_blank" class="text-success ms-2" title="واتساب">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><div class="small fw-bold text-primary"><?php echo ($m['doc_type'] == 'commercial') ? 'سجل تجاري' : 'عمل حر'; ?></div></td>
                                <td>
                                    <?php 
                                    if($m['status'] == 'pending') echo '<span class="badge-pill status-pending">قيد المراجعة</span>';
                                    elseif($m['status'] == 'active') echo '<span class="badge-pill status-active">مفعل</span>';
                                    else echo '<span class="badge-pill status-rejected">مرفوض</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm rounded-3 px-3" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $m['id']; ?>">
                                        <i class="fas fa-eye me-1"></i> الملف
                                    </button>
                                    
                                    <!-- Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $m['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-xl modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                                                <div class="modal-header bg-light border-0">
                                                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-user-check me-2"></i> ملف التحقق</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4 text-end">
                                                    <div class="row">
                                                        <div class="col-lg-7">
                                                            <div class="row g-3">
                                                                <div class="col-12"><div class="section-header">البيانات الشخصية</div></div>
                                                                <div class="col-md-6">
                                                                    <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 2px;">الاسم</div>
                                                                    <div style="font-weight: 600; color: var(--navy);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 2px;">الجوال</div>
                                                                    <div style="font-weight: 600; color: var(--navy);"><?php echo htmlspecialchars($m['phone']); ?></div>
                                                                </div>
                                                                <div class="col-12 mt-3"><div class="section-header">البيانات البنكية</div></div>
                                                                <div class="col-md-6">
                                                                    <div style="font-size: 0.8rem; color: #6c757d;">البنك</div>
                                                                    <div style="font-weight: 600; color: var(--navy);"><?php echo htmlspecialchars($m['bank_name']); ?></div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div style="font-size: 0.8rem; color: #6c757d;">IBAN</div>
                                                                    <div style="font-weight: 600; color: var(--navy); font-family: monospace;"><?php echo htmlspecialchars($m['iban']); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-5 border-start">
                                                         <div class="section-header">الوثائق الثبوتية</div>
<?php if(!empty($m['doc_file'])): ?>
    <div class="mb-3">
        <p class="small text-muted mb-1">صورة السجل / الوثيقة:</p>
        <a href="<?php echo htmlspecialchars($m['doc_file']); ?>" target="_blank">
            <?php if(pathinfo($m['doc_file'], PATHINFO_EXTENSION) != 'pdf'): ?>
                <img src="<?php echo htmlspecialchars($m['doc_file']); ?>" class="doc-preview mb-2" alt="Sajel Document">
            <?php else: ?>
                <div class="p-3 border rounded bg-light text-center">
                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                    <p class="small mb-0">فتح ملف PDF</p>
                </div>
            <?php endif; ?>
        </a>
    </div>
<?php endif; ?>

<?php if(!empty($m['iban_file'])): ?>
    <div class="mb-3">
        <p class="small text-muted mb-1">شهادة الآيبان:</p>
        <a href="<?php echo htmlspecialchars($m['iban_file']); ?>" target="_blank">
            <?php if(pathinfo($m['iban_file'], PATHINFO_EXTENSION) != 'pdf'): ?>
                <img src="<?php echo htmlspecialchars($m['iban_file']); ?>" class="doc-preview" alt="IBAN Document">
            <?php else: ?>
                <div class="p-3 border rounded bg-light text-center">
                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                    <p class="small mb-0">فتح ملف PDF</p>
                </div>
            <?php endif; ?>
        </a>
    </div>
<?php endif; ?>
                                                <div class="modal-footer bg-light border-0 justify-content-between">
                                                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إغلاق</button>
                                                    <?php if($m['status'] == 'pending'): ?>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $m['id']; ?>" data-bs-dismiss="modal">
                                                            رفض
                                                        </button>
                                                        <a href="admin_dashboard.php?action=approve&id=<?php echo $m['id']; ?>" class="btn btn-success px-4" onclick="return confirm('تأكيد تفعيل الحساب؟')">
                                                            قبول وتفعيل
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal رفض مع السبب -->
                                    <div class="modal fade" id="rejectModal<?php echo $m['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form method="POST" action="admin_dashboard.php?action=reject&id=<?php echo $m['id']; ?>">
                                                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                                                    <div class="modal-header border-0 bg-danger text-white">
                                                        <h5 class="modal-title fw-bold">رفض الطلب</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <label class="form-label fw-bold mb-3">سبب الرفض</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="4" placeholder="أدخل السبب بوضوح..." required></textarea>
                                                        <small class="text-muted mt-2 d-block">سيتم إرسال السبب للتاجر عبر الإشعارات</small>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                        <button type="submit" class="btn btn-danger px-4">تأكيد الرفض</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">لا توجد طلبات</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($current_tab == 'tickets'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold" style="color: var(--navy);"><i class="fas fa-headset me-2"></i> تذاكر الدعم الفني</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>رقم التذكرة</th><th>التاجر</th><th>الموضوع</th><th>الحالة</th><th>التاريخ</th><th class="text-center">الإجراء</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result_data) > 0): ?>
                            <?php while($t = mysqli_fetch_assoc($result_data)): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?php echo $t['id']; ?></td>
                                <td><?php echo htmlspecialchars($t['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($t['subject']); ?></td>
                                <td>
                                    <?php 
                                        if($t['status'] == 'open') echo '<span class="badge-pill status-open">مفتوحة</span>';
                                        else echo '<span class="badge-pill status-closed">مغلقة</span>';
                                    ?>
                                </td>
                                <td class="small text-muted"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $t['id']; ?>">
                                        <i class="fas fa-reply me-1"></i> رد
                                    </button>
                                    
                                    <!-- Modal الرد -->
                                    <div class="modal fade" id="ticketModal<?php echo $t['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <form method="POST">
                                                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                                                    <div class="modal-header border-0 bg-light">
                                                        <h5 class="fw-bold text-primary">تذكرة #<?php echo $t['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 text-end">
                                                        <div class="p-3 mb-4 rounded bg-light border">
                                                            <strong class="d-block mb-2">رسالة التاجر:</strong>
                                                            <?php echo nl2br(htmlspecialchars($t['message'])); ?>
                                                        </div>
                                                        <label class="form-label fw-bold mb-2">ردك:</label>
                                                        <textarea name="admin_reply" class="form-control" rows="5" placeholder="اكتب ردك..." required><?php echo htmlspecialchars($t['admin_reply']); ?></textarea>
                                                        <input type="hidden" name="reply_ticket_id" value="<?php echo $t['id']; ?>">
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                        <button type="submit" class="btn btn-success px-4 fw-bold">إرسال الرد</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">لا توجد تذاكر</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>