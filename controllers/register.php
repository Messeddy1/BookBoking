<?php
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');

require_once('../db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'الطريقة غير مسموح بها.']);
    exit;
}

// اقرأ البيانات سواء كانت JSON أو form-data
$raw = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true) ?? [];
} else {
    $data = $_POST;
}

$fullname = trim($data['fullname'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirm = $data['confirm_password'] ?? '';
$role = 'adherent';

$errors = [];

// تحقق من القيم
if ($fullname === '') {
    $errors['fullname'] = 'الاسم الكامل مطلوب.';
}
if ($email === '') {
    $errors['email'] = 'البريد الإلكتروني مطلوب.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'الرجاء إدخال بريد إلكتروني صالح.';
}
if (strlen($password) < 6) {
    $errors['password'] = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل.';
}
if ($password !== $confirm) {
    $errors['confirm_password'] = 'كلمتا المرور غير متطابقتين.';
}

try {
    if (!$errors) {
        // فعل أخطاء PDO الاستثنائية
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // تأكد من عدم تكرار البريد
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'هذا البريد الإلكتروني مستخدم من قبل. الرجاء استخدام بريد آخر.';
        }
    }

    if ($errors) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }

    // إنشاء المستخدم
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (fullname, role, email, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fullname, $role, $email, $hash]);

    $_SESSION['user_id'] = $db->lastInsertId();

    echo json_encode(['status' => 'success', 'message' => 'تم إنشاء الحساب بنجاح!']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطأ غير متوقع. حاول لاحقاً.']);
}
