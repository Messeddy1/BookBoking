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
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: /login');
    exit;
}
$role = $currentUser["role"];
if ($role !== 'superadmin' && $role !== 'gestionnaire') {
    header('Location: /');
    exit;
}
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    switch ($action) {
        case 'get_loans':
            $stmt = $db->query("
                SELECT l.id, b.title AS book_title, u.fullname AS user_fullname, 
                       l.loan_date, l.due_date, l.return_date, l.status
                FROM loans l
                JOIN books b ON l.book_id = b.id
                JOIN users u ON l.user_id = u.id
                ORDER BY l.id DESC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'get_loan_details':
            $id = intval($_GET['id']);
            $stmt = $db->prepare("SELECT * FROM loans WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            exit;

        case 'update_loan':
            $id = intval($_POST['loan_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            if (!$id || !$status) {
                echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية']);
                exit;
            }

            $stmt = $db->prepare("SELECT book_id FROM loans WHERE id=?");
            $stmt->execute([$id]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                echo json_encode(['success' => false, 'message' => 'الإعارة غير موجودة']);
                exit;
            }

            $book_id = $loan['book_id'];

            // تحديث حالة الكتاب حسب حالة الإعارة
            if ($status === "confiremed") {
                $stmt = $db->prepare("UPDATE books SET status='maear' WHERE id=?");
                $stmt->execute([$book_id]);
            } else {
                $stmt = $db->prepare("UPDATE books SET status='disponible' WHERE id=?");
                $stmt->execute([$book_id]);
            }

            $stmt = $db->prepare("UPDATE loans SET status=? WHERE id=?");
            $res = $stmt->execute([$status, $id]);

            echo json_encode($res ? ['success' => true] : ['success' => false, 'message' => 'حدث خطأ']);
            exit;

        case 'delete_loan':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID غير صالح']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM loans WHERE id=?");
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
    <title>إدارة الإعارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <?php include("./includes/navbarDashboard.php"); ?>
    <div class="container mt-4">
        <h2 class="mb-4">إدارة الإعارات</h2>
        <!-- Search Filters -->
        <div class="mb-3 d-flex gap-3 flex-wrap">
            <input type="text" class="form-control" id="searchBook" placeholder="بحث بعنوان الكتاب أو المستخدم">
            <select class="form-select" id="statusFilter">
                <option value="">كل الحالات</option>
                <option value="pending">مؤجلة</option>
                <option value="confiremed">معار</option>
                <option value="returned">مُرجعة</option>
            </select>
        </div>

        <!-- Loans Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الكتاب</th>
                                <th>المستخدم</th>
                                <th>تاريخ الإعارة</th>
                                <th>تاريخ الإرجاع المتوقع</th>
                                <th>تاريخ الإرجاع الفعلي</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="loansTableBody">
                            <tr>
                                <td colspan="8" class="text-center p-5">
                                    <div class="spinner-border text-success"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Loans -->
    <div class="modal fade" id="loanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="loanForm">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل حالة الإعارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">الحالة</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="" disabled selected>اختر الحالة</option>
                                <option value="pending">قيد الانتظار</option>
                                <option value="confiremed">مؤكد</option>
                                <option value="returned">تم إرجاعه</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="loan_id" id="loan_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loansTableBody = document.getElementById('loansTableBody');
            const loanModal = new bootstrap.Modal(document.getElementById('loanModal'));
            const loanForm = document.getElementById('loanForm');
            let loans = [];

            async function loadLoans() {
                const res = await fetch('?action=get_loans');
                loans = await res.json();
                renderLoans(loans);
            }

            function renderLoans(list) {
                if (!list.length) {
                    loansTableBody.innerHTML = '<tr><td colspan="8" class="text-center">لا يوجد إعارات</td></tr>';
                    return;
                }
                loansTableBody.innerHTML = '';
                list.forEach(l => {

                    const statusText = l.status === 'pending' ? 'قيد الانتظار' :
                        l.status === 'confiremed' ? 'مؤكد' :
                        l.status === 'returned' ? 'تم إرجاعه' : 'غير محددة ';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${l.id}</td>
                <td>${l.book_title}</td>
                <td>${l.user_fullname}</td>
                <td>${l.loan_date}</td>
                <td>${l.due_date}</td>
                <td>${l.return_date || ''}</td>
                <td>${statusText}</td>
                <td>
                    <button class="btn btn-sm btn-info text-white me-1" onclick="editLoan(${l.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteLoan(${l.id})"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;
                    loansTableBody.appendChild(tr);
                });
            }

            window.editLoan = async function(id) {
                const res = await fetch(`?action=get_loan_details&id=${id}`);
                const l = await res.json();
                document.getElementById('status').value = l.status;
                document.getElementById('loan_id').value = l.id;
                loanModal.show();
            }

            window.deleteLoan = async function(id) {
                const result = await Swal.fire({
                    title: 'هل أنت متأكد من حذف هذه الإعارة؟',
                    text: "لن تتمكن من التراجع عن هذا الإجراء!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'نعم، احذفها!',
                    cancelButtonText: 'إلغاء'
                });

                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', id);
                    const res = await fetch('?action=delete_loan', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire('تم الحذف!', 'تم حذف الإعارة بنجاح.', 'success');
                        loadLoans();
                    } else {
                        Swal.fire('خطأ!', data.message, 'error');
                    }
                }
            }

            loanForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(loanForm);
                const res = await fetch('?action=update_loan', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire('تم الحفظ!', 'تم تحديث حالة الإعارة بنجاح.', 'success');
                    loanForm.reset();
                    loanModal.hide();
                    loadLoans();
                } else Swal.fire('خطأ!', data.message, 'error');
            });

            document.getElementById('searchBook').addEventListener('input', filterLoans);
            document.getElementById('statusFilter').addEventListener('change', filterLoans);

            function filterLoans() {
                const bookFilter = document.getElementById('searchBook').value.toLowerCase();
                const statusFilter = document.getElementById('statusFilter').value;
                const filtered = loans.filter(l =>
                    (l.book_title.toLowerCase().includes(bookFilter) ||
                        l.user_fullname.toLowerCase().includes(bookFilter)) &&
                    (statusFilter === "" || l.status === statusFilter)
                );
                renderLoans(filtered);
            }

            loadLoans();
        });
    </script>
</body>

</html>