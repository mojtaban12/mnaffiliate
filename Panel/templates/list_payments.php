<?php
// templates/list_payments.php

// جلوگیری از دسترسی مستقیم — این فایل فقط از طریق روتر index.php (با check_auth) لود می‌شود
if (!defined('MNAFF_PANEL')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once __DIR__ . '/layouts/header.php';

$pending = array_filter($payments, function ($p) { return $p['status'] === 'pending'; });
$pending_sum = 0;
foreach ($pending as $p) { $pending_sum += (float)$p['total_amount']; }
?>

<div class="page-head"><h2>درخواست‌های تسویه</h2></div>

<?php echo $pay_message; ?>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-card-label">در انتظار بررسی</span>
        <span class="stat-card-value"><?php echo count($pending); ?> درخواست</span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">مبلغ در انتظار پرداخت</span>
        <span class="stat-card-value"><?php echo number_format($pending_sum); ?> تومان</span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">کل درخواست‌ها</span>
        <span class="stat-card-value"><?php echo count($payments); ?></span>
    </div>
</div>

<div class="card-mn">
    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>#</th>
                    <th>بازاریاب</th>
                    <th>کد</th>
                    <th>مبلغ (تومان)</th>
                    <th>شبا</th>
                    <th>صاحب حساب</th>
                    <th>وضعیت</th>
                    <th>رهگیری / رسید</th>
                    <th>توضیحات</th>
                    <th>تاریخ درخواست</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="11" class="text-center py-4 text-muted">هیچ درخواست تسویه‌ای ثبت نشده است.</td></tr>
                <?php else: foreach ($payments as $p): ?>
                    <?php
                    switch ($p['status']) {
                        case 'pending':   $st_label = 'در انتظار بررسی'; $st_class = 'warn'; break;
                        case 'completed': $st_label = 'پرداخت شده';     $st_class = 'ok';   break;
                        case 'cancelled': $st_label = 'رد شده';         $st_class = 'err';  break;
                        default:          $st_label = 'ناموفق';         $st_class = 'err';
                    }
                    ?>
                    <tr>
                        <td><?php echo (int)$p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['user_mobile']); ?></td>
                        <td><code><?php echo htmlspecialchars($p['affiliate_code']); ?></code></td>
                        <td><strong><?php echo number_format((float)$p['total_amount']); ?></strong></td>
                        <td class="ltr-num"><?php echo htmlspecialchars($p['iban'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['account_holder_name'] ?? '—'); ?></td>
                        <td><span class="badge-soft <?php echo $st_class; ?>"><?php echo $st_label; ?></span></td>
                        <td>
                            <?php if ($p['status'] === 'completed'): ?>
                                <div class="ltr-num"><?php echo htmlspecialchars($p['tracking_number'] ?? '—'); ?></div>
                                <?php if (!empty($p['receipt_image'])): ?>
                                    <a class="btn-link-sm" href="/mnaffiliate/uploads/receipts/<?php echo htmlspecialchars($p['receipt_image']); ?>" target="_blank">مشاهده رسید</a>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['reject_reason'] ?: ($p['admin_notes'] ?? '')) ?: '—'; ?></td>
                        <td><?php echo htmlspecialchars(date('Y/m/d', strtotime($p['requested_at']))); ?></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <button class="btn-mn ok" data-bs-toggle="collapse" data-bs-target="#approve-<?php echo (int)$p['id']; ?>"><i class="bi bi-check2-circle"></i> تأیید</button>
                                <button class="btn-mn danger" data-bs-toggle="collapse" data-bs-target="#reject-<?php echo (int)$p['id']; ?>"><i class="bi bi-x-circle"></i> رد</button>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($p['status'] === 'pending'): ?>
                    <tr class="collapse-row">
                        <td colspan="11">
                            <div class="collapse mb-2" id="approve-<?php echo (int)$p['id']; ?>">
                                <form method="POST" enctype="multipart/form-data" class="pay-form">
                                    <input type="hidden" name="pay_action" value="approve">
                                    <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                    <label>شماره رهگیری: <input type="text" name="tracking_number" required class="form-control"></label>
                                    <label>تصویر رسید (اختیاری): <input type="file" name="receipt_image" accept=".jpg,.jpeg,.png,.pdf" class="form-control"></label>
                                    <button type="submit" class="btn-mn ok"><i class="bi bi-check-lg"></i> ثبت و تکمیل پرداخت</button>
                                </form>
                            </div>
                            <div class="collapse" id="reject-<?php echo (int)$p['id']; ?>">
                                <form method="POST" class="pay-form">
                                    <input type="hidden" name="pay_action" value="reject">
                                    <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                    <label>دلیل رد: <input type="text" name="reject_reason" required class="form-control"></label>
                                    <button type="submit" class="btn-mn danger"><i class="bi bi-x-lg"></i> رد درخواست</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>