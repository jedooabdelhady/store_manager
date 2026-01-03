<?php
session_start();

// 1. تفريغ بيانات السيشن
$_SESSION = array();

// 2. تدمير السيشن بالكامل
session_destroy();

// 3. مسح الكوكيز الخاصة بالسيشن (لضمان خروج نظيف 100%)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. العودة لصفحة الدخول
header("Location: index.php");
exit;
?>