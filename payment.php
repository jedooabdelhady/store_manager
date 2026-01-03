<?php
// payment.php - صفحة الدفع (للعميل)
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("رابط غير صالح.");
}

$order_id = intval($_GET['id']);
$msg = "";

// 1. معالجة رفع الإيصال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    
    if ($_FILES['receipt']['error'] == 0) {
        $uploadDir = __DIR__ . '/uploads/receipts/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $newName = "REC_" . time() . "_" . $order_id . "." . $ext;
        $target = $uploadDir . $newName;
        
        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
            $relativePath = 'uploads/receipts/' . $newName;
            
            $stmt = $conn->prepare("UPDATE orders SET status = 'waiting_confirmation', receipt_image = ? WHERE id = ?");
            $stmt->bind_param("si", $relativePath, $order_id);
            
            if ($stmt->execute()) {
                $msg = "uploaded";
            } else {
                $msg = "error_db";
            }
            $stmt->close();
        } else {
            $msg = "error_upload";
        }
    }
}

// 2. جلب بيانات الطلب + بيانات التاجر (الآيبان)
// هذا هو التعديل الأهم: قمنا بعمل JOIN لجلب الآيبان من جدول التجار
$sql = "SELECT orders.*, merchants.store_name, merchants.iban, merchants.bank_name, merchants.beneficiary_name 
        FROM orders 
        JOIN merchants ON orders.merchant_id = merchants.id 
        WHERE orders.id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die("الطلب غير موجود.");
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفع العربون | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --navy: #004a87; --bg: #f4f7f9; }
        body { background-color: var(--bg); font-family: 'Segoe UI', Tahoma, sans-serif; }
        .payment-card {
            max-width: 450px; margin: 40px auto; background: white;
            border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 30px;
        }
        .amount-box {
            background: #eef2f7; border-radius: 15px; padding: 20px; text-align: center; margin-bottom: 25px;
        }
        .amount-val { font-size: 2.5rem; font-weight: 800; color: var(--navy); }
        .iban-box { background: #fff; border: 1px dashed #ccc; padding: 15px; border-radius: 10px; margin-bottom: 20px; position: relative; }
        .iban-text { font-family: monospace; font-size: 1.1rem; font-weight: bold; letter-spacing: 1px; color: #333; }
        .copy-btn {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            background: var(--navy); color: white; border: none; padding: 5px 15px; border-radius: 5px; font-size: 0.8rem;
        }
        .qr-box { display: flex; justify-content: center; margin-bottom: 25px; }
        .status-badge { padding: 8px 20px; border-radius: 50px; font-weight: bold; font-size: 0.9rem; display: inline-block; margin-bottom: 20px; }
        .st-pending { background: #fff3cd; color: #856404; }
        .st-waiting { background: #cff4fc; color: #055160; }
        .st-paid { background: #d1e7dd; color: #0f5132; }
    </style>
</head>
<body>

<div class="payment-card">
    <div class="text-center mb-4">
        <h5 class="text-muted small">دفع عربون جدية الطلب</h5>
        <h4 class="fw-bold"><?php echo htmlspecialchars($order['store_name']); ?></h4>
    </div>

    <div class="text-center">
        <?php if($order['status'] == 'pending_payment'): ?>
            <span class="status-badge st-pending">بانتظار الدفع</span>
        <?php elseif($order['status'] == 'waiting_confirmation'): ?>
            <span class="status-badge st-waiting">جاري مراجعة الإيصال...</span>
        <?php elseif($order['status'] == 'paid'): ?>
            <span class="status-badge st-paid"><i class="fas fa-check-circle me-1"></i> تم دفع العربون بنجاح</span>
        <?php endif; ?>
    </div>

    <?php if($msg == 'uploaded'): ?>
        <div class="alert alert-success text-center small">تم رفع الإيصال! بانتظار تأكيد التاجر.</div>
    <?php elseif($msg == 'error_upload'): ?>
        <div class="alert alert-danger text-center small">فشل رفع الصورة. تأكد من الحجم والصيغة.</div>
    <?php endif; ?>

    <?php if($order['status'] == 'paid'): ?>
        <div class="text-center py-5">
            <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
            <p class="mt-3 text-muted">شكراً لك، تم تأكيد طلبك.</p>
        </div>
    <?php else: ?>
        <div class="amount-box">
            <div class="small text-muted mb-1">مبلغ العربون المطلوب</div>
            <div class="amount-val"><?php echo number_format($order['deposit_amount'], 2); ?> <small style="font-size: 1rem;">ر.س</small></div>
            <div class="small text-muted mt-2">قيمة الطلب الكلية: <?php echo number_format($order['total_amount'], 2); ?> ر.س</div>
        </div>

        <div class="iban-box">
            <div class="small text-muted mb-1">رقم الآيبان (<?php echo htmlspecialchars($order['bank_name']); ?>)</div>
            <div class="iban-text" id="ibanText"><?php echo htmlspecialchars($order['iban']); ?></div>
            <div class="small text-success mt-1"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($order['beneficiary_name']); ?></div>
            <button class="copy-btn shadow-sm" onclick="copyIban()"><i class="far fa-copy"></i> نسخ</button>
        </div>

        <div class="qr-box">
            <div id="qrcode" class="p-2 bg-white border rounded shadow-sm"></div>
        </div>

        <?php if($order['status'] != 'waiting_confirmation'): ?>
        <div class="upload-area">
            <form method="POST" enctype="multipart/form-data">
                <label class="form-label fw-bold text-dark">📷 إرفاق صورة التحويل</label>
                <input type="file" name="receipt" class="form-control mb-3" accept="image/*" required>
                <button type="submit" class="btn btn-success w-100 py-3 fw-bold shadow-sm rounded-3">
                    <i class="fas fa-paper-plane me-2"></i> إرسال الإيصال للتأكيد
                </button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script>
    // التأكد من وجود الآيبان قبل رسم الكود
    var iban = "<?php echo $order['iban']; ?>";
    
    if (iban && iban.length > 5) {
        new QRCode(document.getElementById("qrcode"), {
            text: iban,
            width: 120,
            height: 120,
            colorDark : "#004a87",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    } else {
        document.getElementById("qrcode").innerHTML = "<small class='text-muted'>الآيبان غير متوفر</small>";
    }

    function copyIban() {
        var copyText = document.getElementById("ibanText").innerText;
        navigator.clipboard.writeText(copyText).then(function() {
            alert("تم نسخ الآيبان: " + copyText);
        });
    }
</script>

</body>
</html>