<?php
// api/endpoints/affiliates.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions.php'; // Include the helper functions file

function createAffiliate(PDO $pdo, array $data): array {
    
    $wordpress_api_url  = MNAFF_WP_REGISTER_AFFILIATE_URL;
    $wordpress_api_key = MNAFF_WP_REST_API_KEY;
    
     // اعتبارسنجی داده‌های ورودی
    if (!isset($data['wp_user_id']) || !isset($data['user_mobile']) || !isset($data['commission_rate']) || !isset($data['referred_user_commission_rate'])) {
        return ['status' => 'error', 'message' => 'اطلاعات مورد نیاز کامل نیست.'];
    }

    $user_mobile = $data['user_mobile'];
    $wp_user_id = (int)$data['wp_user_id'];
    $commission_rate = (float)$data['commission_rate'];
    $referred_user_commission_rate = (float)$data['referred_user_commission_rate']; // New: Get the referred user's rate

    // New: Validate that the total commission does not exceed 10%
    if (($commission_rate + $referred_user_commission_rate) > 10.00) {
        return ['status' => 'error', 'message' => 'مجموع کمیسیون‌ها نمی‌تواند از ۱۰٪ بیشتر باشد.'];
    }

    // Check if user is already an affiliate
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliates WHERE wp_user_id = ?");
    $stmt->execute([$wp_user_id]);
    if ($stmt->fetchColumn() > 0) {
        return ['status' => 'error', 'message' => 'این کاربر قبلا بازاریاب بوده است.'];
    }

    // Generate a unique affiliate code
    $affiliate_code = '';
    $is_unique = false;
    while (!$is_unique) {
        $affiliate_code = generateAffiliateCode();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliates WHERE affiliate_code = ?");
        $stmt->execute([$affiliate_code]);
        if ($stmt->fetchColumn() == 0) {
            $is_unique = true;
        }
    }

    // Insert the new affiliate with both commission rates
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO affiliates (wp_user_id, user_mobile, affiliate_code, commission_rate, referred_user_commission_rate) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$wp_user_id, $user_mobile, $affiliate_code, $commission_rate, $referred_user_commission_rate]);
        
        
        $post_body = [
            'api_key' => $wordpress_api_key,
            'user_id' => $wp_user_id,
            'affiliate_code' => $affiliate_code
        ];
        
        $response = @sendPostRequest($wordpress_api_url, $post_body);        
        $result_from_wp = json_decode($response, true);
        
        // بررسی پاسخ وردپرس
        if ($result_from_wp === null || !isset($result_from_wp['status']) || $result_from_wp['status'] !== 'success') {
            // اگر همگام‌سازی ناموفق بود، تراکنش را برگردان
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'خطا در همگام‌سازی با وردپرس: ' . ($result_from_wp['message'] ?? 'پاسخ نامشخص')];
        }

        // اگر هر دو مرحله موفق بودند، تراکنش را تأیید کن
        $pdo->commit();

        return [
            'status' => 'success',
            'message' => 'بازاریاب با موفقیت ایجاد و با وردپرس همگام‌سازی شد.',
            'affiliate_code' => $affiliate_code,
            'affiliate_id' => $pdo->lastInsertId()
        ];
    } 
    catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'status' => 'error',
            'message' => 'خطا: ' . $e->getMessage()
        ];
    }
}