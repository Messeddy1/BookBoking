<?php
session_start();
// NOTE: Assuming db_config.php contains PDO connection to $db
require_once('./db_config.php');

// --- 1. Authentication and Authorization ---
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /login');
    exit;
}

$role = $user['role'];
if ($role !== 'superadmin' && $role !== 'gestionnaire') {
    header('Location: /');
    exit;
}

// --- 2. Initial Data Fetch for Stats and Dropdowns ---
$totalBooks = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
// Assuming status 'maear' means 'borrowed'
$borrowedBooks = $db->query("SELECT COUNT(*) FROM books WHERE status='maear'")->fetchColumn();
$Totalcategories = $db->query("SELECT COUNT(DISTINCT category_id) FROM books")->fetchColumn();

// Fetch categories and types for JavaScript dropdowns and display mapping
$categories_data = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$types_all_data = $db->query("SELECT * FROM types")->fetchAll(PDO::FETCH_ASSOC);

// Fetch adherents list for admin rent modal
$adherents = [];
if ($role === 'superadmin' || $role === 'gestionnaire') {
    $adherents = $db->query("SELECT id, fullname, email FROM users WHERE role = 'adherent' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
}

// Map categories by ID for easy lookup in JS (used for display)
$categories_map = array_column($categories_data, 'name', 'id');

// Map all types by ID for easy lookup in JS (used for details view)
$all_types_map = array_column($types_all_data, 'name', 'id');

// Map types by category_id for the dynamic "Add/Edit" form
$bookTypes_by_category = [];
foreach ($types_all_data as $t) {
    // Assuming type_id is the primary key and not 1-indexed based on category
    // For simplicity with your previous code, we'll map by category for the form
    // The "index + 1" logic in your original JS was confusing, so we'll adjust the JS to use actual type IDs if available, 
    // but keep the PHP structure for compatibility with the form logic.
    $bookTypes_by_category[$t['category_id']][] = [
        'id' => $t['id'],
        'name' => $t['name']
    ];
}


// --- 3. CRUD Handler via AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        switch ($action) {
            case 'get_books':
                // Joins for efficient data retrieval (recommended for production)
                $stmt = $db->query("
                    SELECT 
                        b.*, c.name AS category_name, t.name AS type_name 
                    FROM books b
                    LEFT JOIN categories c ON b.category_id = c.id
                    LEFT JOIN types t ON b.type_id = t.id
                    ORDER BY b.id DESC
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                exit;

            case 'get_book_details':
                $id = intval($_GET['id']);
                // Fetch book details with category and type names
                $stmt = $db->prepare("
                    SELECT 
                        b.*, c.name AS category_name, t.name AS type_name 
                    FROM books b
                    LEFT JOIN categories c ON b.category_id = c.id
                    LEFT JOIN types t ON b.type_id = t.id
                    WHERE b.id=?
                ");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                exit;

            case 'add_book':
            case 'update_book':
                $id = intval($_POST['bookId'] ?? 0);
                $title = trim($_POST['bookTitle'] ?? '');
                $author = trim($_POST['bookAuthor'] ?? '');
                $category_id = intval($_POST['bookCategory'] ?? 0);
                $type_id = intval($_POST['bookType'] ?? 0);
                $custom_id = trim($_POST['bookCustomId'] ?? '');
                $notes = trim($_POST['bookNotes'] ?? '');
                $excerpts = trim($_POST['bookExcerpts'] ?? '');
                $status = 'disponible';

                if (!$title || !$author || !$category_id || !$type_id) {
                    echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية (العنوان، المؤلف، الصنف، النوع)']);
                    exit;
                }

                $cover_image = '';
                if (isset($_FILES['bookCover']) && $_FILES['bookCover']['error'] === 0) {
                    $ext = pathinfo($_FILES['bookCover']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array(strtolower($ext), $allowed)) {
                        echo json_encode(['success' => false, 'message' => 'صيغة صورة غير مسموح بها']);
                        exit;
                    }
                    if (!is_dir('uploads/covers')) mkdir('uploads/covers', 0777, true);
                    $cover_image = 'uploads/covers/' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['bookCover']['tmp_name'], $cover_image);
                }

                if (!$custom_id) $custom_id = 'BK' . time();

                if ($action === 'add_book') {
                    $stmt = $db->prepare("INSERT INTO books (title, author, category_id, type_id, custom_id, cover_image, notes, excerpts, status) VALUES (?,?,?,?,?,?,?,?,?)");
                    $res = $stmt->execute([$title, $author, $category_id, $type_id, $custom_id, $cover_image, $notes, $excerpts, $status]);
                } else {
                    if ($cover_image) {
                        $stmt = $db->prepare("UPDATE books SET title=?, author=?, category_id=?, type_id=?, custom_id=?, cover_image=?, notes=?, excerpts=?, status=? WHERE id=?");
                        $res = $stmt->execute([$title, $author, $category_id, $type_id, $custom_id, $cover_image, $notes, $excerpts, $status, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE books SET title=?, author=?, category_id=?, type_id=?, custom_id=?, notes=?, excerpts=?, status=? WHERE id=?");
                        $res = $stmt->execute([$title, $author, $category_id, $type_id, $custom_id, $notes, $excerpts, $status, $id]);
                    }
                }

                echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
                exit;

            case 'delete_book':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'ID غير صالح']);
                    exit;
                }

                $stmt = $db->prepare("SELECT cover_image FROM books WHERE id=?");
                $stmt->execute([$id]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($book && $book['cover_image'] && file_exists($book['cover_image'])) unlink($book['cover_image']);

                $stmt = $db->prepare("DELETE FROM books WHERE id=?");
                $res = $stmt->execute([$id]);
                echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ أثناء الحذف']);
                exit;

            case 'rent_book':
                // Admin can create a loan for a user (or for self)
                $book_id = intval($_POST['book_id'] ?? 0);
                $return_date = trim($_POST['return_date'] ?? '');
                $selected_user_id = intval($_POST['selected_user_id'] ?? 0);
                $create_new_adherent = isset($_POST['create_new_adherent']) && $_POST['create_new_adherent'] === '1';

                if (!$book_id || !$return_date) {
                    echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
                    exit;
                }

                // validate return_date
                $d = DateTime::createFromFormat('Y-m-d', $return_date);
                if (!$d) {
                    echo json_encode(['success' => false, 'message' => 'تاريخ إرجاع غير صالح']);
                    exit;
                }

                // determine user id for the loan
                $user_for_loan = $user['id']; // default to current admin if nothing else

                if ($selected_user_id) {
                    $stmtU = $db->prepare("SELECT id FROM users WHERE id=?");
                    $stmtU->execute([$selected_user_id]);
                    if ($stmtU->fetch(PDO::FETCH_ASSOC)) {
                        $user_for_loan = $selected_user_id;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'المستخدم المحدد غير موجود']);
                        exit;
                    }
                }

                if ($create_new_adherent) {
                    $new_fullname = trim($_POST['new_fullname'] ?? '');
                    $new_email = trim($_POST['new_email'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $address = trim($_POST['address'] ?? '');

                    if (!$new_fullname || !$new_email) {
                        echo json_encode(['success' => false, 'message' => 'مطلوب اسم وبريد المستعير الجديد']);
                        exit;
                    }

                    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                        echo json_encode(['success' => false, 'message' => 'بريد إلكتروني غير صالح']);
                        exit;
                    }

                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $stmtCheck->execute([$new_email]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل']);
                        exit;
                    }

                    $temp_pass_hash = password_hash('password', PASSWORD_DEFAULT);
                    $stmtIns = $db->prepare("INSERT INTO users (fullname, email, password, role, address, phone) VALUES (?, ?, ?, 'adherent', ?, ?)");
                    $resIns = $stmtIns->execute([$new_fullname, $new_email, $temp_pass_hash, $address, $phone]);
                    if ($resIns) {
                        $user_for_loan = $db->lastInsertId();
                        $new_pass = 'password' . $user_for_loan;
                        $stmtUpd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmtUpd->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_for_loan]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'فشل في إنشاء مستعير جديد']);
                        exit;
                    }
                }

                // check book exists and is available
                $stmtB = $db->prepare("SELECT b.*,
                    (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.return_date IS NULL) AS active_loans
                    FROM books b WHERE b.id = ?");
                $stmtB->execute([$book_id]);
                $bookRow = $stmtB->fetch(PDO::FETCH_ASSOC);
                if (!$bookRow) {
                    echo json_encode(['success' => false, 'message' => 'الكتاب غير موجود']);
                    exit;
                }
                if (intval($bookRow['active_loans']) > 0) {
                    echo json_encode(['success' => false, 'message' => 'الكتاب معار حاليا']);
                    exit;
                }

                $loan_date = (new DateTime('today'))->format('Y-m-d');
                $due_date = (new DateTime($return_date))->modify('-1 day')->format('Y-m-d');

                $ins = $db->prepare("INSERT INTO loans (book_id, user_id, loan_date, due_date, return_date) VALUES (?, ?, ?, ?, ?)");
                $ok = $ins->execute([$book_id, $user_for_loan, $loan_date, $due_date, $return_date]);

                if ($ok) {
                    echo json_encode(['success' => true, 'message' => 'تم إنشاء الإعارة بنجاح']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'فشل عند إنشاء الإعارة']);
                }
                exit;

            case 'logout':
                session_destroy();
                echo json_encode(['success' => true]);
                exit;

            default:
                echo json_encode(['success' => false, 'message' => 'Action غير معروف']);
                exit;
        }
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - المكتبة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: #f8f9fa;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .card-icon {
            font-size: 3rem;
            opacity: .3;
        }

        .book-cover-sm {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
        }

        .table thead th {
            font-weight: 600;
        }

        /* Styling for the Edit/Add Modal Header */
        .modal-header {
            background: #0d6efd;
            color: white;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Styling for the Details Modal Content */
        .details-list strong {
            display: block;
            font-size: 1.05em;
            color: #333;
        }

        .details-list small {
            font-weight: 500;
        }

        #detailCoverImage {
            width: 100%;
            height: 250px;
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
                    </div>
                <?php } ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">لوحة التحكم</h2>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">إجمالي الكتب</h5>
                            <p class="card-text fs-2 fw-bold" id="totalBooks"><?php echo $totalBooks; ?></p>
                        </div>
                        <i class="fas fa-book card-icon text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">إجمالي الأصناف</h5>
                            <p class="card-text fs-2 fw-bold"><?php echo $Totalcategories; ?></p>
                        </div>
                        <i class="fas fa-layer-group card-icon text-success"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">الكتب المعارة</h5>
                            <p class="card-text fs-2 fw-bold" id="borrowedBooks"><?php echo $borrowedBooks; ?></p>
                        </div>
                        <i class="fas fa-arrow-up-from-bracket card-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- start -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="mb-3">قائمة الكتب</h5>

                <div class="d-flex flex-wrap gap-2 w-100 align-items-center">
                    <!-- Add Book Button -->
                    <button class="btn btn-primary" id="addBookBtn"><i class="fas fa-plus"></i> إضافة كتاب جديد</button>

                    <!-- Search & Filter -->
                    <div class="d-flex flex-grow-1 align-items-center gap-2">
                        <!-- Search Input -->
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="بحث (العنوان، المؤلف، المعرّف)...">
                        </div>

                        <!-- Category Filter -->
                        <select class="form-select w-auto" id="filterCategory">
                            <option value="">كل الأصناف</option>
                            <?php foreach ($categories_data as $cat) : ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Admin Links (Only Superadmin) -->
                    <?php if ($role === 'superadmin' || $role === 'gestionnaire') : ?>
                        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <?php if ($role === 'superadmin') : ?>
                                <a href="/categories" class="btn btn-outline-secondary"><i class="fas fa-layer-group"></i> إدارة الأصناف</a>
                                <a href="/users" class="btn btn-outline-secondary"><i class="fas fa-users"></i> إدارة المستخدمين</a>
                            <?php endif; ?>
                            <a href="/loans" class="btn btn-outline-secondary"><i class="fas fa-book-reader"></i> الكتب المعارة</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- end -->


            <div class="card shadow-sm border-0">

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">صورة</th>
                                    <th scope="col">المعرّف</th>
                                    <th scope="col">عنوان الكتاب</th>
                                    <th scope="col">المؤلف</th>
                                    <th scope="col">الصنف</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="booksTableBody">
                                <tr>
                                    <td colspan="7" class="text-center p-5">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="bookModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bookModalLabel">إضافة كتاب جديد</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="bookForm" enctype="multipart/form-data">
                                <input type="hidden" id="bookId" name="bookId">
                                <div class="row g-3">
                                    <div class="col-md-6"><label>عنوان الكتاب</label><input type="text" class="form-control" name="bookTitle" id="bookTitle" required></div>
                                    <div class="col-md-6"><label>اسم المؤلف</label><input type="text" class="form-control" name="bookAuthor" id="bookAuthor" required></div>
                                    <div class="col-md-6"><label>الصنف</label><select class="form-select" name="bookCategory" id="bookCategory" required></select></div>
                                    <div class="col-md-6"><label>النوع</label><select class="form-select" name="bookType" id="bookType" required></select></div>
                                    <div class="col-md-6"><label>المعرّف المخصص</label><input type="text" class="form-control" id="bookCustomId" name="bookCustomId" placeholder="سيتم إنشاؤه تلقائيا"></div>
                                    <div class="col-md-6"><label>صورة الغلاف</label><input type="file" class="form-control" id="bookCover" name="bookCover"></div>
                                    <div class="col-12"><label>ملاحظات</label><textarea class="form-control" id="bookNotes" name="bookNotes" rows="3"></textarea></div>
                                    <div class="col-12"><label>مقتطفات</label><textarea class="form-control" id="bookExcerpts" name="bookExcerpts" rows="3"></textarea></div>
                                </div>
                                <div class="modal-footer mt-3">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                    <button type="submit" class="btn btn-primary" id="saveBookBtn"><i class="fas fa-save"></i> حفظ</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="bookDetailsModal" tabindex="-1" aria-labelledby="bookDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="bookDetailsModalLabel"><i class="fas fa-eye me-2"></i> تفاصيل الكتاب</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4 text-center mb-3">
                                    <img id="detailCoverImage" src="https://via.placeholder.com/150x200?text=No+Image" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: cover;" alt="غلاف الكتاب">
                                </div>
                                <div class="col-md-8">
                                    <h3 class="mb-3" id="detailTitle"></h3>
                                    <p class="text-muted">المؤلف: <strong id="detailAuthor"></strong></p>
                                    <hr>
                                    <div class="row g-3 details-list">
                                        <div class="col-md-6"><small class="text-secondary">المعرّف المخصص:</small><br><strong id="detailCustomId"></strong></div>
                                        <!-- <div class="col-md-6"><small class="text-secondary">رقم قاعدة البيانات (ID):</small><br><strong id="detailId"></strong></div> -->
                                        <div class="col-md-6"><small class="text-secondary">الصنف:</small><br><strong id="detailCategory"></strong></div>
                                        <div class="col-md-6"><small class="text-secondary">النوع:</small><br><strong id="detailType"></strong></div>
                                        <div class="col-12"><small class="text-secondary">الحالة:</small><br><span id="detailStatus" class="badge"></span></div>
                                    </div>
                                    <hr>
                                    <div class="mt-3">
                                        <h6><i class="fas fa-list-alt me-1"></i> الملاحظات:</h6>
                                        <p id="detailNotes" class="text-break"></p>
                                    </div>
                                    <div class="mt-3">
                                        <h6><i class="fas fa-quote-right me-1"></i> مقتطفات:</h6>
                                        <p id="detailExcerpts" class="text-break"></p>
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


            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <!-- Rent Modal (admin) -->
            <div class="modal fade" id="rentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="fas fa-book me-2"></i> إعارة كتاب</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="rentForm">
                                <input type="hidden" name="book_id" id="rent_book_id">
                                <div class="mb-3 d-flex gap-3 align-items-center">
                                    <div style="width:90px;">
                                        <div id="rentCover" class="modal-cover" style="background-image:url('https://via.placeholder.com/120x170?text=No+Image')"></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 id="rentBookTitle"></h6>
                                        <small id="rentBookAuthor" class="small-note"></small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">إعارة إلى (المستعير)</label>
                                    <select class="form-select" id="selectedUserId" name="selected_user_id">
                                        <option value="">-- اختر مستعيراً موجوداً --</option>
                                    </select>
                                </div>

                                <div class="mb-3 form-check">
                                    <input class="form-check-input" type="checkbox" id="createNewAdherent" name="create_new_adherent" value="1">
                                    <label class="form-check-label" for="createNewAdherent">إنشاء مستعير جديد</label>
                                </div>

                                <div id="newAdherentFields" style="display:none">
                                    <div class="mb-2"><label>الاسم الكامل</label><input type="text" class="form-control" name="new_fullname" id="new_fullname"></div>
                                    <div class="mb-2"><label>البريد الإلكتروني</label><input type="email" class="form-control" name="new_email" id="new_email"></div>
                                    <div class="mb-2"><label>الهاتف</label><input type="text" class="form-control" name="phone" id="new_phone"></div>
                                    <div class="mb-2"><label>العنوان</label><input type="text" class="form-control" name="address" id="new_address"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">تاريخ الإرجاع</label>
                                    <input type="date" class="form-control" name="return_date" id="rent_return_date" required>
                                </div>

                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                    <button type="submit" class="btn btn-success" id="submitRentBtn">تأكيد الإعارة</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                // PHP Variables mapped to JS
                const CATEGORIES = <?php echo json_encode($categories_map); ?>;
                const BOOK_TYPES_BY_CATEGORY = <?php echo json_encode($bookTypes_by_category); ?>;
                // Adherents list for admin rent modal
                const ADHERENTS = <?php echo json_encode($adherents); ?>;

                // Global store for books to enable fast filtering/editing
                let allBooks = [];
                let bookDetailsModalInstance;

                // Small helper to escape strings used inside onclick attributes
                function addslashes(str) {
                    if (str === null || str === undefined) return '';
                    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\"/g, '\\\"');
                }

                function logout() {
                    // Simplified POST request for logout
                    fetch('?action=logout', {
                            method: 'POST'
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = '/login';
                            } else {
                                Swal.fire('خطأ', 'فشل تسجيل الخروج.', 'error');
                            }
                        })
                        .catch(() => Swal.fire('خطأ', 'فشل الاتصال بالخادم.', 'error'));
                }

                // Populates the type dropdown based on the selected category
                function updateTypeDropdown(selectedCategory, selectedTypeId = null) {
                    const typeSelect = document.getElementById('bookType');
                    typeSelect.innerHTML = '<option selected disabled value="">اختر النوع...</option>';

                    const types = BOOK_TYPES_BY_CATEGORY[selectedCategory];

                    if (types && types.length > 0) {
                        types.forEach(type => {
                            const opt = document.createElement('option');
                            opt.value = type.id;
                            opt.textContent = type.name;
                            if (selectedTypeId && parseInt(selectedTypeId) === parseInt(type.id)) {
                                opt.selected = true;
                            }
                            typeSelect.appendChild(opt);
                        });
                    }
                }

                // Function to render the table based on the current filtered list
                function renderBooks(books) {
                    const booksTableBody = document.getElementById('booksTableBody');
                    if (!books.length) {
                        booksTableBody.innerHTML = '<tr><td colspan="7" class="text-center">لا توجد كتب مطابقة</td></tr>';
                        return;
                    }

                    booksTableBody.innerHTML = '';
                    books.forEach(book => {
                        const tr = document.createElement('tr');
                        const statusBadge = book.status === 'disponible' ?
                            '<span class="badge bg-success">متوفر</span>' :
                            '<span class="badge bg-warning text-dark">معار</span>';

                        tr.innerHTML = `
                        <td><img src="${book.cover_image ? book.cover_image : 'https://via.placeholder.com/50x70?text=No+Image'}" class="book-cover-sm" alt="غلاف"></td>
                        <td>${book.custom_id}</td>
                        <td>${book.title}</td>
                        <td>${book.author}</td>
                        <td>${book.category_name || 'غير معروف'}</td>
                            <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewBookDetails(${book.id})" title="عرض التفاصيل"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-info text-white me-1" onclick="editBook(${book.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger me-1" onclick="deleteBook(${book.id})" title="حذف"><i class="fas fa-trash-alt"></i></button>
                            <button class="btn btn-sm btn-success" onclick="openRentModal(${book.id}, ${book.active_loans || 0}, '${book.cover_image ? addslashes(book.cover_image) : 'https://via.placeholder.com/50x70?text=No+Image'}', '${addslashes(book.title)}', '${addslashes(book.author || '')}')" title="إعارة"><i class="fas fa-book"></i></button>
                        </td>
                    `;
                        booksTableBody.appendChild(tr);
                    });
                }

                // Function to apply search and filter logic
                function applyFiltersAndSearch() {
                    const categoryId = document.getElementById('filterCategory').value;
                    const searchTerm = document.getElementById('searchInput').value.toLowerCase();

                    let currentBooks = allBooks;

                    // 1. Filter by Category
                    if (categoryId) {
                        currentBooks = currentBooks.filter(b => b.category_id == categoryId);
                    }

                    // 2. Filter by Search Term
                    if (searchTerm) {
                        currentBooks = currentBooks.filter(b =>
                            b.title.toLowerCase().includes(searchTerm) ||
                            b.author.toLowerCase().includes(searchTerm) ||
                            b.custom_id.toLowerCase().includes(searchTerm)
                        );
                    }

                    renderBooks(currentBooks);
                }

                // Main function to load data from the server
                async function loadBooks() {
                    const booksTableBody = document.getElementById('booksTableBody');
                    booksTableBody.innerHTML = '<tr><td colspan="7" class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';

                    const res = await fetch('?action=get_books');
                    allBooks = await res.json();

                    // Update total stats
                    document.getElementById('totalBooks').textContent = allBooks.length;

                    applyFiltersAndSearch();
                }

                // --- NEW: View Book Details Function ---
                window.viewBookDetails = async function(id) {
                    // Fetch fresh details with category/type names (using your new AJAX endpoint)
                    const res = await fetch(`?action=get_book_details&id=${id}`);
                    const book = await res.json();

                    if (!book || !book.id) {
                        Swal.fire('خطأ', 'لم يتم العثور على تفاصيل الكتاب.', 'error');
                        return;
                    }

                    document.getElementById('detailTitle').textContent = book.title;
                    document.getElementById('detailAuthor').textContent = book.author || 'غير محدد';
                    document.getElementById('detailCustomId').textContent = book.custom_id;
                    // document.getElementById('detailId').textContent = book.id;
                    document.getElementById('detailCategory').textContent = book.category_name || 'غير معروف';
                    document.getElementById('detailType').textContent = book.type_name || 'غير معروف';
                    document.getElementById('detailNotes').textContent = book.notes || 'لا يوجد ملاحظات.';
                    document.getElementById('detailExcerpts').textContent = book.excerpts || 'لا يوجد مقتطفات.';

                    const imgSource = (book.cover_image && book.cover_image.length > 0) ?
                        book.cover_image :
                        'https://via.placeholder.com/150x200?text=No+Image';
                    document.getElementById('detailCoverImage').src = imgSource;

                    const statusEl = document.getElementById('detailStatus');
                    statusEl.textContent = book.status === 'disponible' ? 'متوفر' : 'معار';
                    statusEl.className = 'badge ' + (book.status === 'disponible' ? 'bg-success' : 'bg-warning text-dark');

                    bookDetailsModalInstance.show();
                }
                // ----------------------------------------

                // Edit Book Function (re-uses existing form and modal)
                window.editBook = function(id) {
                    // Find book data from the cached allBooks array
                    const book = allBooks.find(b => b.id == id);

                    if (!book) {
                        Swal.fire('خطأ', 'لم يتم العثور على الكتاب المراد تعديله.', 'error');
                        return;
                    }

                    document.getElementById('bookId').value = book.id;
                    document.getElementById('bookTitle').value = book.title;
                    document.getElementById('bookAuthor').value = book.author;
                    document.getElementById('bookCustomId').value = book.custom_id;
                    document.getElementById('bookNotes').value = book.notes;
                    document.getElementById('bookExcerpts').value = book.excerpts;
                    document.getElementById('bookCategory').value = book.category_id;
                    document.getElementById('bookCover').value = '';

                    updateTypeDropdown(book.category_id, book.type_id);
                    document.getElementById('bookModalLabel').textContent = 'تعديل الكتاب';

                    const bookModal = bootstrap.Modal.getInstance(document.getElementById('bookModal'));
                    bookModal.show();
                }

                // Delete Book Function (remains the same)
                window.deleteBook = async function(id) {
                    const result = await Swal.fire({
                        title: 'هل أنت متأكد من حذف هذا الكتاب؟',
                        text: "لا يمكن التراجع عن هذا الإجراء!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احذفه!',
                        cancelButtonText: 'لا، ألغِ'
                    });

                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    const res = await fetch('?action=delete_book', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await res.json();

                    if (data.success) {
                        Swal.fire('تم الحذف!', 'تم حذف الكتاب بنجاح.', 'success');
                        loadBooks(); // Reload data
                    } else {
                        Swal.fire('خطأ', data.message, 'error');
                    }
                }

                // --- DOM Ready Logic ---
                document.addEventListener('DOMContentLoaded', function() {
                    const bookModal = new bootstrap.Modal(document.getElementById('bookModal'));
                    bookDetailsModalInstance = new bootstrap.Modal(document.getElementById('bookDetailsModal')); // Initialize new modal
                    const bookForm = document.getElementById('bookForm');
                    const categorySelect = document.getElementById('bookCategory');

                    // Initialize Category dropdown options in Add/Edit modal
                    categorySelect.innerHTML = '<option selected disabled value="">اختر الصنف...</option>';
                    for (let id in CATEGORIES) {
                        const opt = document.createElement('option');
                        opt.value = id;
                        opt.textContent = CATEGORIES[id];
                        categorySelect.appendChild(opt);
                    }

                    // Event listener for Add Book button
                    document.getElementById('addBookBtn').addEventListener('click', function() {
                        bookForm.reset();
                        document.getElementById('bookId').value = '';
                        document.getElementById('bookModalLabel').textContent = 'إضافة كتاب جديد';
                        if (Object.keys(CATEGORIES).length > 0) {
                            categorySelect.value = Object.keys(CATEGORIES)[0]; // Set default category
                            updateTypeDropdown(categorySelect.value);
                        }
                        bookModal.show();
                    });

                    // Event listener for Category change to update Type dropdown
                    categorySelect.addEventListener('change', function() {
                        updateTypeDropdown(this.value);
                    });

                    // Event listeners for Search and Filter
                    document.getElementById('filterCategory').addEventListener('change', applyFiltersAndSearch);
                    document.getElementById('searchInput').addEventListener('input', applyFiltersAndSearch);

                    // Form submission for Add/Edit
                    bookForm.addEventListener('submit', async function(e) {
                        e.preventDefault();

                        const submitBtn = document.getElementById('saveBookBtn');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جار الحفظ...';

                        const formData = new FormData(bookForm);
                        const action = formData.get('bookId') ? 'update_book' : 'add_book';

                        try {
                            const res = await fetch(`?action=${action}`, {
                                method: 'POST',
                                body: formData
                            });
                            const data = await res.json();

                            if (data.success) {
                                Swal.fire('تم الحفظ!', 'تم حفظ الكتاب بنجاح.', 'success');
                                bookForm.reset();
                                bookModal.hide();
                                loadBooks(); // Reload the table
                            } else {
                                Swal.fire('خطأ', data.message, 'error');
                            }
                        } catch (error) {
                            Swal.fire('خطأ', 'حدث خطأ في الاتصال بالخادم.', 'error');
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ';
                        }
                    });

                    loadBooks();
                    // Populate adherents select for rent modal
                    const sel = document.getElementById('selectedUserId');
                    if (sel && ADHERENTS && ADHERENTS.length) {
                        ADHERENTS.forEach(a => {
                            const opt = document.createElement('option');
                            opt.value = a.id;
                            opt.textContent = a.fullname + ' <' + (a.email || '') + '>';
                            sel.appendChild(opt);
                        });
                    }

                    // New adherent checkbox toggle
                    document.getElementById('createNewAdherent').addEventListener('change', function() {
                        document.getElementById('newAdherentFields').style.display = this.checked ? 'block' : 'none';
                        // disable select when creating new adherent
                        document.getElementById('selectedUserId').disabled = this.checked;
                    });

                    // Rent form submission via AJAX
                    document.getElementById('rentForm').addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const btn = document.getElementById('submitRentBtn');
                        btn.disabled = true;
                        btn.textContent = 'جارٍ الإعارة...';

                        const form = new FormData(this);

                        try {
                            const res = await fetch('?action=rent_book', {
                                method: 'POST',
                                body: form
                            });
                            const data = await res.json();
                            if (data.success) {
                                Swal.fire('نجاح', data.message || 'تم إنشاء الإعارة', 'success');
                                const rentModal = bootstrap.Modal.getInstance(document.getElementById('rentModal'));
                                rentModal.hide();
                                loadBooks();
                            } else {
                                Swal.fire('خطأ', data.message || 'حدث خطأ', 'error');
                            }
                        } catch (err) {
                            Swal.fire('خطأ', 'فشل الاتصال بالخادم.', 'error');
                        } finally {
                            btn.disabled = false;
                            btn.textContent = 'تأكيد الإعارة';
                        }
                    });
                });

                // Open Rent Modal helper
                window.openRentModal = function(bookId, activeLoans, cover, title, author) {
                    if (activeLoans && parseInt(activeLoans) > 0) {
                        Swal.fire('تنبيه', 'هذا الكتاب معار حالياً ولا يمكن إعارته.', 'warning');
                        return;
                    }

                    document.getElementById('rent_book_id').value = bookId;
                    document.getElementById('rentCover').style.backgroundImage = `url('${cover}')`;
                    document.getElementById('rentBookTitle').textContent = title || 'عنوان غير معروف';
                    document.getElementById('rentBookAuthor').textContent = author || '';
                    document.getElementById('rent_return_date').value = '';
                    document.getElementById('createNewAdherent').checked = false;
                    document.getElementById('newAdherentFields').style.display = 'none';
                    document.getElementById('selectedUserId').disabled = false;
                    const rentModal = new bootstrap.Modal(document.getElementById('rentModal'));
                    rentModal.show();
                }
            </script>
</body>

</html>