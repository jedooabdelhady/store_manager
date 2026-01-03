<?php
session_start();
require_once 'config.php';

// التحقق من الدخول
if (!isset($_SESSION['merchant_id']) || $_SESSION['user_type'] != 'merchant') {
    header("Location: login.php");
    exit;
}

$m_id = $_SESSION['merchant_id'];

// -----------------------------------------------------------
// 1. معالجة تأكيد الدفع (عند ضغط التاجر على "تأكيد الاستلام")
// -----------------------------------------------------------
if (isset($_GET['confirm_order'])) {
    $order_id = intval($_GET['confirm_order']);
    // حماية: التأكد أن الطلب يخص هذا التاجر فعلاً
    $check_sql = "SELECT id FROM orders WHERE id = $order_id AND merchant_id = $m_id AND status = 'waiting_confirmation'";
    if (mysqli_num_rows(mysqli_query($conn, $check_sql)) > 0) {
        // تحديث الحالة إلى مدفوع
        $update = "UPDATE orders SET status = 'paid', transfer_status = 'pending' WHERE id = $order_id";
        mysqli_query($conn, $update);
        
        // (اختياري) يمكن هنا إضافة المبلغ لرصيد المحفظة في جدول merchants
        // mysqli_query($conn, "UPDATE merchants SET wallet_balance = wallet_balance + (SELECT deposit_amount FROM orders WHERE id=$order_id) WHERE id=$m_id");

        header("Location: merchant_dashboard.php?msg=confirmed");
        exit;
    }
}

// -----------------------------------------------------------
// 2. جلب البيانات والإحصائيات
// -----------------------------------------------------------
// بيانات التاجر
$sql_merchant = "SELECT * FROM merchants WHERE id = $m_id";
$merchant = mysqli_fetch_assoc(mysqli_query($conn, $sql_merchant));
$wallet_balance = isset($merchant['wallet_balance']) ? $merchant['wallet_balance'] : 0.00;

// إجمالي العربون المستلم (الطلبات المدفوعة)
$paid_query = mysqli_query($conn, "SELECT SUM(deposit_amount) as total FROM orders WHERE merchant_id = $m_id AND status = 'paid'");
$paid_data = mysqli_fetch_assoc($paid_query);

// عدد الطلبات النشطة (بانتظار الدفع أو التأكيد)
$pending_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE merchant_id = $m_id AND (status = 'pending_payment' OR status = 'waiting_confirmation')");
$pending_data = mysqli_fetch_assoc($pending_query);

// جلب آخر 20 طلب (مع ترتيب: التي تحتاج تأكيد تظهر أولاً)
$orders_sql = "SELECT * FROM orders WHERE merchant_id = $m_id ORDER BY CASE WHEN status = 'waiting_confirmation' THEN 1 ELSE 2 END, created_at DESC LIMIT 20";
$orders = mysqli_query($conn, $orders_sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التاجر | <?php echo $merchant['store_name']; ?></title>
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
        .sidebar .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; border-radius: 10px; padding: 12px 15px; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; transform: translateX(-5px); }
        
        .main-content { margin-right: 260px; padding: 40px; }

        .stat-card {
            background: var(--glass); border-radius: 20px; padding: 25px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: none; transition: 0.3s; position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        .stat-val { font-size: 2rem; font-weight: 800; color: var(--navy); }
        .stat-icon { position: absolute; left: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; color: var(--navy); }

        .api-box {
            background: linear-gradient(135deg, #004a87 0%, #003366 100%);
            color: white; border-radius: 20px; padding: 30px; position: relative; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 74, 135, 0.2);
        }
        .api-key-field {
            background: rgba(255,255,255,0.1); border: 1px dashed rgba(255,255,255,0.3);
            color: #fff; padding: 10px 15px; border-radius: 8px; font-family: monospace; width: 100%; display: block; margin-top: 10px; outline: none;
        }
        .btn-copy {
            position: absolute; left: 5px; top: 5px; bottom: 5px;
            background: white; color: var(--navy); border: none; border-radius: 6px; padding: 0 15px; font-weight: bold; font-size: 0.8rem; cursor: pointer; transition: 0.2s;
        }
        .btn-copy:hover { background: #f0f0f0; }

        .table-container {
            background: var(--glass); border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); margin-top: 30px; border: 1px solid rgba(255,255,255,0.5);
        }
        .status-badge { padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
        .bg-paid { background: #d1fae5; color: #065f46; }
        .bg-pending { background: #fff7ed; color: #9a3412; }
        .bg-waiting { background: #e0f2fe; color: #0369a1; animation: pulse 2s infinite; }
        
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        .receipt-preview { width: 100%; max-height: 400px; object-fit: contain; border: 2px solid #eee; border-radius: 10px; background: #f9f9f9; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-5">
        <h4 class="fw-bold"><i class="fas fa-store me-2"></i> لوحة التاجر</h4>
        <div class="small opacity-50"><?php echo mb_substr($merchant['store_name'], 0, 20); ?></div>
    </div>
    <nav class="nav flex-column">
        <a href="#" class="nav-link active"><i class="fas fa-home me-2"></i> الرئيسية</a>
        <a href="merchant_settings.php" class="nav-link"><i class="fas fa-cog me-2"></i> الإعدادات</a>
        <div class="mt-5 pt-5 border-top border-white border-opacity-10">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> خروج</a>
        </div>
    </nav>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold" style="color: var(--navy);">أهلاً، <?php echo $merchant['first_name']; ?> 👋</h3>
            <p class="text-muted">نظرة عامة على عمليات العربون والمدفوعات.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='confirmed'): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
        <i class="fas fa-check-circle fa-lg me-2"></i>
        <div>تم تأكيد استلام المبلغ وتحديث حالة الطلب بنجاح!</div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-12">
            <div class="api-box shadow-lg">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="fw-bold"><i class="fas fa-plug me-2"></i> الربط البرمجي (API Integration)</h4>
                        <p class="opacity-75 mb-0">استخدم المفتاح التالي لربط متجرك الإلكتروني أو نظام الكاشير لتلقي المدفوعات.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="position-relative mt-3 mt-md-0">
                            <input type="text" class="api-key-field" value="<?php echo $merchant['api_key']; ?>" readonly id="apiKeyField">
                            <button class="btn-copy" onclick="copyApiKey()">نسخ</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card border-end border-4 border-success">
                <i class="fas fa-wallet stat-icon"></i>
                <p class="text-muted fw-bold mb-1">رصيد المحفظة</p>
                <div class="stat-val text-success"><?php echo number_format($wallet_balance, 2); ?> <small class="fs-6">ر.س</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card border-end border-4 border-primary">
                <i class="fas fa-coins stat-icon"></i>
                <p class="text-muted fw-bold mb-1">إجمالي المقبوضات</p>
                <div class="stat-val"><?php echo number_format($paid_data['total'] ?? 0, 2); ?> <small class="fs-6">ر.س</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card border-end border-4 border-warning">
                <i class="fas fa-clock stat-icon"></i>
                <p class="text-muted fw-bold mb-1">طلبات نشطة</p>
                <div class="stat-val text-warning"><?php echo $pending_data['c']; ?></div>
                <div class="small text-muted mt-2">بانتظار التحويل أو التأكيد</div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <h5 class="fw-bold mb-4" style="color: var(--navy);"><i class="fas fa-list me-2"></i> آخر طلبات العربون</h5>
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($orders) > 0): ?>
                        <?php while($order = mysqli_fetch_assoc($orders)): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo $order['external_order_id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo $order['customer_name']; ?></div>
                                <small class="text-muted"><?php echo $order['customer_phone']; ?></small>
                            </td>
                            <td class="fw-bold text-success"><?php echo number_format($order['deposit_amount'], 2); ?></td>
                            <td>
                                <?php 
                                    if($order['status'] == 'paid') echo '<span class="status-badge bg-paid">مدفوع</span>';
                                    elseif($order['status'] == 'waiting_confirmation') echo '<span class="status-badge bg-waiting">مراجعة الإيصال</span>';
                                    else echo '<span class="status-badge bg-pending">بانتظار العميل</span>';
                                ?>
                            </td>
                            <td class="text-muted small"><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                            <td class="text-center">
                                <?php if($order['status'] == 'waiting_confirmation'): ?>
                                    <button class="btn btn-primary btn-sm px-3 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#receiptModal<?php echo $order['id']; ?>">
                                        <i class="fas fa-eye me-1"></i> مراجعة
                                    </button>

                                    <div class="modal fade" id="receiptModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                <div class="modal-header border-0">
                                                    <h5 class="fw-bold text-primary">مراجعة الإيصال البنكي</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-center p-4">
                                                    <p class="text-muted mb-3">قام العميل برفع صورة التحويل التالية:</p>
                                                    <a href="<?php echo $order['receipt_image']; ?>" target="_blank">
                                                        <img src="<?php echo $order['receipt_image']; ?>" class="receipt-preview mb-3" alt="الإيصال">
                                                    </a>
                                                    <div class="d-flex justify-content-between bg-light p-3 rounded mb-3">
                                                        <span>المبلغ المطلوب:</span>
                                                        <span class="fw-bold text-success"><?php echo number_format($order['deposit_amount'], 2); ?> ر.س</span>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 justify-content-center pb-4">
                                                    <a href="merchant_dashboard.php?confirm_order=<?php echo $order['id']; ?>" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow" onclick="return confirm('هل تأكدت من وصول المبلغ لحسابك؟')">
                                                        <i class="fas fa-check-circle me-2"></i> تأكيد استلام المبلغ
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">لا توجد طلبات حتى الآن.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    var copyText = document.getElementById("apiKeyField");
    copyText.select();
    navigator.clipboard.writeText(copyText.value);
    alert("تم نسخ مفتاح API!");
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>