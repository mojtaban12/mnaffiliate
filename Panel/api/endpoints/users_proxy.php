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

    $url = MNAFF_WP_USER_SEARCH_URL;
    $data = [
        'api_key' => MNAFF_WP_REST_API_KEY,
        'phone'   => $phone,
    ];

    try {
        $response = sendPostRequest($url, $data);
        $users = json_decode($response, true);

        if (!is_array($users)) {
            return ['status' => 'error', 'message' => 'پاسخ نامعتبر از وردپرس.'];
        }

        return ['status' => 'success', 'users' => $users];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'خطا در اتصال به وردپرس: ' . $e->getMessage()];
    }
}