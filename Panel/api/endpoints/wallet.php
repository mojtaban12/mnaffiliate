<?php
// api/endpoints/wallet.php
// کیف پول بازاریاب: موجودی، درخواست تسویه، لیست درخواست‌ها

require_once __DIR__ . '/../../config/config.php';

/**
 * تبدیل ارقام فارسی/عربی به انگلیسی
 */
function mnaff_normalize_digits(string $value): string {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $value));
}

/**
 * اعتبارسنجی شبا: فرمت IR + ۲۴ رقم + چک جمع کنترلی mod97
 */
function mnaff_valid_iban(string $iban): bool {
    $iban = strtoupper(preg_replace('/[\s\-]/', '', mnaff_normalize_digits($iban)));
    if (!preg_match('/^IR\d{24}$/', $iban)) {
        return false;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $ch) {
        $numeric .= ctype_alpha($ch) ? (string)(ord(strtoupper($ch)) - 55) : $ch;
    }
    $rem = 0;
    foreach (str_split($numeric) as $d) {
        $rem = ($rem * 10 + (int)$d) % 97;
    }
    return $rem === 1;
}

function mnaff_find_affiliate(PDO $pdo, string $code): ?array {
    $stmt = $pdo->prepare("SELECT id, wp_user_id FROM affiliates WHERE affiliate_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * موجودی کیف پول بازاریاب
 */
function getWallet(PDO $pdo, array $data): array {
    if (empty($data['affiliate_code'])) {
        return ['status' => 'error', 'message' => 'Affiliate code is required.'];
    }
    $affiliate = mnaff_find_affiliate($pdo, $data['affiliate_code']);
    if (!$affiliate) {
        return ['status' => 'error', 'message' => 'Invalid affiliate code provided.'];
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'approved' AND payment_id IS NULL THEN affiliate_commission_amount ELSE 0 END), 0) AS available_balance,
            COALESCE(SUM(CASE WHEN status = 'approved' AND payment_id IS NOT NULL THEN affiliate_commission_amount ELSE 0 END), 0) AS in_settlement_amount,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN affiliate_commission_amount ELSE 0 END), 0) AS paid_total
        FROM commissions
        WHERE affiliate_id = ?
    ");
    $stmt->execute([$affiliate['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return ['status' => 'success', 'data' => [
        'available_balance'    => (float)($row['available_balance'] ?? 0),
        'in_settlement_amount' => (float)($row['in_settlement_amount'] ?? 0),
        'paid_total'           => (float)($row['paid_total'] ?? 0),
        'min_payout_amount'    => (float)MNAFF_MIN_PAYOUT_AMOUNT,
    ]];
}

/**
 * ثبت درخواست تسویه (اتمیک با قفل سطرها)
 */
function requestPayout(PDO $pdo, array $data): array {
    foreach (['affiliate_code', 'iban', 'account_holder_name'] as $field) {
        if (empty($data[$field])) {
            return ['status' => 'error', 'message' => 'اطلاعات ناقص است: ' . $field];
        }
    }

    $code   = $data['affiliate_code'];
    $iban   = strtoupper(preg_replace('/[\s\-]/', '', mnaff_normalize_digits($data['iban'])));
    $holder = trim($data['account_holder_name']);

    if (!mnaff_valid_iban($iban)) {
        return ['status' => 'error', 'message' => 'شماره شبا معتبر نیست.'];
    }
    if (mb_strlen($holder) < 3) {
        return ['status' => 'error', 'message' => 'نام صاحب حساب معتبر نیست.'];
    }

    $affiliate = mnaff_find_affiliate($pdo, $code);
    if (!$affiliate) {
        return ['status' => 'error', 'message' => 'Invalid affiliate code provided.'];
    }

    try {
        $pdo->beginTransaction();

        // جلوگیری از درخواست همزمان/دوبل
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE affiliate_id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$affiliate['id']]);
        if ($stmt->fetchColumn()) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'شما یک درخواست تسویه در حال بررسی دارید.'];
        }


        // قفل کمیسیون‌های قابل برداشت
        $stmt = $pdo->prepare("SELECT id, affiliate_commission_amount FROM commissions WHERE affiliate_id = ? AND status = 'approved' AND payment_id IS NULL FOR UPDATE");
        $stmt->execute([$affiliate['id']]);
        $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0.0;
        foreach ($commissions as $c) {
            $total += (float)$c['affiliate_commission_amount'];
        }

        if ($total < (float)MNAFF_MIN_PAYOUT_AMOUNT) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'موجودی قابل برداشت شما (' . number_format($total) . ' تومان) کمتر از حداقل مبلغ تسویه (' . number_format((float)MNAFF_MIN_PAYOUT_AMOUNT) . ' تومان) است.'];
        }

        $stmt = $pdo->prepare("INSERT INTO payments (affiliate_id, wp_user_id, total_amount, commission_ids, payment_method, iban, account_holder_name, status) VALUES (?, ?, ?, ?, 'bank_transfer', ?, ?, 'pending')");
        $stmt->execute([
            $affiliate['id'],
            $affiliate['wp_user_id'],
            round($total, 2),
            json_encode(array_column($commissions, 'id')),
            $iban,
            $holder,
        ]);
        $payment_id = (int)$pdo->lastInsertId();

        // اتصال کمیسیون‌ها به این درخواست
        $ids = array_column($commissions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE commissions SET payment_id = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$payment_id], $ids));

        $pdo->commit();

        return [
            'status' => 'success',
            'message' => 'درخواست تسویه با موفقیت ثبت شد.',
            'payment_id' => $payment_id,
            'amount' => round($total, 2),
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => 'error', 'message' => 'خطا: ' . $e->getMessage()];
    }
}


/**
 * لیست درخواست‌های تسویه بازاریاب
 */
function getPayouts(PDO $pdo, array $data): array {
    if (empty($data['affiliate_code'])) {
        return ['status' => 'error', 'message' => 'Affiliate code is required.'];
    }
    $affiliate = mnaff_find_affiliate($pdo, $data['affiliate_code']);
    if (!$affiliate) {
        return ['status' => 'error', 'message' => 'Invalid affiliate code provided.'];
    }

    $stmt = $pdo->prepare("SELECT id, total_amount, status, tracking_number, receipt_image, reject_reason, iban, requested_at, processed_at FROM payments WHERE affiliate_id = ? ORDER BY requested_at DESC");
    $stmt->execute([$affiliate['id']]);

    $payouts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $payouts[] = [
            'id'              => (int)$p['id'],
            'date'            => date('Y-m-d', strtotime($p['requested_at'])),
            'amount'          => (float)$p['total_amount'],
            'status'          => $p['status'],
            'tracking_number' => $p['tracking_number'],
            'receipt_image'   => $p['receipt_image'],
            'reject_reason'   => $p['reject_reason'],
            'iban'            => $p['iban'],
            'processed_at'    => $p['processed_at'] ? date('Y-m-d', strtotime($p['processed_at'])) : null,
        ];
    }

    return ['status' => 'success', 'payouts' => $payouts];
}
