<?php
// api/endpoints/payments.php
// عملیات ادمین روی درخواست‌های تسویه (فقط از طریق روتر پنل با check_auth)

/**
 * لیست همه درخواست‌های تسویه
 */
function listPayments(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT p.*, a.user_mobile, a.affiliate_code
        FROM payments p
        JOIN affiliates a ON p.affiliate_id = a.id
        ORDER BY FIELD(p.status, 'pending', 'completed', 'cancelled', 'failed'), p.requested_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ذخیره امن تصویر رسید (نام تصادفی + اعتبارسنجی mime واقعی)
 */
function saveReceiptImage(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'خطا در آپلود فایل رسید.'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['status' => 'error', 'message' => 'حجم فایل رسید نباید بیشتر از ۵ مگابایت باشد.'];
    }

    $allowed = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return ['status' => 'error', 'message' => 'فرمت فایل رسید باید JPG، PNG یا PDF باشد.'];
    }

    $dir = __DIR__ . '/../../uploads/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['status' => 'error', 'message' => 'ذخیره فایل رسید ناموفق بود.'];
    }

    return ['status' => 'success', 'filename' => $name];
}

/**
 * تأیید پرداخت: ثبت رهگیری/رسید + تسویه کمیسیون‌ها (اتمیک)
 */
function approvePayment(PDO $pdo, array $data, array $files): array {
    $payment_id = (int)($data['payment_id'] ?? 0);
    $tracking   = trim($data['tracking_number'] ?? '');

    if (!$payment_id) {
        return ['status' => 'error', 'message' => 'شناسه پرداخت نامعتبر است.'];
    }
    if ($tracking === '') {
        return ['status' => 'error', 'message' => 'شماره رهگیری الزامی است.'];
    }

    // آپلود رسید (اختیاری)
    $receipt_filename = null;
    if (!empty($files['receipt_image']['name'])) {
        $res = saveReceiptImage($files['receipt_image']);
        if ($res['status'] !== 'success') {
            return $res;
        }
        $receipt_filename = $res['filename'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$payment_id]);
        if (!$stmt->fetchColumn()) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'درخواست در حال بررسی یافت نشد.'];
        }

        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', tracking_number = ?, receipt_image = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$tracking, $receipt_filename, $payment_id]);

        $stmt = $pdo->prepare("UPDATE commissions SET status = 'paid' WHERE payment_id = ? AND status = 'approved'");
        $stmt->execute([$payment_id]);

        $pdo->commit();
        return ['status' => 'success', 'message' => 'پرداخت با موفقیت تکمیل و به بازاریاب اطلاع‌رسانی می‌شود.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => 'error', 'message' => 'خطا: ' . $e->getMessage()];
    }
}

/**
 * رد درخواست: برگشت موجودی به کیف پول بازاریاب (اتمیک)
 */
function rejectPayment(PDO $pdo, array $data): array {
    $payment_id = (int)($data['payment_id'] ?? 0);
    $reason     = trim($data['reject_reason'] ?? '');

    if (!$payment_id) {
        return ['status' => 'error', 'message' => 'شناسه پرداخت نامعتبر است.'];
    }
    if ($reason === '') {
        return ['status' => 'error', 'message' => 'دلیل رد درخواست الزامی است.'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$payment_id]);
        if (!$stmt->fetchColumn()) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'درخواست در حال بررسی یافت نشد.'];
        }

        $stmt = $pdo->prepare("UPDATE payments SET status = 'cancelled', reject_reason = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$reason, $payment_id]);

        // آزادسازی کمیسیون‌ها — موجودی به بازاریاب برمی‌گردد
        $stmt = $pdo->prepare("UPDATE commissions SET payment_id = NULL WHERE payment_id = ?");
        $stmt->execute([$payment_id]);

        $pdo->commit();
        return ['status' => 'success', 'message' => 'درخواست رد شد و موجودی به کیف پول بازاریاب برگشت.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => 'error', 'message' => 'خطا: ' . $e->getMessage()];
    }
}