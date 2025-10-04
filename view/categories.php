<?php
session_start();
require_once('./db_config.php');


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
if ($role !== 'superadmin') {
    header('Location: /');
    exit;
}

// CRUD Categories & Types عبر AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        // ===== Categories =====
        case 'get_categories':
            $stmt = $db->query("SELECT * FROM categories ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'add_category':
        case 'update_category':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (!$name) {
                echo json_encode(['success' => false, 'message' => 'الرجاء إدخال اسم الصنف']);
                exit;
            }

            if ($action === 'add_category') {
                $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                $res = $stmt->execute([$name]);
            } else {
                $stmt = $db->prepare("UPDATE categories SET name=? WHERE id=?");
                $res = $stmt->execute([$name, $id]);
            }
            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

        case 'delete_category':
            $id = intval($_POST['id'] ?? 0);
            // حذف جميع الأنواع التابعة للصنف
            $stmt = $db->prepare("DELETE FROM types WHERE category_id=?");
            $stmt->execute([$id]);
            // حذف الصنف
            $stmt = $db->prepare("DELETE FROM categories WHERE id=?");
            $res = $stmt->execute([$id]);
            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

            // ===== Types =====
        case 'get_types':
            $category_id = intval($_GET['category_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM types WHERE category_id=? ORDER BY id DESC");
            $stmt->execute([$category_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'add_type':
        case 'update_type':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $category_id = intval($_POST['category_id'] ?? 0);

            if (!$name || !$category_id) {
                echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول']);
                exit;
            }

            if ($action === 'add_type') {
                $stmt = $db->prepare("INSERT INTO types (name, category_id) VALUES (?,?)");
                $res = $stmt->execute([$name, $category_id]);
            } else {
                $stmt = $db->prepare("UPDATE types SET name=?, category_id=? WHERE id=?");
                $res = $stmt->execute([$name, $category_id, $id]);
            }
            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

        case 'delete_type':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM types WHERE id=?");
            $res = $stmt->execute([$id]);
            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

        case 'get_books':
            $category_id = intval($_GET['category_id'] ?? 0);
            $type_id = intval($_GET['type_id'] ?? 0);

            $where = [];
            $params = [];

            if ($category_id) {
                $where[] = "b.category_id = :category_id";
                $params[':category_id'] = $category_id;
            }
            if ($type_id) {
                $where[] = "b.type_id = :type_id";
                $params[':type_id'] = $type_id;
            }

            $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT b.*, c.name as category_name, t.name as type_name,
                   (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.return_date IS NULL) as active_loans
                   FROM books b
                   LEFT JOIN categories c ON c.id = b.category_id
                   LEFT JOIN types t ON t.id = b.type_id
                   $whereClause
                   ORDER BY b.title ASC";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'books' => $books]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Action غير معروف']);
            exit;
    }
}

// Fetch categories for initial load
$categories = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأصناف والأنواع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: #f8f9fa;
        }

        .card-icon {
            font-size: 2.5rem;
            opacity: .3;
        }

        .modal-header {
            background: #0d6efd;
            color: white;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>

<body>
    <div class="">
        <?php include("./includes/navbarDashboard.php"); ?>

        <div class="container mt-4">
            <h2 class="mb-4">إدارة الأصناف والأنواع</h2>

            <div class="row g-4">

                <!-- Categories Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">الأصناف</h5>
                            <button class="btn btn-primary btn-sm" id="addCategoryBtn"><i class="fas fa-plus"></i> إضافة صنف</button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>اسم الصنف</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categoriesTableBody">
                                        <tr>
                                            <td colspan="3" class="text-center p-4">جارٍ تحميل الأصناف...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Types Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="mb-2 mb-md-0">الأنواع</h5>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm w-auto" id="filterCategory">
                                    <option selected disabled value="">اختر الصنف...</option>
                                    <?php foreach ($categories as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
                                </select>
                                <button class="btn btn-primary btn-sm" id="addTypeBtn"><i class="fas fa-plus"></i> إضافة نوع</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>اسم النوع</th>
                                            <th>الصنف</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody id="typesTableBody">
                                        <tr>
                                            <!-- <td colspan="4" class="text-center p-4">جارٍ تحميل الأنواع...</td> -->
                                            <td colspan="4" class="text-center p-4">اختر صنفًا من القائمة</td>

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- row end -->
        </div>

    </div>
    <!-- Modal -->
    <div class="modal fade" id="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">...</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="modalForm">
                        <input type="hidden" id="itemId">
                        <div class="mb-3" id="categoryDiv">
                            <label>اسم الصنف</label>
                            <input type="text" class="form-control" id="categoryName">
                        </div>
                        <div id="typeDiv">
                            <label>اسم النوع</label>
                            <input type="text" class="form-control mb-2" id="typeName">
                            <label>اختر الصنف</label>
                            <select class="form-select" id="typeCategory"></select>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            <button type="submit" class="btn btn-primary">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Books Modal -->
    <div class="modal fade" id="booksModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="booksModalTitle"><i class="fas fa-books me-2"></i></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>عنوان الكتاب</th>
                                    <th>المؤلف</th>
                                    <th>الصنف</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody id="booksTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modal'));
        const modalTitle = document.getElementById('modalTitle');
        const modalForm = document.getElementById('modalForm');
        const categoriesTableBody = document.getElementById('categoriesTableBody');
        const typesTableBody = document.getElementById('typesTableBody');
        const filterCategory = document.getElementById('filterCategory');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const addTypeBtn = document.getElementById('addTypeBtn');
        const categoryDiv = document.getElementById('categoryDiv');
        const typeDiv = document.getElementById('typeDiv');
        const itemId = document.getElementById('itemId');
        const categoryName = document.getElementById('categoryName');
        const typeName = document.getElementById('typeName');
        const typeCategory = document.getElementById('typeCategory');

        let categories = <?php echo json_encode($categories); ?>;

        // ===== Functions =====
        async function loadCategories() {
            const res = await fetch('?action=get_categories');
            const data = await res.json();
            categories = data;
            categoriesTableBody.innerHTML = '';
            typeCategory.innerHTML = '';
            data.forEach((c, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${i+1}</td>
                        <td>${c.name}</td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewCategoryBooks(${c.id},'${c.name}')"><i class="fas fa-book"></i></button>
                            <button class="btn btn-sm btn-info me-1" onclick="editCategory(${c.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCategory(${c.id})"><i class="fas fa-trash"></i></button>
                        </td>`;
                categoriesTableBody.appendChild(tr);
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                typeCategory.appendChild(opt);
            });
        }

        async function loadTypes(cat_id) {
            if (!cat_id) {
                typesTableBody.innerHTML = '<tr><td colspan="4" class="text-center">اختر صنف لعرض الأنواع</td></tr>';
                return;
            }
            const res = await fetch(`?action=get_types&category_id=${cat_id}`);
            const data = await res.json();
            typesTableBody.innerHTML = '';
            data.forEach((t, i) => {
                const tr = document.createElement('tr');
                const catName = categories.find(c => c.id == t.category_id)?.name || '-';
                tr.innerHTML = `<td>${i+1}</td>
                        <td>${t.name}</td>
                        <td>${catName}</td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewTypeBooks(${t.id},'${t.name}')"><i class="fas fa-book"></i></button>
                            <button class="btn btn-sm btn-info me-1" onclick="editType(${t.id},${t.category_id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteType(${t.id})"><i class="fas fa-trash"></i></button>
                        </td>`;
                typesTableBody.appendChild(tr);
            });
        }

        // ===== CRUD =====
        addCategoryBtn.addEventListener('click', () => {
            modalTitle.textContent = 'إضافة صنف جديد';
            categoryDiv.style.display = 'block';
            typeDiv.style.display = 'none';
            itemId.value = '';
            categoryName.value = '';
            modal.show();
        });

        addTypeBtn.addEventListener('click', () => {
            modalTitle.textContent = 'إضافة نوع جديد';
            categoryDiv.style.display = 'none';
            typeDiv.style.display = 'block';
            itemId.value = '';
            typeName.value = '';
            typeCategory.value = categories[0]?.id || '';
            modal.show();
        });

        modalForm.addEventListener('submit', async e => {
            e.preventDefault();
            let action = '',
                formData = new FormData();
            if (categoryDiv.style.display === 'block') {
                const name = categoryName.value.trim();
                if (!name) return alert('أدخل اسم الصنف');
                formData.append('name', name);
                if (itemId.value) action = 'update_category';
                else action = 'add_category';
                if (itemId.value) formData.append('id', itemId.value);
            } else {
                const name = typeName.value.trim();
                const cat_id = typeCategory.value;
                if (!name || !cat_id) return // sweetalert error
                Swal.fire('خطأ', 'الرجاء ملء جميع الحقول', 'error');
                formData.append('name', name);
                formData.append('category_id', cat_id);
                if (itemId.value) action = 'update_type';
                else action = 'add_type';
                if (itemId.value) formData.append('id', itemId.value);
            }
            const res = await fetch(`?action=${action}`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                // sweetalert success
                Swal.fire('تم الحفظ!', 'تم حفظ البيانات بنجاح.', 'success');
                modalForm.reset();
                modal.hide();
                loadCategories();
                if (filterCategory.value) loadTypes(filterCategory.value);
            } else alert(data.message);
        });

        window.editCategory = (id) => {
            const cat = categories.find(c => c.id == id);
            if (!cat) return;
            modalTitle.textContent = 'تعديل الصنف';
            categoryDiv.style.display = 'block';
            typeDiv.style.display = 'none';
            itemId.value = cat.id;
            categoryName.value = cat.name;
            modal.show();
        }

        window.deleteCategory = async (id) => {
            // عرض تأكيد الحذف باستخدام SweetAlert2
            const result = await Swal.fire({
                title: 'هل أنت متأكد من حذف هذا الصنف؟',
                text: "سيتم حذف جميع الأنواع التابعة له!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'نعم، احذفه!',
                cancelButtonText: 'إلغاء'
            });

            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', id);

                const res = await fetch('?action=delete_category', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    Swal.fire('تم الحذف!', 'تم حذف الصنف بنجاح.', 'success');
                    loadCategories();
                    typesTableBody.innerHTML = '<tr><td colspan="4" class="text-center p-4">اختر صنفًا من القائمة</td></tr>';
                } else {
                    Swal.fire('خطأ', data.message, 'error');
                }
            }
        };


        window.editType = (id, cat_id) => {
            modalTitle.textContent = 'تعديل النوع';
            categoryDiv.style.display = 'none';
            typeDiv.style.display = 'block';
            itemId.value = id;
            typeCategory.value = cat_id;
            fetch(`?action=get_types&category_id=${cat_id}`).then(r => r.json()).then(types => {
                const t = types.find(x => x.id == id);
                if (t) typeName.value = t.name;
            });
            modal.show();
        }

        window.deleteType = async (id) => {
            const result = await Swal.fire({
                title: 'هل أنت متأكد من حذف هذا النوع؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'نعم، احذفه!',
                cancelButtonText: 'إلغاء'
            });

            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('?action=delete_type', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) loadTypes(filterCategory.value);
                else Swal.fire('خطأ', data.message, 'error');
            }
        }
        filterCategory.addEventListener('change', () => {
            loadTypes(filterCategory.value);
        });

        const booksModal = new bootstrap.Modal(document.getElementById('booksModal'));
        const booksModalTitle = document.getElementById('booksModalTitle');
        const booksTableBody = document.getElementById('booksTableBody');

        async function loadBooks(categoryId = null, typeId = null) {
            let url = '?action=get_books';
            if (categoryId) url += `&category_id=${categoryId}`;
            if (typeId) url += `&type_id=${typeId}`;

            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                booksTableBody.innerHTML = '';
                if (data.books.length === 0) {
                    booksTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4">لا توجد كتب</td></tr>';
                    return;
                }

                data.books.forEach((book, index) => {
                    const tr = document.createElement('tr');
                    const isLoaned = parseInt(book.active_loans) > 0;
                    tr.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${book.title}</td>
                        <td>${book.author || 'غير محدد'}</td>
                        <td>${book.category_name || '—'}</td>
                        <td>${book.type_name || '—'}</td>
                        <td><span class="badge ${isLoaned ? 'bg-warning text-dark' : 'bg-success'}">${isLoaned ? 'معار' : 'متوفر'}</span></td>
                    `;
                    booksTableBody.appendChild(tr);
                });
            }
        }

        window.viewCategoryBooks = async (categoryId, categoryName) => {
            booksModalTitle.innerHTML = `<i class="fas fa-book me-2"></i> الكتب في صنف: ${categoryName}`;
            booksTableBody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            booksModal.show();
            await loadBooks(categoryId);
        }

        window.viewTypeBooks = async (typeId, typeName) => {
            booksModalTitle.innerHTML = `<i class="fas fa-book me-2"></i> الكتب في نوع: ${typeName}`;
            booksTableBody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            booksModal.show();
            await loadBooks(null, typeId);
        }

        loadCategories();
    </script>
</body>

</html>