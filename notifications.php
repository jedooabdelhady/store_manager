<?php
// notifications.php - نظام الإشعارات الكامل
require_once 'config.php';

class NotificationManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * إرسال إشعار للتاجر
     * @param int $merchant_id
     * @param string $type (approved, rejected, payment_received, etc)
     * @param array $data بيانات الإشعار
     */
    public function sendNotification($merchant_id, $type, $data) {
        // جلب بيانات التاجر
        $merchant = $this->getMerchantData($merchant_id);
        
        if (!$merchant) return false;
        
        // بناء الرسالة حسب النوع
        $notification = $this->buildNotification($type, $data, $merchant);
        
        if (!$notification) return false;
        
        // حفظ في قاعدة البيانات
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications (merchant_id, type, title, message, channel, recipient, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $status = 'pending';
        $stmt->bind_param(
            "issssss",
            $merchant_id,
            $notification['type'],
            $notification['title'],
            $notification['message'],
            $notification['channel'],
            $notification['recipient'],
            $status
        );
        
        if ($stmt->execute()) {
            $notification_id = $stmt->insert_id;
            $stmt->close();
            
            // محاولة الإرسال الفعلي
            $this->attemptSend($notification_id, $notification);
            
            return true;
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * بناء محتوى الإشعار
     */
    private function buildNotification($type, $data, $merchant) {
        switch ($type) {
            case 'approved':
                return [
                    'type' => 'approved',
                    'title' => 'تم تفعيل حسابك! 🎉',
                    'message' => "مبروك يا {$merchant['first_name']}! تم تفعيل حسابك بنجاح على منصة TaKeedPay. يمكنك الآن البدء باستخدام الخدمة.\n\nمفتاح API: {$data['api_key']}\n\nرابط الدخول: " . $_SERVER['HTTP_HOST'] . "/login.php",
                    'channel' => 'sms',
                    'recipient' => $merchant['phone']
                ];
                
            case 'rejected':
                return [
                    'type' => 'rejected',
                    'title' => 'تحديث بشأن طلب الانضمام',
                    'message' => "عذراً يا {$merchant['first_name']}، لم نتمكن من الموافقة على طلب الانضمام حالياً.\n\nالسبب: {$data['reason']}\n\nللتواصل مع الدعم: support@takeedpay.com",
                    'channel' => 'email',
                    'recipient' => $merchant['email']
                ];
                
            case 'payment_received':
                return [
                    'type' => 'payment_received',
                    'title' => 'تم استلام دفعة جديدة ✅',
                    'message' => "تم استلام عربون جديد!\n\nرقم الطلب: {$data['order_id']}\nالمبلغ: {$data['amount']} ر.س\nالعميل: {$data['customer_name']}\n\nتحقق من لوحتك للتفاصيل",
                    'channel' => 'whatsapp',
                    'recipient' => $merchant['phone']
                ];
                
            case 'payment_confirmed':
                return [
                    'type' => 'payment_confirmed',
                    'title' => 'تم تأكيد الدفعة 🔔',
                    'message' => "تم تأكيد الدفعة بنجاح!\n\nالمبلغ: {$data['amount']} ر.س\nتم إضافته إلى رصيدك",
                    'channel' => 'sms',
                    'recipient' => $merchant['phone']
                ];
                
            case 'document_expiring':
                return [
                    'type' => 'document_expiring',
                    'title' => 'تنبيه: الوثيقة ستنتهي قريباً ⏰',
                    'message' => "تحذير: وثيقتك ({$data['doc_type']}) ستنتهي في {$data['days']} أيام.\n\nيرجى تحديثها قبل انتهاء الصلاحية",
                    'channel' => 'sms',
                    'recipient' => $merchant['phone']
                ];
                
            default:
                return null;
        }
    }
    
    /**
     * محاولة إرسال الإشعار الفعلي
     */
    private function attemptSend($notification_id, $notification) {
        switch ($notification['channel']) {
            case 'sms':
                $this->sendSMS($notification['recipient'], $notification['message'], $notification_id);
                break;
            case 'email':
                $this->sendEmail($notification['recipient'], $notification['title'], $notification['message'], $notification_id);
                break;
            case 'whatsapp':
                $this->sendWhatsApp($notification['recipient'], $notification['message'], $notification_id);
                break;
        }
    }
    
    /**
     * إرسال SMS (يمكن استخدام Twilio أو خدمة أخرى)
     */
    private function sendSMS($phone, $message, $notification_id) {
        // مثال باستخدام cURL - تطبيق مع Twilio أو خدمة SMS محلية
        
        // حالياً: محاكاة فقط
        $stmt = $this->conn->prepare("UPDATE notifications SET status = ?, sent_at = NOW() WHERE id = ?");
        $status = 'sent';
        $stmt->bind_param("si", $status, $notification_id);
        $stmt->execute();
        $stmt->close();
        
        // إذا كنت تستخدم Twilio:
        /*
        $twilio = new Client(SID, TOKEN);
        try {
            $twilio->messages->create($phone, ['from' => TWILIO_NUMBER, 'body' => $message]);
            $stmt = $this->conn->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $notification_id);
            $stmt->execute();
        } catch (Exception $e) {
            $stmt = $this->conn->prepare("UPDATE notifications SET status = 'failed' WHERE id = ?");
            $stmt->bind_param("i", $notification_id);
            $stmt->execute();
        }
        */
    }
    
    /**
     * إرسال البريد الإلكتروني
     */
    private function sendEmail($email, $subject, $message, $notification_id) {
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: noreply@takeedpay.com\r\n";
        
        if (mail($email, $subject, $message, $headers)) {
            $stmt = $this->conn->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $notification_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $this->conn->prepare("UPDATE notifications SET status = 'failed' WHERE id = ?");
            $stmt->bind_param("i", $notification_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * إرسال عبر WhatsApp (يمكن استخدام Twilio)
     */
    private function sendWhatsApp($phone, $message, $notification_id) {
        // استخدام API Twilio WhatsApp
        
        // حالياً: محاكاة
        $stmt = $this->conn->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * جلب بيانات التاجر
     */
    private function getMerchantData($merchant_id) {
        $stmt = $this->conn->prepare("SELECT id, first_name, email, phone, store_name FROM merchants WHERE id = ?");
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    /**
     * حذف الإشعارات القديمة (أكثر من 30 يوم)
     */
    public function cleanupOldNotifications() {
        $stmt = $this->conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $stmt->close();
    }
}

// مثال على الاستخدام في admin_dashboard.php:
/*
require_once 'notifications.php';
$notificationManager = new NotificationManager($conn);

// عند قبول التاجر:
if ($action == 'approve') {
    $api_key = "tkp_" . bin2hex(random_bytes(16));
    $sql = "UPDATE merchants SET status = 'active', api_key = '$api_key' WHERE id = $id";
    mysqli_query($conn, $sql);
    
    // إرسال إشعار
    $notificationManager->sendNotification($id, 'approved', ['api_key' => $api_key]);
}

// عند رفض التاجر:
if ($action == 'reject') {
    $reason = $_POST['reason'];
    $sql = "UPDATE merchants SET status = 'rejected', rejection_reason = '$reason' WHERE id = $id";
    mysqli_query($conn, $sql);
    
    // إرسال إشعار
    $notificationManager->sendNotification($id, 'rejected', ['reason' => $reason]);
}

// عند استلام دفعة:
$notificationManager->sendNotification(
    $merchant_id, 
    'payment_received', 
    [
        'order_id' => $order_id,
        'amount' => $deposit_amount,
        'customer_name' => $customer_name
    ]
);
*/
?>