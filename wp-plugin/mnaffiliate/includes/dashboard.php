<?php
/**
 * includes/dashboard.php
 * تب «همکاری در فروش» در حساب کاربری ووکامرس (کیف پول + ریدیزاین)
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ثبت اندپوینت حساب کاربری */
add_filter('query_vars', function ($vars) {
    $vars[] = 'mnaffiliate';
    return $vars;
}, 0);

/* افزودن منو فقط برای بازاریابان */
add_filter('woocommerce_account_menu_items', function ($items) {
    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'mnaffiliate_code', true)) {
        $items['mnaffiliate'] = __('همکاری در فروش', 'mnaffiliate');
    }
    return $items;
});

/**
 * برچسب فارسی و کلاس CSS وضعیت کمیسیون
 */
function mnaff_commission_status($status) {
    switch ($status) {
        case 'pending':  return ['در انتظار تأیید', 'warn'];
        case 'approved': return ['تأیید شده', 'ok'];
        case 'paid':     return ['پرداخت شده', 'info'];
        default:         return ['ناموفق', 'err'];
    }
}

/**
 * برچسب فارسی وضعیت درخواست تسویه
 */
function mnaff_payout_status($status) {
    switch ($status) {
        case 'pending':   return ['در انتظار بررسی', 'warn'];
        case 'completed': return ['پرداخت شده', 'ok'];
        case 'cancelled': return ['رد شده', 'err'];
        default:          return ['ناموفق', 'err'];
    }
}

/* محتوای تب */
add_action('woocommerce_account_mnaffiliate_endpoint', 'mnaffiliate_content');
function mnaffiliate_content() {
    $user_id = get_current_user_id();
    $affiliate_code = get_user_meta($user_id, 'mnaffiliate_code', true);

    if (empty($affiliate_code)) {
        echo '<p>شما در حال حاضر به عنوان بازاریاب ثبت نشده‌اید.</p>';
        return;
    }

    $api_url = MN_AFFILIATE_WEBHOOK_URL;
    $api_key = MN_AFFILIATE_API_KEY;

    // --- آمار و کیف پول ---
    $stats_response = wp_remote_post($api_url, ['body' => [
        'action'         => 'get_referral_stats',
        'api_key'        => $api_key,
        'affiliate_code' => $affiliate_code,
    ], 'timeout' => 45]);
    $stats_result = json_decode(wp_remote_retrieve_body($stats_response), true);

    $referral_count    = $stats_result['referral_count'] ?? 0;
    $commission_list   = $stats_result['commissions'] ?? [];
    $wallet            = $stats_result['wallet'] ?? [];
    $available_balance = (float)($wallet['available_balance'] ?? 0);
    $in_settlement     = (float)($wallet['in_settlement_amount'] ?? 0);
    $paid_total        = (float)($wallet['paid_total'] ?? 0);
    $min_payout        = (float)($wallet['min_payout_amount'] ?? 0);

    // --- لیست درخواست‌های تسویه ---
    $payouts_response = wp_remote_post($api_url, ['body' => [
        'action'         => 'get_payouts',
        'api_key'        => $api_key,
        'affiliate_code' => $affiliate_code,
    ], 'timeout' => 45]);
    $payouts_result = json_decode(wp_remote_retrieve_body($payouts_response), true);
    $payouts_list = (isset($payouts_result['status']) && $payouts_result['status'] === 'success')
        ? ($payouts_result['payouts'] ?? []) : [];

    $referral_link = home_url('/?referral=' . $affiliate_code);
    ?>    <style>
    @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');
    .mnaff-dash { font-family: Vazirmatn, Tahoma, sans-serif; display: flex; flex-direction: column; gap: 22px; direction: rtl; }
    .mnaff-card { background: #fff; padding: 22px; border-radius: 14px; box-shadow: 0 4px 18px rgba(15,23,42,.07); }
    .mnaff-card-title { font-size: 1.05rem; font-weight: 800; margin: 0 0 16px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
    .mnaff-tabs { display: flex; flex-wrap: wrap; gap: 6px; background: #fff; padding: 8px; border-radius: 12px; box-shadow: 0 4px 18px rgba(15,23,42,.07); }
    .mnaff-tab-btn { border: 0; background: transparent; padding: 10px 18px; border-radius: 9px; font-family: inherit; font-size: .88rem; font-weight: 700; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all .2s; }
    .mnaff-tab-btn:hover { background: #eef2ff; color: #4f46e5; }
    .mnaff-tab-btn.active { background: #4f46e5; color: #fff; box-shadow: 0 6px 16px rgba(79,70,229,.35); }
    .mnaff-tab-panel { display: none; }
    .mnaff-tab-panel.active { display: block; }
    .mnaff-wallet { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; padding: 26px; border-radius: 16px; box-shadow: 0 10px 30px rgba(79,70,229,.35); }
    .mnaff-wallet-label { font-size: .85rem; opacity: .85; display: block; margin-bottom: 6px; }
    .mnaff-wallet-amount { font-size: 1.9rem; font-weight: 800; display: block; }
    .mnaff-wallet-sub { display: flex; flex-wrap: wrap; gap: 18px; margin: 18px 0; }
    .mnaff-wallet-sub > div { background: rgba(255,255,255,.14); border-radius: 10px; padding: 10px 16px; min-width: 130px; }
    .mnaff-wallet-sub span { display: block; font-size: .74rem; opacity: .8; }
    .mnaff-wallet-sub strong { font-size: 1rem; }
    .mnaff-btn-payout { background: #fff; color: #4f46e5; border: 0; border-radius: 10px; padding: 10px 22px; font-weight: 700; font-family: inherit; cursor: pointer; font-size: .9rem; }
    .mnaff-btn-payout:hover { background: #eef2ff; }
    #mnaff-payout-form { background: rgba(255,255,255,.12); border-radius: 12px; padding: 16px; margin-top: 14px; display: none; }
    #mnaff-payout-form .mnaff-field { margin-bottom: 12px; }
    #mnaff-payout-form label { display: block; font-size: .78rem; margin-bottom: 5px; opacity: .9; }
    #mnaff-payout-form input { width: 100%; max-width: 340px; border: 0; border-radius: 8px; padding: 9px 12px; font-family: inherit; direction: ltr; text-align: left; }
    .mnaff-hint { font-size: .74rem; opacity: .8; margin: 10px 0 0; }
    .mnaff-msg { border-radius: 10px; padding: 10px 14px; font-size: .85rem; margin-top: 12px; }
    .mnaff-msg.ok { background: #dcfce7; color: #15803d; }
    .mnaff-msg.err { background: #fee2e2; color: #b91c1c; }
    .mnaff-code-row { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
    .mnaff-code { font-size: 1.5rem; font-weight: 800; color: #4f46e5; background: #eef2ff; padding: 8px 20px; border-radius: 10px; letter-spacing: 2px; direction: ltr; display: inline-block; }
    .mnaff-link-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mnaff-link-row a { color: #0284c7; font-size: .88rem; word-break: break-all; direction: ltr; unicode-bidi: embed; }
    .mnaff-btn-copy { background: #1e293b; color: #fff; border: 0; border-radius: 8px; padding: 7px 16px; font-size: .8rem; font-family: inherit; cursor: pointer; }
    .mnaff-btn-copy:hover { background: #334155; }
    .mnaff-table { width: 100%; border-collapse: collapse; }
    .mnaff-table th, .mnaff-table td { padding: 11px 12px; text-align: right; border-bottom: 1px solid #e2e8f0; font-size: .85rem; }
    .mnaff-table thead th { background: #f8fafc; color: #64748b; font-size: .74rem; font-weight: 700; }
    .mnaff-table tbody tr:hover td { background: #f8fafc; }
    .mnaff-table .num { direction: ltr; unicode-bidi: embed; }
    .mnaff-badge { padding: 4px 12px; border-radius: 20px; font-size: .73rem; font-weight: 700; display: inline-block; }
    .mnaff-badge.ok { background: #dcfce7; color: #15803d; }
    .mnaff-badge.warn { background: #fef3c7; color: #b45309; }
    .mnaff-badge.err { background: #fee2e2; color: #b91c1c; }
    .mnaff-badge.info { background: #e0f2fe; color: #0369a1; }
    .mnaff-empty { color: #64748b; font-size: .87rem; background: #f8fafc; border-radius: 10px; padding: 18px; text-align: center; }
    .mnaff-receipt-link { font-size: .78rem; color: #0284c7; }
    .mnaff-search-input { width: 100%; max-width: 460px; border: 1px solid #e2e8f0; border-radius: 9px; padding: 10px 14px; font-family: inherit; font-size: .88rem; }
    .mnaff-search-results { list-style: none; margin: 6px 0 0; padding: 0; border: 1px solid #e2e8f0; border-radius: 10px; max-width: 460px; max-height: 240px; overflow-y: auto; background: #fff; display: none; }
    .mnaff-search-results li { cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .mnaff-search-results li:last-child { border-bottom: 0; }
    .mnaff-search-results li:hover { background: #eef2ff; }
    .mnaff-search-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; }
    .mnaff-search-item img { width: 38px; height: 38px; border-radius: 8px; object-fit: cover; }
    .mnaff-search-info { display: flex; flex-direction: column; gap: 2px; }
    .mnaff-search-name { font-size: .85rem; font-weight: 700; color: #1e293b; }
    .mnaff-search-price { font-size: .76rem; color: #64748b; }
    .mnaff-search-none { padding: 10px 14px; color: #64748b; font-size: .82rem; text-align: center; }
    .mnaff-selected { margin-top: 10px; font-size: .85rem; color: #1e293b; }
    .mnaff-link-actions { margin-top: 12px; }
    .mnaff-links-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; vertical-align: middle; margin-inline-end: 8px; }
    .mnaff-links-copy { color: #0284c7; font-size: .8rem; word-break: break-all; direction: ltr; unicode-bidi: embed; }
    @media (max-width: 768px) {
        .mnaff-wallet-sub { flex-direction: column; }
        .mnaff-table thead { display: none; }
        .mnaff-table td { display: block; border: 0; padding: 4px 8px; }
        .mnaff-table tbody tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 8px 0; }
        .mnaff-tabs { overflow-x: auto; flex-wrap: nowrap; }
    }
    </style>

    <div class="mnaff-dash">

        <!-- تب‌ها -->
        <div class="mnaff-tabs">
            <button type="button" class="mnaff-tab-btn active" data-tab="tab-wallet"><i class="bi bi-wallet2"></i> کیف پول</button>
            <button type="button" class="mnaff-tab-btn" data-tab="tab-link"><i class="bi bi-link-45deg"></i> لینک بازاریابی</button>
            <button type="button" class="mnaff-tab-btn" data-tab="tab-commissions"><i class="bi bi-cash-stack"></i> کمیسیون‌ها</button>
            <button type="button" class="mnaff-tab-btn" data-tab="tab-payouts"><i class="bi bi-clock-history"></i> تاریخچه تسویه</button>
            <button type="button" class="mnaff-tab-btn" data-tab="tab-links"><i class="bi bi-link-45deg"></i> لینک‌های فروش</button>
        </div>

        <!-- تب: کیف پول -->
        <div id="tab-wallet" class="mnaff-tab-panel active">
        <div class="mnaff-wallet">
            <div class="mnaff-wallet-main">
                <span class="mnaff-wallet-label">موجودی قابل برداشت شما</span>
                <span class="mnaff-wallet-amount"><?php echo wp_kses_post(wc_price($available_balance)); ?></span>
            </div>
            <div class="mnaff-wallet-sub">
                <div><span>در حال تسویه</span><strong><?php echo wp_kses_post(wc_price($in_settlement)); ?></strong></div>
                <div><span>پرداخت‌شده</span><strong><?php echo wp_kses_post(wc_price($paid_total)); ?></strong></div>
                <div><span>ارجاعات موفق</span><strong><?php echo esc_html(number_format_i18n($referral_count)); ?></strong></div>
            </div>
            <button type="button" class="mnaff-btn-payout" id="mnaff-toggle-form"><i class="bi bi-cash-coin"></i> درخواست تسویه</button>
            <form id="mnaff-payout-form">
                <div class="mnaff-field">
                    <label for="mnaff-iban">شماره شبا</label>
                    <input type="text" id="mnaff-iban" name="iban" placeholder="IR______________________" required>
                </div>
                <div class="mnaff-field">
                    <label for="mnaff-holder">نام صاحب حساب</label>
                    <input type="text" id="mnaff-holder" name="account_holder_name" required>
                </div>
                <input type="hidden" name="action" value="mnaff_request_payout">
                <button type="submit" class="mnaff-btn-payout">ثبت درخواست</button>
                <p class="mnaff-hint">حداقل مبلغ تسویه: <?php echo wp_kses_post(wc_price($min_payout)); ?> — پرداخت به‌صورت دستی و طی حداکثر ۴۸ ساعت کاری انجام می‌شود.</p>
            </form>
            <div id="mnaff-payout-msg"></div>
        </div>
        </div><!-- /tab-wallet -->

        <!-- تب: لینک بازاریابی -->
        <div id="tab-link" class="mnaff-tab-panel">
        <div class="mnaff-card">
            <h3 class="mnaff-card-title">لینک بازاریابی شما</h3>
            <div class="mnaff-code-row">
                <span class="mnaff-code"><?php echo esc_html($affiliate_code); ?></span>
                <div class="mnaff-link-row">
                    <a id="mnaff-ref-link" href="<?php echo esc_url($referral_link); ?>" target="_blank" rel="noopener"><?php echo esc_html($referral_link); ?></a>
                    <button type="button" class="mnaff-btn-copy" id="mnaff-copy-link">کپی لینک</button>
                </div>
            </div>
            <p style="color:#64748b;font-size:.78rem;margin:12px 0 0">این لینک را با دوستان خود به اشتراک بگذارید؛ با ثبت‌نام و خرید آن‌ها کمیسیون دریافت می‌کنید.</p>
        </div>
        </div><!-- /tab-link -->

        <!-- تب: کمیسیون‌ها -->
        <div id="tab-commissions" class="mnaff-tab-panel">
        <div class="mnaff-card">
            <h3 class="mnaff-card-title">لیست کمیسیون‌ها</h3>
            <?php if (!empty($commission_list)): ?>
            <div style="overflow-x:auto">
                <table class="mnaff-table">
                    <thead>
                        <tr><th>تاریخ</th><th>مبلغ سفارش</th><th>کمیسیون شما</th><th>وضعیت</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($commission_list as $commission):
                        [$st_label, $st_class] = mnaff_commission_status($commission['status']); ?>
                        <tr>
                            <td class="num"><?php echo esc_html(date('Y-m-d', strtotime($commission['date']))); ?></td>
                            <td><?php echo wp_kses_post(wc_price($commission['order_total'])); ?></td>
                            <td><strong><?php echo wp_kses_post(wc_price($commission['commission_amount'])); ?></strong></td>
                            <td><span class="mnaff-badge <?php echo esc_attr($st_class); ?>"><?php echo esc_html($st_label); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="mnaff-empty">در حال حاضر کمیسیونی برای نمایش وجود ندارد.</p>
            <?php endif; ?>
        </div>
        </div><!-- /tab-commissions -->

        <!-- تب: تاریخچه تسویه -->
        <div id="tab-payouts" class="mnaff-tab-panel">
        <div class="mnaff-card">
            <h3 class="mnaff-card-title">تاریخچه تسویه‌ها</h3>
            <?php if (!empty($payouts_list)): ?>
            <div style="overflow-x:auto">
                <table class="mnaff-table">
                    <thead>
                        <tr><th>تاریخ درخواست</th><th>مبلغ</th><th>وضعیت</th><th>شماره رهگیری</th><th>رسید</th><th>توضیحات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payouts_list as $payout):
                        [$st_label, $st_class] = mnaff_payout_status($payout['status']); ?>
                        <tr>
                            <td class="num"><?php echo esc_html($payout['date']); ?></td>
                            <td><strong><?php echo wp_kses_post(wc_price($payout['amount'])); ?></strong></td>
                            <td><span class="mnaff-badge <?php echo esc_attr($st_class); ?>"><?php echo esc_html($st_label); ?></span></td>
                            <td class="num"><?php echo esc_html($payout['tracking_number'] ?: '—'); ?></td>
                            <td>
                                <?php if (!empty($payout['receipt_image'])): ?>
                                    <a class="mnaff-receipt-link" href="<?php echo esc_url(MN_AFFILIATE_PANEL_URL . '/uploads/receipts/' . $payout['receipt_image']); ?>" target="_blank" rel="noopener">مشاهده رسید</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo esc_html($payout['reject_reason'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="mnaff-empty">هنوز درخواست تسویه‌ای ثبت نکرده‌اید.</p>
            <?php endif; ?>
        </div>
        </div><!-- /tab-payouts -->

        <!-- تب: لینک‌های فروش -->
        <div id="tab-links" class="mnaff-tab-panel">
        <div class="mnaff-card">
            <h3 class="mnaff-card-title">ساخت لینک فروش</h3>
            <p class="mnaff-hint">محصول را جستجو کنید، یک لینک یکتا بسازید و آن را در تلگرام / شبکه‌های اجتماعی به اشتراک بگذارید.</p>
            <div class="mnaff-field">
                <label for="mnaff-link-search">جستجوی محصول:</label>
                <input type="text" id="mnaff-link-search" class="mnaff-search-input" autocomplete="off" placeholder="نام محصول را بنویسید...">
                <ul id="mnaff-link-results" class="mnaff-search-results"></ul>
            </div>
            <div id="mnaff-selected-product" class="mnaff-selected"></div>
            <div class="mnaff-link-actions">
                <button type="button" class="mnaff-btn-payout" id="mnaff-create-link" disabled><i class="bi bi-link-45deg"></i> ساخت لینک</button>
            </div>
            <div id="mnaff-links-msg"></div>
        </div>
        <div class="mnaff-card">
            <h3 class="mnaff-card-title">لینک‌های شما <span id="mnaff-links-count"></span></h3>
            <div style="overflow-x:auto">
                <table class="mnaff-table">
                    <thead>
                        <tr><th>محصول</th><th>لینک</th><th>بازدید</th><th>بازدید یکتا</th><th>خرید</th><th>مبلغ فروش</th><th>نرخ تبدیل</th><th>تاریخ</th></tr>
                    </thead>
                    <tbody id="mnaff-links-body"></tbody>
                </table>
            </div>
            <p class="mnaff-empty" id="mnaff-links-empty">در حال بارگذاری...</p>
        </div>
        </div><!-- /tab-links -->

    </div>

    <script>
    jQuery(function ($) {
        // سوئیچ تب‌ها
        $(document).on('click', '.mnaff-tab-btn', function () {
            $('.mnaff-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.mnaff-tab-panel').removeClass('active');
            $('#' + $(this).data('tab')).addClass('active');
        });
        // کپی لینک بازاریابی
        $(document).on('click', '#mnaff-copy-link', function (e) {
            e.preventDefault();
            var link = document.getElementById('mnaff-ref-link').href;
            var $btn = $(this);
            var done = function () { $btn.text('کپی شد ✓'); setTimeout(function () { $btn.text('کپی لینک'); }, 1600); };
            if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done); }
            else { var $t = $('<input>').val(link).appendTo('body'); $t.select(); document.execCommand('copy'); $t.remove(); done(); }
        });
        // نمایش/مخفی کردن فرم تسویه
        $(document).on('click', '#mnaff-toggle-form', function () {
            $('#mnaff-payout-form').slideToggle();
        });
        // ارسال درخواست تسویه
        $('#mnaff-payout-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $msg = $('#mnaff-payout-msg');
            $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', $form.serialize(), function (res) {
                if (res && res.status === 'success') {
                    $msg.html('<div class="mnaff-msg ok">' + (res.message || 'درخواست تسویه ثبت شد.') + '</div>');
                    $form[0].reset();
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $msg.html('<div class="mnaff-msg err">' + ((res && res.message) || 'خطا در ثبت درخواست.') + '</div>');
                }
            }).fail(function () {
                $msg.html('<div class="mnaff-msg err">خطا در ارتباط با سرور.</div>');
            });
        });
    });

    // --- لینک‌های فروش ---
    jQuery(function ($) {
        var mnaffLinks = {
            ajax: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_attr(wp_create_nonce('mnaff_links_nonce')); ?>'
        };
        var linkSelected = null, linkSearchTimer = null;

        // جستجوی زنده با debounce
        $('#mnaff-link-search').on('keyup', function () {
            var q = $(this).val().trim();
            clearTimeout(linkSearchTimer);
            if (q.length < 2) { $('#mnaff-link-results').empty().hide(); return; }
            linkSearchTimer = setTimeout(function () { mnaffSearchProducts(q); }, 400);
        });

        function mnaffSearchProducts(q) {
            $.post(mnaffLinks.ajax, { action: 'mnaff_search_products', nonce: mnaffLinks.nonce, term: q }, function (res) {
                var $r = $('#mnaff-link-results').empty().hide();
                if (!res || !res.success) return;
                var prods = res.data.products || [];
                if (!prods.length) {
                    $r.append('<li class="mnaff-search-none">محصولی یافت نشد.</li>').show();
                    return;
                }
                prods.forEach(function (p) {
                    var $li = $('<li>').data('product', p);
                    var inner = $('<div class="mnaff-search-item">');
                    if (p.image) inner.append('<img src="' + p.image + '" alt="">');
                    var info = $('<div class="mnaff-search-info">');
                    info.append('<span class="mnaff-search-name">' + p.name + '</span>');
                    if (p.price) info.append('<span class="mnaff-search-price">' + p.price + '</span>');
                    inner.append(info);
                    $li.append(inner);
                    $r.append($li);
                });
                $r.show();
            });
        }

        // انتخاب محصول
        $(document).on('click', '#mnaff-link-results li', function () {
            var p = $(this).data('product');
            linkSelected = p;
            $('#mnaff-link-results').empty().hide();
        $('#mnaff-selected-product').html('<span>محصول انتخاب‌شده: <strong>' + p.name + '</strong></span>');
        $('#mnaff-create-link').prop('disabled', false);
    });

    // ساخت لینک
    $('#mnaff-create-link').on('click', function () {
        if (!linkSelected) return;
        var $b = $(this), $msg = $('#mnaff-links-msg');
        $b.prop('disabled', true);
        $.post(mnaffLinks.ajax, { action: 'mnaff_create_link', nonce: mnaffLinks.nonce, product_id: linkSelected.id }, function (res) {
            $b.prop('disabled', false);
            if (res && res.success) {
                $msg.attr('class', 'mnaff-msg ok').text('لینک ساخته شد ✓');
                linkSelected = null;
                $('#mnaff-selected-product').empty();
                $('#mnaff-link-search').val('');
                mnaffLoadLinks();
            } else {
                $msg.attr('class', 'mnaff-msg err').text((res && res.data && res.data.message) || 'خطا در ساخت لینک.');
            }
        }).fail(function () {
            $b.prop('disabled', false);
            $msg.attr('class', 'mnaff-msg err').text('خطا در ارتباط با سرور.');
        });
    });

    // بارگذاری لیست لینک‌ها
    function mnaffLoadLinks() {
        $.post(mnaffLinks.ajax, { action: 'mnaff_get_links', nonce: mnaffLinks.nonce }, function (res) {
            var $tb = $('#mnaff-links-body').empty();
            var $empty = $('#mnaff-links-empty');
            if (!res || !res.success) { $empty.show().text('خطا در دریافت لینک‌ها.'); return; }
            var links = res.data.links || [];
            if (!links.length) { $empty.show().text('هنوز لینکی نساخته‌اید.'); return; }
            $empty.hide();
            $('#mnaff-links-count').text('(' + links.length + ')');
            links.forEach(function (l) {
                var img = l.product_image ? '<img class="mnaff-links-thumb" src="' + l.product_image + '" alt="">' : '';
                var row = '<tr>' +
                    '<td>' + img + l.product_name + '</td>' +
                    '<td class="num"><a class="mnaff-links-copy" href="' + l.url + '" target="_blank" rel="noopener">' + l.url.replace(/^https?:\/\//, '') + '</a></td>' +
                    '<td class="num">' + l.clicks + '</td>' +
                    '<td class="num">' + l.unique_visitors + '</td>' +
                    '<td class="num">' + l.purchases + '</td>' +
                    '<td class="num">' + l.sales + '</td>' +
                    '<td class="num">' + l.conversion_rate + '%</td>' +
                    '<td class="num">' + l.created_at + '</td>' +
                    '</tr>';
                $tb.append(row);
            });
        });
    }

    // بارگذاری هنگام باز شدن تب
    $(document).on('click', '.mnaff-tab-btn[data-tab="tab-links"]', function () {
        mnaffLoadLinks();
    });

    // کپی لینک
    $(document).on('click', '.mnaff-links-copy', function (e) {
        e.preventDefault();
        var $a = $(this), $t = $('<input>').val(this.href).appendTo('body');
        $t.select();
        $t.remove();
        if (navigator.clipboard) { navigator.clipboard.writeText(this.href); }
    });
    });
    </script>
    <?php
}
