<?php
// api/endpoints/referral_info.php

/**
 * Retrieves referral information for a given user.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $data The data received from the webhook.
 * @return array The response data.
 */
function getReferralInfo(PDO $pdo, array $data): array {
    // Check for the required parameter
    if (!isset($data['user_id']) || empty($data['user_id'])) {
        return ['status' => 'error', 'message' => 'Missing required data: user_id.'];
    }

    $user_id = (int)$data['user_id'];
    
    try {
        // Query to find referral info and affiliate commission rate
        $stmt = $pdo->prepare("
            SELECT
                a.affiliate_code,
                a.referred_user_commission_rate
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

        if ($referral_info) {
            return ['status' => 'success', 'data' => $referral_info];
        } else {
            return ['status' => 'error', 'message' => 'No referral found for this user.'];
        }
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}