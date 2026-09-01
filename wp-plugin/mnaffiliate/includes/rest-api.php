<?php
/**
 * includes/rest-api.php
 * اندپوینت‌های REST برای ارتباط میکروسرویس با وردپرس
 */

if (!defined('ABSPATH')) {
    exit;
}

/* GET /wp-json/mnaffiliates/v1/users — لیست کاربران */
add_action('rest_api_init', function () {
    register_rest_route('mnaffiliates/v1', '/users', [
        'methods'             => 'GET',
        'callback'            => 'mnaffiliates_get_users_list',
        'permission_callback' => 'mnaff_rest_check_key',
    ]);
});

/* GET /wp-json/mnaffiliates/v1/users/search?phone=... — جستجو با موبایل */
add_action('rest_api_init', function () {
    register_rest_route('mnaffiliates/v1', '/users/search', [
        'methods'             => 'GET',
        'callback'            => 'mnaffiliates_search_users_by_phone',
        'permission_callback' => 'mnaff_rest_check_key',
    ]);
});

/* POST /wp-json/mnaffiliates/v1/registerAffiliate — ذخیره کد بازاریاب در پروفایل کاربر */
add_action('rest_api_init', function () {
    register_rest_route('mnaffiliates/v1', '/registerAffiliate', [
        'methods'             => 'POST',
        'callback'            => 'mn_affiliate_api_handle_registration',
        'permission_callback' => 'mnaff_rest_check_key',
    ]);
});

/**
 * بررسی کلید API با hash_equals (مقاوم به timing attack)
 */
function mnaff_rest_check_key($request) {
    $received_key = (string)($request->get_param('api_key') ?: ($_GET['api_key'] ?? ''));
    return $received_key !== '' && hash_equals(MN_AFFILIATE_REST_API_KEY, $received_key);
}

/**
 * لیست کاربران (id، نام، موبایل)
 */
function mnaffiliates_get_users_list(WP_REST_Request $request) {
    $users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);

    $user_data = [];
    foreach ($users as $user) {
        $user_data[] = [
            'id'     => $user->ID,
            'name'   => $user->display_name,
            'mobile' => $user->user_login,
        ];
    }

    return new WP_REST_Response($user_data, 200);
}

/**
 * جستجوی کاربر بر اساس شماره موبایل (user_login)
 */
function mnaffiliates_search_users_by_phone(WP_REST_Request $request) {
    $phone_number = $request->get_param('phone');
    if (empty($phone_number)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Phone number is required.'], 400);
    }

    $users = get_users([
        'search'         => '*' . $phone_number . '*',
        'search_columns' => ['user_login'],
        'fields'         => ['ID', 'display_name', 'user_email', 'user_login'],
    ]);

    $user_data = [];
    foreach ($users as $user) {
        $user_data[] = [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => $user->user_login,
        ];
    }

    return new WP_REST_Response($user_data, 200);
}

/**
 * ذخیره کد بازاریابی در متای کاربر (فراخوانی از پنل ادمین میکروسرویس)
 */
function mn_affiliate_api_handle_registration(WP_REST_Request $request) {
    $user_id        = $request->get_param('user_id');
    $affiliate_code = $request->get_param('affiliate_code');

    $log = wc_get_logger();
    $log->add('mn-affiliate-api', 'Received affiliate registration request for User ID: ' . $user_id . ' with code: ' . $affiliate_code);

    if (empty($user_id) || empty($affiliate_code)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'User ID and affiliate code are required.'], 400);
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'User not found.'], 404);
    }

    update_user_meta($user_id, 'mnaffiliate_code', $affiliate_code);
    $log->add('mn-affiliate-api', 'Successfully updated user meta for User ID: ' . $user_id);

    return new WP_REST_Response(['status' => 'success', 'message' => 'Affiliate code saved successfully.'], 200);
}