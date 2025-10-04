<?php
// books.php
session_start();
// تأكد من تحديث هذا المسار ليتناسب مع هيكل مشروعك
require_once('./db_config.php');

// -------------------------------------
// تأكد من صلاحية المستخدم
// -------------------------------------
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user = null;

if ($user_id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$role = $user ? $user['role'] : 'guest';

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

// ---------- إعدادات ----------
$perPage = 9;
// --------------------------------

// -------------------------------------
// ----- معالجة POST لإجراء الإعارة -----
// -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rent') {

    if (!$user_id) {
        $_SESSION['flash'] = "يجب أن تكون مسجلاً لإجراء الإعارة.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $book_id = intval($_POST['book_id']);
    $return_date = isset($_POST['return_date']) ? $_POST['return_date'] : null;
    $user_id_for_loan = $user_id;
    $is_admin_action = $user && ($user['role'] === 'superadmin' || $user['role'] === 'gestionnaire');
    $create_new_adherent = false; // تهيئة

    // 1. تحديد المستخدم الذي ستُسجَّل باسمه الإعارة (بما في ذلك إنشاء مستخدم جديد)
    if ($is_admin_action) {
        $selected_user_option = $_POST['selected_user_id'] ?? '';
        $create_new_adherent = isset($_POST['create_new_adherent']) && $_POST['create_new_adherent'] === '1';

        if (is_numeric($selected_user_option) && $selected_user_option !== '') {
            // حالة: تم اختيار مستخدم موجود
            $selected_user_id = intval($selected_user_option);
            $stmtUser = $db->prepare("SELECT id FROM users WHERE id=?");
            $stmtUser->execute([$selected_user_id]);
            if ($stmtUser->fetch(PDO::FETCH_ASSOC)) {
                $user_id_for_loan = $selected_user_id;
            } else {
                $_SESSION['flash'] = "المستخدم المختار غير موجود.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        } elseif ($create_new_adherent) {
            // حالة: إنشاء مستخدم جديد
            // $new_fullname = trim($_POST['new_fullname'] ?? '');
            $new_fullname = trim($_POST['new_fullname'] ?? '');
            $new_email = trim($_POST['new_email'] ?? '');

            if (empty($new_fullname) || empty($new_email)) {
                $_SESSION['flash'] = "يجب إدخال جميع بيانات المستعير الجديد.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash'] = "البريد الإلكتروني غير صالح.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }

            // تحقق من عدم التكرار
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE  email = ?");
            $stmtCheck->execute([$new_email]);
            if ($stmtCheck->fetchColumn() > 0) {
                $_SESSION['flash'] = " البريد الإلكتروني مستخدم بالفعل.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }

            // إنشاء المستخدم الجديد بكلمة مرور '0' مؤقتة
            $temp_password_hash = password_hash('password', PASSWORD_DEFAULT);
            $default_role = 'adherent';

            $stmtInsert = $db->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");

            if ($stmtInsert->execute([$new_fullname, $new_email, $temp_password_hash, $default_role])) {
                $user_id_for_loan = $db->lastInsertId();

                if ($user_id_for_loan) {
                    // تحديث كلمة المرور لتكون ID المستخدم (كما طلب المستخدم)
                    $new_id = $user_id_for_loan;
                    $new_password = "password" . $new_id;
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmtUpdate = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUpdate->execute([$new_password_hash, $new_id]);
                } else {
                    $_SESSION['flash'] = "فشل في إنشاء حساب مستعير جديد (لم يتم الحصول على ID).";
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            } else {
                $_SESSION['flash'] = "فشل في إنشاء حساب مستعير جديد (خطأ في الإدراج).";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }

    // **نقطة الإصلاح للخطأ 1452:** التحقق من أن ID المستخدم صالح
    if (empty($user_id_for_loan) || $user_id_for_loan <= 0) {
        $_SESSION['flash'] = "خطأ داخلي: لم يتم تحديد هوية المستعير بشكل صحيح.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }


    // 2. التحقق من التاريخ
    if (!$return_date) {
        $_SESSION['flash'] = "يجب اختيار تاريخ الإرجاع.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    $d = DateTime::createFromFormat('Y-m-d', $return_date);
    $today = new DateTime('today');
    if (!$d || $d < $today) {
        $_SESSION['flash'] = "تاريخ الإرجاع غير صالح أو يسبق اليوم.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 3. تحقق أن الكتاب موجود و متاح
    $stmtChk = $db->prepare("SELECT b.*,
        (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.return_date IS NULL) AS active_loans
        FROM books b WHERE b.id = ?");
    $stmtChk->execute([$book_id]);
    $bookRow = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if (!$bookRow) {
        $_SESSION['flash'] = "الكتاب غير موجود.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    if (intval($bookRow['active_loans']) > 0) {
        $_SESSION['flash'] = "الكتاب حالياً معار ولا يمكن إعارتُه الآن.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 4. أدخل الإعارة
    $loan_date = (new DateTime('today'))->format('Y-m-d');
    $due_date = (new DateTime($return_date))->modify('-1 day')->format('Y-m-d');
    $ins = $db->prepare("INSERT INTO loans (book_id, user_id, loan_date, due_date, return_date) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$book_id, $user_id_for_loan, $loan_date, $due_date, $return_date]);

    // جلب اسم المستخدم الذي تمت له الإعارة لعرضه في رسالة النجاح
    $stmtUserSuccess = $db->prepare("SELECT fullname, id FROM users WHERE id=?");
    $stmtUserSuccess->execute([$user_id_for_loan]);
    $loan_user_data = $stmtUserSuccess->fetch(PDO::FETCH_ASSOC);
    $loan_fullname = $loan_user_data['fullname'] ?? 'المستخدم';
    $loan_id = $loan_user_data['id'] ?? 'N/A';

    $success_message = "تمّ طلب الكتاب بنجاح بواسطة **" . htmlspecialchars($loan_fullname) . "** — آخر أجل الإرجاع: " . htmlspecialchars($return_date);

    if ($create_new_adherent) {
        $success_message .= ". تم إنشاء حساب جديد ($loan_fullname) برقم ID: $loan_id. كلمة المرور الافتراضية هي: **$new_password**.";
    }

    $_SESSION['flash'] = $success_message;
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
// -----------------------------------------

// جلب البيانات اللازمة للعرض
// -----------------------------------------
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) && $_GET['category'] !== '' ? intval($_GET['category']) : null;
$type = isset($_GET['type']) && $_GET['type'] !== '' ? intval($_GET['type']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// جلب أصناف و أنواع
$cats = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$types = $db->query("SELECT id, name, category_id FROM types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة المستخدمين
$adherents = [];
if ($user && ($user['role'] === 'superadmin' || $user['role'] === 'gestionnaire')) {
    $adherents = $db->query("SELECT id, fullname, fullname, email FROM users WHERE role = 'adherent' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
}


// بناء WHERE
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(b.title LIKE :q OR b.author LIKE :q OR b.custom_id LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($category) {
    $where[] = "b.category_id = :category";
    $params[':category'] = $category;
}
if ($type) {
    $where[] = "b.type_id = :type";
    $params[':type'] = $type;
}
if ($status === 'disponible' || $status === 'maear') {
    $where[] = "b.status = :status";
    $params[':status'] = $status;
}

$whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// اجمالي النتائج
$countStmt = $db->prepare("SELECT COUNT(*) FROM books b $whereSQL");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

// جلب الكتب
$sql = "SELECT b.*, c.name AS category_name, t.name AS type_name,
       (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.return_date IS NULL) AS active_loans
       FROM books b
       LEFT JOIN categories c ON c.id = b.category_id
       LEFT JOIN types t ON t.id = b.type_id
       $whereSQL
       ORDER BY b.title ASC
       LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// flash message
$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// helper cover
function cover_url($book)
{
    if (!empty($book['cover_image'])) {
        return htmlspecialchars($book['cover_image']);
    }

    // تأكد من أن هذا المسار صحيح
    return '/assets/placeHolder.webp';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المكتبة - عرض الكتب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: #f7f9fc;
        }

        .card-book {
            min-height: 440px;
            display: flex;
            flex-direction: column;
        }

        .card-book .cover {
            height: 240px;
            background-size: cover;
            background-position: center;
            border-bottom-left-radius: .5rem;
            border-bottom-right-radius: .5rem;
        }

        .truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .small-note {
            font-size: .9rem;
            color: #6c757d;
        }

        .filter-row .form-control,
        .filter-row .form-select {
            min-width: 180px;
        }

        .modal-cover {
            width: 120px;
            height: 170px;
            background-size: cover;
            background-position: center;
            border-radius: .4rem;
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
                        <span class="navbar-text">مرحبا, <strong><?php echo htmlspecialchars($role); ?></strong></span>
                    </div>

                    <div class="dropdown">
                        <a href="/profile" class="btn btn-outline-secondary"><i class="fas fa-user"></i> الملف الشخصي</a>
                        <button class="btn btn-outline-danger" onclick="logout()"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</button>
                        <a href="/me-loans" class="btn btn-outline-secondary"><i class="fas fa-book"></i> إعارتي</a>
                    </div>
                    <?php if ($role === 'superadmin' || $role === 'gestionnaire'): ?>
                        <div class="dropdown">
                            <a href="/dashboard" class="btn btn-outline-secondary"><i class="fas fa-cog"></i> إدارة</a>
                        </div>
                    <?php endif; ?>
                <?php } else { ?>
                    <div class="d-flex align-items-center">
                        <span class="navbar-text">مرحبا, <strong>زائر</strong></span>
                    </div>
                    <div class="dropdown">
                        <a href="/login" class="btn btn-outline-primary"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
                        <a href="/register" class="btn btn-outline-success"><i class="fas fa-user-plus"></i> إنشاء حساب</a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="mb-0">📚 مكتبتي</h3>
                <small class="text-muted">تصفّح الكتب، ابحث، أو فلتر حسب الأصناف والحالة</small>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <form id="filtersForm" method="get" class="card p-3 mb-4 filter-row">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <input autocomplete="off" type="search" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="ابحث عن عنوان، مؤلف أو رقم مخصص...">
                </div>

                <div class="col-auto">
                    <select name="category" id="categorySelect" class="form-select">
                        <option value="">كل الأصناف</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($category == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-auto">
                    <select name="type" id="typeSelect" class="form-select">
                        <option value="">كل الأنواع</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" data-cat="<?= $t['category_id'] ?>" <?= ($type == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="">كل الحالات</option>
                        <option value="maear" <?= ($status === 'maear') ? 'selected' : '' ?>>معار</option>
                        <option value="disponible" <?= ($status === 'disponible') ? 'selected' : '' ?>>متوفر</option>
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                    <a href="?" class="btn btn-outline-secondary">مسح</a>
                </div>
            </div>
        </form>

        <div class="row g-3">
            <?php if (count($books) === 0): ?>
                <div class="col-12">
                    <div class="card p-4 text-center">
                        <h5 class="mb-1">ماكاين حتى كتاب حسب المعايير ديالك.</h5>
                        <p class="small text-muted">جرّب تغيّر شروط البحث أو ازل الفلاتر.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($books as $b):
                $isLoaned = $b['status'] === 'maear';
                $cover = cover_url($b);
            ?>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="card card-book shadow-sm">
                        <div class="cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');"></div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1" title="<?= htmlspecialchars($b['title']) ?>"><?= htmlspecialchars($b['title']) ?></h5>
                            <p class="small-note mb-1">المؤلف: <?= htmlspecialchars($b['author'] ?: 'غير محدد') ?></p>
                            <p class="small-note mb-1">الرمز: <?= htmlspecialchars($b['custom_id'] ?: $b['id']) ?></p>
                            <p class="mb-2">
                                <span class="badge <?= $isLoaned ? 'bg-danger' : 'bg-success' ?>"><?= $isLoaned ? 'معار' : 'متوفر' ?></span>
                                <span class="badge bg-secondary"><?= htmlspecialchars($b['category_name'] ?: '—') ?></span>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($b['type_name'] ?: '—') ?></span>
                            </p>

                            <p class="card-text truncate-2"><?= htmlspecialchars($b['excerpts'] ?: $b['notes'] ?: 'لا وصف متاح.') ?></p>

                            <div class="mt-auto d-flex gap-2">
                                <button
                                    class="btn btn-outline-primary btn-sm flex-grow-1 details-btn"
                                    data-book='<?= htmlspecialchars(json_encode([
                                                    'id' => $b['id'],
                                                    'title' => $b['title'],
                                                    'author' => $b['author'],
                                                    'category' => $b['category_name'],
                                                    'type' => $b['type_name'],
                                                    'custom_id' => $b['custom_id'],
                                                    'status' => $b['status'],
                                                    'notes' => $b['notes'],
                                                    'excerpts' => $b['excerpts'],
                                                    'cover' => $cover
                                                ], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                                    <i class="fa fa-info-circle"></i> عرض التفاصيل
                                </button>


                                <?php if ($user_id): ?>
                                    <button
                                        class="btn btn-<?= $isLoaned ? 'secondary' : 'success' ?> btn-sm rent-btn"
                                        data-book='<?= htmlspecialchars(json_encode([
                                                        'id' => $b['id'],
                                                        'title' => $b['title'],
                                                        'author' => $b['author'],
                                                        'cover' => $cover,
                                                        'isLoaned' => $isLoaned
                                                    ], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                        <?= $isLoaned ? 'disabled' : '' ?>>
                                        <i class="fa fa-book"></i> <?= $isLoaned ? 'غير متاح' : 'إعارة' ?>
                                    </button>
                                <?php else: ?>
                                    <a href="/login" class="btn btn-outline-success btn-sm">سجّل للإعارة</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                $baseParams = $_GET;
                unset($baseParams['page']);
                function buildPageUrl($p, $baseParams)
                {
                    $baseParams['page'] = $p;
                    return '?' . http_build_query($baseParams);
                }
                $start = max(1, $page - 3);
                $end = min($pages, $page + 3);
                if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= buildPageUrl($page - 1, $baseParams) ?>">&laquo; السابق</a></li>
                <?php endif;
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>"><a class="page-link" href="<?= buildPageUrl($i, $baseParams) ?>"><?= $i ?></a></li>
                <?php endfor;
                if ($page < $pages): ?>
                    <li class="page-item"><a class="page-link" href="<?= buildPageUrl($page + 1, $baseParams) ?>">التالي &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    </div>

    <div class="modal fade" id="rentModal" tabindex="-1" aria-labelledby="rentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" dir="rtl">
                <div class="modal-header">
                    <h5 class="modal-title" id="rentModalLabel">تأكيد إعارة الكتاب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <form id="rentForm" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="rent">
                    <input type="hidden" name="book_id" id="modal_book_id" value="">
                    <div class="modal-body">
                        <div class="d-flex gap-3 align-items-start">
                            <div id="modal_cover" class="modal-cover" style="background-image: url('');"></div>
                            <div class="flex-grow-1">
                                <h6 id="modal_title" class="mb-1"></h6>
                                <p class="small-note mb-2" id="modal_author"></p>

                                <?php if ($user && ($user['role'] === 'superadmin' || $user['role'] === 'gestionnaire')): ?>
                                    <div class="mb-3 d-flex align-items-end gap-2">
                                        <div class="flex-grow-1">
                                            <label for="modal_selected_user_id" class="form-label">المستعير</label>
                                            <select name="selected_user_id" id="modal_selected_user_id" class="form-select">
                                                <option value="">(المستخدم الحالي: <?= htmlspecialchars($user['fullname'] ?? '—') ?>)</option>
                                                <option disabled>–––––––––––––––––––</option>
                                                <?php foreach ($adherents as $adherent): ?>
                                                    <option value="<?= $adherent['id'] ?>"><?= htmlspecialchars($adherent['fullname']) ?> | <?= htmlspecialchars($adherent['fullname']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="small text-muted mt-1">إذا لم يتم الاختيار، فستُسجَّل الإعارة باسمك.</p>
                                        </div>
                                        <button type="button" id="addNewAdherentBtn" class="btn btn-outline-primary" title="إضافة مستعير جديد">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    </div>

                                    <div class="card p-3 mb-3 border-primary" id="newAdherentInputGroup" style="display:none;">
                                        <h6 class="card-title text-primary"><i class="fas fa-user-plus"></i> مستعير جديد</h6>
                                        <input type="hidden" name="create_new_adherent" id="create_new_adherent_flag" value="0">

                                        <!-- <div class="mb-2">
                                            <label for="new_fullname" class="form-label small">اسم المستخدم</label>
                                            <input type="text" name="new_fullname" id="new_fullname" class="form-control form-control-sm" placeholder="مثل: ahmed.l">
                                            <div class="invalid-feedback">مطلوب.</div>
                                        </div> -->
                                        <div class="mb-2">
                                            <label for="new_fullname" class="form-label small">الاسم الكامل</label>
                                            <input type="text" name="new_fullname" id="new_fullname" class="form-control form-control-sm" placeholder="مثل: أحمد العزاوي">
                                            <div class="invalid-feedback">مطلوب.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label for="new_email" class="form-label small">البريد الإلكتروني</label>
                                            <input type="email" name="new_email" id="new_email" class="form-control form-control-sm" placeholder="مثل: ahmed@example.com">
                                            <div class="invalid-feedback">مطلوب وصيغته صحيحة.</div>
                                        </div>
                                        <p class="small text-danger mt-1 mb-0">كلمة المرور الافتراضية ستكون ID المستخدم الجديد.</p>
                                        <button type="button" id="cancelNewAdherentBtn" class="btn btn-sm btn-outline-secondary mt-2">إلغاء الإضافة</button>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <label class="form-label">تاريخ الإرجاع</label>
                                    <input type="date" name="return_date" id="modal_return_date" class="form-control" required>
                                    <div class="invalid-feedback">من فضلك اختر تاريخ الإرجاع (اليوم أو بعده).</div>
                                </div>
                                <p class="small text-muted" id="modal_note">النظام يفرض أن تاريخ الإرجاع لا يكون قبل اليوم.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" id="modal_confirm_btn" class="btn btn-success">تأكيد الإعارة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" dir="rtl">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailsModalLabel"><i class="fas fa-eye me-2"></i> تفاصيل الكتاب</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <img id="details_cover" src="/assets/placeHolder.webp" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: cover;" alt="غلاف الكتاب">
                        </div>
                        <div class="col-md-8">
                            <h3 class="mb-3" id="details_title"></h3>
                            <p class="text-muted">المؤلف: <strong id="details_author"></strong></p>
                            <hr>
                            <div class="row g-3 details-list">
                                <div class="col-md-6"><small class="text-secondary">المعرّف المخصص:</small><br><strong id="details_custom_id"></strong></div>
                                <div class="col-md-6"><small class="text-secondary">رقم قاعدة البيانات (ID):</small><br><strong id="details_id"></strong></div>
                                <div class="col-md-6"><small class="text-secondary">الصنف:</small><br><strong id="details_category"></strong></div>
                                <div class="col-md-6"><small class="text-secondary">النوع:</small><br><strong id="details_type"></strong></div>
                                <div class="col-12"><small class="text-secondary">الحالة:</small><br><span id="details_status" class="badge"></span></div>
                            </div>
                            <hr>
                            <div class="mt-3">
                                <h6><i class="fas fa-list-alt me-1"></i> الملاحظات:</h6>
                                <p id="details_notes" class="text-break"></p>
                            </div>
                            <div class="mt-3">
                                <h6><i class="fas fa-quote-right me-1"></i> مقتطفات:</h6>
                                <p id="details_excerpts" class="text-break"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Details modal logic
        const detailsModalEl = document.getElementById('detailsModal');
        const detailsModal = new bootstrap.Modal(detailsModalEl);

        const detailsButtons = document.querySelectorAll('.details-btn');
        const detailsCover = document.getElementById('details_cover');
        const detailsTitle = document.getElementById('details_title');
        const detailsAuthor = document.getElementById('details_author');
        const detailsId = document.getElementById('details_id');
        const detailsCategory = document.getElementById('details_category');
        const detailsType = document.getElementById('details_type');
        const detailsCustomId = document.getElementById('details_custom_id');
        const detailsStatus = document.getElementById('details_status');
        const detailsNotes = document.getElementById('details_notes');
        const detailsExcerpts = document.getElementById('details_excerpts');

        detailsButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                try {
                    const book = JSON.parse(btn.getAttribute('data-book'));
                    detailsCover.src = book.cover;
                    detailsTitle.textContent = book.title;
                    detailsAuthor.textContent = book.author || 'غير محدد';
                    detailsId.textContent = book.id;
                    detailsCategory.textContent = book.category || '—';
                    detailsType.textContent = book.type || '—';
                    detailsCustomId.textContent = book.custom_id || book.id;
                    detailsStatus.className = 'badge ' + (book.status === 'disponible' ? 'bg-success' : 'bg-warning text-dark');
                    detailsStatus.textContent = book.status === 'disponible' ? 'متوفر' : 'معار';
                    detailsNotes.textContent = book.notes || 'لا توجد ملاحظات.';
                    detailsExcerpts.textContent = book.excerpts || 'لا توجد مقتطفات.';
                    detailsModal.show();
                } catch (err) {
                    console.error('خطأ في بيانات الكتاب:', err);
                }
            });
        });

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

        (function() {
            // filter types by category
            const categorySelect = document.getElementById('categorySelect');
            const typeSelect = document.getElementById('typeSelect');

            const initialTypeEl = typeSelect.querySelector(`option[value="${"<?= $type ?>"}"]`);
            const initialTypeCat = initialTypeEl ? initialTypeEl.dataset.cat : '';

            const originalOptions = Array.from(typeSelect.options).map(opt => ({
                value: opt.value,
                text: opt.text,
                cat: opt.dataset.cat || ''
            }));

            function filterTypes() {
                const cat = categorySelect.value;
                const currentSelectedType = typeSelect.value;

                typeSelect.innerHTML = '';

                const optAll = document.createElement('option');
                optAll.value = '';
                optAll.text = 'كل الأنواع';
                typeSelect.appendChild(optAll);

                let reselected = false;

                originalOptions.forEach(o => {
                    if (o.value === '') return;
                    if (!cat || o.cat === cat) {
                        const el = document.createElement('option');
                        el.value = o.value;
                        el.text = o.text;
                        el.dataset.cat = o.cat;

                        if (currentSelectedType === o.value) {
                            el.selected = true;
                            reselected = true;
                        }

                        if (!cat && "<?= $type ?>" === o.value) {
                            el.selected = true;
                            reselected = true;
                        }

                        typeSelect.appendChild(el);
                    }
                });

                if (!reselected && currentSelectedType && cat) {
                    typeSelect.value = '';
                }
            }
            categorySelect.addEventListener('change', filterTypes);
            filterTypes();


            // Modal logic for Rent
            const rentModalEl = document.getElementById('rentModal');
            const rentModal = new bootstrap.Modal(rentModalEl);
            const rentButtons = document.querySelectorAll('.rent-btn');

            const modalCover = document.getElementById('modal_cover');
            const modalTitle = document.getElementById('modal_title');
            const modalAuthor = document.getElementById('modal_author');
            const modalBookId = document.getElementById('modal_book_id');
            const modalReturnDate = document.getElementById('modal_return_date');
            const modalConfirmBtn = document.getElementById('modal_confirm_btn');
            const rentForm = document.getElementById('rentForm');

            // Admin/New Adherent Fields
            const modalSelectedUserId = document.getElementById('modal_selected_user_id');
            const newAdherentInputGroup = document.getElementById('newAdherentInputGroup');
            const createNewAdherentFlag = document.getElementById('create_new_adherent_flag');
            const addNewAdherentBtn = document.getElementById('addNewAdherentBtn');
            const cancelNewAdherentBtn = document.getElementById('cancelNewAdherentBtn');
            // const newfullnameInput = document.getElementById('new_fullname');
            const newFullnameInput = document.getElementById('new_fullname');
            const newEmailInput = document.getElementById('new_email');


            // helper to format date to YYYY-MM-DD
            function yyyy_mm_dd(date) {
                const y = date.getFullYear();
                const m = ('0' + (date.getMonth() + 1)).slice(-2);
                const d = ('0' + date.getDate()).slice(-2);
                return y + '-' + m + '-' + d;
            }

            // set default return_date to today + 14 days
            function defaultReturnDate() {
                const d = new Date();
                d.setDate(d.getDate() + 14);
                return yyyy_mm_dd(d);
            }

            // Function to reset new adherent fields
            function resetNewAdherentFields() {
                if (newAdherentInputGroup) {
                    newAdherentInputGroup.style.display = 'none';
                    createNewAdherentFlag.value = '0';
                    // newfullnameInput.removeAttribute('required');
                    newFullnameInput.removeAttribute('required');
                    newEmailInput.removeAttribute('required');
                    // newfullnameInput.classList.remove('is-invalid');
                    newFullnameInput.classList.remove('is-invalid');
                    newEmailInput.classList.remove('is-invalid');
                    // newfullnameInput.value = '';
                    newFullnameInput.value = '';
                    newEmailInput.value = '';
                }
            }

            // Event listeners for New Adherent Buttons
            if (addNewAdherentBtn) {
                addNewAdherentBtn.addEventListener('click', function() {
                    newAdherentInputGroup.style.display = 'block';
                    createNewAdherentFlag.value = '1';
                    if (modalSelectedUserId) modalSelectedUserId.value = ''; // Clear selection

                    // Set required for new fields
                    // newfullnameInput.setAttribute('required', 'required');
                    newFullnameInput.setAttribute('required', 'required');
                    newEmailInput.setAttribute('required', 'required');
                });
            }

            if (cancelNewAdherentBtn) {
                cancelNewAdherentBtn.addEventListener('click', resetNewAdherentFields);
            }

            // Event listener for Adherent Select
            if (modalSelectedUserId) {
                modalSelectedUserId.addEventListener('change', function() {
                    if (this.value !== '') {
                        resetNewAdherentFields(); // If an existing user is selected, hide and reset new adherent fields
                    }
                });
            }


            rentButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    try {
                        const dataAttr = btn.getAttribute('data-book');
                        const info = JSON.parse(dataAttr);

                        if (info.isLoaned) {
                            btn.disabled = true;
                            return;
                        }

                        modalCover.style.backgroundImage = `url('${info.cover}')`;
                        modalTitle.textContent = info.title || '—';
                        modalAuthor.textContent = info.author ? 'المؤلف: ' + info.author : 'المؤلف: غير محدد';
                        modalBookId.value = info.id;
                        modalReturnDate.value = defaultReturnDate();

                        // Reset admin fields on modal open
                        if (modalSelectedUserId) {
                            modalSelectedUserId.value = '';
                            resetNewAdherentFields();
                        }

                        // ensure the confirm button enabled
                        modalConfirmBtn.disabled = false;

                        rentModal.show();
                    } catch (err) {
                        console.error('خطأ فقراءة بيانات الكتاب:', err);
                    }
                });
            });

            // form validation
            rentForm.addEventListener('submit', function(ev) {
                let isValid = true;

                // 1. Validate Return Date
                const rdateVal = modalReturnDate.value;
                if (!rdateVal) {
                    modalReturnDate.classList.add('is-invalid');
                    isValid = false;
                } else {
                    const sel = new Date(rdateVal);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    if (sel < today) {
                        modalReturnDate.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        modalReturnDate.classList.remove('is-invalid');
                    }
                }

                // 2. Validate New Adherent fields (if flag is set)
                if (createNewAdherentFlag && createNewAdherentFlag.value === '1') {
                    // if (newfullnameInput.value.trim() === '') {
                    //     newfullnameInput.classList.add('is-invalid');
                    //     isValid = false;
                    // } else {
                    //     newfullnameInput.classList.remove('is-invalid');
                    // }

                    if (newFullnameInput.value.trim() === '') {
                        newFullnameInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        newFullnameInput.classList.remove('is-invalid');
                    }

                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(newEmailInput.value.trim())) {
                        newEmailInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        newEmailInput.classList.remove('is-invalid');
                    }

                    // If trying to create a new adherent, ensure no existing adherent is selected
                    if (modalSelectedUserId && modalSelectedUserId.value !== '') {
                        modalSelectedUserId.value = '';
                    }
                }

                if (!isValid) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }
            });

            // clear invalid on change
            modalReturnDate.addEventListener('change', function() {
                modalReturnDate.classList.remove('is-invalid');
            });

            // if (newfullnameInput) {
            //     newfullnameInput.addEventListener('input', function() {
            //         this.classList.remove('is-invalid');
            //     });
            // }
            if (newFullnameInput) {
                newFullnameInput.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            }
            if (newEmailInput) {
                newEmailInput.addEventListener('input', function() {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (emailRegex.test(this.value.trim())) {
                        this.classList.remove('is-invalid');
                    }
                });
            }

        })();
    </script>
</body>

</html>