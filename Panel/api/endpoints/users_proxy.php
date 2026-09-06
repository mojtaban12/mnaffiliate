<?php
// api/endpoints/users_proxy.php

/**
 * پروکسی سمت سرور برای جستجوی کاربران وردپرس.
 * کلید API وردپرس هرگز به مرورگر ارسال نمی‌شود؛
 * این اندپوینت فقط برای کاربران لاگین‌شده‌ی پنل قابل دسترسی است.
 */

function proxyUserSearch(string $phone): array {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../functions.php';

    $phone = trim($phone);
    if (strlen($phone) < 3) {
        return ['status' => 'error', 'message' => 'حداقل ۳ کاراکتر وارد کنید.'];
    }

    // اندپوینت وردپرس /users/search با GET کار می‌کند (نه POST)
    $url = MNAFF_WP_USER_SEARCH_URL
         . '?api_key=' . urlencode(MNAFF_WP_REST_API_KEY)
         . '&phone=' . urlencode($phone);

    $context = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);

    $http_code = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $http_code = (int)$m[1];
    }

    if ($body === false) {
        return ['status' => 'error', 'message' => 'خطا در اتصال به وردپرس.'];
    }

    // در صورت خطای وردپرس، پیامِ خودِ آن را برمی‌گردانیم
    if ($http_code >= 400) {
        $err = json_decode($body, true);
        $msg = $err['message'] ?? ('HTTP Error: ' . $http_code);
        return ['status' => 'error', 'message' => 'خطا در وردپرس: ' . $msg];
    }

    $users = json_decode($body, true);
    if (!is_array($users)) {
        return ['status' => 'error', 'message' => 'پاسخ نامعتبر از وردپرس.'];
    }

    return ['status' => 'success', 'users' => $users];
}