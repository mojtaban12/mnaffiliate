<?php
// api/endpoints/affiliate_info.php
// اطلاعات بازاریاب بر اساس کد (برای ساخت کد تخفیف سمت وردپرس)

function getAffiliateInfo(PDO $pdo, array $data): array {
    if (empty($data['affiliate_code'])) {
        return ['status' => 'error', 'message' => 'Affiliate code is required.'];
    }

    // کولیشن ستون utf8mb4_unicode_ci است → مقایسه case-insensitive
    $stmt = $pdo->prepare("
        SELECT id, wp_user_id, affiliate_code, commission_rate, referred_user_commission_rate, status
        FROM affiliates
        WHERE affiliate_code = ?
        LIMIT 1
    ");
    $stmt->execute([$data['affiliate_code']]);
    $aff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aff) {
        return ['status' => 'error', 'message' => 'Invalid affiliate code provided.'];
    }

    return ['status' => 'success', 'data' => [
        'affiliate_code'                => $aff['affiliate_code'],
        'wp_user_id'                    => (int)$aff['wp_user_id'],
        'commission_rate'               => (float)$aff['commission_rate'],
        'referred_user_commission_rate' => (float)$aff['referred_user_commission_rate'],
        'affiliate_status'              => $aff['status'],
    ]];
}
