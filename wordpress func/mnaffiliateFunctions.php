<?php

if (!defined('MN_AFFILIATE_WEBHOOK_URL')) {
    define('MN_AFFILIATE_WEBHOOK_URL', 'https://www.puonak.com/mnaffiliate/api/webhook.php');
}
if (!defined('MN_AFFILIATE_API_KEY')) {
    define('MN_AFFILIATE_API_KEY', '7217e4da-7ffd-11f0-bf11-726dbfac5a6c');
}
// کلید REST API اختصاصی میکروسرویس (باید با MNAFF_WP_REST_API_KEY در پنل یکی باشد)
if (!defined('MN_AFFILIATE_REST_API_KEY')) {
    define('MN_AFFILIATE_REST_API_KEY', '6e01b310-7fff-11f0-bf11-726dbfac5a6c');
}

add_action('wp_enqueue_scripts', 'mnaff_enqueue_checkout_script');
function mnaff_enqueue_checkout_script() {
    if (is_checkout() || is_page(wc_get_page_id('checkout'))) {
        wp_enqueue_script('mnaff-checkout-script', get_stylesheet_directory_uri() . '/assets/js/mnreferral.js', array('jquery'), '1.0.0', true);
        wp_localize_script('mnaff-checkout-script', 'mnaff_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}


// add_action('wp_enqueue_scripts', function() {
//     if ( class_exists('WooCommerce') && function_exists('is_checkout') && ( is_checkout() || is_page( wc_get_page_id('checkout') ) ) ) {
//         wp_enqueue_script(
//             'mnaff-checkout-script',
//             get_stylesheet_directory_uri() . '/assets/js/mnreferral.js',
//             array('jquery'),
//             '1.0.0',
//             true
//         );

//         wp_localize_script('mnaff-checkout-script', 'mnaff_ajax_object', array(
//             'ajax_url' => admin_url('admin-ajax.php'),
//         ));
//     }
// });

/* define rest api */

function mnaffiliates_register_users_api_endpoint() {
    register_rest_route( 'mnaffiliates/v1', '/users', array(
        'methods' => 'GET',
        'callback' => 'mnaffiliates_get_users_list',
        'permission_callback' => function() {
            // Only allow authenticated requests with a specific API key
            $received_key = isset($_GET['api_key']) ? (string)$_GET['api_key'] : '';
            return $received_key !== '' && hash_equals(MN_AFFILIATE_REST_API_KEY, $received_key);
        }
    ));
}
add_action( 'rest_api_init', 'mnaffiliates_register_users_api_endpoint' );


function mnaffiliates_register_user_search_api_endpoint() {
    register_rest_route( 'mnaffiliates/v1', '/users/search', array(
        'methods' => 'GET',
        'callback' => 'mnaffiliates_search_users_by_phone',
        'permission_callback' => function() {
            $received_key = isset($_GET['api_key']) ? (string)$_GET['api_key'] : '';
            return $received_key !== '' && hash_equals(MN_AFFILIATE_REST_API_KEY, $received_key);
        }
    ));
}
add_action( 'rest_api_init', 'mnaffiliates_register_user_search_api_endpoint' );

function mn_affiliate_api_register_routes() {
    register_rest_route('mnaffiliates/v1', '/registerAffiliate', [
        'methods' => 'POST',
        'callback' => 'mn_affiliate_api_handle_registration',
        'permission_callback' => 'mn_affiliate_api_check_permission',
    ]);
}
add_action('rest_api_init', 'mn_affiliate_api_register_routes');


/**** end of api's ****/

/**
 * Callback function to retrieve the list of users.
 */
function mnaffiliates_get_users_list(WP_REST_Request $request) {
    // Get users with a specific role, or all users
    $users = get_users(array(
        'fields' => array('ID', 'display_name', 'user_email')
    ));
    
    // Format the data for a cleaner response
    $user_data = array();
    foreach ($users as $user) {
        $user_data[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'mobile' => $user->user_login,
        );
    }
    
    return new WP_REST_Response( $user_data, 200 );
}


/**
 * Callback function to search for users by phone number.
 */
function mnaffiliates_search_users_by_phone(WP_REST_Request $request) {
    $phone_number = $request->get_param('phone');
    if (empty($phone_number)) {
        return new WP_REST_Response( ['status' => 'error', 'message' => 'Phone number is required.'], 400 );
    }

    $args = array(
        'search'         => '*' . $phone_number . '*', // Adds wildcards for partial search
        'search_columns' => array('user_login'), // Search only in user_login field
        'fields'         => array('ID', 'display_name', 'user_email', 'user_login'), // Also retrieve user_login
    );
    
    $users = get_users($args);
    
    // Format the data
    $user_data = array();
    foreach ($users as $user) {
        $user_data[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $user->user_login, 
        );
    }
    
    return new WP_REST_Response( $user_data, 200 );
}



/**
 * Checks if the request is authenticated with the correct API key.
 *
 * @param WP_REST_Request $request The request object.
 * @return bool
 */
function mn_affiliate_api_check_permission(WP_REST_Request $request) {
    
    // A shared secret key between your microservice and this endpoint
    $received_key = (string)$request->get_param('api_key');
    
    return $received_key !== '' && hash_equals(MN_AFFILIATE_REST_API_KEY, $received_key);
}

/**
 * Handles the affiliate registration request from the microservice.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function mn_affiliate_api_handle_registration(WP_REST_Request $request) {
    $user_id = $request->get_param('user_id');
    $affiliate_code = $request->get_param('affiliate_code');
    
    // Log the incoming request for debugging, if WooCommerce logger is available
    if (class_exists('WC_Logger')) {
        $log = wc_get_logger();
        $log->add('mn-affiliate-api', 'Received affiliate registration request for User ID: ' . $user_id . ' with code: ' . $affiliate_code);
    }

    // Validate the request data
    if (empty($user_id) || empty($affiliate_code)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'User ID and affiliate code are required.'
        ], 400);
    }
    
    // Check if the user exists
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'User not found.'
        ], 404);
    }

    // Save the affiliate code to the user's meta
    update_user_meta($user_id, 'mnaffiliate_code', $affiliate_code);
    
    // Log success
    if (class_exists('WC_Logger')) {
        $log->add('mn-affiliate-api', 'Successfully updated user meta for User ID: ' . $user_id);
    }

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Affiliate code saved successfully.'
    ], 200);
}







function capture_referral_code() {
    // Check if the 'referral' query parameter exists.
    if (isset($_GET['referral']) && !empty($_GET['referral'])) {
        $referral_code = sanitize_text_field($_GET['referral']);
        
        // Set a cookie that expires in 6 months (60*60*24*30*6).
        setcookie('mnaffiliate_code', $referral_code, time() + (60 * 60 * 24 * 180), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Redirect the user to the URL without the referral parameter.
        wp_safe_redirect(remove_query_arg('referral'));
        exit;
    }
}
add_action('init', 'capture_referral_code');


function send_new_user_referral_to_mnaffiliates($user_id) {
    
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

    if (isset($_COOKIE['mnaffiliate_code'])) {
        $affiliate_code = sanitize_text_field($_COOKIE['mnaffiliate_code']);
        if (!empty($affiliate_code)) {        
            $api_url = MN_AFFILIATE_WEBHOOK_URL;
            $api_key = MN_AFFILIATE_API_KEY; // Must match the key in webhook.php
            $referred_user = get_user_by('id', $user_id);
            if ($referred_user) {
                $referred_user_mobile = $referred_user->user_login;
                $data = [
                    'action' => 'record_referral',
                    'api_key' => $api_key,
                    'affiliate_code' => $affiliate_code,
                    'referred_wp_user_id' => $user_id,
                    'referred_user_mobile' => $referred_user_mobile, // New field to send
                ];
                $response = wp_remote_post($api_url, [
                    'method' => 'POST',
                    'body' => $data,
                    'timeout' => 45,
                ]);
                $log->add('mnaffiliates', 'Sending referral request to microservice. Data: ' . print_r($data, true));
                
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $result = json_decode($body, true);
                    
                    $log->add('mnaffiliates', 'Request successful. Received response: ' . $body);
                    
                    if (isset($result['status']) && $result['status'] === 'success') {
                        // علامت‌گذاری پایدار در user meta (مطمئن‌تر از کوکی — مستقل از مرورگر/دستگاه)
                        update_user_meta($user_id, 'mnaffiliate_referred', '1');
                        $sent_in_request[$user_id] = true;
                        // Optionally, delete the referral code cookie to clean up.
                        setcookie('mnaffiliate_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                    } else {
                        // Log the error message from the microservice.
                        $log->add('mnaffiliates', "Microservice returned an error for user ID " . $user_id . ": " . ($result['message'] ?? 'Unknown error'));
                    }
                } else {
                    $error_message = $response->get_error_message();
                    $log->add('mnaffiliates', "Failed to send referral request for user ID " . $user_id . ": " . $error_message);
                }
            }
        }
    }
}
add_action('user_register', 'send_new_user_referral_to_mnaffiliates', 10, 1);


function mnaffiliates_maybe_send_on_login($user_id) {
    // اگر این کاربر قبلاً ثبت ارجاع شده یا کوکی کد ارجاع ندارد، کاری نکن
    if (empty($_COOKIE['mnaffiliate_code']) || get_user_meta($user_id, 'mnaffiliate_referred', true)) {
        return;
    }
    $log = wc_get_logger();
    $log->add('mnaffiliates', 'User ID ' . $user_id . ' login user.');
    
    send_new_user_referral_to_mnaffiliates($user_id);
}

add_action('wp_login', function($user_login, $user){
    mnaffiliates_maybe_send_on_login($user->ID);
}, 10, 2);

add_action('woocommerce_login_user', function($user_id){
    mnaffiliates_maybe_send_on_login($user_id);
}, 10, 1);


add_action('set_logged_in_cookie', function($logged_in_cookie, $expire, $expiration, $user_id){
    if ($user_id) {
        mnaffiliates_maybe_send_on_login($user_id);
    }
}, 10, 4);



add_action('wp_ajax_mnaff_check_referral', 'mnaff_check_referral_callback');
add_action('wp_ajax_nopriv_mnaff_check_referral', 'mnaff_check_referral_callback');
function mnaff_check_referral_callback() {
    // Check if the user is logged in
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('کاربر لاگین نیست.');
    }
    
    
    $api_url = MN_AFFILIATE_WEBHOOK_URL;
    $api_data = array(
        'api_key' => MN_AFFILIATE_API_KEY, // Your API key
        'action' => 'get_referral_info', // The new action for our webhook
        'user_id' => $user_id
    );
    
    $response = wp_remote_post($api_url, array(
        'body'    => $api_data,
        'timeout' => 45,
    ));
    if (is_wp_error($response)) {
        wp_send_json_error('خطا در اتصال به API.');
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['status']) && $data['status'] === 'success' && $data['data']['referred_user_commission_rate'] > 0) {
        // Store the referral data in the user's session
        WC()->session->set('mnaff_referral_data', $data['data']);
        wp_send_json_success();
    } else {
        WC()->session->set('mnaff_referral_data', null);
        wp_send_json_error('کاربر ارجاعی تخفیف ندارد.');
    }
}

add_action('woocommerce_cart_calculate_fees', 'mnaff_apply_referral_discount');
function mnaff_apply_referral_discount() {
    // This hook runs multiple times. Only apply the fee on the frontend,
    // and especially during AJAX updates.
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $referral_data = WC()->session->get('mnaff_referral_data');
    
    if ($referral_data && isset($referral_data['referred_user_commission_rate']) && $referral_data['referred_user_commission_rate'] > 0) {
        $discount_rate = (float)$referral_data['referred_user_commission_rate'];
        $cart_total = WC()->cart->get_subtotal();
        $discount_amount = $cart_total * ($discount_rate / 100);

        WC()->cart->add_fee(
            'تخفیف بازاریابی ' . $referral_data['affiliate_code'],
            -$discount_amount,
            true, // is_taxable
            'standard' // tax_class
        );
    }
}

add_action('woocommerce_before_checkout_form', 'mnaff_display_referral_message');
function mnaff_display_referral_message() {
    // Check for the referral data.
    $referral_data = WC()->session->get('mnaff_referral_data');
    if ($referral_data && $referral_data['referred_user_commission_rate'] > 0) {
        $message = sprintf(
            '<strong>%s%% تخفیف</strong> برای کد بازاریابی <strong>%s</strong> به سبد خرید شما اعمال شد.',
            htmlspecialchars($referral_data['referred_user_commission_rate']),
            htmlspecialchars($referral_data['affiliate_code'])
        );
        wc_print_notice($message, 'notice');
    }
}


add_action('woocommerce_thankyou', 'mnaff_send_commission_to_api');
function mnaff_send_commission_to_api($order_id) {
    
    if (class_exists('WC_Logger')) {
        $log = wc_get_logger();
        $log->add('mn-affiliate-api', 'commission for order: ' . $order_id);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return; // سفارش وجود ندارد — جلوگیری از Fatal Error روی null
    }
    
    // فقط برای وضعیت‌های موفق کمیسیون ثبت شود
    // (اینکوبات‌شده: پردازش، رسید کارت یا تکمیل‌شده — جلوگیری از ثبت دوبل در سمت API انجام می‌شود)
    $allowed_statuses = array('processing', 'receipt-card', 'completed');
    $order_status = $order->get_status();
    if (!in_array($order_status, $allowed_statuses, true)) {
        if (class_exists('WC_Logger')) {
            $log = wc_get_logger();
            $log->add('mn-affiliate-api', 'commission skipped for order: ' . $order_id . ' with status: ' . $order_status);
        }
        return;
    }
    
    // Get the required data from the order
    $user_id = $order->get_customer_id();
    $referral_discount_amount = 0;
    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'تخفیف بازاریابی') !== false) {
            $referral_discount_amount = $fee->get_amount();
            break;
        }
    }
    
    if (class_exists('WC_Logger')) {
        $log = wc_get_logger();
        $log->add('mn-affiliate-api', 'commission for order: ' . $order_id .' and the referaled User is : ' . $user_id);
    }

    
    
    // Check if a referral discount was applied.
    // if ($referral_discount_amount == 0) {
    //     return;
    // }
    
    $order_total = $order->get_total();

    // Prepare the data to be sent to your central webhook
    $api_data = array(
        'api_key' => MN_AFFILIATE_API_KEY, // Your API key
        'action' => 'record_commission', // The new action for our webhook
        'user_id' => $user_id,
        'order_id' => $order_id,
        'order_total' => $order_total,
        'discount_amount' => abs($referral_discount_amount) // NEW: Send the absolute value of the discount
    );

    // Call your external webhook with a POST request
    $api_url = MN_AFFILIATE_WEBHOOK_URL;
    $response = wp_remote_post($api_url, array(
        'body'    => $api_data,
        'timeout' => 45,
        'blocking' => false // This is important! Don't wait for the response
    ));
    
}


add_action('woocommerce_order_status_changed', 'mnaff_approve_commission_on_completion', 10, 4);
function mnaff_approve_commission_on_completion($order_id, $old_status, $new_status, $order) {
    // Check if the new status is 'processing' or 'completed'
    if ($new_status === 'completed') {
        // Prepare the data to be sent to your central webhook
        $api_data = array(
            'api_key' => MN_AFFILIATE_API_KEY,
            'action' => 'approve_commission',
            'order_id' => $order_id,
        );

        // Call your external webhook with a POST request
        $api_url = MN_AFFILIATE_WEBHOOK_URL;
        wp_remote_post($api_url, array(
            'body'    => $api_data,
            'timeout' => 45,
            'blocking' => false // Keep it asynchronous to avoid slowing down the site
        ));
    }
}

