<?php

require 'db_connect.php';

// SQL statements to create the tables
$sql_statements = [
    // 1. affiliates table
    "CREATE TABLE IF NOT EXISTS `affiliates` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `wp_user_id` INT(11) UNSIGNED NOT NULL,
        `user_mobile` varchar(20) COLLATE  utf8mb4_unicode_ci, 
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

    // 2. referrals table
    "CREATE TABLE IF NOT EXISTS `referrals` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `affiliate_id` INT(11) UNSIGNED NOT NULL,
        `referred_wp_user_id` INT(11) UNSIGNED NOT NULL,
        `user_mobile` varchar(20) COLLATE  utf8mb4_unicode_ci, 
        `referral_type` ENUM('signup', 'checkout') COLLATE utf8mb4_unicode_ci NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_referral` (`affiliate_id`, `referred_wp_user_id`),
        FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // 3. commissions table
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

    // 4. payments table
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

    // 5. logs table
    "CREATE TABLE IF NOT EXISTS `logs` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `request_type` VARCHAR(20) NOT NULL,
        `action` VARCHAR(100) NULL,
        `request_body` TEXT,
        `ip_address` VARCHAR(45),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // 6. login_attempts table (محافظت Brute-force لاگین)
    "CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(45) NOT NULL,
        `username` VARCHAR(100) NULL,
        `success` TINYINT(1) NOT NULL DEFAULT '0',
        `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Execute the SQL statements
try {
    foreach ($sql_statements as $sql) {
        $pdo->exec($sql);
    }
    echo "Tables created successfully or already exist.";
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}