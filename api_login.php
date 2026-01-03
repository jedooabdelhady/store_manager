<?php
// 1. إعدادات الهيدر (ضرورية جداً للتطبيقات)
header("Access-Control-Allow-Origin: *"); // السماح للتطبيق بالاتصال من أي مكان
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. استدعاء قاعدة البيانات
// ملاحظة: نستخدم ../ للرجوع للمجلد الرئيسي لأن هذا الملف داخل مجلد api
require_once '../config.php'; 

// مصفوفة الرد الموحدة
$response = array();

// 3. استقبال البيانات (سواء JSON أو Form Data)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // أحياناً التطبيقات ترسل JSON خام
    $json_input = file_get_contents("php://input");
    $data = json_decode($json_input, true);

    // تحديد المتغيرات سواء جاءت من JSON أو POST عادي
    $email = isset($data['email']) ? $data['email'] : $_POST['email'];
    $password = isset($data['password']) ? $data['password'] : $_POST['password'];

    // تنظيف البيانات
    $email = mysqli_real_escape_string($conn, trim($email));

    if (!empty($email) && !empty($password)) {
        
        // 4. البحث عن التاجر
        $query = "SELECT * FROM merchants WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $merchant = mysqli_fetch_assoc($result);

            // 5. التحقق من كلمة المرور (يجب أن تكون مشفرة في قاعدة البيانات)
            // ملاحظة: إذا كانت باسووردات التجار حالياً غير مشفرة، استخدم ($password == $merchant['password']) مؤقتاً
            if (password_verify($password, $merchant['password'])) {
                
                // 6. التحقق من حالة الحساب (هل وافق الأدمن؟)
                if ($merchant['status'] == 'active') {
                    $response['status'] = true;
                    $response['message'] = "تم تسجيل الدخول بنجاح";
                    $response['data'] = array(
                        'id' => $merchant['id'],
                        'first_name' => $merchant['first_name'],
                        'store_name' => $merchant['store_name'],
                        'email' => $merchant['email'],
                        'phone' => $merchant['phone'],
                        'api_key' => $merchant['api_key'], // مهم جداً للتطبيق
                        'avatar' => $merchant['doc_image'] // مثال
                    );
                } elseif ($merchant['status'] == 'pending') {
                    $response['status'] = false;
                    $response['message'] = "حسابك لا يزال قيد المراجعة من الإدارة.";
                } else {
                    $response['status'] = false;
                    $response['message'] = "عذراً، تم رفض حسابك. السبب: " . $merchant['rejection_reason'];
                }

            } else {
                $response['status'] = false;
                $response['message'] = "كلمة المرور غير صحيحة.";
            }
        } else {
            $response['status'] = false;
            $response['message'] = "البريد الإلكتروني غير مسجل.";
        }
    } else {
        $response['status'] = false;
        $response['message'] = "البيانات ناقصة (الإيميل وكلمة المرور مطلوبان).";
    }

} else {
    $response['status'] = false;
    $response['message'] = "طريقة الطلب غير صحيحة (يجب أن تكون POST).";
}

// 7. طباعة الرد بصيغة JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>