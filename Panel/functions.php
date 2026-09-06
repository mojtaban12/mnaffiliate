<?php
// functions.php

function getWordPressUsers($url, $key) {
    $response = file_get_contents($url . '?api_key=' . $key);
    if ($response === FALSE) {
        return ['error' => 'Failed to connect to WordPress API.'];
    }
    return json_decode($response, true);
}

// Function to generate a unique affiliate code
function generateAffiliateCode($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function logRequest(PDO $pdo, $action) {
    // Capture the entire request body
    $request_body = file_get_contents('php://input');
    logRequestBody($pdo, $action, $request_body);
}

/**
 * ثبت لاگ با بدنه‌ی از پیش آماده‌شده (برای حذف کلید API از لاگ‌ها)
 */
function logRequestBody(PDO $pdo, $action, $request_body) {
    // Get the request type (e.g., POST, GET)
    $request_type = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    // Get the IP address of the client
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (request_type, action, request_body, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$request_type, $action, $request_body, $ip_address]);
    } catch (PDOException $e) {
        // Log to file instead of echoing (echo may corrupt JSON responses)
        error_log('Failed to log request: ' . $e->getMessage());
    }
}

/**
 * دریافت IP واقعی کلاینت (با احترام به پروکسی nginx)
 */
function mnaff_get_client_ip(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        return trim($parts[0]);
    }
    $real = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if ($real !== '') {
        return trim($real);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * تضمین وجود جدول login_attempts (ساخت در صورت نبود — self-healing)
 */
function mnaff_ensure_login_attempts_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `username` VARCHAR(100) NULL,
            `success` TINYINT(1) NOT NULL DEFAULT '0',
            `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $checked = true;
    } catch (PDOException $e) {
        // نبود اجازه/DB در دسترس نیست — دفعه بعد دوباره تلاش می‌شود
        error_log('login_attempts table error: ' . $e->getMessage());
    }
}

/**
 * تعداد تلاش‌های ناموفق در بازه زمانی قفل
 */
function mnaff_login_failed_count(PDO $pdo, string $ip): int {
    try {
        mnaff_ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = ? AND success = 0
              AND attempted_at >= (NOW() - INTERVAL " . (int)MNAFF_LOGIN_WINDOW_MINUTES . " MINUTE)
        ");
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('login attempts count failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * بررسی قفل بودن لاگین؛ در صورت قفل، زمان باقی‌مانده (ثانیه) را به $remaining می‌دهد
 */
function mnaff_login_is_locked(PDO $pdo, string $ip, ?int &$remaining = null): bool {
    try {
        mnaff_ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare("
            SELECT MIN(attempted_at) FROM login_attempts
            WHERE ip_address = ? AND success = 0
              AND attempted_at >= (NOW() - INTERVAL " . (int)MNAFF_LOGIN_WINDOW_MINUTES . " MINUTE)
        ");
        $stmt->execute([$ip]);
        $oldest = $stmt->fetchColumn();

        if (mnaff_login_failed_count($pdo, $ip) < (int)MNAFF_LOGIN_MAX_ATTEMPTS) {
            $remaining = 0;
            return false;
        }

        if (isset($oldest) && $oldest) {
            $window   = (int)MNAFF_LOGIN_WINDOW_MINUTES * 60;
            $elapsed  = time() - strtotime($oldest);
            $remaining = max(0, $window - $elapsed);
        } else {
            $remaining = 0;
        }
        return true;
    } catch (PDOException $e) {
        // در صورت هر خطای DB، قفل را نادیده بگیر (fail-open) اما لاگ کن
        error_log('login lockout check failed: ' . $e->getMessage());
        $remaining = 0;
        return false;
    }
}

/**
 * ثبت یک تلاش ناموفق ورود + پاک‌سازی رکوردهای قدیمی
 */
function mnaff_login_record_failure(PDO $pdo, string $ip, string $username): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, 0)");
        $stmt->execute([$ip, $username]);
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)");
    } catch (PDOException $e) {
        error_log('Failed to record login attempt: ' . $e->getMessage());
    }
}

/**
 * پاک‌سازی سابقه تلاش‌ها پس از ورود موفق
 */
function mnaff_login_clear(PDO $pdo, string $ip, string $username): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = ?");
        $stmt->execute([$ip, $username]);
    } catch (PDOException $e) {
        error_log('Failed to clear login attempts: ' . $e->getMessage());
    }
}

function sendPostRequest($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for HTTP errors (like 401, 404, etc.)
    if ($http_code >= 400) {
        throw new Exception("HTTP Error: " . $http_code . " - " . strip_tags($response));
    }
    
    return $response;
}