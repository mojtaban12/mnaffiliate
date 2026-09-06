<?php
/**
 * config/auto_migrate.php
 * ------------------------------------------------------------------
 * اجرای خودکار و idempotent مایگریشن‌ها هنگام لود شدن پنل/وب‌هوک.
 * این فایل از وب deny است (نکند)، ولی از طریق require در PHP قابل اجراست.
 * اگر جدول‌ها/ستون‌ها نباشند ساخته می‌شوند، در غیر این صورت NOTHING.
 */

function mnaff_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mnaff_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function mnaff_index_exists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function mnaff_auto_migrate(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        // ---------- جداول پایه (CREATE TABLE IF NOT EXISTS — idempotent) ----------
        $tables = [
            "CREATE TABLE IF NOT EXISTS `affiliates` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_user_id` INT(11) UNSIGNED NOT NULL,
                `user_mobile` varchar(20) COLLATE utf8mb4_unicode_ci,
                `affiliate_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL,
                `commission_rate` DECIMAL(5,2) DEFAULT '10.00',
                `referred_user_commission_rate` DECIMAL(5,2) DEFAULT '0.00',
                `status` ENUM('active', 'inactive', 'suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
                `total_earnings` DECIMAL(12,2) DEFAULT '0.00',
                `total_referrals` INT(11) UNSIGNED DEFAULT '0',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_wp_user_id` (`wp_user_id`),
                INDEX `idx_affiliate_code` (`affiliate_code`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `referrals` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `affiliate_id` INT(11) UNSIGNED NOT NULL,
                `referred_wp_user_id` INT(11) UNSIGNED NOT NULL,
                `user_mobile` varchar(20) COLLATE utf8mb4_unicode_ci,
                `referral_type` ENUM('signup', 'checkout') COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_referral` (`affiliate_id`, `referred_wp_user_id`),
                FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `commissions` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_order_id` INT(11) UNSIGNED NOT NULL,
                `affiliate_id` INT(11) UNSIGNED NOT NULL,
                `referral_id` INT(11) UNSIGNED NULL,
                `order_total` DECIMAL(12,2) NOT NULL,
                `affiliate_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
                `referred_user_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
                `commission_rate` DECIMAL(5,2) NOT NULL,
                `level` INT(11) UNSIGNED DEFAULT '1',
                `parent_commission_id` INT(11) UNSIGNED NULL,
                `status` ENUM('pending', 'approved', 'paid', 'cancelled', 'failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_order_affiliate` (`wp_order_id`, `affiliate_id`),
                INDEX `idx_wp_order_id` (`wp_order_id`),
                INDEX `idx_affiliate_id` (`affiliate_id`),
                FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`referral_id`) REFERENCES `referrals`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `payments` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `affiliate_id` INT(11) UNSIGNED NOT NULL,
                `wp_user_id` INT(11) UNSIGNED NOT NULL,
                `total_amount` DECIMAL(12,2) NOT NULL,
                `commission_ids` JSON NOT NULL,
                `payment_method` ENUM('bank_transfer', 'paypal', 'wallet') COLLATE utf8mb4_unicode_ci NOT NULL,
                `payment_details` TEXT COLLATE utf8mb4_unicode_ci NULL,
                `status` ENUM('pending', 'completed', 'failed', 'cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
                `reference_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
                `admin_notes` TEXT COLLATE utf8mb4_unicode_ci NULL,
                `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `processed_at` TIMESTAMP NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_affiliate_id` (`affiliate_id`),
                FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `logs` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `request_type` VARCHAR(20) NOT NULL,
                `action` VARCHAR(100) NULL,
                `request_body` TEXT,
                `ip_address` VARCHAR(45),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `username` VARCHAR(100) NULL,
                `success` TINYINT(1) NOT NULL DEFAULT '0',
                `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `affiliate_bank_info` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `affiliate_id` INT(11) UNSIGNED NOT NULL,
                `iban` VARCHAR(34) NOT NULL,
                `account_holder_name` VARCHAR(100) NOT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_affiliate` (`affiliate_id`),
                FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        // ---------- ستون‌های فاز ۱ ----------
        if (!mnaff_column_exists($pdo, 'affiliates', 'total_referrals')) {
            $pdo->exec("ALTER TABLE `affiliates` ADD COLUMN `total_referrals` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `total_earnings`");
        }
        if (!mnaff_column_exists($pdo, 'affiliates', 'referred_user_commission_rate')) {
            $pdo->exec("ALTER TABLE `affiliates` ADD COLUMN `referred_user_commission_rate` DECIMAL(5,2) NOT NULL DEFAULT '0.00' AFTER `commission_rate`");
        }
        if (!mnaff_column_exists($pdo, 'commissions', 'affiliate_commission_amount')) {
            $pdo->exec("ALTER TABLE `commissions` ADD COLUMN `affiliate_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00' AFTER `order_total`");
        }
        if (!mnaff_column_exists($pdo, 'commissions', 'referred_user_commission_amount')) {
            $pdo->exec("ALTER TABLE `commissions` ADD COLUMN `referred_user_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00' AFTER `affiliate_commission_amount`");
        }

        // ---------- ستون/ایندکس‌های فاز ۲ (تسویه‌حساب) ----------
        if (!mnaff_column_exists($pdo, 'payments', 'iban')) {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `iban` VARCHAR(34) NULL AFTER `payment_details`");
        }
        if (!mnaff_column_exists($pdo, 'payments', 'account_holder_name')) {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `account_holder_name` VARCHAR(100) NULL AFTER `iban`");
        }
        if (!mnaff_column_exists($pdo, 'payments', 'tracking_number')) {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `tracking_number` VARCHAR(100) NULL AFTER `reference_number`");
        }
        if (!mnaff_column_exists($pdo, 'payments', 'receipt_image')) {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `receipt_image` VARCHAR(100) NULL AFTER `tracking_number`");
        }
        if (!mnaff_column_exists($pdo, 'payments', 'reject_reason')) {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `reject_reason` TEXT NULL AFTER `admin_notes`");
        }
        if (!mnaff_column_exists($pdo, 'commissions', 'payment_id')) {
            $pdo->exec("ALTER TABLE `commissions` ADD COLUMN `payment_id` INT(11) UNSIGNED NULL AFTER `referral_id`, ADD INDEX `idx_payment_id` (`payment_id`)");
        }

        // ---------- ایندکس یونیک جلوگیری از کمیسیون دوبل ----------
        if (!mnaff_index_exists($pdo, 'commissions', 'uniq_order_affiliate')) {
            $pdo->exec("ALTER TABLE `commissions` ADD UNIQUE KEY `uniq_order_affiliate` (`wp_order_id`, `affiliate_id`)");
        }

    } catch (PDOException $e) {
        error_log('auto_migrate failed: ' . $e->getMessage());
    }
}
