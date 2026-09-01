<?php
// api/endpoints/stats.php

function getReferralStats(PDO $pdo, array $data): array {
    if (empty($data['affiliate_code'])) {
        return ['status' => 'error', 'message' => 'Affiliate code is required.'];
    }

    $affiliate_code = $data['affiliate_code'];
    
    try {
        //0. Get affiliate_id 
        $stmt_affiliate = $pdo->prepare("SELECT id FROM affiliates WHERE affiliate_code = ?");
        $stmt_affiliate->execute([$affiliate_code]);
        $affiliate_id = $stmt_affiliate->fetchColumn();

        if (!$affiliate_id) {
            return ['status' => 'error', 'message' => 'Invalid affiliate code provided.'];
        }
        
        
        // 1. Get referral count using the affiliate_id
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE affiliate_id = ?");
        $stmt_count->execute([$affiliate_id]);
        $referral_count = (int)$stmt_count->fetchColumn();

         // 2. Get commission list using the affiliate_id
        $stmt_commissions = $pdo->prepare("SELECT * FROM commissions WHERE affiliate_id = ? ORDER BY created_at DESC");
        $stmt_commissions->execute([$affiliate_id]);
        $commissions = $stmt_commissions->fetchAll(PDO::FETCH_ASSOC);

        // Format commissions for the response + محاسبه جمع‌ها بر اساس وضعیت
        $formatted_commissions = [];
        $approved_total = 0.0;   // تأیید شده + پرداخت شده
        $pending_total  = 0.0;   // در انتظار تأیید
        foreach ($commissions as $commission) {
            $formatted_commissions[] = [
                'date' => date('Y-m-d', strtotime($commission['created_at'])),
                'order_total' => $commission['order_total'],
                'commission_amount' => $commission['affiliate_commission_amount'],
                'status' => $commission['status'],
            ];

            $amount = (float)$commission['affiliate_commission_amount'];
            if ($commission['status'] === 'approved' || $commission['status'] === 'paid') {
                $approved_total += $amount;
            } elseif ($commission['status'] === 'pending') {
                $pending_total += $amount;
            }
        }

        // 3. کیف پول بازاریاب (موجودی قابل برداشت / در حال تسویه / پرداخت‌شده)
        $stmt_wallet = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'approved' AND payment_id IS NULL THEN affiliate_commission_amount ELSE 0 END), 0) AS available_balance,
                COALESCE(SUM(CASE WHEN status = 'approved' AND payment_id IS NOT NULL THEN affiliate_commission_amount ELSE 0 END), 0) AS in_settlement_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN affiliate_commission_amount ELSE 0 END), 0) AS paid_total
            FROM commissions
            WHERE affiliate_id = ?
        ");
        $stmt_wallet->execute([$affiliate_id]);
        $wallet = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
        $available_balance    = (float)($wallet['available_balance'] ?? 0);
        $in_settlement_amount = (float)($wallet['in_settlement_amount'] ?? 0);
        $paid_total           = (float)($wallet['paid_total'] ?? 0);

        return [
            'status' => 'success',
            'referral_count' => $referral_count,
            'commissions' => $formatted_commissions,
            'approved_commission_total' => round($approved_total, 2),
            'pending_commission_total' => round($pending_total, 2),
            'wallet' => [
                'available_balance'    => round($available_balance, 2),
                'in_settlement_amount' => round($in_settlement_amount, 2),
                'paid_total'           => round($paid_total, 2),
                'min_payout_amount'    => (float)MNAFF_MIN_PAYOUT_AMOUNT,
            ],
        ];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}