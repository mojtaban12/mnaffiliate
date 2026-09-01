<?php
// templates/list_commissions.php

// جلوگیری از دسترسی مستقیم — این فایل فقط از طریق روتر index.php (با check_auth) لود می‌شود
if (!defined('MNAFF_PANEL')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Include the database connection file
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Corrected Query: Selects user_mobile from the referrals table
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            a.user_mobile AS affiliate_name,
            r.user_mobile AS referred_user_mobile
        FROM
            commissions AS c
        JOIN
            affiliates AS a ON c.affiliate_id = a.id
        JOIN
            referrals AS r ON c.referral_id = r.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<p>خطا در دریافت اطلاعات کمیسیون‌ها: ' . $e->getMessage() . '</p>';
    return;
}


require_once __DIR__ . '/layouts/header.php';
?>

<div class="commission-list-container">
    <h2>لیست کمیسیون‌ها (پنل ادمین)</h2>
    <table class="commission-table">
        <thead>
            <tr>
                <th>شماره سفارش</th>
                <th>نام بازاریاب (موبایل)</th>
                <th>شماره موبایل کاربر ارجاعی</th>
                <th>مبلغ سفارش</th>
                <th>کمیسیون بازاریاب</th>
                <th>کمیسیون کاربر ارجاعی</th>
                <th>وضعیت</th>
                <th>تاریخ ثبت</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($commissions)): ?>
                <tr>
                    <td colspan="8">هیچ کمیسیونی برای نمایش وجود ندارد.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($commissions as $commission): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($commission['wp_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($commission['affiliate_name']); ?></td>
                        <td><?php echo htmlspecialchars($commission['referred_user_mobile']); ?></td>
                        <td><?php echo number_format($commission['order_total']); ?> تومان</td>
                        <td><?php echo number_format($commission['affiliate_commission_amount']); ?> تومان</td>
                        <td><?php echo number_format($commission['referred_user_commission_amount']); ?> تومان</td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch ($commission['status']) {
                                case 'pending':
                                    $status_class = 'status-pending';
                                    $status_text = 'در انتظار';
                                    break;
                                case 'approved':
                                    $status_class = 'status-approved';
                                    $status_text = 'تأیید شده';
                                    break;
                                case 'paid':
                                    $status_class = 'status-paid';
                                    $status_text = 'پرداخت شده';
                                    break;
                                default:
                                    $status_class = 'status-unknown';
                                    $status_text = 'نامشخص';
                                    break;
                            }
                            ?>
                            <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td><?php echo $commission['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>