<?php

/**
 * migrate_phase3.php
 * ------------------------------------------------------------------
 * مایگریشن فاز ۳ (محافظت Brute-force لاگین) — امن و idempotent.
 * جدول login_attempts را فقط در صورت نبود می‌سازد.
 *
 * اجرا:  php migrate_phase3.php
 */

require_once __DIR__ . '/db_connect.php';

function tableExists3(PDO $pdo, $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

try {
    if (!tableExists3($pdo, 'login_attempts')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `username` VARCHAR(100) NULL,
            `success` TINYINT(1) NOT NULL DEFAULT '0',
            `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        echo "[DONE] table login_attempts\n";
    } else {
        echo "[SKIP] table login_attempts (از قبل موجود است)\n";
    }
    echo "Phase 3 migration completed successfully.\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
