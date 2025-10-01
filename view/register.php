<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Book Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }

        .register-box {
            max-width: 400px;
            margin: 80px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        .error-text {
            color: red;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="register-box">
        <h2 class="mb-4 text-center">Create Account</h2>
        <form id="registerForm" method="POST" action="/controllers/register.php">
            <div class="mb-3">
                <label for="fullname" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullname" name="fullname" required>
                <div id="fullnameError" class="error-text"></div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div id="emailError" class="error-text"></div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div id="passwordError" class="error-text"></div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                <div id="confirmPasswordError" class="error-text"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        <div class="mt-3 text-center">
            <a href="/login">Already have an account? Login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            let isValid = true;

            // Reset error messages
            document.getElementById('fullnameError').innerText = '';
            document.getElementById('emailError').innerText = '';
            document.getElementById('passwordError').innerText = '';
            document.getElementById('confirmPasswordError').innerText = '';

            // Get form values
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Front-end validation (Inline Errors)
            if (fullname === '') {
                document.getElementById('fullnameError').innerText = 'الاسم الكامل مطلوب.';
                isValid = false;
            }

            const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            if (!emailPattern.test(email)) {
                document.getElementById('emailError').innerText = 'الرجاء إدخال بريد إلكتروني صالح.';
                isValid = false;
            }

            if (password.length < 6) {
                document.getElementById('passwordError').innerText = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل.';
                isValid = false;
            }

            if (password !== confirmPassword) {
                document.getElementById('confirmPasswordError').innerText = 'كلمتا المرور غير متطابقتين.';
                isValid = false;
            }

            if (isValid) {
                fetch('/controllers/register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            fullname: fullname,
                            email: email,
                            password: password,
                            confirm_password: confirmPassword
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم إنشاء الحساب بنجاح!',
                                showConfirmButton: false,
                                timer: 1500
                            });
                            setTimeout(() => {
                                window.location.href = '/';
                            }, 1500);
                        } else {
                            // ✅ السيرفر سيرجع أخطاء معينة لـ كل فيلد
                            if (data.errors) {
                                if (data.errors.fullname) {
                                    document.getElementById('fullnameError').innerText = data.errors.fullname;
                                }
                                if (data.errors.email) {
                                    document.getElementById('emailError').innerText = data.errors.email;
                                }
                                if (data.errors.password) {
                                    document.getElementById('passwordError').innerText = data.errors.password;
                                }
                                if (data.errors.confirm_password) {
                                    document.getElementById('confirmPasswordError').innerText = data.errors.confirm_password;
                                }
                            }
                        }
                    })
                    .catch(err => console.error(err));
            }
        });
    </script>

</body>

</html>