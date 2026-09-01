<?php

/**
 * Database configuration for the mnaffiliates microservice
 * مقادیر از config/config.php خوانده می‌شوند
 */

require_once __DIR__ . '/config.php';

$db_host = MNAFF_DB_HOST;
$db_name = MNAFF_DB_NAME;
$db_user = MNAFF_DB_USER;
$db_pass = MNAFF_DB_PASS;


try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Set PDO attributes for error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Uncomment the line below if you want to see a success message
    // echo "Database connection successful!<br>";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}