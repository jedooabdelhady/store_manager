<?php
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("رابط غير صالح.");
}

$order_id = intval($_GET['id']);
$msg = "";

// معالجة رفع الإيصال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    
    if ($_FILES['receipt']['error'] == 0) {
        // استخدام __DIR__
        $uploadDir = __DIR__ . '/uploads/receipts/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $newName = "REC_" . time() . "_" . $order_id . "." . $ext;
        $target = $uploadDir . $newName;
        
        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
            // حفظ المسار النسبي
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

// باقي الكود كما هو...
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سداد العربون | <?php echo $order['store_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --navy: #004a87; --green: #28a745; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .pay-card {
            background: white; width: 100%; max-width: 500px;
            border-radius: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .pay-header {
            background: linear-gradient(135deg, var(--navy) 0%, #003366 100%);
            color: white; padding: 30px 20px; text-align: center; position: relative;
        }
        .amount-box {
            background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);
            border-radius: 15px; padding: 15px; display: inline-block; margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .amount-val { font-size: 2.2rem; font-weight: 800; line-height: 1; }
        
        .bank-info {
            background: #f8f9fa; margin: 20px; padding: 20px; border-radius: 15px;
            border: 1px dashed #cbd5e0; position: relative;
        }
        .iban-text { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; color: var(--navy); font-weight: bold; }
        .copy-btn {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            background: white; border: 1px solid #ddd; color: var(--navy);
            padding: 5px 10px; border-radius: 8px; font-size: 0.8rem; cursor: pointer;
        }
        
        .qr-box { display: flex; justify-content: center; margin: 20px 0; }
        .upload-area { margin: 20px; text-align: center; }
        
        .status-success { text-align: center; padding: 50px 20px; }
        .success-icon { font-size: 80px; color: var(--green); margin-bottom: 20px; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
</head>
<body>

<div class="pay-card">
    
    <?php if ($msg === 'uploaded' || $order['status'] === 'waiting_confirmation'): ?>
        <div class="status-success">
            <i class="fas fa-clock success-icon text-warning"></i>
            <h3 class="fw-bold">جاري التحقق...</h3>
            <p class="text-muted">تم استلام الإيصال بنجاح. سيقوم التاجر بتأكيد الطلب فور وصول المبلغ.</p>
            <div class="alert alert-light border mt-4 small">
                رقم العملية: <strong>#<?php echo $order['id']; ?></strong>
            </div>
            <p class="small text-muted mt-3">يمكنك إغلاق هذه الصفحة الآن.</p>
        </div>

    <?php elseif ($order['status'] === 'paid'): ?>
        <div class="status-success">
            <i class="fas fa-check-circle success-icon"></i>
            <h3 class="fw-bold">مدفوع مسبقاً</h3>
            <p class="text-muted">تم استلام عربون هذا الطلب بالفعل.</p>
        </div>

    <?php else: ?>
        <div class="pay-header">
            <h5 class="mb-0 opacity-75">سداد العربون لـ</h5>
            <h3 class="fw-bold mt-1"><?php echo $order['store_name']; ?></h3>
            <div class="amount-box">
                <div class="small opacity-75">المبلغ المطلوب</div>
                <div class="amount-val"><?php echo number_format($order['deposit_amount'], 2); ?> <small class="fs-6">ر.س</small></div>
            </div>
        </div>

        <div class="text-center mt-3 text-muted small px-3">
            يرجى تحويل المبلغ الموضح أعلاه إلى الحساب البنكي التالي، ثم رفع صورة الإيصال.
        </div>

        <div class="bank-info">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">اسم البنك</span>
                <span class="fw-bold small"><?php echo $order['bank_name']; ?></span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small">اسم المستفيد</span>
                <span class="fw-bold small"><?php echo $order['beneficiary_name']; ?></span>
            </div>
            <hr class="my-2 opacity-25">
            <div class="text-center mt-3 position-relative">
                <div class="small text-muted mb-1">رقم الآيبان (IBAN)</div>
                <div class="iban-text" id="ibanText"><?php echo $order['iban']; ?></div>
                <button class="copy-btn shadow-sm" onclick="copyIban()"><i class="far fa-copy"></i> نسخ</button>
            </div>
        </div>

        <div class="qr-box">
            <div id="qrcode" class="p-2 bg-white border rounded shadow-sm"></div>
        </div>

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

</div>

<script>
    // توليد QR Code للآيبان
    var iban = "<?php echo $order['iban']; ?>";
    new QRCode(document.getElementById("qrcode"), {
        text: iban,
        width: 120,
        height: 120,
        colorDark : "#004a87",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    function copyIban() {
        var text = document.getElementById("ibanText").innerText;
        navigator.clipboard.writeText(text);
        alert("تم نسخ الآيبان: " + text);
    }
</script>

</body>
</html>