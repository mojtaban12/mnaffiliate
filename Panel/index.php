<?php
// index.php (The Router)

// 1. Include the necessary files and middleware
// سخت‌سازی کوکی سشن قبل از شروع سشن
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

define('MNAFF_PANEL', true); // برای مسدودسازی دسترسی مستقیم به template ها

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/auth.php'; // Includes and uses the middleware
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api/endpoints/affiliates.php';
require_once __DIR__ . '/api/endpoints/users_proxy.php';

// Get the current URL path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = trim($request_uri, '/'); // Remove leading/trailing slashes for easier comparison

// Set the base path for your project
$base_path = MNAFF_BASE_PATH;
// Simple routing logic to handle different pages
switch ($request_uri) {
    case $base_path: // Handles: /mnaffiliate/
    case $base_path . '/index.php': // Handles: /mnaffiliate/index.php
    case $base_path . '/dashboard': // Handles: /mnaffiliate/dashboard
        check_auth(); // Protect this page
        // Handle form submission and load the dashboard view
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = createAffiliate($pdo, $_POST);
            if ($result['status'] === 'success') {
                $message = '<div class="alert alert-success">موفقیت! ' . htmlspecialchars($result['message']) . '</div>';
            } else {
                $message = '<div class="alert alert-danger">خطا: ' . htmlspecialchars($result['message']) . '</div>';
            }
        }
        require_once __DIR__ . '/templates/manage_affiliates.php';
        break;
   case $base_path . '/commissions':
        check_auth(); // Protect this page
        require_once __DIR__ . '/templates/list_commissions.php';
        break;

    case $base_path . '/payments':
        check_auth(); // Protect this page
        require_once __DIR__ . '/api/endpoints/payments.php';
        $payments = listPayments($pdo);
        $pay_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pay_action = $_POST['pay_action'] ?? '';
            if ($pay_action === 'approve') {
                $result = approvePayment($pdo, $_POST, $_FILES);
            } elseif ($pay_action === 'reject') {
                $result = rejectPayment($pdo, $_POST);
            } else {
                $result = ['status' => 'error', 'message' => 'عملیات نامعتبر است.'];
            }
            $pay_message = $result['status'] === 'success'
                ? '<div class="alert alert-success">موفقیت! ' . htmlspecialchars($result['message']) . '</div>'
                : '<div class="alert alert-danger">خطا: ' . htmlspecialchars($result['message']) . '</div>';
            $payments = listPayments($pdo); // بروزرسانی لیست پس از عملیات
        }
        require_once __DIR__ . '/templates/list_payments.php';
        break;

    case $base_path . '/list':
        check_auth(); // Protect this page
        // Fetch affiliates list (previously in list.php)
        $affiliates = [];
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
                    COUNT(r.id) AS referral_count,
                    COALESCE(c.available_balance, 0) AS available_balance
                FROM
                    affiliates AS a
                LEFT JOIN
                    referrals AS r ON a.id = r.affiliate_id
                LEFT JOIN
                    (SELECT affiliate_id, SUM(affiliate_commission_amount) AS available_balance
                     FROM commissions
                     WHERE status = 'approved' AND payment_id IS NULL
                     GROUP BY affiliate_id) AS c ON c.affiliate_id = a.id
                GROUP BY
                    a.id
                ORDER BY
                    a.created_at DESC
            ");
            $stmt->execute();
            $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $affiliates = [];
        }
        require_once __DIR__ . '/templates/list_affiliates.php';
        break;

    case $base_path . '/api/users-search':
        // پروکسی جستجوی کاربر وردپرس — فقط برای ادمین لاگین‌شده
        check_auth();
        header('Content-Type: application/json');
        echo json_encode(proxyUserSearch($_GET['phone'] ?? ''));
        exit;

    case $base_path . '/login':
        // Handle login form submission without middleware check
        $message = '';
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            header('Location: /' . $base_path);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            // بررسی نام کاربری و پسورد (هش‌شده یا متن ساده fallback)
            $is_valid_user = hash_equals(MNAFF_ADMIN_USERNAME, $username);
            if (!empty(MNAFF_ADMIN_PASSWORD_HASH)) {
                $is_valid_pass = password_verify($password, MNAFF_ADMIN_PASSWORD_HASH);
            } elseif (defined('MNAFF_ADMIN_PASSWORD')) {
                $is_valid_pass = hash_equals(MNAFF_ADMIN_PASSWORD, $password);
            } else {
                $is_valid_pass = false;
            }

            if ($is_valid_user && $is_valid_pass) {
                session_regenerate_id(true); // جلوگیری از Session Fixation
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                header('Location: /' . $base_path . '/dashboard');
                exit;
            } else {
                $message = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
        require_once __DIR__ . '/templates/login.php';
        break;

    case $base_path . '/logout':
        session_destroy();
        header('Location: /' . $base_path . '/login');
        exit;
        
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}