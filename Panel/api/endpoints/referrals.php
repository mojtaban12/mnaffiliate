<?php
// referrals.php

/**
 * Records a new referral in the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $data The data received from the webhook.
 * @return array The response data.
 */
function recordReferral(PDO $pdo, array $data): array {
    // Check for required data
    if (!isset($data['affiliate_code']) || !isset($data['referred_wp_user_id'])) {
        return ['status' => 'error', 'message' => 'Missing required data: affiliate_code or referred_wp_user_id.'];
    }

    $affiliate_code = $data['affiliate_code'];
    $referred_wp_user_id = (int)$data['referred_wp_user_id'];
    $referred_user_mobile = isset($data['referred_user_mobile']) ? $data['referred_user_mobile'] : '';
    
    try {
        // Find the affiliate_id based on the affiliate_code
        $stmt = $pdo->prepare("SELECT id FROM affiliates WHERE affiliate_code = ?");
        $stmt->execute([$affiliate_code]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$affiliate) {
            return ['status' => 'error', 'message' => 'Affiliate code not found.'];
        }

        // Insert the new referral
        $stmt = $pdo->prepare("INSERT INTO referrals (affiliate_id, referred_wp_user_id, user_mobile, referral_type) VALUES (?, ?, ?, 'signup')");
        $stmt->execute([$affiliate['id'], $referred_wp_user_id, $referred_user_mobile]);

        return [
            'status' => 'success',
            'message' => 'Referral recorded successfully.',
            'referral_id' => $pdo->lastInsertId()
        ];

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            return ['status' => 'error', 'message' => 'Referral already exists.'];
        }
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}