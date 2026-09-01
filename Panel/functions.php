<?php
// functions.php

function getWordPressUsers($url, $key) {
    $response = file_get_contents($url . '?api_key=' . $key);
    if ($response === FALSE) {
        return ['error' => 'Failed to connect to WordPress API.'];
    }
    return json_decode($response, true);
}

// Function to generate a unique affiliate code
function generateAffiliateCode($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function logRequest(PDO $pdo, $action) {
    // Capture the entire request body
    $request_body = file_get_contents('php://input');
    logRequestBody($pdo, $action, $request_body);
}

/**
 * ثبت لاگ با بدنه‌ی از پیش آماده‌شده (برای حذف کلید API از لاگ‌ها)
 */
function logRequestBody(PDO $pdo, $action, $request_body) {
    // Get the request type (e.g., POST, GET)
    $request_type = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    // Get the IP address of the client
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (request_type, action, request_body, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$request_type, $action, $request_body, $ip_address]);
    } catch (PDOException $e) {
        // Log to file instead of echoing (echo may corrupt JSON responses)
        error_log('Failed to log request: ' . $e->getMessage());
    }
}

function sendPostRequest($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for HTTP errors (like 401, 404, etc.)
    if ($http_code >= 400) {
        throw new Exception("HTTP Error: " . $http_code . " - " . strip_tags($response));
    }
    
    return $response;
}