<?php
// list.php

// 1. Session check and authentication
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Include necessary files
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/functions.php';

// 3. Fetch data from the database with the updated query
try {
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.wp_user_id,
            a.user_mobile,
            a.affiliate_code,
            a.commission_rate,
            a.referred_user_commission_rate,
            a.status,
            a.total_earnings,
            a.created_at,
            COUNT(r.id) AS referral_count
        FROM
            affiliates AS a
        LEFT JOIN
            referrals AS r ON a.id = r.affiliate_id
        GROUP BY
            a.id
        ORDER BY
            a.created_at DESC
    ");
    $stmt->execute();
    $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $affiliates = [];
}

// 4. Load the view file to display the data
require_once __DIR__ . '/templates/list_affiliates.php';