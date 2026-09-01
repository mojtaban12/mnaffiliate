<?php
// api/endpoints/approve_commission.php

/**
 * Updates the status of a commission to 'approved'.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $data The data received from the webhook.
 * @return array The response data.
 */
function approveCommission(PDO $pdo, array $data): array {
    // Check for required data
    if (!isset($data['order_id'])) {
        return ['status' => 'error', 'message' => 'Missing required data: order_id.'];
    }

    $order_id = (int)$data['order_id'];
    
    try {
        // Find the commission to ensure it exists and is 'pending'
        $stmt = $pdo->prepare("SELECT id FROM commissions WHERE wp_order_id = ? AND status = 'pending'");
        $stmt->execute([$order_id]);
        $commission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$commission) {
            return ['status' => 'error', 'message' => 'Pending commission not found for this order.'];
        }

        // Update the commission status to 'approved'
        $stmt = $pdo->prepare("UPDATE commissions SET status = 'approved' WHERE id = ?");
        $stmt->execute([$commission['id']]);

        return ['status' => 'success', 'message' => 'Commission approved successfully.'];

    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}