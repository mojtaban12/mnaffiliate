<?php
// middleware/auth.php

function check_auth() {
    // Start the session if it hasn't been already
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if the user is logged in. If not, redirect to the login page.
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        // مسیر مطلق مطابق با روتر پنل (pretty URL)
        header('Location: /mnaffiliate/login');
        exit;
    }
}