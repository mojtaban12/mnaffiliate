<?php

/**
 * migrate_phase1.php
 * ------------------------------------------------------------------
 * مایگریشن امن و idempotent برای دیتابیس موجود.
 * ستون‌ها/ایندکس‌ها را فقط در صورت نبود اضافه می‌کند و داده‌های
 * ستون قدیمی commission_amount را به ستون‌های جدید منتقل می‌کند.
 *
 * اجرا: یک بار در مرورگر یا CLI:  php migrate_phase1.php
 */

require_once __DIR__ . '/db_connect.php';

function columnExists(PDO $pdo, $table, $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function indexExists(PDO $pdo, $table, $index): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

$migrations = [
    // 1. ستون نرخ کمیسیون کاربر ارجاعی در affiliates
    [
        'check' => fn() => !columnExists($pdo, 'affiliates', 'referred_user_commission_rate'),
        'sql'   => "ALTER TABLE `affiliates` ADD COLUMN `referred_user_commission_rate` DECIMAL(5,2) NOT NULL DEFAULT '0.00' AFTER `commission_rate`",
        'label' => 'affiliates.referred_user_commission_rate',
    ],
    // 2. ستون‌های جدید کمیسیون در commissions
    [
        'check' => fn() => !columnExists($pdo, 'commissions', 'affiliate_commission_amount'),
        'sql'   => "ALTER TABLE `commissions` ADD COLUMN `affiliate_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00' AFTER `order_total`",
        'label' => 'commissions.affiliate_commission_amount',
    ],
    [
        'check' => fn() => !columnExists($pdo, 'commissions', 'referred_user_commission_amount'),
        'sql'   => "ALTER TABLE `commissions` ADD COLUMN `referred_user_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00' AFTER `affiliate_commission_amount`",
        'label' => 'commissions.referred_user_commission_amount',
    ],
    // 3. انتقال داده از ستون قدیمی commission_amount (در صورت وجود)
    [
        'check' => function () use ($pdo) {
            return columnExists($pdo, 'commissions', 'commission_amount')
                && columnExists($pdo, 'commissions', 'affiliate_commission_amount');
        },
        'sql'   => "UPDATE `commissions` SET `affiliate_commission_amount` = `commission_amount` WHERE `affiliate_commission_amount` = 0 AND `commission_amount` > 0",
        'label' => 'data migration: commission_amount -> affiliate_commission_amount',
    ],
    // 4. ایندکس یونیک جلوگیری از کمیسیون دوبل
    [
        'check' => fn() => !indexExists($pdo, 'commissions', 'uniq_order_affiliate'),
        'sql'   => "ALTER TABLE `commissions` ADD UNIQUE KEY `uniq_order_affiliate` (`wp_order_id`, `affiliate_id`)",
        'label' => 'commissions UNIQUE (wp_order_id, affiliate_id)',
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
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}