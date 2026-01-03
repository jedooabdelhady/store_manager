<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['merchant_id'])) {
    header("Location: login.php");
    exit;
}

$merchant_id = $_SESSION['merchant_id'];

// جلب كافة الطلبات المدفوعة للتاجر
$sql = "SELECT * FROM orders WHERE merchant_id = $merchant_id AND status = 'paid' ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>التقارير المالية | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="main-content" style="margin-right: 0; padding: 40px;">
        <div class="d-flex justify-content-between mb-4">
            <h3 class="fw-bold" style="color: var(--navy);">التقارير المالية للفواتير</h3>
            <a href="merchant_dashboard.php" class="btn btn-secondary">العودة للرئيسية</a>
        </div>

        <div class="table-container bg-white p-4 shadow-sm rounded-3">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>رقم العملية</th>
                        <th>تاريخ الدفع</th>
                        <th>مبلغ العربون</th>
                        <th>حالة التحويل لك</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row['payment_ref']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td class="text-success fw-bold"><?php echo $row['deposit_amount']; ?> ر.س</td>
                        <td>
                            <?php echo ($row['payout_status'] == 'paid') ? '<span class="badge bg-success">تم التحويل</span>' : '<span class="badge bg-warning text-dark">قيد الانتظار</span>'; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>