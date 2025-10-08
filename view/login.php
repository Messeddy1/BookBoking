<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - حجز الكتب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <script src="../assets/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8f9fa;
        }

        .login-box {
            max-width: 400px;
            margin: 80px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            direction: rtl;
        }

        .error-text {
            color: red;
            font-size: 0.9em;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2 class="mb-4 text-center">تسجيل الدخول</h2>
        <form id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div id="emailError" class="error-text"></div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div id="passwordError" class="error-text"></div>
            </div>

            <button type="submit" class="btn btn-primary w-100">تسجيل الدخول</button>
        </form>

        <div class="mt-3 text-center">
            <a href="/register">ليس لديك حساب؟ إنشاء حساب جديد</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // مسح الأخطاء السابقة
            document.getElementById('emailError').textContent = '';
            document.getElementById('passwordError').textContent = '';

            // الحصول على البيانات من النموذج
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            // إنشاء FormData لإرسال البيانات
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            // إنشاء طلب AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/controllers/login.php', true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);

                        if (response.success) {
                            // ✅ تسجيل الدخول بنجاح
                            Swal.fire({
                                icon: 'success',
                                title: 'تم تسجيل الدخول بنجاح!',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            setTimeout(() => {
                                // إذا كان المستخدم مديرًا أو مشرفًا → توجيه إلى لوحة التحكم
                                if (response.user.role === 'superadmin' || response.user.role === 'gestionnaire') {
                                    window.location.href = '/dashboard';
                                } else {
                                    // المستخدم العادي → الصفحة الرئيسية
                                    window.location.href = '/';
                                }
                            }, 1500);

                        } else {
                            // ❌ عرض الأخطاء القادمة من السيرفر
                            if (response.error) {
                                if (response.error.email) {
                                    document.getElementById('emailError').textContent = response.error.email;
                                }
                                if (response.error.password) {
                                    document.getElementById('passwordError').textContent = response.error.password;
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'بيانات الدخول غير صحيحة!',
                                    text: 'تحقق من البريد الإلكتروني أو كلمة المرور.',
                                });
                            }
                        }
                    } catch (e) {
                        console.error("خطأ أثناء قراءة استجابة السيرفر:", e);
                    }
                } else {
                    console.error("فشل الطلب. الحالة:", xhr.status);
                    document.getElementById('emailError').textContent = 'حدث خطأ غير متوقع، حاول مرة أخرى.';
                }
            };

            xhr.onerror = function() {
                console.error("فشل الاتصال بالشبكة.");
                document.getElementById('emailError').textContent = 'فشل الاتصال. تحقق من الإنترنت وحاول مجددًا.';
            };

            xhr.send(formData);
        });
    </script>
</body>

</html>