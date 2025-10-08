<?php
session_start();
require_once('./db_config.php');

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$role = $currentUser['role'];
if (!$currentUser) {
    header('Location: /login');
    exit;
}
if ($currentUser && isset($_GET['action'])) {
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

// AJAX Update Handler
if (isset($_GET['action']) && $_GET['action'] === 'update_profile') {
    header('Content-Type: application/json');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$fullname || !$email) {
        echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة']);
        exit;
    }

    if ($email !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email=? AND id<>?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل']);
            exit;
        }
    }

    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, phone=?, address=?, password=? WHERE id=?");
            $res = $stmt->execute([$fullname, $email, $phone, $address, $hash, $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, phone=?, address=? WHERE id=?");
            $res = $stmt->execute([$fullname, $email, $phone, $address, $user_id]);
        }

        if ($res) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['currentUser'] = $updatedUser;

            echo json_encode([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'new_data' => [
                    'fullname' => $fullname,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث']);
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
    }

    exit;
}

$createdAt = !empty($currentUser['created_at']) ? date('d-m-Y', strtotime($currentUser['created_at'])) : 'غير محدد';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - <?= htmlspecialchars($currentUser['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fc;
        }

        .profile-card {
            max-width: 700px;
            margin: 60px auto;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: #fff;
            overflow: hidden;
            transition: 0.3s;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .profile-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            text-align: center;
            padding: 40px 20px;
            position: relative;
        }

        .profile-header i {
            font-size: 80px;
            margin-bottom: 10px;
        }

        .edit-btn-container {
            position: absolute;
            top: 20px;
            left: 20px;
        }

        .profile-body {
            padding: 30px;
        }

        .profile-details dt {
            font-weight: 600;
            color: #6c757d;
        }

        .profile-details dd {
            margin-bottom: 10px;
            color: #212529;
        }

        .profile-separator {
            border-top: 1px solid #ddd;
            margin: 15px 0;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.4);
        }

        .modal-content {
            border-radius: 12px;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: scale(1.02);
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
    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <i class="fas fa-user-circle"></i>
                <h3 class="mt-2" id="headerFullname"><?= htmlspecialchars($currentUser['fullname']) ?></h3>
                <p class="opacity-75 mb-0">دور المستخدم: <strong><?= htmlspecialchars($currentUser['role'] ?? 'عضو') ?></strong></p>
                <div class="edit-btn-container">
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                </div>
            </div>
            <div class="profile-body profile-details">
                <dl class="row">
                    <dt class="col-sm-4">الاسم الكامل:</dt>
                    <dd class="col-sm-8" id="displayFullname"><?= htmlspecialchars($currentUser['fullname']) ?></dd>

                    <dt class="col-sm-4">البريد الإلكتروني:</dt>
                    <dd class="col-sm-8" id="displayEmail"><?= htmlspecialchars($currentUser['email']) ?></dd>

                    <dt class="col-sm-4">رقم الهاتف:</dt>
                    <dd class="col-sm-8" id="displayPhone"><?= htmlspecialchars($currentUser['phone'] ?? 'غير محدد') ?></dd>

                    <dt class="col-sm-4">العنوان:</dt>
                    <dd class="col-sm-8" id="displayAddress"><?= htmlspecialchars($currentUser['address'] ?? 'غير محدد') ?></dd>

                    <dt class="col-sm-4">تاريخ التسجيل:</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($createdAt) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editProfileForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> تعديل الملف الشخصي</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalMessage"></div>

                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($currentUser['fullname']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف <small class="text-muted">(اختياري)</small></label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" pattern="[0-9+\-\s]+" placeholder="مثل: 0600000000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">العنوان <small class="text-muted">(اختياري)</small></label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($currentUser['address'] ?? '') ?>" placeholder="المدينة / الشارع">
                        </div>

                        <hr class="profile-separator">

                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="password" class="form-control" placeholder="اتركه فارغاً إذا لم ترغب في التغيير">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('editProfileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const modalMsg = document.getElementById('modalMessage');

            try {
                const res = await fetch('?action=update_profile', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('displayFullname').textContent = data.new_data.fullname;
                    document.getElementById('displayEmail').textContent = data.new_data.email;
                    document.getElementById('displayPhone').textContent = data.new_data.phone || 'غير محدد';
                    document.getElementById('displayAddress').textContent = data.new_data.address || 'غير محدد';
                    document.getElementById('headerFullname').textContent = data.new_data.fullname;

                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحفظ!',
                        text: 'تم تحديث الملف الشخصي بنجاح.',
                        timer: 1800,
                        showConfirmButton: false
                    });

                    bootstrap.Modal.getInstance(document.getElementById('editProfileModal')).hide();
                } else {
                    modalMsg.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            } catch (err) {
                modalMsg.innerHTML = `<div class="alert alert-danger">حدث خطأ غير متوقع. حاول لاحقاً.</div>`;
                console.error(err);
            }
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
    </script>
</body>

</html>