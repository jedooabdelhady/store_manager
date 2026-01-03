<?php
// منع ظهور أي أخطاء نصية تفسد رد الـ JSON
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';

// =====================================================
// تحسينات الأمان - إنشاء CSRF Token
// =====================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// معالجة البيانات عند الإرسال النهائي (AJAX)
// =====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'final_submit') {
    header('Content-Type: application/json; charset=utf-8');
    
    // 1. التحقق الأمني
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'خطأ في التحقق الأمني. يرجى التحديث.']);
        exit;
    }
    
    // 2. التحقق من قوة كلمة المرور
    $password_raw = $_POST['password'] ?? '';
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{10,}$/', $password_raw)) {
        echo json_encode(['status' => 'error', 'message' => 'كلمة المرور لا تستوفي الشروط.']);
        exit;
    }
    
    // 3. التحقق من البريد والهاتف
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    
    $check_dup = mysqli_query($conn, "SELECT id FROM merchants WHERE email = '$email' OR phone = '$phone' LIMIT 1");
    if (mysqli_num_rows($check_dup) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'البريد الإلكتروني أو رقم الجوال مسجل مسبقاً.']);
        exit;
    }
    
    // 4. دالة الرفع المعدلة لتناسب InfinityFree (المسار المطلق)
    function uploadFile($fileKey) {
        // استخدام المسار المطلق للسيرفر للنقل
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documents/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] != 0) return null;
        
        $file = $_FILES[$fileKey];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowedExt) || $file['size'] > 5 * 1024 * 1024) return null;
        
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $uploadDir . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // نرجع المسار النسبي فقط للتخزين في القاعدة
            return 'uploads/documents/' . $newName;
        }
        return null;
    }
    
    // 5. رفع الملفات
    $doc_img = uploadFile('doc_image');
    $iban_img = uploadFile('iban_image');
    $vat_img = uploadFile('vat_image'); // اختياري
    
    if (!$doc_img || !$iban_img) {
        echo json_encode(['status' => 'error', 'message' => 'فشل في رفع الوثائق. تأكد من أن الحجم أقل من 5MB.']);
        exit;
    }
    
    // 6. تجهيز باقي البيانات
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $store_link = mysqli_real_escape_string($conn, $_POST['store_link']);
    $doc_type = mysqli_real_escape_string($conn, $_POST['doc_type']);
    $doc_number = mysqli_real_escape_string($conn, $_POST['doc_number']);
    $doc_expiry = mysqli_real_escape_string($conn, $_POST['doc_expiry']);
    $national_address = mysqli_real_escape_string($conn, $_POST['national_address']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $iban = mysqli_real_escape_string($conn, $_POST['iban']);
    $beneficiary_name = mysqli_real_escape_string($conn, $_POST['beneficiary_name']);
    $password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);
    $api_key = "tkp_" . bin2hex(random_bytes(24));

    // 7. الإدخال في القاعدة (تأكد من مطابقة أسماء الأعمدة)
    $sql = "INSERT INTO merchants (
        first_name, last_name, email, phone, password, api_key,
        store_name, store_link, doc_type, doc_number, doc_expiry, doc_file, national_address,
        bank_name, iban, beneficiary_name, iban_file, status
    ) VALUES (
        '$first_name', '$last_name', '$email', '$phone', '$password_hashed', '$api_key',
        '$store_name', '$store_link', '$doc_type', '$doc_number', '$doc_expiry', '$doc_img', '$national_address',
        '$bank_name', '$iban', '$beneficiary_name', '$iban_img', 'pending'
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'تم التسجيل بنجاح']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل شريك جديد | TaKeedPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { 
            --p-navy: #004a87; 
            --p-green: #28a745; 
        }
        
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .wizard-container { max-width: 900px; margin: 50px auto; }
        
        .wizard-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.05); 
            padding: 40px; 
            border-top: 8px solid var(--p-navy); 
        }

        .step-header { display: flex; justify-content: space-between; margin-bottom: 50px; position: relative; }
        .step-circle { 
            width: 45px; height: 45px; background: #e2e8f0; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-weight: bold; color: #64748b; position: relative; z-index: 2; 
            transition: 0.3s;
        }
        .step-circle.active { background: var(--p-navy); color: white; transform: scale(1.1); }
        .step-circle.completed { background: var(--p-green); color: white; }
        .progress-line { position: absolute; top: 22px; left: 0; right: 0; height: 2px; background: #e2e8f0; z-index: 1; }

        .form-step { display: none; }
        .form-step.active { display: block; animation: slideUp 0.4s ease-out; }
        
        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        .btn-navy { background: var(--p-navy); color: white; padding: 12px 35px; border-radius: 10px; font-weight: 600; border: none; transition: 0.3s; cursor: pointer; }
        .btn-navy:hover { background: #003663; color: white; transform: translateY(-2px); }
        
        .pass-strength-bar { height: 6px; background: #eee; border-radius: 3px; margin-top: 10px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; transition: 0.4s; }
        
        .review-box { background: #fdfdfd; border: 1px solid #edf2f7; border-radius: 15px; padding: 25px; margin-bottom: 20px; }
        .review-box h6 { color: var(--p-navy); font-weight: 700; margin-bottom: 15px; border-bottom: 1px solid #edf2f7; padding-bottom: 10px; }
    </style>
</head>
<body>

<div class="container wizard-container">
    <div class="wizard-card">
        <div class="text-center mb-5">
            <img src="logo.png" style="height: 60px;" alt="TaKeedPay">
            <h3 class="mt-4 fw-bold" style="color: var(--p-navy);">فتح حساب تاجر جديد</h3>
            <p class="text-muted text-center">خطوات بسيطة لربط متجرك بـ TaKeedPay</p>
        </div>

        <div class="step-header">
            <div class="progress-line"></div>
            <div class="step-circle active" id="s1">1</div>
            <div class="step-circle" id="s2">2</div>
            <div class="step-circle" id="s3">3</div>
            <div class="step-circle" id="s4">4</div>
        </div>

        <form id="regForm" enctype="multipart/form-data">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="final_submit">
            
            <!-- الخطوة 1 -->
            <div class="form-step active" id="step1">
                <h5 class="mb-4"><i class="fas fa-user-circle me-2 text-primary"></i> بيانات مدير الحساب</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">الاسم الأول</label>
                        <input type="text" class="form-control form-control-lg" name="first_name" required minlength="2" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">الاسم الأخير</label>
                        <input type="text" class="form-control form-control-lg" name="last_name" required minlength="2" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رقم الجوال</label>
                        <input type="text" class="form-control form-control-lg" name="phone" placeholder="05xxxxxxxx" required pattern="05[0-9]{8}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">البريد الإلكتروني</label>
                        <input type="email" class="form-control form-control-lg" name="email" required maxlength="255">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">كلمة المرور</label>
                        <input type="password" class="form-control form-control-lg" name="password" id="passInput" 
                               placeholder="10 خانات + حرف كبير + رقم" required oninput="checkPass()">
                        <div class="pass-strength-bar"><div id="strengthFill" class="strength-fill"></div></div>
                        <small id="passText" class="text-muted mt-2 d-block">يجب أن تحتوي على 10 خانات على الأقل، حرف كبير واحد، ورقم واحد.</small>
                    </div>
                </div>
                <div class="text-end mt-5">
                    <button type="button" class="btn btn-navy btn-lg" onclick="nextStep(2)">التالي: بيانات المنشأة <i class="fas fa-chevron-left ms-2"></i></button>
                </div>
            </div>

            <!-- الخطوة 2 -->
            <div class="form-step" id="step2">
                <h5 class="mb-4"><i class="fas fa-store me-2 text-primary"></i> بيانات المنشأة والتوثيق</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم المتجر</label>
                        <input type="text" class="form-control form-control-lg" name="store_name" required minlength="3" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رابط المتجر الإلكتروني</label>
                        <input type="url" class="form-control form-control-lg" name="store_link" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">نوع وثيقة التوثيق</label>
                        <select class="form-select form-select-lg" name="doc_type" id="docType" onchange="updateDocLabels()" required>
                            <option value="commercial">سجل تجاري</option>
                            <option value="freelance">وثيقة عمل حر</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" id="docNumLabel">رقم السجل التجاري</label>
                        <input type="text" class="form-control form-control-lg" name="doc_number" required maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">تاريخ انتهاء الوثيقة</label>
                        <input type="date" class="form-control form-control-lg" name="doc_expiry" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" id="docFileLabel">رفع ملف السجل التجاري</label>
                        <input type="file" class="form-control" name="doc_image" accept="image/*,.pdf" required>
                        <small class="text-muted">PDF أو صورة (5MB كحد أقصى)</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">العنوان الوطني</label>
                        <input type="text" class="form-control form-control-lg" name="national_address" required maxlength="255">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch p-3 border rounded">
                            <input class="form-check-input ms-0 me-3" type="checkbox" id="vatSwitch" name="is_vat" onchange="toggleVatSection()">
                            <label class="form-check-label fw-bold">المنشأة مسجلة في ضريبة القيمة المضافة (VAT)</label>
                        </div>
                    </div>
                    <div id="vatSection" class="row g-4 d-none">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">الرقم الضريبي</label>
                            <input type="text" class="form-control form-control-lg" name="vat_number" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">شهادة تسجيل الضريبة</label>
                            <input type="file" class="form-control" name="vat_image" accept="image/*,.pdf">
                            <small class="text-muted">PDF أو صورة (5MB كحد أقصى)</small>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-5">
                    <button type="button" class="btn btn-outline-secondary px-5" onclick="prevStep(1)">سابق</button>
                    <button type="button" class="btn btn-navy btn-lg" onclick="nextStep(3)">التالي: البيانات البنكية <i class="fas fa-chevron-left ms-2"></i></button>
                </div>
            </div>

            <!-- الخطوة 3 -->
            <div class="form-step" id="step3">
                <h5 class="mb-4"><i class="fas fa-university me-2 text-primary"></i> بيانات الحساب البنكي</h5>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i> <strong>تنبيه:</strong> يجب أن يطابق اسم المستفيد الاسم في السجل التجاري أو الوثيقة.
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم البنك</label>
                        <input type="text" class="form-control form-control-lg" name="bank_name" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رقم الآيبان (IBAN)</label>
                        <input type="text" class="form-control form-control-lg" name="iban" placeholder="SA..." required maxlength="34" pattern="SA[0-9]{22}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رمز السويفت (SWIFT)</label>
                        <input type="text" class="form-control form-control-lg" name="swift_code" required maxlength="11">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم المستفيد الكامل</label>
                        <input type="text" class="form-control form-control-lg" name="beneficiary_name" required maxlength="100">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">رفع صورة شهادة الآيبان</label>
                        <input type="file" class="form-control" name="iban_image" accept="image/*,.pdf" required>
                        <small class="text-muted">PDF أو صورة (5MB كحد أقصى)</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-5">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep(2)">سابق</button>
                    <button type="button" class="btn btn-navy btn-lg" onclick="showReview()">مراجعة وإرسال <i class="fas fa-chevron-left ms-2"></i></button>
                </div>
            </div>

            <!-- الخطوة 4 -->
            <div class="form-step" id="step4">
                <h4 class="text-center mb-4">مراجعة بيانات الانضمام</h4>
                <div id="reviewGrid" class="row"></div>
                <div class="form-check bg-light p-4 rounded-4 mt-4 border">
                    <input class="form-check-input ms-2" type="checkbox" id="finalAgree" required>
                    <label class="form-check-label fw-bold" for="finalAgree">
                        أقر بصحة جميع البيانات المدخلة وأني مفوض بالتسجيل نيابة عن المنشأة وأتحمل المسؤولية القانونية.
                    </label>
                </div>
                <div class="d-flex justify-content-between mt-5">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep(3)">تعديل البيانات</button>
                    <button type="button" class="btn btn-success btn-lg px-5 shadow-sm fw-bold" onclick="submitRegistration()">تأكيد وإرسال الطلب</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
let currentStep = 1;

function showStep(s) {
    document.querySelectorAll('.form-step').forEach(div => div.classList.remove('active'));
    document.getElementById('step' + s).classList.add('active');
    
    document.querySelectorAll('.step-circle').forEach((c, idx) => {
        if(idx + 1 < s) c.className = 'step-circle completed';
        else if(idx + 1 === s) c.className = 'step-circle active';
        else c.className = 'step-circle';
    });
    currentStep = s;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep(s) {
    if (currentStep === 1 && !checkPass()) {
        alert("يرجى استيفاء شروط كلمة المرور (10 خانات، حرف كبير، ورقم).");
        return;
    }
    showStep(s);
}

function prevStep(s) { showStep(s); }

function checkPass() {
    const p = document.getElementById('passInput').value;
    const fill = document.getElementById('strengthFill');
    const txt = document.getElementById('passText');
    
    const hasLower = /[a-z]/.test(p);
    const hasUpper = /[A-Z]/.test(p);
    const hasNumber = /[0-9]/.test(p);
    const isLong = p.length >= 10;

    if (!isLong) {
        fill.style.width = '30%'; fill.style.backgroundColor = '#ef4444';
        txt.innerHTML = '<span class="text-danger">❌ كلمة المرور قصيرة جداً (أقل من 10)</span>';
        return false;
    } else if (!hasUpper || !hasNumber) {
        fill.style.width = '60%'; fill.style.backgroundColor = '#f59e0b';
        txt.innerHTML = '<span class="text-warning">⚠️ يجب إضافة حرف كبير ورقم</span>';
        return false;
    } else if (!hasLower) {
        fill.style.width = '70%'; fill.style.backgroundColor = '#f59e0b';
        txt.innerHTML = '<span class="text-warning">⚠️ يجب إضافة حرف صغير</span>';
        return false;
    } else {
        fill.style.width = '100%'; fill.style.backgroundColor = '#10b981';
        txt.innerHTML = '<span class="text-success fw-bold">✅ كلمة مرور قوية</span>';
        return true;
    }
}

function updateDocLabels() {
    const type = document.getElementById('docType').value;
    document.getElementById('docNumLabel').innerText = (type === 'commercial') ? 'رقم السجل التجاري' : 'رقم وثيقة العمل الحر';
    document.getElementById('docFileLabel').innerText = (type === 'commercial') ? 'رفع صورة السجل التجاري' : 'رفع صورة الوثيقة';
}

function toggleVatSection() {
    document.getElementById('vatSection').classList.toggle('d-none', !document.getElementById('vatSwitch').checked);
}

function showReview() {
    const fd = new FormData(document.getElementById('regForm'));
    const html = `
        <div class="col-md-6"><div class="review-box"><h6>البيانات الشخصية</h6><p>الاسم: ${fd.get('first_name')} ${fd.get('last_name')}</p><p>الجوال: ${fd.get('phone')}</p><p>البريد: ${fd.get('email')}</p></div></div>
        <div class="col-md-6"><div class="review-box"><h6>بيانات المتجر</h6><p>المتجر: ${fd.get('store_name')}</p><p>الرابط: ${fd.get('store_link')}</p></div></div>
        <div class="col-md-12"><div class="review-box"><h6>التوثيق والبنك</h6><p><strong>رقم الوثيقة:</strong> ${fd.get('doc_number')}</p><p><strong>IBAN:</strong> ${fd.get('iban')}</p><p><strong>البنك:</strong> ${fd.get('bank_name')}</p><p><strong>المستفيد:</strong> ${fd.get('beneficiary_name')}</p></div></div>
    `;
    document.getElementById('reviewGrid').innerHTML = html;
    nextStep(4);
}

async function submitRegistration() {
    if (!document.getElementById('finalAgree').checked) {
        alert("يرجى الموافقة على الشروط والأحكام للمتابعة");
        return;
    }
    
    const btn = document.querySelector('.btn-success');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> جاري الإرسال...';
    
    const fd = new FormData(document.getElementById('regForm'));
    
    try {
        const response = await fetch('register.php', { method: 'POST', body: fd });
        const result = await response.json();
        
        if (result.status === 'success') {
            document.querySelector('.wizard-card').innerHTML = `
                <div class="text-center py-5">
                    <div class="mb-4"><i class="fas fa-check-circle" style="font-size: 100px; color: var(--p-green);"></i></div>
                    <h2 class="fw-bold">تم استلام طلبك بنجاح! 🎉</h2>
                    <p class="text-muted fs-5 mt-3">شكراً لك على الانضمام إلى TaKeedPay</p>
                    <div class="alert alert-info mt-4 mx-auto" style="max-width: 500px;">
                        <i class="fas fa-info-circle me-2"></i>
                        بياناتك قيد المراجعة والتدقيق. سيتم إشعارك عبر البريد والجوال فور تفعيل حسابك (2-5 أيام عمل).
                    </div>
                    <a href="status.php" class="btn btn-navy me-2">
                        <i class="fas fa-search me-1"></i> تحقق من حالتك
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-1"></i> العودة للرئيسية
                    </a>
                </div>`;
        } else {
            alert("❌ خطأ: " + result.message);
            btn.disabled = false;
            btn.innerHTML = 'تأكيد وإرسال الطلب';
        }
    } catch (err) {
        alert("❌ خطأ في الاتصال بالسيرفر. حاول مجدداً.");
        btn.disabled = false;
        btn.innerHTML = 'تأكيد وإرسال الطلب';
    }
}
</script>

</body>
</html>