<?php
session_start();
require_once('../db_config.php'); // Include the database connection

$response = array('success' => false, 'error' => array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($email) || empty($password)) {
        $response['error'] = array(
            'email' => 'البريد الإلكتروني مطلوب.',
            'password' => 'كلمة المرور مطلوبة.'
        );
    } else {
        // Prepare and execute SQL query to check if the user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Login successful, set session variables
            $_SESSION['user_id'] = $user['id'];

            // Respond with success
            $response['success'] = true;
            $response['user'] = array(
                'role' => $user['role']
            );
        } else {
            // Invalid email or password
            $response['error'] = array(
                'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
                'password' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'
            );
        }
    }

    // Return the response as JSON
    echo json_encode($response);
    exit();
}
