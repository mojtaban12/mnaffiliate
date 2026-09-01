<?php
/**
 * includes/wallet-ajax.php
 * AJAX: ثبت درخواست تسویه توسط بازاریاب (فقط کاربران لاگین‌شده)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mnaff_request_payout', 'mnaff_request_payout_callback');
function mnaff_request_payout_callback() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('کاربر لاگین نیست.');
    }

    $affiliate_code = get_user_meta($user_id, 'mnaffiliate_code', true);
    if (empty($affiliate_code)) {
        wp_send_json_error('شما بازاریاب ثبت‌شده نیستید.');
    }

    $iban   = strtoupper(sanitize_text_field(wp_unslash($_POST['iban'] ?? '')));
    $holder = sanitize_text_field(wp_unslash($_POST['account_holder_name'] ?? ''));

    if (empty($iban) || empty($holder)) {
        wp_send_json_error('شماره شبا و نام صاحب حساب الزامی است.');
    }

    $response = wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'body' => [
            'api_key'             => MN_AFFILIATE_API_KEY,
            'action'              => 'request_payout',
            'affiliate_code'      => $affiliate_code,
            'iban'                => $iban,
            'account_holder_name' => $holder,
        ],
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('خطا در اتصال به سرور تسویه.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        wp_send_json_error('پاسخ نامعتبر از سرور تسویه.');
    }

    // پاسخ میکروسرویس مستقیماً بازگردانده می‌شود (status/message)
    wp_send_json($body);
}