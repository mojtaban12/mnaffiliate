<?php
// templates/list_affiliates.php

// جلوگیری از دسترسی مستقیم — این فایل فقط از طریق روتر index.php (با check_auth) لود می‌شود
if (!defined('MNAFF_PANEL')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once __DIR__ . '/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card p-4 form-section">
            <h2 class="text-center mb-4">لیست بازاریابان</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>شناسه کاربر وردپرس</th>
                            <th>شماره همراه</th>
                            <th>کد بازاریابی</th>
                            <th>نرخ کمیسیون بازاریاب</th>
                            <th>نرخ کمیسیون ارجاع</th>
                            <th>وضعیت</th>
                            <th>موجودی قابل برداشت</th>
                            <th>درآمد کل</th>
                            <th>ارجاعات کل</th>
                            <th>لینک بازاریابی</th>

                            <th>تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($affiliates)): ?>
                        <tr>
                            <td colspan="10" class="text-center">هیچ بازاریابی یافت نشد.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($affiliates as $affiliate): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($affiliate['id']); ?></td>
                            <td><?php echo htmlspecialchars($affiliate['wp_user_id']); ?></td>
                            <td><?php echo htmlspecialchars($affiliate['user_mobile']); ?></td>
                            <td><?php echo htmlspecialchars($affiliate['affiliate_code']); ?></td>
                            <td><?php echo htmlspecialchars($affiliate['commission_rate']); ?>%</td>
                            <td><?php echo htmlspecialchars($affiliate['referred_user_commission_rate']); ?>%</td>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($affiliate['status']); ?></span></td>
                            <td><strong><?php echo number_format((float)$affiliate['available_balance']); ?></strong> تومان</td>
                            <td><?php echo htmlspecialchars($affiliate['total_earnings']); ?></td>
                            <td><?php echo htmlspecialchars($affiliate['referral_count']); ?></td>
                             <td>https://www.puonak.com/?referral=<?php echo htmlspecialchars($affiliate['affiliate_code']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y/m/d', strtotime($affiliate['created_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/layouts/footer.php';
?>