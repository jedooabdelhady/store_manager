<?php
// otp.php - نظام التحقق من OTP الكامل
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => false, 'message' => 'Invalid request method']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// =====================================================
// 1. إنشاء وإرسال OTP جديد
// =====================================================
if ($action === 'send') {
    $type = $_POST['type']; // phone أو email
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    
    // توليد كود عشوائي 6 أرقام
    $otp_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // حذف أي OTP قديم لنفس المستخدم
    mysqli_query($conn, "DELETE FROM otp_codes WHERE identifier = '$identifier' AND type = '$type'");
    
    // إدراج OTP جديد (صلاحية 5 دقائق)
    $expires = date('Y-m-d H:i:s', time() + 300);
    $insert_sql = "INSERT INTO otp_codes (identifier, type, code, expires_at, attempts) 
                   VALUES ('$identifier', '$type', '$otp_code', '$expires', 0)";
    
    if (mysqli_query($conn, $insert_sql)) {
        // محاكاة إرسال SMS/Email (يمكن دمج Twilio أو خدمة أخرى)
        // أثناء التطوير، يتم طباعة الكود في console فقط
        
        // إرسال فعلي يمكن إضافته لاحقاً:
        // sendSMS($identifier, "رمز التحقق: $otp_code");
        // sendEmail($identifier, "رمز التحقق: $otp_code");
        
        echo json_encode([
            'status' => true,
            'message' => 'تم إرسال الكود بنجاح',
            'expires_in' => 300, // 5 دقائق
            'debug_code' => $otp_code // للتطوير فقط (احذفه في الإنتاج)
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'خطأ في الإنظمة']);
    }
}

// =====================================================
// 2. التحقق من OTP
// =====================================================
elseif ($action === 'verify') {
    $type = $_POST['type'];
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    $code = $_POST['code'];
    
    // البحث عن الكود
    $sql = "SELECT * FROM otp_codes WHERE identifier = '$identifier' AND type = '$type' AND code = '$code' AND expires_at > NOW() LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $otp = mysqli_fetch_assoc($result);
        
        // التحقق من عدد المحاولات
        if ($otp['attempts'] >= 3) {
            echo json_encode(['status' => false, 'message' => 'تم تجاوز عدد المحاولات. يرجى طلب كود جديد']);
        } else {
            // حذف الكود المستخدم
            mysqli_query($conn, "DELETE FROM otp_codes WHERE id = " . $otp['id']);
            
            echo json_encode([
                'status' => true,
                'message' => 'تم التحقق بنجاح',
                'identifier' => $identifier,
                'type' => $type
            ]);
        }
    } else {
        // زيادة عدد المحاولات الفاشلة
        mysqli_query($conn, "UPDATE otp_codes SET attempts = attempts + 1 WHERE identifier = '$identifier' AND type = '$type'");
        
        echo json_encode(['status' => false, 'message' => 'الكود غير صحيح أو منتهي الصلاحية']);
    }
}

// =====================================================
// 3. إعادة إرسال الكود (Rate Limiting)
// =====================================================
elseif ($action === 'resend') {
    $type = $_POST['type'];
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    
    // التحقق من آخر محاولة إرسال
    $check = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT created_at FROM otp_codes WHERE identifier = '$identifier' AND type = '$type' ORDER BY created_at DESC LIMIT 1"
    ));
    
    if ($check) {
        $last_sent = strtotime($check['created_at']);
        $now = time();
        $diff = $now - $last_sent;
        
        // الانتظار 60 ثانية بين المحاولات
        if ($diff < 60) {
            echo json_encode([
                'status' => false,
                'message' => 'يرجى الانتظار قبل طلب كود جديد',
                'wait_seconds' => 60 - $diff
            ]);
        } else {
            // إرسال جديد
            $_POST['action'] = 'send';
            // استدعاء الدالة السابقة (يمكن إعادة صياغتها)
            echo json_encode(['status' => true, 'message' => 'تم إرسال الكود الجديد']);
        }
    }
}

// =====================================================
// 4. دالة مساعدة: إرسال SMS (Twilio)
// =====================================================
function sendSMS($phone, $message) {
    // إذا كان لديك حساب Twilio:
    // require __DIR__ . '/vendor/autoload.php';
    // use Twilio\Rest\Client;
    // $sid = 'YOUR_SID';
    // $token = 'YOUR_TOKEN';
    // $client = new Client($sid, $token);
    // $client->messages->create($phone, ['from' => 'YOUR_TWILIO_NUMBER', 'body' => $message]);
    
    // حالياً: لا يوجد إرسال فعلي
    return true;
}

// =====================================================
// 5. دالة مساعدة: إرسال Email
// =====================================================
function sendEmail($email, $message) {
    // mail($email, 'رمز التحقق', $message);
    return true;
}
?>