<?php
/**
 * Plugin Name:       MN Affiliate — سیستم بازاریابی یک‌سطحی
 * Plugin URI:        https://puonak.com
 * Description:       سیستم بازاریابی یک‌سطحی متصل به میکروسرویس mnaffiliates؛ شامل لینک ارجاع، تخفیف خودکار خریدار، کمیسیون، کیف پول و درخواست تسویه دستی.
 * Version:           2.0.0
 * Author:            MN Affiliate
 * Text Domain:       mnaffiliate
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit; // دسترسی مستقیم ممنوع
}

define('MNAFF_VERSION', '2.0.0');
define('MNAFF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MNAFF_PLUGIN_URL', plugin_dir_url(__FILE__));

/*
 * ------------------------------------------------------------------
 * ثابت‌های اتصال به میکروسرویس
 * (قابل بازنویسی در wp-config.php با define قبل از نصب افزونه)
 * ------------------------------------------------------------------
 */
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
// آدرس عمومی پنل (برای لینک رسیدهای پرداخت)
if (!defined('MN_AFFILIATE_PANEL_URL')) {
    define('MN_AFFILIATE_PANEL_URL', 'https://www.puonak.com/mnaffiliate');
}

/**
 * بارگذاری ماژول‌ها بعد از آماده شدن ووکامرس
 */
add_action('plugins_loaded', 'mnaff_plugin_init');
function mnaff_plugin_init() {

    // وابستگی: ووکامرس
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>افزونه MN Affiliate:</strong> برای کار کردن به <strong>ووکامرس</strong> نیاز دارد.</p></div>';
        });
        return;
    }

    require_once MNAFF_PLUGIN_DIR . 'includes/rest-api.php';      // اندپوینت‌های REST
    require_once MNAFF_PLUGIN_DIR . 'includes/referrals.php';     // ثبت ارجاع‌ها
    require_once MNAFF_PLUGIN_DIR . 'includes/commissions.php';   // تخفیف + ثبت/تأیید کمیسیون
    require_once MNAFF_PLUGIN_DIR . 'includes/wallet-ajax.php';   // AJAX درخواست تسویه
    require_once MNAFF_PLUGIN_DIR . 'includes/dashboard.php';     // تب «همکاری در فروش»
}

/**
 * فعال‌سازی افزونه: ثبت اندپوینت + ساخت مجدد قوانین بازنویسی
 */
register_activation_hook(__FILE__, 'mnaff_plugin_activate');
function mnaff_plugin_activate() {
    add_rewrite_endpoint('mnaffiliate', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
    update_option('mnaffiliate_version', MNAFF_VERSION);
}

/**
 * غیرفعال‌سازی: پاک‌سازی قوانین بازنویسی
 */
register_deactivation_hook(__FILE__, 'mnaff_plugin_deactivate');
function mnaff_plugin_deactivate() {
    flush_rewrite_rules();
}

/**
 * اگر نسخه افزونه تغییر کرد، قوانین بازنویسی را بازسازی کن
 * (تا آپدیت‌های بعدی هم اندپوینت خراب نشود)
 */
add_action('admin_init', function () {
    if (get_option('mnaffiliate_version') !== MNAFF_VERSION) {
        update_option('mnaffiliate_version', MNAFF_VERSION);
        add_rewrite_endpoint('mnaffiliate', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }
});

/**
 * ثبت اندپوینت «همکاری در فروش» در حساب کاربری ووکامرس
 * (هر بار اجرا می‌شود تا قانون بازنویسی همیشه موجود باشد)
 */
add_action('init', function () {
    add_rewrite_endpoint('mnaffiliate', EP_ROOT | EP_PAGES);
});

/**
 * لینک «افزونه‌ها» — نمایش تنظیمات (فعلاً لینک به تب پنل کاربری)
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(wc_get_account_endpoint_url('mnaffiliate')) . '">پنل بازاریاب</a>');
    return $links;
});