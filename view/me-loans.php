<?php
session_start();
require_once('./db_config.php');

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
    // يمكنك توجيه المستخدم لصفحة تسجيل الدخول إذا لم يتم العثور عليه
    session_destroy();
    header('Location: /login');
    exit;
}

$role = $user['role'];
if ($user && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
        exit;
    }
}

// جلب جميع الإعارات الخاصة بالمستخدم وتضمين due_date (الذي يمثل تاريخ الإرجاع الفعلي/المتوقع)
// ملاحظة: افترضت أن لديك عمود "due_date" أو "return_date" يمثل التاريخ المتوقع للإرجاع.
// سنعتبر أن 'return_date' هو التاريخ الفعلي للإرجاع، وإذا كان NULL فما زالت الإعارة نشطة.
$stmt = $db->prepare("
    SELECT loans.id AS loan_id, books.title AS book_title, books.status AS status_tag, books.author AS book_author, books.cover_image AS book_image, loans.loan_date, loans.due_date, loans.return_date 
    FROM loans
    INNER JOIN books ON loans.book_id = books.id
    WHERE loans.user_id = ?
    ORDER BY loans.loan_date DESC
");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
// حساب ملخص الإعارات (للوحة التحكم المصغرة)
$active_loans_count = 0;
$overdue_loans_count = 0;
$returned_loans_count = 0;

$today = new DateTime('today');



// دالة لتسجيل الخروج (يجب أن تكون موجودة في ملف منفصل أو جزء من هذا الملف)
// يمكنك استخدام كود JavaScript في الأسفل لتنفيذها
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>إعاراتي - مكتبتي</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f7f9fc;
        }

        .summary-card {
            border-left: 5px solid;
            border-radius: .5rem;
        }

        .summary-card.active-loans {
            border-color: #0d6efd;
        }

        .summary-card.overdue-loans {
            border-color: #dc3545;
        }

        .summary-card.returned-loans {
            border-color: #28a745;
        }

        .loan-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .loan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .cover {
            height: 240px;
            background-size: cover;
            background-position: center;
            border-bottom-left-radius: .5rem;
            border-bottom-right-radius: .5rem;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/"><i class="fas fa-book-open text-primary"></i> مكتبتي</a>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($user_id) { ?>

                    <div class="d-flex align-items-center">
                        <span class="navbar-text">مرحبا, <strong><?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($role); ?>)</strong></span>
                    </div>

                    <div class="dropdown">
                        <a href="/profile" class="btn btn-outline-secondary"><i class="fas fa-user"></i> الملف الشخصي</a>
                        <button class="btn btn-outline-danger" onclick="logout()"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</button>
                        <a href="/me-loans" class="btn btn-primary"><i class="fas fa-book"></i> إعارتي</a>
                    </div>
                    <?php if ($role === 'superadmin' || $role === 'gestionnaire'): ?>
                        <div class="dropdown">
                            <a href="/dashboard" class="btn btn-outline-secondary"><i class="fas fa-cog"></i> إدارة</a>
                        </div>
                    <?php endif; ?>
                <?php } ?>
            </div>
        </div>
    </nav>

    <div class="container mt-5">

        <h2 class="mb-4">📚 الإعارات</h2>


        <?php if (count($loans) > 0): ?>
            <div class="row g-4">
                <?php foreach ($loans as $loan):
                    $status_tag = $loan['status_tag'];
                    $badge_class = '';
                    $status_text = '';
                    $icon_class = '';
                    $card_border = '';

                    if ($status_tag == 'maear') {
                        $badge_class = 'bg-warning text-dark';
                        $status_text = 'قيد الإعارة';
                        $icon_class = 'fa-clock';
                        $card_border = 'border-primary';
                    } elseif ($status_tag == 'disponible') {
                        $badge_class = 'bg-success';
                        $status_text = 'تم الإرجاع';
                        $icon_class = 'fa-check';
                        $card_border = 'border-success';
                    } else {
                        $badge_class = 'bg-info';
                        $status_text = 'قيد الانتظار';
                        $icon_class = 'fa-exclamation-circle';
                        $card_border = 'border-danger';
                    }
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm loan-card <?= $card_border ?>" style="border-left: 4px solid;">
                            <div class="cover" style="background-image: url('<?= htmlspecialchars($loan['book_image']) ?>');"></div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h5 class="card-title text-primary"><?= htmlspecialchars($loan['book_title']) ?></h5>
                                    <span class="badge <?= $badge_class ?> fs-6 py-2 px-3">
                                        <i class="fas <?= $icon_class ?> me-1"></i> <?= $status_text ?>
                                    </span>
                                </div>
                                <h6 class="card-subtitle mb-3 text-muted">المؤلف: <?= htmlspecialchars($loan['book_author']) ?></h6>
                                <hr class="my-2">

                                <p class="card-text mb-1 small">
                                    <i class="fas fa-calendar-alt text-secondary me-1"></i>
                                    <strong>تاريخ الإعارة:</strong> <?= htmlspecialchars($loan['loan_date']) ?>
                                </p>

                                <p class="card-text mb-1 small <?= ($status_tag == 'overdue') ? 'text-danger' : '' ?>">
                                    <i class="fas fa-calendar-times text-secondary me-1"></i>
                                    <strong>الموعد الأقصى:</strong> <?= htmlspecialchars($loan['due_date'] ?? 'غير محدد') ?>
                                </p>

                                <?php if ($status_tag == 'returned'): ?>
                                    <p class="card-text mb-0 small text-success">
                                        <i class="fas fa-calendar-check text-success me-1"></i>
                                        <strong>تاريخ الإرجاع الفعلي:</strong> <?= htmlspecialchars($loan['return_date']) ?>
                                    </p>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center p-4">
                <i class="fas fa-inbox fa-3x mb-2"></i>
                <h4>لا توجد إعارات حاليًا</h4>
                <p>لم تقم بأي إعارات بعد. يمكنك تصفح <a href="/">المكتبة</a> لإضافة كتب.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function logout() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '?action=logout', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            window.location.href = '/';
                        } else {
                            Swal.fire('خطأ', response.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('خطأ', 'حدث خطأ غير متوقع في الاستجابة.', 'error');
                    }
                } else if (xhr.readyState === 4) {
                    Swal.fire('خطأ', 'فشل في تسجيل الخروج.', 'error');
                }
            };
            xhr.send();
        }
    </script>
</body>

</html>