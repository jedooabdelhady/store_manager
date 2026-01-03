<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد باي | TaKeedPay - منصة الربط الذكي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> <style>
        /* تحسينات خاصة بالرئيسية */
        .hero-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #fff 0%, #f0f4f8 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-logo {
            max-width: 250px;
            animation: floatLogo 3s ease-in-out infinite;
        }
        @keyframes floatLogo {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .feature-icon {
            font-size: 2.5rem;
            color: var(--green);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="logo.png" alt="TaKeedPay" style="height: 45px;">
            </a>
            <div class="ms-auto d-flex gap-3">
                <a href="login.php" class="btn btn-outline-primary rounded-3 fw-bold">دخول</a>
                <a href="register.php" class="btn btn-primary-custom rounded-3">انضم كتاجر</a>
            </div>
        </div>
    </nav>

    <section class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <img src="logo.png" alt="TaKeedPay Logo" class="hero-logo">
                    </div>
                    
                    <h1 class="display-5 fw-bold" style="color: var(--navy);">منصة الربط الأذكى لتوثيق الجدية</h1>
                    <p class="lead text-muted mb-4 mt-3">
                        اربط متجرك الإلكتروني بـ <span class="fw-bold text-success">TaKeedPay</span> مجاناً، 
                        واضمن جدية عملائك عبر نظام "العربون الذكي" المتوافق مع جميع المتاجر.
                    </p>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="register.php" class="btn btn-success-custom btn-lg shadow-lg px-5">
                            <i class="fas fa-store me-2"></i> سجل متجرك الآن
                        </a>
                        <a href="login.php" class="btn btn-outline-secondary btn-lg px-4 rounded-3">
                            <i class="fas fa-sign-in-alt me-2"></i> دخول التجار
                        </a>
                    </div>
                    
                    <div class="mt-4 text-muted small">
                        <i class="fas fa-check-circle text-success me-1"></i> مجاني 100%
                        <span class="mx-2">|</span>
                        <i class="fas fa-check-circle text-success me-1"></i> بدون اشتراكات
                        <span class="mx-2">|</span>
                        <i class="fas fa-check-circle text-success me-1"></i> تفعيل فوري
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="glass-card text-center h-100 border-0 shadow-sm bg-light">
                        <i class="fas fa-link feature-icon"></i>
                        <h4 style="color: var(--navy);">ربط مباشر ومجاني</h4>
                        <p class="text-muted">لا توجد رسوم تأسيس أو رسوم شهرية. خدمة الربط مجانية بالكامل لدعم التجار.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card text-center h-100 border-0 shadow-sm bg-light">
                        <i class="fas fa-calculator feature-icon"></i>
                        <h4 style="color: var(--navy);">شرائح عربون ذكية</h4>
                        <p class="text-muted">نظام يحسب العربون تلقائياً (من 20 إلى 99 ريال) بناءً على قيمة سلة العميل.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card text-center h-100 border-0 shadow-sm bg-light">
                        <i class="fas fa-shield-alt feature-icon"></i>
                        <h4 style="color: var(--navy);">ضمان وتوثيق</h4>
                        <p class="text-muted">قلل من الطلبات الوهمية واضمن حقك عبر توثيق جدية العميل بالدفع المسبق.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer style="background: var(--navy-dark); color: white;" class="py-4 mt-auto">
        <div class="container text-center">
            <div class="mb-3">
                <img src="logo.png" alt="Logo" style="height: 35px; filter: brightness(0) invert(1);">
            </div>
            <p class="small opacity-75 mb-0">جميع الحقوق محفوظة © 2026 TaKeedPay</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>