<?php
/**
 * includes/referrals.php
 * ثبت ارجاع: کوکی لینک ارجاع + اتصال کاربر جدید به بازاریاب
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * گرفتن کد ارجاع از URL (?referral=CODE) و ذخیره در کوکی ۶ ماهه
 */
add_action('init', 'mnaff_capture_referral_code');
function mnaff_capture_referral_code() {
    if (isset($_GET['referral']) && !empty($_GET['referral'])) {
        $referral_code = sanitize_text_field($_GET['referral']);

        setcookie('mnaffiliate_code', $referral_code, time() + (60 * 60 * 24 * 180), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        // ریدایرکت به همان آدرس بدون پارامتر ارجاع
        wp_safe_redirect(remove_query_arg('referral'));
        exit;
    }
}

/**
 * ارسال ارجاع کاربر به میکروسرویس (با جلوگیری از ثبت تکراری)
 */
function mnaff_send_new_user_referral($user_id) {
    $log = wc_get_logger();

    // جلوگیری از ارسال تکراری در یک اجرا (چند هوک لاگین همزمان fire می‌شوند)
    static $sent_in_request = [];
    if (isset($sent_in_request[$user_id])) {
        return;
    }

    // اگر این کاربر قبلاً ثبت ارجاع شده، دیگر ارسال نکن
    // (اتکای به کوکی کافی نیست چون در همان درخواست هنوز ست نشده است)
    if (get_user_meta($user_id, 'mnaffiliate_referred', true)) {
        return;
    }

    if (!isset($_COOKIE['mnaffiliate_code'])) {
        return;
    }

    $affiliate_code = sanitize_text_field($_COOKIE['mnaffiliate_code']);
    if (empty($affiliate_code)) {
        return;
    }

    $referred_user = get_user_by('id', $user_id);
    if (!$referred_user) {
        return;
    }

    $data = [
        'action'               => 'record_referral',
        'api_key'              => MN_AFFILIATE_API_KEY,
        'affiliate_code'       => $affiliate_code,
        'referred_wp_user_id'  => $user_id,
        'referred_user_mobile' => $referred_user->user_login,
    ];

    $response = wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'method'  => 'POST',
        'body'    => $data,
        'timeout' => 45,
    ]);
    $log->add('mnaffiliates', 'Sending referral request to microservice. Data: ' . print_r($data, true));

    if (is_wp_error($response)) {
        $log->add('mnaffiliates', 'Failed to send referral request for user ID ' . $user_id . ': ' . $response->get_error_message());
        return;
    }

    $body   = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    $log->add('mnaffiliates', 'Request successful. Received response: ' . $body);

    if (isset($result['status']) && $result['status'] === 'success') {
        // علامت‌گذاری پایدار در user meta (مطمئن‌تر از کوکی — مستقل از مرورگر/دستگاه)
        update_user_meta($user_id, 'mnaffiliate_referred', '1');
        $sent_in_request[$user_id] = true;
        // پاک‌سازی کوکی کد ارجاع
        setcookie('mnaffiliate_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    } else {
        $log->add('mnaffiliates', 'Microservice returned an error for user ID ' . $user_id . ': ' . ($result['message'] ?? 'Unknown error'));
    }
}

/* هنگام ثبت‌نام */
add_action('user_register', 'mnaff_send_new_user_referral', 10, 1);

/* هنگام ورود (برای کاربرانی که با کوکی ارجاع ثبت‌نام کرده‌اند) */
function mnaff_maybe_send_on_login($user_id) {
    if (empty($_COOKIE['mnaffiliate_code']) || get_user_meta($user_id, 'mnaffiliate_referred', true)) {
        return;
    }
    $log = wc_get_logger();
    $log->add('mnaffiliates', 'User ID ' . $user_id . ' login user.');

    mnaff_send_new_user_referral($user_id);
}

add_action('wp_login', function ($user_login, $user) {
    mnaff_maybe_send_on_login($user->ID);
}, 10, 2);

add_action('woocommerce_login_user', function ($user_id) {
    mnaff_maybe_send_on_login($user_id);
}, 10, 1);

add_action('set_logged_in_cookie', function ($logged_in_cookie, $expire, $expiration, $user_id) {
    if ($user_id) {
        mnaff_maybe_send_on_login($user_id);
    }
}, 10, 4);