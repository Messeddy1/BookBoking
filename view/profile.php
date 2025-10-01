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

// AJAX تحديث البيانات
if (isset($_GET['action']) && $_GET['action'] === 'update_profile') {
    header('Content-Type: application/json');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$fullname || !$email) {
        echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية']);
        exit;
    }

    // تحقق من البريد الإلكتروني إذا كان مختلف
    if ($email !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email=? AND id<>?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل']);
            exit;
        }
    }

    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, password=? WHERE id=?");
        $res = $stmt->execute([$fullname, $email, $hash, $user_id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET fullname=?, email=? WHERE id=?");
        $res = $stmt->execute([$fullname, $email, $user_id]);
    }

    if ($res) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث البيانات بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>الملف الشخصي - <?= htmlspecialchars($currentUser['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f5f6fa;
        }

        .profile-card {

            margin: 50px auto;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: #fff;
            padding: 40px 20px;
            text-align: center;
        }

        .profile-header i {
            font-size: 80px;
            margin-bottom: 15px;
        }

        .profile-body {
            background-color: #fff;
            padding: 30px 20px;
        }

        .profile-item {
            margin-bottom: 15px;
        }

        .profile-item label {
            font-weight: bold;
            color: #555;
        }

        .profile-item span {
            display: block;
            margin-top: 5px;
            font-size: 1.1em;
        }

        .edit-btn {
            float: left;
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4 mt-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-layer-group"></i> الملف الشخصي <?= htmlspecialchars($currentUser['fullname']) ?></li>
            </ol>
        </nav>

        <!-- Profile Card -->
        <div class="profile-card shadow-sm">
            <div class="profile-header">
                <i class="fas fa-user-circle "></i>
                <h2><?= htmlspecialchars($currentUser['fullname']) ?></h2>
                <p>دور المستخدم: <strong><?= htmlspecialchars($currentUser['role']) ?></strong></p>
                <button class="btn btn-light btn-sm mt-2 edit-btn p-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit"></i> تعديل الملف الشخصي
                </button>

            </div>
            <div class="profile-body">
                <div class="profile-item">
                    <label>الاسم الكامل:</label>
                    <span id="displayFullname"><?= htmlspecialchars($currentUser['fullname']) ?></span>
                </div>
                <div class="profile-item">
                    <label>البريد الإلكتروني:</label>
                    <span id="displayEmail"><?= htmlspecialchars($currentUser['email']) ?></span>
                </div>
                <div class="profile-item">
                    <label>تاريخ التسجيل:</label>
                    <span><?= htmlspecialchars(date('d-m-Y', strtotime($currentUser['created_at'] ?? ''))) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editProfileForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileLabel"><i class="fas fa-edit"></i> تعديل الملف الشخصي</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalMessage"></div>
                        <div class="mb-3">
                            <label>الاسم الكامل</label>
                            <input type="text" class="form-control" name="fullname" value="<?= htmlspecialchars($currentUser['fullname']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>البريد الإلكتروني</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>كلمة المرور الجديدة (اختياري)</label>
                            <input type="password" class="form-control" name="password" placeholder="اتركه إذا لم ترغب في التغيير">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('?action=update_profile', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            const msgDiv = document.getElementById('modalMessage');
            msgDiv.innerHTML = '';
            if (data.success) {
                msgDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                // تحديث البيانات في الصفحة مباشرة
                document.getElementById('displayFullname').textContent = formData.get('fullname');
                document.getElementById('displayEmail').textContent = formData.get('email');
                // إفراغ حقل كلمة المرور
                this.password.value = '';
                // إغلاق المودال بعد ثانيتين
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
                    modal.hide();
                    msgDiv.innerHTML = '';
                }, 1000);
                // sweetalert success after modal is closed
                setTimeout(() => {
                    Swal.fire('تم الحفظ!', 'تم تحديث الملف الشخصي بنجاح.', 'success');
                }, 1500);
            } else {
                msgDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        });
    </script>

</body>

</html>