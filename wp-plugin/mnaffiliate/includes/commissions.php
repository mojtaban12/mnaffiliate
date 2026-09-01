<?php
/**
 * includes/commissions.php
 * تخفیف خرید ارجاعی + ثبت و تأیید کمیسیون
 */

if (!defined('ABSPATH')) {
    exit;
}

/* اسکریپت تسویه‌حساب (فقط صفحه checkout) */
add_action('wp_enqueue_scripts', 'mnaff_enqueue_checkout_script');
function mnaff_enqueue_checkout_script() {
    if (is_checkout() || is_page(wc_get_page_id('checkout'))) {
        wp_enqueue_script(
            'mnaff-checkout-script',
            get_stylesheet_directory_uri() . '/assets/js/mnreferral.js',
            ['jquery'],
            MNAFF_VERSION,
            true
        );
        wp_localize_script('mnaff-checkout-script', 'mnaff_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
}

/* AJAX: بررسی ارجاع کاربر لاگین‌شده و ذخیره در سشن */
add_action('wp_ajax_mnaff_check_referral', 'mnaff_check_referral_callback');
add_action('wp_ajax_nopriv_mnaff_check_referral', 'mnaff_check_referral_callback');
function mnaff_check_referral_callback() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('کاربر لاگین نیست.');
    }

    $response = wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'body' => [
            'api_key' => MN_AFFILIATE_API_KEY,
            'action'  => 'get_referral_info',
            'user_id' => $user_id,
        ],
        'timeout' => 45,
    ]);
    if (is_wp_error($response)) {
        wp_send_json_error('خطا در اتصال به API.');
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['status']) && $data['status'] === 'success' && $data['data']['referred_user_commission_rate'] > 0) {
        WC()->session->set('mnaff_referral_data', $data['data']);
        wp_send_json_success();
    } else {
        WC()->session->set('mnaff_referral_data', null);
        wp_send_json_error('کاربر ارجاعی تخفیف ندارد.');
    }
}

/* اعمال تخفیف بازاریابی در سبد خرید */
add_action('woocommerce_cart_calculate_fees', 'mnaff_apply_referral_discount');
function mnaff_apply_referral_discount() {
    // این هوک چند بار اجرا می‌شود؛ فقط در فرانت و AJAX
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $referral_data = WC()->session->get('mnaff_referral_data');

    if ($referral_data && isset($referral_data['referred_user_commission_rate']) && $referral_data['referred_user_commission_rate'] > 0) {
        $discount_rate   = (float)$referral_data['referred_user_commission_rate'];
        $cart_total      = WC()->cart->get_subtotal();
        $discount_amount = $cart_total * ($discount_rate / 100);

        WC()->cart->add_fee(
            'تخفیف بازاریابی ' . $referral_data['affiliate_code'],
            -$discount_amount,
            true,      // is_taxable
            'standard' // tax_class
        );
    }
}

/* نمایش پیام تخفیف در صفحه تسویه‌حساب */
add_action('woocommerce_before_checkout_form', 'mnaff_display_referral_message');
function mnaff_display_referral_message() {
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

/* ثبت کمیسیون پس از پرداخت موفق */
add_action('woocommerce_thankyou', 'mnaff_send_commission_to_api');
function mnaff_send_commission_to_api($order_id) {
    $log = wc_get_logger();
    $log->add('mn-affiliate-api', 'commission for order: ' . $order_id);

    $order = wc_get_order($order_id);
    if (!$order) {
        return; // سفارش وجود ندارد
    }

    // فقط برای وضعیت‌های موفق کمیسیون ثبت شود
    $allowed_statuses = ['processing', 'receipt-card', 'completed'];
    $order_status     = $order->get_status();
    if (!in_array($order_status, $allowed_statuses, true)) {
        $log->add('mn-affiliate-api', 'commission skipped for order: ' . $order_id . ' with status: ' . $order_status);
        return;
    }

    $user_id = $order->get_customer_id();

    // مبلغ تخفیف بازاریابی (برای محاسبه مبلغ واقعی سفارش)
    $referral_discount_amount = 0;
    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'تخفیف بازاریابی') !== false) {
            $referral_discount_amount = $fee->get_amount();
            break;
        }
    }

    $log->add('mn-affiliate-api', 'commission for order: ' . $order_id . ' and the referaled User is : ' . $user_id);

    $api_data = [
        'api_key'         => MN_AFFILIATE_API_KEY,
        'action'          => 'record_commission',
        'user_id'         => $user_id,
        'order_id'        => $order_id,
        'order_total'     => $order->get_total(),
        'discount_amount' => abs($referral_discount_amount),
    ];

    // ارسال غیرهمزمان (blocking=false) تا سرعت سایت حفظ شود
    wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'body'     => $api_data,
        'timeout'  => 45,
        'blocking' => false,
    ]);
}

/* تأیید کمیسیون هنگام تکمیل سفارش */
add_action('woocommerce_order_status_changed', 'mnaff_approve_commission_on_completion', 10, 4);
function mnaff_approve_commission_on_completion($order_id, $old_status, $new_status, $order) {
    if ($new_status !== 'completed') {
        return;
    }

    wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'body' => [
            'api_key'  => MN_AFFILIATE_API_KEY,
            'action'   => 'approve_commission',
            'order_id' => $order_id,
        ],
        'timeout'  => 45,
        'blocking' => false,
    ]);
}