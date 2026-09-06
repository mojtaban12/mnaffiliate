<?php
// webhook.php

header('Content-Type: application/json');

// Include the database connection file from the config folder
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auto_migrate.php'; // ساخت خودکار اسکیما
mnaff_auto_migrate($pdo);
require_once __DIR__ . '/../functions.php'; // Includes the logRequest function

// Get the action from the request early
$action = $_POST['action'] ?? 'unknown_action';

// Log the incoming request immediately (با حذف کلید API از بدنه لاگ)
$request_body_for_log = file_get_contents('php://input');
parse_str($request_body_for_log, $body_parts);
if (isset($body_parts['api_key'])) {
    $body_parts['api_key'] = '[REDACTED]';
    $request_body_for_log = http_build_query($body_parts);
}
logRequestBody($pdo, $action, $request_body_for_log);

// Check if the request is a POST request and has the correct API key
$received_key = $_POST['api_key'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($received_key)
    || !hash_equals(MNAFF_WEBHOOK_API_KEY, $received_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid API key or request method.']);
    exit();
}

// Get the action from the request
$response = [];

switch ($action) {
    case 'create_affiliate':
        // Include the specific endpoint file for affiliates
        require_once __DIR__ . '/endpoints/affiliates.php';
        $response = createAffiliate($pdo, $_POST);
        break;
        
    case 'get_referral_stats':
        require_once __DIR__ . '/endpoints/stats.php'; // Create this file
        $response = getReferralStats($pdo, $_POST);
        break;
    case 'record_referral':
        // Include the new endpoint file
        require_once __DIR__ . '/endpoints/referrals.php';
        $response = recordReferral($pdo, $_POST);
        break;
        
    case 'get_referral_info':
        require_once __DIR__ . '/endpoints/referral_info.php';
        $response = getReferralInfo($pdo, $_POST);
        break;
        
    case 'record_commission':
        require_once __DIR__ . '/endpoints/record_commission.php';
        $response = recordCommission($pdo, $_POST);
        break;
    
    case 'approve_commission':
        require_once __DIR__ . '/endpoints/approve_commission.php';
        $response = approveCommission($pdo, $_POST);
        break;

    case 'get_wallet':
        require_once __DIR__ . '/endpoints/wallet.php';
        $response = getWallet($pdo, $_POST);
        break;

    case 'request_payout':
        require_once __DIR__ . '/endpoints/wallet.php';
        $response = requestPayout($pdo, $_POST);
        break;

    case 'get_payouts':
        require_once __DIR__ . '/endpoints/wallet.php';
        $response = getPayouts($pdo, $_POST);
        break;
            
    default:
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Bad Request: Invalid action specified.'];
        break;
}

echo json_encode($response);