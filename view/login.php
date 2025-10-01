<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Book Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        }

        .error-text {
            color: red;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2 class="mb-4 text-center">Login</h2>
        <form id="loginForm">
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
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="mt-3 text-center">
            <a href="/register">Create Account</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Reset previous errors
            document.getElementById('emailError').textContent = '';
            document.getElementById('passwordError').textContent = '';

            // Get the form data
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            // Create a FormData object to send the data via AJAX
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            // Create a new XMLHttpRequest to send data asynchronously
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/controllers/login.php', true);

            // Handle the response from PHP
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);

                        if (response.success) {
                            // Redirect to dashboard or home page
                            // show toast success message for sweet alert from cdn js and then redirect
                            Swal.fire({
                                icon: 'success',
                                title: 'Login Successful',
                                showConfirmButton: false,
                                timer: 1500
                            });
                            setTimeout(() => {
                                // whait ontil the timer is finished then redirect ila kan admin ila makanch superadmin awla gestiennair yredirec ila home
                                if (response.user.role === 'superadmin' || response.user.role === 'gestionnaire') {
                                    window.location.href = '/dashboard';
                                } else {
                                    window.location.href = '/';

                                }
                            }, 1500);

                        } else {
                            // Display error messages from PHP
                            if (response.error) {
                                if (response.error.email) {
                                    document.getElementById('emailError').textContent = response.error.email;
                                }
                                if (response.error.password) {
                                    document.getElementById('passwordError').textContent = response.error.password;
                                }
                            }
                        }
                    } catch (e) {
                        console.error("Error parsing JSON:", e);
                    }
                } else {
                    console.error("Request failed with status:", xhr.status);
                    // Optionally display a generic error message
                    document.getElementById('emailError').textContent = 'An error occurred, please try again.';
                }
            };

            // Handle network errors
            xhr.onerror = function() {
                console.error("Request failed due to network error.");
                document.getElementById('emailError').textContent = 'A network error occurred. Please try again later.';
            };

            // Send the request
            xhr.send(formData);
        });
    </script>
</body>

</html>