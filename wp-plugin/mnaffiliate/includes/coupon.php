<?php
/**
 * includes/coupon.php
 * ------------------------------------------------------------------
 * «کد بازاریاب به‌عنوان کد تخفیف»:
 *  - کد بازاریاب را به یک کوپن واقعی ووکامرس (درصدی) تبدیل می‌کند
 *  - برچسب و پیام «تخفیف بازاریابی کد فلان» را نمایش می‌دهد
 *  - هنگام اعمال کوپن، ارجاع را برای کمیسیون ثبت می‌کند
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------ *
 *  گرفتن اطلاعات بازاریاب از میکروسرویس (با کش)
 * ------------------------------------------------------------------ */
function mnaff_get_affiliate_by_code($code) {
    $code = wc_format_coupon_code($code);
    if ($code === '') {
        return false;
    }

    $key    = 'mnaff_aff_' . md5(strtolower($code));
    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached ?: false;
    }

    $response = wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'timeout' => 15,
        'body'    => [
            'api_key'        => MN_AFFILIATE_API_KEY,
            'action'         => 'get_affiliate_info',
            'affiliate_code' => $code,
        ],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || ($body['status'] ?? '') !== 'success' || empty($body['data'])) {
        set_transient($key, [], 5 * MINUTE_IN_SECONDS); // کش منفی کوتاه
        return false;
    }

    $data = $body['data'];
    set_transient($key, $data, HOUR_IN_SECONDS);
    return $data;
}

/* ------------------------------------------------------------------ *
 *  ساخت / به‌روزرسانی کوپن ووکامرس برای کد بازاریاب
 * ------------------------------------------------------------------ */
function mnaff_ensure_affiliate_coupon($code, $rate, $canonical_code = '') {
    $code = wc_format_coupon_code($code);
    $rate = (float)$rate;
    if ($code === '' || $rate <= 0) {
        return 0;
    }

    $coupon_id = wc_get_coupon_id_by_code($code);
    $coupon    = $coupon_id ? new WC_Coupon($coupon_id) : new WC_Coupon();

    $coupon->set_code($code);
    $coupon->set_discount_type('percent');
    $coupon->set_amount($rate);
    $coupon->set_description('تخفیف بازاریابی کد ' . ($canonical_code ?: $code));
    $coupon->update_meta_data('_mnaff_affiliate_code', ($canonical_code ?: $code));
    $coupon->save();

    return $coupon->get_id();
}

/* ------------------------------------------------------------------ *
 *  آیا این کوپن، کوپن بازاریابی است؟
 * ------------------------------------------------------------------ */
function mnaff_is_affiliate_coupon($coupon) {
    if (!$coupon instanceof WC_Coupon) {
        return false;
    }
    return (bool)$coupon->get_meta('_mnaff_affiliate_code');
}

/**
 * آیا سبد خرید فعلی کوپن بازاریابی دارد؟ (برای جلوگیری از تخفیف دوبل با fee)
 */
function mnaff_cart_has_affiliate_coupon() {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_applied_coupons() as $code) {
        $id = wc_get_coupon_id_by_code($code);
        if ($id && mnaff_is_affiliate_coupon(new WC_Coupon($id))) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------ *
 *  پیش از پردازش فرم کوپن ووکامرس، کوپن بازاریاب را بساز/به‌روز کن
 *  (ووکامرس روی wp_loaded با اولویت ۲۰ فرم را پردازش می‌کند)
 * ------------------------------------------------------------------ */
add_action('wp_loaded', 'mnaff_prepare_affiliate_coupon', 15);
function mnaff_prepare_affiliate_coupon() {
    if (empty($_POST['apply_coupon']) || empty($_POST['coupon_code'])) {
        return;
    }

    $code = wc_format_coupon_code(wp_unslash($_POST['coupon_code']));
    if ($code === '') {
        return;
    }

    $aff = mnaff_get_affiliate_by_code($code);
    if (!$aff || ($aff['affiliate_status'] ?? 'active') !== 'active') {
        return; // کد بازاریاب معتبر/فعال نیست → بگذار ووکامرس خطای خودش را بدهد
    }

    $rate      = (float)$aff['referred_user_commission_rate'];
    $canonical = $aff['affiliate_code'];
    $coupon_id = wc_get_coupon_id_by_code($code);

    if ($coupon_id) {
        $coupon = new WC_Coupon($coupon_id);
        // فقط کوپن‌های خودمان را به‌روز کن (به کوپن‌های دیگر دست نزن)
        if (mnaff_is_affiliate_coupon($coupon) && (float)$coupon->get_amount() !== $rate) {
            mnaff_ensure_affiliate_coupon($code, $rate, $canonical);
        }
    } else {
        mnaff_ensure_affiliate_coupon($code, $rate, $canonical);
    }
}

/* ------------------------------------------------------------------ *
 *  برچسب زیبا در جمع فاکتور
 * ------------------------------------------------------------------ */
add_filter('woocommerce_cart_totals_coupon_label', function ($label, $coupon) {
    if (mnaff_is_affiliate_coupon($coupon)) {
        return 'تخفیف بازاریابی کد ' . $coupon->get_code();
    }
    return $label;
}, 10, 2);

/* ------------------------------------------------------------------ *
 *  پیام موفقیت سفارشی هنگام اعمال کوپن
 * ------------------------------------------------------------------ */
add_filter('woocommerce_coupon_message', function ($msg, $msg_code, $coupon) {
    if ($msg_code === WC_Coupon::WC_COUPON_SUCCESS && mnaff_is_affiliate_coupon($coupon)) {
        return sprintf(
            '%s%% تخفیف بازاریابی کد %s اعمال شد.',
            (float)$coupon->get_amount(),
            $coupon->get_code()
        );
    }
    return $msg;
}, 10, 3);

/* ------------------------------------------------------------------ *
 *  هنگام اعمال کوپن بازاریاب: ست کوکی + ثبت ارجاع (برای کمیسیون)
 * ------------------------------------------------------------------ */
add_action('woocommerce_applied_coupon', 'mnaff_on_affiliate_coupon_applied');
function mnaff_on_affiliate_coupon_applied($code) {
    $coupon_id = wc_get_coupon_id_by_code($code);
    if (!$coupon_id) {
        return;
    }
    $coupon = new WC_Coupon($coupon_id);
    if (!mnaff_is_affiliate_coupon($coupon)) {
        return;
    }

    $affiliate_code = $coupon->get_meta('_mnaff_affiliate_code') ?: $coupon->get_code();

    // کوکی ارجاع برای مسیرهای بعدی (ثبت‌نام/ورود)
    setcookie('mnaffiliate_code', $affiliate_code, time() + (60 * 60 * 24 * 180), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

    // ثبت ارجاع برای کمیسیون (فقط کاربر لاگین‌شده و اگر قبلاً ثبت نشده)
    $user_id = get_current_user_id();
    if (!$user_id || get_user_meta($user_id, 'mnaffiliate_referred', true)) {
        return;
    }

    $user = get_user_by('id', $user_id);
    $response = wp_remote_post(MN_AFFILIATE_WEBHOOK_URL, [
        'timeout' => 20,
        'body'    => [
            'api_key'              => MN_AFFILIATE_API_KEY,
            'action'               => 'record_referral',
            'affiliate_code'       => $affiliate_code,
            'referred_wp_user_id'  => $user_id,
            'referred_user_mobile' => $user ? $user->user_login : '',
        ],
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['status']) && $body['status'] === 'success') {
        update_user_meta($user_id, 'mnaffiliate_referred', '1');
    }
}
