<?php
// api/endpoints/record_commission.php

function recordCommission(PDO $pdo, array $data): array {
    // Check for required data
    if (!isset($data['user_id']) || !isset($data['order_id']) || !isset($data['order_total']) || !isset($data['discount_amount'])) {
        return ['status' => 'error', 'message' => 'Missing required data.'];
    }

    $user_id = (int)$data['user_id'];
    $order_id = (int)$data['order_id'];
    $order_total_paid = (float)$data['order_total']; // This is the final amount paid
    $discount_amount = (float)$data['discount_amount']; // This is the discount amount

    // NEW: Calculate the real total for commission calculation
    $real_order_total = $order_total_paid + $discount_amount;
    
    // Prevent duplicate commissions for the same order
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM commissions WHERE wp_order_id = ?");
    $stmt->execute([$order_id]);
    if ($stmt->fetchColumn() > 0) {
        return ['status' => 'error', 'message' => 'Commission already recorded for this order.'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                a.id AS affiliate_id,
                a.commission_rate,
                a.referred_user_commission_rate,
                r.id AS referral_id
            FROM
                referrals AS r
            JOIN
                affiliates AS a ON r.affiliate_id = a.id
            WHERE
                r.referred_wp_user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $referral_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral_info) {
            return ['status' => 'error', 'message' => 'No affiliate found for this user.'];
        }

        // Calculate the commission amounts based on the real order total
        $affiliate_commission = $real_order_total * ($referral_info['commission_rate'] / 100);
        $referred_user_commission = $real_order_total * ($referral_info['referred_user_commission_rate'] / 100);

        $stmt = $pdo->prepare("
            INSERT INTO commissions (wp_order_id, affiliate_id, referral_id, order_total, affiliate_commission_amount, referred_user_commission_amount, commission_rate, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $referral_info['affiliate_id'],
            $referral_info['referral_id'],
            $real_order_total, // Use the real total
            $affiliate_commission,
            $referred_user_commission,
            ($referral_info['commission_rate'] + $referral_info['referred_user_commission_rate']),
            'pending'
        ]);

        return ['status' => 'success', 'message' => 'Commission recorded successfully.', 'commission_id' => $pdo->lastInsertId()];

    } catch (PDOException $e) {
        // خطای تکراری بودن (Unique Key) را جداگانه مدیریت کن
        if ($e->getCode() == 23000) {
            return ['status' => 'error', 'message' => 'Commission already recorded for this order.'];
        }
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}