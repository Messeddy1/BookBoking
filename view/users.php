<?php
session_start();
require_once('./db_config.php');

// تحقق من صلاحية المستخدم
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: /login');
    exit;
}

$role = $currentUser['role'];
if ($role !== 'superadmin') {
    header('Location: /');
    exit;
}

// CRUD عبر AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        case 'get_users':
            $stmt = $db->query("SELECT id, fullname, email, role, phone, address FROM users ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'get_user_details':
            $id = intval($_GET['id']);
            $stmt = $db->prepare("SELECT id, fullname, email, role, phone, address FROM users WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            exit;

        case 'add_user':
        case 'update_user':
            $id = intval($_POST['userId'] ?? 0);
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $userRole = $_POST['role'] ?? 'adherent';
            $phone_number = $_POST['phone'] ?? null; // اختياري
            $address = trim($_POST['address'] ?? ''); // اختياري

            if (!$fullname || !$email) {
                echo json_encode(['success' => false, 'message' => 'الرجاء ملء الاسم والبريد الإلكتروني']);
                exit;
            }

            if ($phone_number && !ctype_digit($phone_number)) {
                echo json_encode(['success' => false, 'message' => "رقم الهاتف يجب أن يحتوي على أرقام فقط."]);
                exit;
            }

            if ($action === 'add_user') {
                if (!$password) {
                    echo json_encode(['success' => false, 'message' => 'الرجاء إدخال كلمة المرور']);
                    exit;
                }
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email=?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل']);
                    exit;
                }

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (fullname, email, password, role, phone, address) VALUES (?,?,?,?,?,?)");
                $res = $stmt->execute([$fullname, $email, $hash, $userRole, $phone_number, $address]);
            } else {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, password=?, role=?, phone=?, address=? WHERE id=?");
                    $res = $stmt->execute([$fullname, $email, $hash, $userRole, $phone_number, $address, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, role=?, phone=?, address=? WHERE id=?");
                    $res = $stmt->execute([$fullname, $email, $userRole, $phone_number, $address, $id]);
                }
            }

            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

        case 'delete_user':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID غير صالح']);
                exit;
            }
            if ($id == $currentUser['id']) {
                echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM users WHERE id=?");
            $res = $stmt->execute([$id]);
            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ أثناء الحذف']);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Action غير معروف']);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>إدارة المنخرطين والأعضاء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <?php include("./includes/navbarDashboard.php"); ?>
    <div class="container mt-4">
        <h2 class="mb-4">إدارة المنخرطين والأعضاء</h2>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="mb-3">قائمة المنخرطين والأعضاء</h5>

                <div class="d-flex flex-wrap gap-2 w-100 align-items-center">
                    <button class="btn btn-primary" id="addUserBtn"><i class="fas fa-plus"></i> إضافة منخرط/عضو جديد</button>
                    <div class="d-flex flex-grow-1 align-items-center gap-2">
                        <div class="input-group flex-grow-1">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="بحث بالاسم أو البريد...">
                        </div>
                        <select class="form-select w-auto" id="filterRole">
                            <option value="">كل الأدوار</option>
                            <option value="superadmin">Superadmin</option>
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="adherent">Adherent</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الاسم الكامل</th>
                                <th>البريد الإلكتروني</th>
                                <th>رقم الهاتف</th>
                                <th>العنوان</th>
                                <th>الدور</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="text-center p-5">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="userForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="userModalLabel">إضافة المنخرطين أوالأعضاء</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="userId" name="userId">
                        <div class="mb-3"><label>الاسم الكامل</label><input type="text" class="form-control" name="fullname" id="fullname" required></div>
                        <div class="mb-3"><label>البريد الإلكتروني</label><input type="email" class="form-control" name="email" id="email" required></div>

                        <div class="mb-2">
                            <label for="phone" class="form-label small">رقم الهاتف (اختياري)</label>
                            <input type="text" name="phone" id="phone" class="form-control form-control-sm" placeholder="مثال: 0612345678">
                        </div>

                        <div class="mb-2">
                            <label for="address" class="form-label small">العنوان (اختياري)</label>
                            <input type="text" name="address" id="address" class="form-control form-control-sm" placeholder="مثال: الدار البيضاء، المغرب">
                        </div>

                        <div class="mb-3"><label>كلمة المرور</label><input type="password" class="form-control" name="password" id="password"></div>
                        <div class="mb-3"><label>الدور</label>
                            <select class="form-select" name="role" id="role">
                                <option value="superadmin">Superadmin</option>
                                <option value="gestionnaire">Gestionnaire</option>
                                <option value="adherent" selected>Adherent</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userModal = new bootstrap.Modal(document.getElementById('userModal'));
            const userForm = document.getElementById('userForm');
            const usersTableBody = document.getElementById('usersTableBody');
            let users = [];

            async function loadUsers() {
                const res = await fetch('?action=get_users');
                users = await res.json();
                renderUsers(users);
            }

            function renderUsers(list) {
                if (!list.length) {
                    usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center">لا يوجد مستخدمين</td></tr>';
                    return;
                }
                usersTableBody.innerHTML = '';
                list.forEach(u => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${u.id}</td>
                        <td>${u.fullname}</td>
                        <td>${u.email}</td>
                        <td>${u.phone || '-'}</td>
                        <td>${u.address || '-'}</td>
                        <td><span class="badge bg-${u.role==='superadmin'?'danger':u.role==='gestionnaire'?'warning text-dark':'secondary'}">${u.role}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info text-white me-1" onclick="editUser(${u.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    `;
                    usersTableBody.appendChild(tr);
                });
            }

            window.editUser = async function(id) {
                const res = await fetch(`?action=get_user_details&id=${id}`);
                const u = await res.json();
                document.getElementById('userId').value = u.id;
                document.getElementById('fullname').value = u.fullname;
                document.getElementById('email').value = u.email;
                document.getElementById('phone').value = u.phone || '';
                document.getElementById('address').value = u.address || '';
                document.getElementById('password').value = '';
                document.getElementById('role').value = u.role;
                document.getElementById('userModalLabel').textContent = 'تعديل مستخدم';
                userModal.show();
            }

            window.deleteUser = async function(id) {
                const result = await Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "لن تتمكن من التراجع عن هذا الإجراء!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'نعم، احذفه!',
                    cancelButtonText: 'إلغاء'
                });
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('?action=delete_user', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire('تم الحذف!', 'تم حذف المستخدم بنجاح.', 'success');
                    loadUsers()
                } else Swal.fire({
                    title: 'خطأ',
                    text: data.message,
                    icon: 'error'
                });
            }

            userForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(userForm);
                const action = formData.get('userId') ? 'update_user' : 'add_user';
                const res = await fetch(`?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire('تم الحفظ!', 'تم حفظ المستخدم بنجاح.', 'success');
                    userForm.reset();
                    userModal.hide();
                    loadUsers();
                } else Swal.fire('خطأ', data.message, 'error');
            });

            document.getElementById('addUserBtn').addEventListener('click', function() {
                userForm.reset();
                document.getElementById('userId').value = '';
                document.getElementById('userModalLabel').textContent = 'إضافة مستخدم جديد';
                userModal.show();
            });

            document.getElementById('filterRole').addEventListener('change', function() {
                let role = this.value;
                let filtered = role ? users.filter(u => u.role === role) : users;
                renderUsers(filtered);
            });

            document.getElementById('searchInput').addEventListener('input', function() {
                let term = this.value.toLowerCase();
                let filtered = users.filter(u =>
                    u.fullname.toLowerCase().includes(term) ||
                    u.email.toLowerCase().includes(term)
                );
                renderUsers(filtered);
            });

            loadUsers();
        });
    </script>
</body>

</html>