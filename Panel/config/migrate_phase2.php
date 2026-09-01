<?php

/**
 * migrate_phase2.php
 * ------------------------------------------------------------------
 * مایگریشن فاز ۲ (سیستم تسویه‌حساب) — امن و idempotent.
 * پیش‌نیاز: اجرای migrate_phase1.php
 *
 * اجرا:  php migrate_phase2.php
 */

require_once __DIR__ . '/db_connect.php';

function columnExists2(PDO $pdo, $table, $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function tableExists2(PDO $pdo, $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

$migrations = [
    // 1. جدول اطلاعات بانکی بازاریاب
    [
        'check' => fn() => !tableExists2($pdo, 'affiliate_bank_info'),
        'sql'   => "CREATE TABLE IF NOT EXISTS `affiliate_bank_info` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` INT(11) UNSIGNED NOT NULL,
            `iban` VARCHAR(34) NOT NULL,
            `account_holder_name` VARCHAR(100) NOT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_affiliate` (`affiliate_id`),
            FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'label' => 'table affiliate_bank_info',
    ],
    // 2. ستون‌های جدید جدول payments
    [
        'check' => fn() => !columnExists2($pdo, 'payments', 'iban'),
        'sql'   => "ALTER TABLE `payments` ADD COLUMN `iban` VARCHAR(34) NULL AFTER `payment_details`",
        'label' => 'payments.iban',
    ],
    [
        'check' => fn() => !columnExists2($pdo, 'payments', 'account_holder_name'),
        'sql'   => "ALTER TABLE `payments` ADD COLUMN `account_holder_name` VARCHAR(100) NULL AFTER `iban`",
        'label' => 'payments.account_holder_name',
    ],
    [
        'check' => fn() => !columnExists2($pdo, 'payments', 'tracking_number'),
        'sql'   => "ALTER TABLE `payments` ADD COLUMN `tracking_number` VARCHAR(100) NULL AFTER `reference_number`",
        'label' => 'payments.tracking_number',
    ],
    [
        'check' => fn() => !columnExists2($pdo, 'payments', 'receipt_image'),
        'sql'   => "ALTER TABLE `payments` ADD COLUMN `receipt_image` VARCHAR(100) NULL AFTER `tracking_number`",
        'label' => 'payments.receipt_image',
    ],
    [
        'check' => fn() => !columnExists2($pdo, 'payments', 'reject_reason'),
        'sql'   => "ALTER TABLE `payments` ADD COLUMN `reject_reason` TEXT NULL AFTER `admin_notes`",
        'label' => 'payments.reject_reason',
    ],
    // 3. اتصال کمیسیون‌ها به درخواست تسویه
    [
        'check' => fn() => !columnExists2($pdo, 'commissions', 'payment_id'),
        'sql'   => "ALTER TABLE `commissions`
                    ADD COLUMN `payment_id` INT(11) UNSIGNED NULL AFTER `referral_id`,
                    ADD INDEX `idx_payment_id` (`payment_id`)",
        'label' => 'commissions.payment_id + index',
    ],
];

try {
    foreach ($migrations as $migration) {
        if (($migration['check'])()) {
            $pdo->exec($migration['sql']);
            echo "[DONE] {$migration['label']}\n";
        } else {
            echo "[SKIP] {$migration['label']} (از قبل موجود است)\n";
        }
    }
    echo "Phase 2 migration completed successfully.\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}