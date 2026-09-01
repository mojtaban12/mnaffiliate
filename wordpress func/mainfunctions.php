<?php 

function mnaffiliate_query_vars($vars) {
    $vars[] = 'mnaffiliate';
    return $vars;
}
add_filter('query_vars', 'mnaffiliate_query_vars', 0);

function mnaffiliate_add_to_account_menu($items) {
    // Check if the current user is an affiliate before adding the menu item
    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'mnaffiliate_code', true)) {
        // Insert the new item before the 'customer-logout' item
        $items['mnaffiliate'] = __('همکاری در فروش', 'mnaffiliate');
    }
    return $items;
}
add_filter('woocommerce_account_menu_items', 'mnaffiliate_add_to_account_menu');


function mnaffiliate_content() {
    $user_id = get_current_user_id();
    $affiliate_code = get_user_meta($user_id, 'mnaffiliate_code', true);

    if (empty($affiliate_code)) {
        echo '<p>شما در حال حاضر به عنوان بازاریاب ثبت نشده‌اید.</p>';
        return;
    }

    // Your microservice API endpoint and key
    $api_url = MN_AFFILIATE_WEBHOOK_URL;
    $api_key = MN_AFFILIATE_API_KEY;
    // Get the referral count from your microservice
    $referral_data = [
        'action' => 'get_referral_stats',
        'api_key' => $api_key,
        'affiliate_code' => $affiliate_code,
    ];
    $referral_response = wp_remote_post($api_url, ['body' => $referral_data]);
    $referral_result = json_decode(wp_remote_retrieve_body($referral_response), true);
    $referral_count = $referral_result['referral_count'] ?? 0;
    $commission_list = $referral_result['commissions'] ?? [];
    
    // جمع کمیسیون‌های تأییدشده و در انتظار (فقط approved/paid به‌عنوان درآمد واقعی حساب می‌شود)
    $approved_commission = (float)($referral_result['approved_commission_total'] ?? 0);
    $pending_commission = (float)($referral_result['pending_commission_total'] ?? 0);
    
    ?>
        <style>
        .mnaffiliate-dashboard {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .mnaffiliate-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .mnaffiliate-code-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .mnaffiliate-code {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa; /* WordPress primary color */
            padding: 10px 20px;
            background-color: #f7f7f7;
            border-radius: 5px;
            letter-spacing: 2px;
            display: inline-block;
        }
        
        .mnaffiliate-code-container .description {
            font-size: 0.9em;
            color: #888;
            margin-top: 5px;
        }

        .stats-section {
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
        }

        .stat-box {
            text-align: center;
            flex-basis: 45%;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            margin: 10px 0;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 1em;
            color: #555;
        }
        .mn-rtl{
            direction: ltr;
        }
        @media (max-width: 768px) {
            .stats-section {
                flex-direction: column;
            }
            .stat-box {
                flex-basis: 100%;
            }
        }
    </style>
    
    
    <?php
    
    
    echo '<div class="mnaffiliate-dashboard">';

    // Affiliate Link Section
    echo '<div class="mnaffiliate-section affiliate-link-section">';
    echo '<h3 class="section-title">لینک بازاریابی شما</h3>';
    $referral_link = home_url('/?referral=' . $affiliate_code);
    echo '<div class="mnaffiliate-code-container">';
    echo '<span class="mnaffiliate-code">' . esc_html($affiliate_code) . '</span>';
    echo '<p class="description">کد بازاریابی شما</p>';
    echo '</div>';
    echo '<p class="copy-link-text">لینک اختصاصی شما برای دعوت از دوستان: <strong><a href="' . esc_url($referral_link) . '">' . esc_url($referral_link) . '</a></strong></p>';
    // echo '<p class="copy-link-text">در صورتی که قصد دارید محصولی خاص را با لینک بازاریابی خود، تبلیغ کنید از روش زیر استفاده نمایید</p>';
    // echo '<p>به طول مثال لینک اصلی محصول : puonak.com/product/جعبه-کوئیزنر-طلقی-مدل-کارا </p>';
    // echo '<p>لینک بازاریابی شما برای این محصول : <span class="mn-rtl">puonak.com/product/جعبه-کوئیزنر-طلقی-مدل-کارا/'. esc_url($affiliate_code).'</span></p>';

    
    echo '</div>';

     // Stats Section (Referrals and Total Commission)
    echo '<div class="mnaffiliate-section stats-section">';
    echo '<div class="stat-box referrals-box">';
    echo '<span class="stat-number">' . number_format($referral_count) . '</span>';
    echo '<span class="stat-label">تعداد ارجاعات موفق</span>';
    echo '</div>';
    
    echo '<div class="stat-box approved-commission-box">';
    echo '<span class="stat-number">' . wc_price($approved_commission) . '</span>';
    echo '<span class="stat-label">کمیسیون تأییدشده</span>';
    echo '</div>';

    echo '<div class="stat-box pending-commission-box">';
    echo '<span class="stat-number">' . wc_price($pending_commission) . '</span>';
    echo '<span class="stat-label">در انتظار تأیید</span>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="mnaffiliate-section commissions-list-section">';
    echo '<h3 class="section-title">لیست کمیسیون‌ها</h3>';
    if (!empty($commission_list)) {
        echo '<table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">';
        echo '<thead><tr><th>تاریخ</th><th>مبلغ سفارش</th><th>کمیسیون شما</th><th>وضعیت</th></tr></thead>';
        echo '<tbody>';
        foreach ($commission_list as $commission) {
            echo '<tr>';
            echo '<td>' . esc_html(date('Y-m-d', strtotime($commission['date']))) . '</td>';
            echo '<td>' . wc_price($commission['order_total']) . '</td>';
            echo '<td>' . wc_price($commission['commission_amount']) . '</td>';
            echo '<td>' . esc_html($commission['status']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="mnaffiliate-notice info">در حال حاضر کمیسیونی برای نمایش وجود ندارد.</p>';
    }
    echo '</div>';

    echo '</div>'; // End of mnaffiliate-dashboard
}
add_action('woocommerce_account_mnaffiliate_endpoint', 'mnaffiliate_content');


include "mnaffiliateFunctions.php";
