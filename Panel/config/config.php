<?php
/**
 * config/config.php
 * ------------------------------------------------------------------
 * مرکز مدیریت تنظیمات و کلیدهای امنیتی پنل بازاریابی (mnaffiliates)
 * این فایل تنها منبع حقیقت (Single Source of Truth) برای کلیدهاست.
 * توجه: این فایل نباید به صورت عمومی در دسترس باشد (به .htaccess مراجعه کنید)
 */

// ---------- تنظیمات دیتابیس ----------
define('MNAFF_DB_HOST', '192.168.150.100:3306');
define('MNAFF_DB_NAME', 'mnaffiliates_db');
define('MNAFF_DB_USER', 'root');
define('MNAFF_DB_PASS', 'PZ93uH2P9qntsn');

// ---------- کلید ارتباط میکروسرویس <-> وردپرس (webhook) ----------
// باید با MN_AFFILIATE_API_KEY در سمت وردپرس یکی باشد
define('MNAFF_WEBHOOK_API_KEY', '7217e4da-7ffd-11f0-bf11-726dbfac5a6c');

// ---------- کلید ارتباط پنل <-> REST API وردپرس ----------
// باید با کلید تعریف‌شده در فایل functions تم چایلد (سمت وردپرس) یکی باشد
define('MNAFF_WP_REST_API_KEY', '6e01b310-7fff-11f0-bf11-726dbfac5a6c');

// ---------- آدرس‌های وردپرس ----------
define('MNAFF_WP_REGISTER_AFFILIATE_URL', 'https://puonak.com/wp-json/mnaffiliates/v1/registerAffiliate');
define('MNAFF_WP_USER_SEARCH_URL',        'https://puonak.com/wp-json/mnaffiliates/v1/users/search');

// ---------- اطلاعات ورود پنل ادمین ----------
define('MNAFF_ADMIN_USERNAME', 'admin');
// هش bcrypt پسورد ادمین (پسورد فعلی: admin@123 — حتماً تغییرش دهید!)
// برای تغییر: با config/generate_password_hash.php هش جدید بسازید و جایگزین کنید
define('MNAFF_ADMIN_PASSWORD_HASH', '$2y$10$cqDjFrZIZMVkBe7HNkZod.NSZVh8CG9Rlone0onKqTiqzevBC1OIm');

// ---------- مسیر پایه روتر پنل ----------
define('MNAFF_BASE_PATH', 'mnaffiliate');

// ---------- تسویه‌حساب ----------
// حداقل مبلغ برداشت (تومان)
define('MNAFF_MIN_PAYOUT_AMOUNT', 500000);
// آدرس عمومی پنل (برای ساخت لینک رسیدهای پرداخت)
define('MNAFF_PANEL_PUBLIC_URL', 'https://www.puonak.com/mnaffiliate');
