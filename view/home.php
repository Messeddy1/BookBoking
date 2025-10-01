<?php
session_start();
require_once('./db_config.php'); // path صحيح لقاعدة البيانات

// تأكد من صلاحية المستخدم
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

// الإحصائيات
$totalBooks = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$borrowedBooks = $db->query("SELECT COUNT(*) FROM books WHERE status='maear'")->fetchColumn();
$Totalcategories = $db->query("SELECT COUNT(DISTINCT category_id) FROM books")->fetchColumn();

// CRUD عبر AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        case 'get_books':
            $stmt = $db->query("SELECT * FROM books ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'get_book_details':
            $id = intval($_GET['id']);
            $stmt = $db->prepare("SELECT * FROM books WHERE id=?");
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

            if (!$title || !$author || !$category_id) {
                echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية']);
                exit;
            }

            // رفع صورة الغلاف
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

            // توليد custom_id تلقائي
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
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
        default:
            echo json_encode(['success' => false, 'message' => 'Action غير معروف']);
            exit;
    }
}

// get categories and types
$categories = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$types_all = $db->query("SELECT * FROM types")->fetchAll(PDO::FETCH_ASSOC);

// تحويل الـ types إلى مصفوفة حسب category_id للـ JS
$bookTypes = [];
foreach ($types_all as $t) {
    $bookTypes[$t['category_id']][] = $t['name'];
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

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">إجمالي الكتب</h5>
                            <p class="card-text fs-2 fw-bold" id="totalBooks"></p>
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

        <!-- Books Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="mb-3">قائمة الكتب</h5>

                <div class="d-flex flex-wrap gap-2 w-100 align-items-center">
                    <!-- Add Book Button -->
                    <button class="btn btn-primary" id="addBookBtn"><i class="fas fa-plus"></i> إضافة كتاب جديد</button>

                    <!-- Search & Filter -->
                    <div class="d-flex flex-grow-1 align-items-center gap-2">
                        <!-- Search Input -->
                        <div class="input-group flex-grow-1">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="بحث...">
                        </div>

                        <!-- Category Filter -->
                        <select class="form-select w-auto" id="filterCategory">
                            <option value="">كل الأصناف</option>
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Admin Links (Only Superadmin) -->
                    <?php if ($role === 'superadmin') : ?>
                        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <a href="/categories" class="btn btn-outline-secondary"><i class="fas fa-layer-group"></i> إدارة الأصناف</a>
                            <a href="/users" class="btn btn-outline-secondary"><i class="fas fa-users"></i> إدارة المستخدمين</a>
                            <a href="/loans" class="btn btn-outline-secondary"><i class="fas fa-book-reader"></i> الكتب المعارة</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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

        <!-- Modal -->
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
                                <button type="submit" class="btn btn-primary">حفظ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
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
            document.addEventListener('DOMContentLoaded', function() {
                const bookModal = new bootstrap.Modal(document.getElementById('bookModal'));
                const bookForm = document.getElementById('bookForm');
                const categorySelect = document.getElementById('bookCategory');
                const typeSelect = document.getElementById('bookType');
                const booksTableBody = document.getElementById('booksTableBody');

                const categories = <?php echo json_encode(array_column($categories, 'name', 'id')); ?>;
                const bookTypes = <?php echo json_encode($bookTypes); ?>;

                // Load Categories
                for (let key in categories) {
                    const opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = categories[key];
                    categorySelect.appendChild(opt);
                }

                function updateTypeDropdown(selectedCategory, selectedType = null) {
                    typeSelect.innerHTML = '<option selected disabled value="">اختر النوع...</option>';
                    if (bookTypes[selectedCategory]) {
                        bookTypes[selectedCategory].forEach((type, index) => {
                            const opt = document.createElement('option');
                            opt.value = index + 1;
                            opt.textContent = type;
                            if (selectedType && parseInt(selectedType) === index + 1) opt.selected = true;
                            typeSelect.appendChild(opt);
                        });
                    }
                }

                async function loadBooks() {


                    booksTableBody.innerHTML = '<tr><td colspan="7" class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
                    const res = await fetch('?action=get_books');
                    const books = await res.json();
                    filteredBooks = books;
                    // get length of books
                    const totalBooks = books.length;
                    document.getElementById('totalBooks').textContent = totalBooks;
                    document.getElementById('filterCategory').value = '';
                    // when selct category filter
                    document.getElementById('filterCategory').addEventListener('change', (e) => {
                        const categoryId = e.target.value;
                        if (categoryId) {
                            filteredBooks = books.filter(b => b.category_id == categoryId);
                        } else {
                            filteredBooks = books;
                        }
                        renderBooks(filteredBooks);
                    });
                    document.getElementById('searchInput').addEventListener('input', (e) => {
                        const searchTerm = e.target.value.toLowerCase();
                        filteredBooks = filteredBooks.filter(b =>
                            b.title.toLowerCase().includes(searchTerm) ||
                            b.author.toLowerCase().includes(searchTerm) ||
                            b.custom_id.toLowerCase().includes(searchTerm)
                        );
                        renderBooks(filteredBooks);
                    });

                    function renderBooks(books) {
                        if (!books.length) {
                            booksTableBody.innerHTML = '<tr><td colspan="7" class="text-center">لا توجد كتب</td></tr>';
                            return;
                        }
                        booksTableBody.innerHTML = '';
                        books.forEach(book => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td><img src="${book.cover_image ? book.cover_image : 'https://via.placeholder.com/50x70?text=No+Image'}" class="book-cover-sm"></td>
                                <td>${book.custom_id}</td>
                                <td>${book.title}</td>
                                <td>${book.author}</td>
                                <td>${categories[book.category_id] || 'غير معروف'}</td>
                                <td>${book.status === 'disponible' ? '<span class="badge bg-success">متوفر</span>' : '<span class="badge bg-warning text-dark">معار</span>'}</td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white me-1" onclick="editBook(${book.id})"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteBook(${book.id})"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            `;
                            booksTableBody.appendChild(tr);
                        });
                    }
                    renderBooks(books);
                }

                window.editBook = async function(id) {
                    const res = await fetch(`?action=get_book_details&id=${id}`);
                    const book = await res.json();
                    document.getElementById('bookId').value = book.id;
                    document.getElementById('bookTitle').value = book.title;
                    document.getElementById('bookAuthor').value = book.author;
                    document.getElementById('bookCustomId').value = book.custom_id;
                    document.getElementById('bookNotes').value = book.notes;
                    document.getElementById('bookExcerpts').value = book.excerpts;
                    document.getElementById('bookCategory').value = book.category_id;
                    updateTypeDropdown(book.category_id, book.type_id);
                    document.getElementById('bookModalLabel').textContent = 'تعديل الكتاب';
                    bookModal.show();
                }

                window.deleteBook = async function(id) {
                    /// sweetalert here
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
                        loadBooks();
                    } else {
                        Swal.fire('خطأ', data.message, 'error');
                    }
                }

                bookForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(bookForm);
                    const action = formData.get('bookId') ? 'update_book' : 'add_book';
                    const res = await fetch(`?action=${action}`, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        // sweetalert success
                        Swal.fire('تم الحفظ!', 'تم حفظ الكتاب بنجاح.', 'success');
                        bookForm.reset();
                        bookModal.hide();
                        loadBooks();
                    } else {
                        // sweetalert error
                        Swal.fire('خطأ', data.message, 'error');
                    }
                });

                document.getElementById('addBookBtn').addEventListener('click', function() {
                    bookForm.reset();
                    document.getElementById('bookId').value = '';
                    document.getElementById('bookModalLabel').textContent = 'إضافة كتاب جديد';
                    updateTypeDropdown(Object.keys(categories)[0]);
                    bookModal.show();
                });

                document.getElementById('bookCategory').addEventListener('change', function() {
                    updateTypeDropdown(this.value);
                });

                loadBooks();
            });
        </script>
</body>

</html>