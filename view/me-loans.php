<?php
session_start();
require_once('./db_config.php'); // تأكد أن هذا الملف يحتوي على PDO $db

// التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];

// جلب بيانات المستخدم
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "المستخدم غير موجود.";
    exit;
}

// جلب جميع الإعارات الخاصة بالمستخدم
$stmt = $db->prepare("
    SELECT loans.id AS loan_id, books.title AS book_title, books.author AS book_author, loans.loan_date, loans.return_date, loans.status
    FROM loans
    INNER JOIN books ON loans.book_id = books.id
    WHERE loans.user_id = ?
    ORDER BY loans.loan_date DESC
");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>جميع الإعارات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">جميع الإعارات الخاصة بك</h2>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-layer-group"></i> الإعارات</li>
            </ol>
        </nav>
        <?php if (count($loans) > 0): ?>
            <div class="row g-4">
                <?php foreach ($loans as $loan): ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($loan['book_title']) ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($loan['book_author']) ?></h6>
                                <p class="card-text mb-1"><strong>تاريخ الإعارة:</strong> <?= htmlspecialchars($loan['loan_date']) ?></p>
                                <p class="card-text mb-1"><strong>تاريخ الإرجاع:</strong> <?= htmlspecialchars($loan['return_date']) ?></p>
                                <p class="card-text">
                                    <strong>الحالة:</strong>
                                    <?php
                                    if ($loan['status'] == 'returned') echo '<span class="badge bg-success">تم الإرجاع</span>';
                                    else echo '<span class="badge bg-warning text-dark">قيد الإعارة</span>';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">لم تقم بأي إعارات بعد.</div>
        <?php endif; ?>
    </div>
</body>

</html>