<?php
// index.php

// Get the current path from the URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove any trailing slashes for consistency
$path = rtrim($path, '/');


switch ($path) {
    case '';
    case '/':
        include "landingPage/landing.php";
        break;
    case '/home':
        include 'view/home.php';
        break;
    case '/dashboard':
        include 'view/dashboard.php';
        break;
    case '/login':
        include 'view/login.php';
        break;
    case '/register':
        include 'view/register.php';
        break;
    case '/categories':
        include 'view/categories.php';
        break;
    case '/users':
        include 'view/users.php';
        break;
    case '/loans':
        include 'view/loans.php';
        break;
    case '/profile':
        include 'view/profile.php';
        break;
    case '/me-loans':
        include 'view/me-loans.php';
        break;
    default:
        http_response_code(404);
        include 'view/404.php';
        break;
}
