<?php

/**
 * generate_password_hash.php
 * ------------------------------------------------------------------
 * برای ساخت هش امن پسورد ادمین، این فایل را روی سرور اجرا کنید:
 *   php generate_password_hash.php [پسورد]
 * یا در مرورگر:  /mnaffiliate/config/generate_password_hash.php?pass=رمز-جدید
 *
 * خروجی را در config/config.php داخل MNAFF_ADMIN_PASSWORD_HASH قرار دهید
 * و سپس MNAFF_ADMIN_PASSWORD را از کانفیگ حذف کنید و این فایل را پاک کنید.
 */

$password = $argv[1] ?? ($_GET['pass'] ?? '');

if (empty($password)) {
    echo "Usage: php generate_password_hash.php <password>\n";
    echo "   or: generate_password_hash.php?pass=<password>\n";
    exit(1);
}

echo "Password hash (این مقدار را در MNAFF_ADMIN_PASSWORD_HASH قرار دهید):\n\n";
echo password_hash($password, PASSWORD_DEFAULT) . "\n";