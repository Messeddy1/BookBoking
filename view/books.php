<?php
// books.php
session_start();
require_once('./db_config.php'); // path صحيح لقاعدة البيانات

// تأكد من صلاحية المستخدم

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // header('Location: /login');


    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        $action = $_GET['action'];
        if ($action === 'logout') {
            // Destroy the session to log out the user
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
            exit;
        }
    }
}
if ($user) {
    $role = $user['role'];
    # code...
}
// ---------- إعدادات ----------
$perPage = 9;
// --------------------------------

// ----- معالجة POST لإجراء الإعارة -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rent') {
    // تحقق أن المستخدم مسجّل
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash'] = "خاصك تكون مسجّل باش تدير الإعارة.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $book_id = intval($_POST['book_id']);
    $user_id = intval($_SESSION['user_id']);
    $return_date = isset($_POST['return_date']) ? $_POST['return_date'] : null;

    // validate return_date
    if (!$return_date) {
        $_SESSION['flash'] = "خاصك تختار تاريخ الإرجاع.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    // ensure date format is YYYY-MM-DD and >= today
    $d = DateTime::createFromFormat('Y-m-d', $return_date);
    $today = new DateTime('today');
    if (!$d) {
        $_SESSION['flash'] = "تاريخ الإرجاع غير صالح.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    if ($d < $today) {
        $_SESSION['flash'] = "تاريخ الإرجاع يجب أن يكون اليوم أو بعده.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // تحقق أن الكتاب موجود و متاح (مافيهش loan مفتوح)
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

    // أدخل الإعارة
    $loan_date = (new DateTime('today'))->format('Y-m-d');
    // make the due_date is deferet betwen loan_date and return_date by number of days
    $due_date = (new DateTime($return_date))->modify('-1 day')->format('Y-m-d');
    $ins = $db->prepare("INSERT INTO loans (book_id, user_id, loan_date, due_date, return_date) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$book_id, $user_id, $loan_date, $due_date, $return_date]);

    $_SESSION['flash'] = "تمّت الإعارة بنجاح — آخر أجل الإرجاع: " . htmlspecialchars($return_date);
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
// -----------------------------------------

// جلب فلترز من GET
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) && $_GET['category'] !== '' ? intval($_GET['category']) : null;
$type = isset($_GET['type']) && $_GET['type'] !== '' ? intval($_GET['type']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : ''; // available | loaned | ''

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// جلب أصناف و أنواع
$cats = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$types = $db->query("SELECT id, name, category_id FROM types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
if ($status === 'available') {
    // Books with NO active confirmed loans
    $where[] = "NOT EXISTS (
        SELECT 1 
        FROM loans l 
        WHERE l.book_id = b.id AND l.status = 'confiremed'
    )";
} elseif ($status === 'loaned') {
    // Books with at least one active confirmed loan
    $where[] = "EXISTS (
        SELECT 1 
        FROM loans l 
        WHERE l.book_id = b.id AND l.status = 'confiremed'
    )";
}

$whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// اجمالي النتائج من أجل pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM books b $whereSQL");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

// جلب الكتب مع معلومات العدد الحالي للإعارات لكل كتاب
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

// helper cover (استعمال unsplash للكتب بدون غلاف)
function cover_url($book)
{
    if (!empty($book['cover_image'])) return htmlspecialchars($book['cover_image']);
    // $q = urlencode(($book['title'] ?? 'book') . ' ' . ($book['author'] ?? ''));
    return "../assets/placeHolder.webp";
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
    <div class="container py-4">
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

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="mb-0">📚 مكتبتي</h3>
                <small class="text-muted">تصفّح الكتب، ابحث، أو فلتر حسب الأصناف والحالة</small>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <!-- Filters -->
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
                        <option value="available" <?= ($status === 'available') ? 'selected' : '' ?>>متوفر</option>
                        <option value="loaned" <?= ($status === 'loaned') ? 'selected' : '' ?>>معار</option>
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                    <a href="?" class="btn btn-outline-secondary">مسح</a>
                </div>
            </div>
        </form>

        <!-- books grid -->
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

                $isLoaned = $b['status'] == "maear";
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
                                                    'status' => $isLoaned ? 'معار' : 'متوفر',
                                                    'description' => $b['excerpts'] ?: $b['notes'] ?: 'لا وصف متاح.',
                                                    'cover' => $cover
                                                ], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                                    <i class="fa fa-info-circle"></i> عرض التفاصيل
                                </button>


                                <?php if (isset($_SESSION['user_id'])): ?>
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

        <!-- pagination -->
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

    <!-- Modal لإتمام الإعارة -->
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

    <!-- Modal لعرض تفاصيل الكتاب -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" dir="rtl">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">تفاصيل الكتاب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body d-flex gap-3">
                    <div id="details_cover" style="width:150px;height:220px;background-size:cover;background-position:center;border-radius:0.5rem;"></div>
                    <div class="flex-grow-1">
                        <h5 id="details_title"></h5>
                        <p class="small-note mb-1" id="details_author"></p>
                        <p class="small-note mb-1" id="details_category"></p>
                        <p class="small-note mb-1" id="details_type"></p>
                        <p class="small-note mb-1" id="details_custom_id"></p>
                        <p class="small-note mb-1" id="details_status"></p>
                        <hr>
                        <p id="details_description" class="small text-muted"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
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
        const detailsCategory = document.getElementById('details_category');
        const detailsType = document.getElementById('details_type');
        const detailsCustomId = document.getElementById('details_custom_id');
        const detailsStatus = document.getElementById('details_status');
        const detailsDescription = document.getElementById('details_description');

        detailsButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                try {
                    const book = JSON.parse(btn.getAttribute('data-book'));
                    detailsCover.style.backgroundImage = `url('${book.cover}')`;
                    detailsTitle.textContent = book.title;
                    detailsAuthor.textContent = 'المؤلف: ' + (book.author || 'غير محدد');
                    detailsCategory.textContent = 'الصنف: ' + (book.category || '—');
                    detailsType.textContent = 'النوع: ' + (book.type || '—');
                    detailsCustomId.textContent = 'الرمز: ' + (book.custom_id || '—');
                    detailsStatus.textContent = 'الحالة: ' + book.status;
                    detailsDescription.textContent = book.description;
                    detailsModal.show();
                } catch (err) {
                    console.error('خطأ في بيانات الكتاب:', err);
                }
            });
        });

        function logout() {
            // Add logout functionality here delete the user from the session
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '?action=logout', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.location.href = '/login';
                    } else {
                        alert(response.message);
                    }
                }
            };
            xhr.send();
        }

        (function() {
            // filter types by category (UI nicety)
            const categorySelect = document.getElementById('categorySelect');
            const typeSelect = document.getElementById('typeSelect');
            const originalOptions = Array.from(typeSelect.options).map(opt => ({
                value: opt.value,
                text: opt.text,
                cat: opt.dataset.cat || ''
            }));

            function filterTypes() {
                const cat = categorySelect.value;
                typeSelect.innerHTML = '';
                const optAll = document.createElement('option');
                optAll.value = '';
                optAll.text = 'كل الأنواع';
                typeSelect.appendChild(optAll);
                originalOptions.forEach(o => {
                    if (o.value === '') return;
                    if (!cat || o.cat === cat) {
                        const el = document.createElement('option');
                        el.value = o.value;
                        el.text = o.text;
                        el.dataset.cat = o.cat;
                        if ("<?= $type ?>" === o.value) el.selected = true;
                        typeSelect.appendChild(el);
                    }
                });
            }
            categorySelect.addEventListener('change', filterTypes);
            filterTypes();

            // Modal logic
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

            rentButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    try {
                        const dataAttr = btn.getAttribute('data-book');
                        const info = JSON.parse(dataAttr);

                        if (info.isLoaned) {
                            // safety: shouldn't happen because button disabled, but just in case
                            btn.disabled = true;
                            return;
                        }

                        modalCover.style.backgroundImage = `url('${info.cover}')`;
                        modalTitle.textContent = info.title || '—';
                        modalAuthor.textContent = info.author ? 'المؤلف: ' + info.author : 'المؤلف: غير محدد';
                        modalBookId.value = info.id;
                        modalReturnDate.value = defaultReturnDate();

                        // ensure the confirm button enabled
                        modalConfirmBtn.disabled = false;

                        rentModal.show();
                    } catch (err) {
                        console.error('خطأ فقراءة بيانات الكتاب:', err);
                    }
                });
            });

            // form validation: return_date must be >= today
            rentForm.addEventListener('submit', function(ev) {
                const rdateVal = modalReturnDate.value;
                if (!rdateVal) {
                    modalReturnDate.classList.add('is-invalid');
                    ev.preventDefault();
                    ev.stopPropagation();
                    return;
                }
                const sel = new Date(rdateVal);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (sel < today) {
                    modalReturnDate.classList.add('is-invalid');
                    ev.preventDefault();
                    ev.stopPropagation();
                    return;
                }
                modalReturnDate.classList.remove('is-invalid');
                // allow submit; server will re-check safety
            });

            // clear invalid on change
            modalReturnDate.addEventListener('change', function() {
                modalReturnDate.classList.remove('is-invalid');
            });

        })();
    </script>
</body>

</html>