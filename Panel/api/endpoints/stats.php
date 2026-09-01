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

        return [
            'status' => 'success',
            'referral_count' => $referral_count,
            'commissions' => $formatted_commissions,
            'approved_commission_total' => round($approved_total, 2),
            'pending_commission_total' => round($pending_total, 2),
        ];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}