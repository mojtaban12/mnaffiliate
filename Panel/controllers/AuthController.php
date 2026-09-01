<?php
// controllers/AuthController.php

class AuthController {
    
    public function showLogin() {
        // Display the login form
        $message = ''; // You can pass messages here if needed
        require_once __DIR__ . '/../templates/login.php';
    }

    public function handleLogin() {
        // Process login form submission
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($username === 'admin' && $password === 'admin@123') {
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                header('Location: /dashboard'); // Redirect to a protected page
                exit;
            } else {
                $message = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
        $this->showLogin();
    }
    
    public function handleLogout() {
        session_destroy();
        header('Location: /login');
        exit;
    }
}