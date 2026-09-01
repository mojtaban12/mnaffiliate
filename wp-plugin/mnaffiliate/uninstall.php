<?php
/**
 * uninstall.php — پاک‌سازی هنگام حذف افزونه
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// حذف متای بازاریابی از پروفایل کاربران
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('mnaffiliate_code', 'mnaffiliate_referred')");

// پاک‌سازی ترنسینت‌های احتمالی
delete_option('mnaffiliate_version');