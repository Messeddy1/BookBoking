<?php
session_start();
require_once('./db_config.php');

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
// Security: Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    // Security: Handle case where session user_id is invalid
    header('Location: /login');
    exit;
}

// AJAX Update Handler
if (isset($_GET['action']) && $_GET['action'] === 'update_profile') {
    header('Content-Type: application/json');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$fullname || !$email) {
        echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول الضرورية']);
        exit;
    }

    // Input Validation: Basic email format check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة']);
        exit;
    }

    // Check for duplicate email (if changed)
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
            // Security: Password hashing
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET fullname=?, email=?, password=? WHERE id=?");
            $res = $stmt->execute([$fullname, $email, $hash, $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET fullname=?, email=? WHERE id=?");
            $res = $stmt->execute([$fullname, $email, $user_id]);
        }

        if ($res) {
            // Re-fetch updated user data for display (important for full page refresh later)
            $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['currentUser'] = $updatedUser; // Optional: update session if needed

            echo json_encode([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                // Send back new data for JS to update UI
                'new_data' => [
                    'fullname' => $fullname,
                    'email' => $email
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث']);
        }
    } catch (PDOException $e) {
        // Log the error and show a generic message to the user
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
    }

    exit;
}

// Ensure date is formatted correctly
$createdAt = !empty($currentUser['created_at']) ? date('d-m-Y', strtotime($currentUser['created_at'])) : 'غير محدد';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - <?= htmlspecialchars($currentUser['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            --header-bg: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }

        body {
            background-color: var(--background-color);
            padding: 20px;
        }

        .profile-card {
            max-width: 600px;
            margin: 40px auto;
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            background-color: #fff;
        }

        .profile-header {
            background: var(--header-bg);
            color: #fff;
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }

        .profile-header i.fa-user-circle {
            font-size: 70px;
            margin-bottom: 10px;
        }

        .edit-btn-container {
            position: absolute;
            top: 15px;
            left: 15px;
            /* Adjust for RTL */
        }

        .profile-body {
            padding: 30px;
        }

        /* Definition List for Profile Details - Improved UX */
        .profile-details dl {
            display: grid;
            grid-template-columns: 120px 1fr;
            /* Set label width */
            gap: 15px;
            margin-bottom: 0;
        }

        .profile-details dt {
            font-weight: 600;
            color: var(--secondary-color);
            grid-column: 1 / 2;
        }

        .profile-details dd {
            margin-bottom: 0;
            font-size: 1.05em;
            color: #333;
            grid-column: 2 / 3;
        }

        .profile-separator {
            margin: 20px 0;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <nav aria-label="breadcrumb" class="mb-4 mt-4" style="max-width: 600px; margin: 0 auto;">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i> الرئيسية</a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-user me-1"></i> الملف الشخصي</li>
            </ol>
        </nav>

        <div class="card profile-card">
            <div class="profile-header">
                <i class="fas fa-user-circle"></i>
                <h2 class="h4 mt-2 mb-1" id="headerFullname"><?= htmlspecialchars($currentUser['fullname']) ?></h2>
                <p class="small opacity-75 mb-0">دور المستخدم: <strong><?= htmlspecialchars($currentUser['role'] ?? 'عضو') ?></strong></p>

                <div class="edit-btn-container">
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal" title="تعديل الملف">
                        <i class="fas fa-edit"></i> <span class="d-none d-sm-inline">تعديل</span>
                    </button>
                </div>
            </div>

            <div class="profile-body profile-details">
                <dl>
                    <dt>الاسم الكامل:</dt>
                    <dd id="displayFullname"><?= htmlspecialchars($currentUser['fullname']) ?></dd>

                    <dt>البريد الإلكتروني:</dt>
                    <dd id="displayEmail"><?= htmlspecialchars($currentUser['email']) ?></dd>

                    <dt>تاريخ التسجيل:</dt>
                    <dd><?= htmlspecialchars($createdAt) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editProfileForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileLabel"><i class="fas fa-edit me-2"></i> تعديل الملف الشخصي</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalMessage"></div>

                        <div class="mb-3">
                            <label for="fullname" class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?= htmlspecialchars($currentUser['fullname']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                        </div>

                        <hr class="profile-separator">

                        <div class="mb-3">
                            <label for="password" class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="اتركه فارغاً إذا لم ترغب في التغيير">
                            <div class="form-text">لتغيير كلمة المرور، أدخل كلمة مرور جديدة هنا.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="saveButton"><i class="fas fa-save me-1"></i> حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const saveButton = document.getElementById('saveButton');
            const msgDiv = document.getElementById('modalMessage');

            // UI/UX: Disable button and show loading state
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جار الحفظ...';
            msgDiv.innerHTML = ''; // Clear previous messages

            try {
                const res = await fetch('?action=update_profile', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    // Update UI elements on the main page
                    document.getElementById('headerFullname').textContent = data.new_data.fullname;
                    document.getElementById('displayFullname').textContent = data.new_data.fullname;
                    document.getElementById('displayEmail').textContent = data.new_data.email;

                    // Clear the password field for security
                    form.password.value = '';

                    // Show success message inside the modal temporarily
                    msgDiv.innerHTML = `<div class="alert alert-success mt-2">${data.message}</div>`;

                    // Close the modal after a short delay
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
                        modal.hide();
                        // Show SweetAlert for a nicer final confirmation
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ!',
                            text: 'تم تحديث الملف الشخصي بنجاح.',
                            timer: 2000,
                            timerProgressBar: true
                        });
                        msgDiv.innerHTML = ''; // Clear the message div after closing
                    }, 1000);

                } else {
                    // Show error message inside the modal
                    msgDiv.innerHTML = `<div class="alert alert-danger mt-2">${data.message}</div>`;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                msgDiv.innerHTML = `<div class="alert alert-danger mt-2">حدث خطأ غير متوقع. حاول مرة أخرى.</div>`;
            } finally {
                // UI/UX: Restore button state
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-save me-1"></i> حفظ التغييرات';
            }
        });
    </script>
</body>

</html>