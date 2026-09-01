<?php
// login.php (Controller)

session_start();

$message = '';

// Check if user is already logged in, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Sample data for demonstration
    if ($username === 'admin' && $password === 'admin@123') {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $message = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}

// Load the login form (View)
require_once __DIR__ . '/templates/login.php';