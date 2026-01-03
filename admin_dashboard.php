<?php
session_start();
require_once 'config.php';

// التأكد من أن الأدمن مسجل دخول (اختياري حسب نظامك)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 1. معالجة الرد على التذاكر ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket_id'])) {
    $ticket_id = intval($_POST['reply_ticket_id']);
    $reply = mysqli_real_escape_string($conn, $_POST['admin_reply']);
    
    if(!empty($reply)) {
        $update_sql = "UPDATE tickets SET admin_reply = '$reply', status = 'closed' WHERE id = $ticket_id";
        mysqli_query($conn, $update_sql);
        header("Location: admin_dashboard.php?tab=tickets&msg=replied");
        exit;
    }
}

// --- 2. معالجة قبول/رفض التجار ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    // جلب بيانات التاجر للإشعارات (يمكن تفعيلها لاحقاً)
    $merchant_query = mysqli_query($conn, "SELECT phone, first_name FROM merchants WHERE id = $id");
    $merchant_data = mysqli_fetch_assoc($merchant_query);

    if ($action == 'approve') {
        $api_key = "tkp_" . bin2hex(random_bytes(16));
        $sql = "UPDATE merchants SET status = 'active', api_key = '$api_key' WHERE id = $id";
        mysqli_query($conn, $sql);
        header("Location: admin_dashboard.php?msg=approved");
        exit;
    } 
    
    if ($action == 'reject' && isset($_POST['reason'])) {
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $sql = "UPDATE merchants SET status = 'rejected', rejection_reason = '$reason' WHERE id = $id";
        mysqli_query($conn, $sql);
        header("Location: admin_dashboard.php?msg=rejected");
        exit;
    }
}

// --- 3. الإحصائيات ---
$total_merchants = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants"))['c'];
$pending_merchants = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM merchants WHERE status = 'pending'"))['c'];
$open_tickets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE status = 'open'"))['c'];

// --- 4. تحديد التبويب الحالي والبيانات ---
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'merchants';

if ($current_tab == 'tickets') {
    $data_query = "SELECT tickets.*, merchants.store_name, merchants.first_name, merchants.last_name 
                   FROM tickets 
                   JOIN merchants ON tickets.merchant_id = merchants.id 
                   ORDER BY FIELD(tickets.status, 'open', 'closed'), tickets.created_at DESC";
} else {
    // الافتراضي: عرض التجار
    $data_query = "SELECT * FROM merchants ORDER BY created_at DESC";
}
$result_data = mysqli_query($conn, $data_query);
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

        /* القائمة الجانبية */
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

        /* المحتوى الرئيسي */
        .main-content { margin-right: 260px; padding: 40px; }

        /* البطاقات الإحصائية */
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

        /* الجداول */
        .table-container {
            background: var(--glass); border-radius: 25px; padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.02); border: 1px solid rgba(255,255,255,0.5);
        }
        .table thead th {
            background-color: transparent; border-bottom: 2px solid #f0f0f0;
            color: #888; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;
        }
        .table tbody tr { transition: 0.2s; cursor: default; }
        .table tbody tr:hover { background-color: #fcfdfe; }

        /* Badges */
        .badge-pill { padding: 6px 14px; border-radius: 50px; font-weight: 600; font-size: 0.75rem; }
        .status-pending { background: #fff7ed; color: #c2410c; }
        .status-active { background: #ecfdf5; color: #047857; }
        .status-rejected { background: #fef2f2; color: #b91c1c; }
        .status-open { background: #fee2e2; color: #b91c1c; animation: pulse 2s infinite; }
        .status-closed { background: #dcfce7; color: #15803d; }
        
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        /* Modal Details */
        .detail-label { font-size: 0.8rem; color: #6c757d; margin-bottom: 2px; }
        .detail-value { font-weight: 600; color: var(--navy); font-size: 0.95rem; margin-bottom: 12px; border-bottom: 1px dashed #eee; padding-bottom: 5px; }
        .doc-preview { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; border: 2px solid #f0f0f0; cursor: pointer; transition: 0.3s; }
        .doc-preview:hover { transform: scale(1.02); border-color: var(--navy); }
        .section-header { font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: 15px; border-right: 4px solid var(--green); padding-right: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <img src="logo.png" style="height: 45px; filter: brightness(0) invert(1);" alt="TaKeedPay">
        <div class="mt-3 badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 px-3">نظام الأدمن v1.0</div>
    </div>
    <nav class="nav flex-column">
        <a href="admin_dashboard.php?tab=merchants" class="nav-link <?php echo ($current_tab == 'merchants') ? 'active' : ''; ?>">
            <i class="fas fa-users-gear me-2"></i> إدارة التجار
        </a>
        <a href="admin_dashboard.php?tab=tickets" class="nav-link <?php echo ($current_tab == 'tickets') ? 'active' : ''; ?>">
            <i class="fas fa-headset me-2"></i> تذاكر الدعم
            <?php if($open_tickets > 0): ?>
                <span class="badge bg-danger ms-auto float-end rounded-pill"><?php echo $open_tickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_edit.php" class="nav-link">
            <i class="fas fa-user-shield me-2"></i> إعدادات المشرف
        </a>
        <div class="mt-auto pt-5">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-power-off me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <p class="text-muted fw-semibold mb-1">إجمالي التجار</p>
                <div class="stat-val"><?php echo $total_merchants; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon" style="color: #f59e0b;"></i>
                <p class="text-muted fw-semibold mb-1">طلبات معلقة</p>
                <div class="stat-val" style="color: #f59e0b;"><?php echo $pending_merchants; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-ticket-alt stat-icon" style="color: #dc3545;"></i>
                <p class="text-muted fw-semibold mb-1">تذاكر مفتوحة</p>
                <div class="stat-val text-danger"><?php echo $open_tickets; ?></div>
            </div>
        </div>
    </div>

    <div class="table-container">
        
        <?php if ($current_tab == 'merchants'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold" style="color: var(--navy);"><i class="fas fa-file-invoice me-2"></i> طلبات الانضمام</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm rounded-3 shadow-sm" onclick="window.print()">
                        <i class="fas fa-file-excel me-1"></i> تصدير (طباعة)
                    </button>
                    <div class="input-group" style="width: 250px;">
                        <input type="text" class="form-control border-0 bg-light rounded-start-3" placeholder="بحث...">
                        <button class="btn btn-light rounded-end-3 border-0"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>التاجر والمتجر</th><th>نوع الكيان</th><th>الحالة</th><th class="text-center">الإجراءات</th></tr></thead>
                    <tbody>
                        <?php while($m = mysqli_fetch_assoc($result_data)): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="merchant-avatar me-3 text-center bg-light rounded p-2 fw-bold text-primary" style="width:45px;">
                                        <?php echo mb_substr($m['store_name'], 0, 1, 'utf-8'); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $m['first_name'] . ' ' . $m['last_name']; ?></div>
                                        <div class="small text-muted">
                                            <?php echo $m['store_name']; ?>
                                            <a href="https://wa.me/<?php echo str_replace('+', '', $m['phone']); ?>" target="_blank" class="text-success ms-2" title="مراسلة واتساب">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><div class="small fw-bold text-primary"><?php echo ($m['doc_type'] == 'commercial') ? 'سجل تجاري' : 'وثيقة عمل حر'; ?></div></td>
                            <td>
                                <?php 
                                if($m['status'] == 'pending') echo '<span class="badge-pill status-pending">قيد المراجعة</span>';
                                elseif($m['status'] == 'active') echo '<span class="badge-pill status-active">حساب مفعل</span>';
                                else echo '<span class="badge-pill status-rejected">مرفوض</span>';
                                ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm rounded-3 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $m['id']; ?>">
                                    <i class="fas fa-eye me-1"></i> الملف
                                </button>
                                
                                <div class="modal fade" id="viewModal<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
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
                                                            <div class="col-md-6"><div class="detail-label">الاسم</div><div class="detail-value"><?php echo $m['first_name'] . ' ' . $m['last_name']; ?></div></div>
                                                            <div class="col-md-6"><div class="detail-label">الجوال</div><div class="detail-value"><?php echo $m['phone']; ?></div></div>
                                                            <div class="col-12 mt-3"><div class="section-header">البيانات البنكية</div></div>
                                                            <div class="col-md-6"><div class="detail-label">البنك</div><div class="detail-value"><?php echo $m['bank_name']; ?></div></div>
                                                            <div class="col-md-6"><div class="detail-label">IBAN</div><div class="detail-value font-monospace"><?php echo $m['iban']; ?></div></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-5 border-start">
                                                        <div class="section-header">الوثائق</div>
                                                        <a href="<?php echo $m['doc_image']; ?>" target="_blank"><img src="<?php echo $m['doc_image']; ?>" class="doc-preview mb-2" alt="Document"></a>
                                                        <a href="<?php echo $m['iban_image']; ?>" target="_blank"><img src="<?php echo $m['iban_image']; ?>" class="doc-preview" alt="IBAN"></a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-0 justify-content-between">
                                                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إغلاق</button>
                                                <?php if($m['status'] == 'pending'): ?>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-danger px-4" onclick="location.href='admin_dashboard.php?action=reject&id=<?php echo $m['id']; ?>'">رفض</button>
                                                    <a href="admin_dashboard.php?action=approve&id=<?php echo $m['id']; ?>" class="btn btn-success px-4" onclick="return confirm('تأكيد التفعيل؟')">قبول وتفعيل</a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($current_tab == 'tickets'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold" style="color: var(--navy);"><i class="fas fa-headset me-2"></i> تذاكر الدعم الفني</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>رقم التذكرة</th><th>التاجر</th><th>الموضوع</th><th>الحالة</th><th>تاريخ</th><th class="text-center">الإجراء</th></tr></thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result_data) > 0): ?>
                            <?php while($t = mysqli_fetch_assoc($result_data)): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?php echo $t['id']; ?></td>
                                <td><?php echo $t['store_name']; ?></td>
                                <td><?php echo $t['subject']; ?></td>
                                <td>
                                    <?php 
                                        if($t['status'] == 'open') echo '<span class="badge-pill status-open">مفتوحة</span>';
                                        else echo '<span class="badge-pill status-closed">مغلقة</span>';
                                    ?>
                                </td>
                                <td class="small text-muted"><?php echo date('Y-m-d', strtotime($t['created_at'])); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm px-3 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $t['id']; ?>">
                                        <i class="fas fa-reply me-1"></i> رد
                                    </button>
                                    
                                    <div class="modal fade" id="ticketModal<?php echo $t['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <form method="POST">
                                                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                                                    <div class="modal-header border-0 bg-light">
                                                        <h5 class="fw-bold text-primary">تذكرة #<?php echo $t['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 text-end">
                                                        <div class="p-3 mb-4 rounded bg-light border"><?php echo nl2br($t['message']); ?></div>
                                                        <textarea name="admin_reply" class="form-control" rows="5" placeholder="اكتب ردك هنا..." required><?php echo $t['admin_reply']; ?></textarea>
                                                        <input type="hidden" name="reply_ticket_id" value="<?php echo $t['id']; ?>">
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="submit" class="btn btn-success px-4 fw-bold">إرسال الرد</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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