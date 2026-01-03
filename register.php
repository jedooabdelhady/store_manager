<?php
require_once 'config.php';

// --- الجزء الأول: معالجة البيانات عند الإرسال النهائي ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'final_submit') {
    header('Content-Type: application/json');
    
    // 1. التحقق من قوة كلمة المرور برمجياً (زيادة أمان)
    $password_raw = $_POST['password'];
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{10,}$/', $password_raw)) {
        echo json_encode(['status' => 'error', 'message' => 'عذراً، كلمة المرور لا تستوفي الشروط (10 خانات، حرف كبير، ورقم).']);
        exit;
    }

    // 2. تنظيف وتجهيز البيانات
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $password   = password_hash($password_raw, PASSWORD_DEFAULT);
    
    $store_name       = mysqli_real_escape_string($conn, $_POST['store_name']);
    $store_link       = mysqli_real_escape_string($conn, $_POST['store_link']);
    $doc_type         = mysqli_real_escape_string($conn, $_POST['doc_type']);
    $doc_number       = mysqli_real_escape_string($conn, $_POST['doc_number']);
    $doc_expiry       = mysqli_real_escape_string($conn, $_POST['doc_expiry']);
    $national_address = mysqli_real_escape_string($conn, $_POST['national_address']);
    
    $is_vat     = isset($_POST['is_vat']) ? 1 : 0;
    $vat_number = ($is_vat) ? mysqli_real_escape_string($conn, $_POST['vat_number']) : NULL;
    
    $bank_name   = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $iban        = mysqli_real_escape_string($conn, $_POST['iban']);
    $swift       = mysqli_real_escape_string($conn, $_POST['swift_code']);
    $beneficiary = mysqli_real_escape_string($conn, $_POST['beneficiary_name']);

    // دالة رفع الملفات إلى مجلد uploads
    function uploadFile($fileKey) {
        if(isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0){
            $fileName = time() . "_" . rand(100,999) . "_" . basename($_FILES[$fileKey]["name"]);
            $targetPath = "uploads/" . $fileName;
            if(move_uploaded_file($_FILES[$fileKey]["tmp_name"], $targetPath)) {
                return $targetPath;
            }
        }
        return NULL;
    }

    $doc_img  = uploadFile('doc_image');
    $vat_img  = uploadFile('vat_image');
    $iban_img = uploadFile('iban_image');

    // 3. إدخال البيانات في قاعدة البيانات
    $sql = "INSERT INTO merchants (
        first_name, last_name, email, phone, password, 
        store_name, store_link, doc_type, doc_number, doc_expiry, doc_image, national_address,
        is_vat_registered, vat_number, vat_image,
        bank_name, iban, swift_code, beneficiary_name, iban_image
    ) VALUES (
        '$first_name', '$last_name', '$email', '$phone', '$password',
        '$store_name', '$store_link', '$doc_type', '$doc_number', '$doc_expiry', '$doc_img', '$national_address',
        '$is_vat', '$vat_number', '$vat_img',
        '$bank_name', '$iban', '$swift', '$beneficiary', '$iban_img'
    )";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
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

        /* Steps Indicator */
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

        .btn-navy { background: var(--p-navy); color: white; padding: 12px 35px; border-radius: 10px; font-weight: 600; border: none; transition: 0.3s; }
        .btn-navy:hover { background: #003663; color: white; transform: translateY(-2px); }
        
        .pass-strength-bar { height: 6px; background: #eee; border-radius: 3px; margin-top: 10px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; transition: 0.4s; }
        
        .review-box { background: #fdfdfd; border: 1px solid #edf2f7; border-radius: 15px; padding: 25px; margin-bottom: 20px; position: relative; }
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
            
            <div class="form-step active" id="step1">
                <h5 class="mb-4"><i class="fas fa-user-circle me-2 text-primary"></i> بيانات مدير الحساب</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">الاسم الأول</label>
                        <input type="text" class="form-control form-control-lg" name="first_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">الاسم الأخير</label>
                        <input type="text" class="form-control form-control-lg" name="last_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رقم الجوال</label>
                        <input type="text" class="form-control form-control-lg" name="phone" placeholder="05xxxxxxxx" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">البريد الإلكتروني</label>
                        <input type="email" class="form-control form-control-lg" name="email" required>
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

            <div class="form-step" id="step2">
                <h5 class="mb-4"><i class="fas fa-store me-2 text-primary"></i> بيانات المنشأة والتوثيق</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم المتجر</label>
                        <input type="text" class="form-control form-control-lg" name="store_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رابط المتجر الإلكتروني</label>
                        <input type="url" class="form-control form-control-lg" name="store_link" placeholder="https://..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">نوع وثيقة التوثيق</label>
                        <select class="form-select form-select-lg" name="doc_type" id="docType" onchange="updateDocLabels()">
                            <option value="commercial">سجل تجاري</option>
                            <option value="freelance">وثيقة عمل حر</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" id="docNumLabel">رقم السجل التجاري</label>
                        <input type="text" class="form-control form-control-lg" name="doc_number" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">تاريخ انتهاء الوثيقة</label>
                        <input type="date" class="form-control form-control-lg" name="doc_expiry" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" id="docFileLabel">رفع ملف السجل التجاري</label>
                        <input type="file" class="form-control" name="doc_image" accept="image/*,application/pdf" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">العنوان الوطني</label>
                        <input type="text" class="form-control form-control-lg" name="national_address" required>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch p-3 border rounded">
                            <input class="form-check-input ms-0 me-3" type="checkbox" id="vatSwitch" name="is_vat" onchange="toggleVatSection()">
                            <label class="form-check-label fw-bold">المنشأة مسجلة في ضريبة القيمة المضافة (VAT)</label>
                        </div>
                    </div>
                    <div id="vatSection" class="row g-4 d-none mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">الرقم الضريبي</label>
                            <input type="text" class="form-control form-control-lg" name="vat_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">شهادة تسجيل الضريبة</label>
                            <input type="file" class="form-control" name="vat_image" accept="image/*,application/pdf">
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-5">
                    <button type="button" class="btn btn-outline-secondary px-5" onclick="prevStep(1)">سابق</button>
                    <button type="button" class="btn btn-navy btn-lg" onclick="nextStep(3)">التالي: البيانات البنكية <i class="fas fa-chevron-left ms-2"></i></button>
                </div>
            </div>

            <div class="form-step" id="step3">
                <h5 class="mb-4"><i class="fas fa-university me-2 text-primary"></i> بيانات الحساب البنكي (للتسويات)</h5>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i> **تنبيه:** يجب أن يطابق اسم المستفيد الاسم الموجود في السجل التجاري أو الوثيقة لضمان قبول الطلب.
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم البنك</label>
                        <input type="text" class="form-control form-control-lg" name="bank_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رقم الآيبان (IBAN)</label>
                        <input type="text" class="form-control form-control-lg" name="iban" placeholder="SA..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">رمز السويفت (SWIFT)</label>
                        <input type="text" class="form-control form-control-lg" name="swift_code" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">اسم المستفيد الكامل</label>
                        <input type="text" class="form-control form-control-lg" name="beneficiary_name" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">رفع صورة شهادة الآيبان</label>
                        <input type="file" class="form-control" name="iban_image" accept="image/*" required>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-5">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep(2)">سابق</button>
                    <button type="button" class="btn btn-navy btn-lg" onclick="showReview()">مراجعة وإرسال الطلب <i class="fas fa-chevron-left ms-2"></i></button>
                </div>
            </div>

            <div class="form-step" id="step4">
                <h4 class="text-center mb-4">مراجعة بيانات الانضمام</h4>
                <div id="reviewGrid" class="row">
                    </div>
                <div class="form-check bg-light p-4 rounded-4 mt-4 border">
                    <input class="form-check-input ms-2" type="checkbox" id="finalAgree" required>
                    <label class="form-check-label fw-bold" for="finalAgree">
                        أقر بصحة جميع البيانات المدخلة وأني مفوض بالتسجيل نيابة عن المنشأة المذكورة وأتحمل المسؤولية القانونية تجاه ذلك.
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
}

function nextStep(s) {
    if (currentStep === 1 && !checkPass()) {
        alert("يرجى التأكد من استيفاء شروط كلمة المرور (10 خانات، حرف كبير، ورقم).");
        return;
    }
    showStep(s);
}

function prevStep(s) { showStep(s); }

function checkPass() {
    const p = document.getElementById('passInput').value;
    const fill = document.getElementById('strengthFill');
    const txt = document.getElementById('passText');
    
    const hasUpper = /[A-Z]/.test(p);
    const hasNumber = /[0-9]/.test(p);
    const isLong = p.length >= 10;

    if (!isLong) {
        fill.style.width = '30%'; fill.style.backgroundColor = '#ef4444';
        txt.innerHTML = '<span class="text-danger">كلمة المرور قصيرة جداً (أقل من 10)</span>';
        return false;
    } else if (!hasUpper || !hasNumber) {
        fill.style.width = '60%'; fill.style.backgroundColor = '#f59e0b';
        txt.innerHTML = '<span class="text-warning">يجب إضافة حرف كبير واحد ورقم واحد على الأقل</span>';
        return false;
    } else {
        fill.style.width = '100%'; fill.style.backgroundColor = '#10b981';
        txt.innerHTML = '<span class="text-success fw-bold">كلمة مرور قوية ومثالية ✅</span>';
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
        <div class="col-md-6"><div class="review-box"><h6>البيانات الشخصية</h6><p>الاسم: ${fd.get('first_name')} ${fd.get('last_name')}</p><p>الجوال: ${fd.get('phone')}</p></div></div>
        <div class="col-md-6"><div class="review-box"><h6>بيانات المتجر</h6><p>المتجر: ${fd.get('store_name')}</p><p>الرابط: ${fd.get('store_link')}</p></div></div>
        <div class="col-md-12"><div class="review-box"><h6>التوثيق والبنك</h6><p>رقم الوثيقة: ${fd.get('doc_number')}</p><p>IBAN: ${fd.get('iban')}</p><p>البنك: ${fd.get('bank_name')}</p></div></div>
    `;
    document.getElementById('reviewGrid').innerHTML = html;
    nextStep(4);
}

async function submitRegistration() {
    if(!document.getElementById('finalAgree').checked) return alert("يرجى التوقيع على الإقرار للمتابعة");
    
    const btn = document.querySelector('.btn-success');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> جاري الإرسال...';
    
    const fd = new FormData(document.getElementById('regForm'));
    fd.append('action', 'final_submit');
    
    try {
        const response = await fetch('register.php', { method: 'POST', body: fd });
        const result = await response.json();
        
        if (result.status === 'success') {
            document.querySelector('.wizard-card').innerHTML = `
                <div class="text-center py-5">
                    <div class="mb-4"><i class="fas fa-check-circle" style="font-size: 100px; color: var(--p-green);"></i></div>
                    <h2 class="fw-bold">تم استلام طلبك بنجاح!</h2>
                    <p class="text-muted fs-5 mt-3">بياناتك الآن قيد المراجعة والتدقيق من قبل فريق العمل.</p>
                    <div class="alert alert-info mt-4 mx-auto" style="max-width: 500px;">سيتم إشعارك عبر البريد الإلكتروني فور تفعيل حسابك (خلال 2-5 أيام عمل).</div>
                    <a href="index.php" class="btn btn-navy mt-4 btn-lg">العودة للرئيسية</a>
                </div>`;
        } else {
            alert("حدث خطأ: " + result.message);
            btn.disabled = false; btn.innerText = 'تأكيد وإرسال الطلب';
        }
    } catch (err) {
        alert("حدث خطأ في الاتصال بالسيرفر، يرجى المحاولة لاحقاً.");
        btn.disabled = false;
    }
}
</script>

</body>
</html>