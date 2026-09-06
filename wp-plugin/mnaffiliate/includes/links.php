<?php
/**
 * includes/links.php
 * ------------------------------------------------------------------
 * «لینک‌های فروش»: ساخت لینک کوتاه یکتا برای محصول، ریدایرکت ۳۰۲،
 * ثبت بازدید/بازدیدکننده یکتا، اتصال سفارش به لینک و آمار.
 *
 * جدول‌ها هنگام فعال‌سازی افزونه با mnaff_install_link_tables() ساخته می‌شوند.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MNAFF_MAX_LINKS_PER_AFFILIATE', 50);
// پیشوند ثابت لینک‌های فروش — فقط مسیرهایی که با این شروع شوند به دیتابیس می‌روند
define('MNAFF_LINK_PREFIX', 'mna');

/* ------------------------------------------------------------------ *
 *  تبدیل تاریخ به شمسی (برای نمایش در جدول لینک‌ها)
 * ------------------------------------------------------------------ */
function mnaff_greg_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100)
          + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function mnaff_persian_digits($str) {
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace($en, $fa, $str);
}

function mnaff_to_jalali($date) {
    if (empty($date)) {
        return $date;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    [$jy, $jm, $jd] = mnaff_greg_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $months = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
               'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    return mnaff_persian_digits($jd . ' ' . $months[$jm] . ' ' . $jy);
}

/* ------------------------------------------------------------------ *
 *  جدول‌ها
 * ------------------------------------------------------------------ */
function mnaff_install_link_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mnaff_links (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(16) NOT NULL,
        affiliate_code VARCHAR(50) NOT NULL,
        wp_user_id BIGINT(20) UNSIGNED NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY affiliate_code (affiliate_code),
        KEY product_id (product_id)
    ) $charset;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mnaff_link_views (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        link_id BIGINT(20) UNSIGNED NOT NULL,
        visitor_hash CHAR(32) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY link_id (link_id),
        KEY visitor_hash (visitor_hash)
    ) $charset;");
}

/* ------------------------------------------------------------------ *
 *  اسلاگ یکتا
 * ------------------------------------------------------------------ */
function mnaff_link_slug_charset() {
    // بدون کاراکترهای مبهم: بدون 0/O ، 1/l/I
    return 'abcdefghjkmnpqrstuvwxyz23456789';
}

function mnaff_random_slug($length = 10) {
    $c   = mnaff_link_slug_charset();
    $max = strlen($c) - 1;
    $s   = MNAFF_LINK_PREFIX;
    for ($i = 0; $i < $length; $i++) {
        $s .= $c [ random_int(0, $max) ];
    }
    return $s;
}

function mnaff_reserved_slugs() {
    return [
        'shop', 'cart', 'checkout', 'product', 'my-account', 'account', 'orders',
        'wp-admin', 'wp-json', 'mnaffiliate', 'feed', 'page', 'author', 'category',
        'tag', 'search', 'login', 'register', 'logout', 'dashboard', 'contact',
        'about', 'faq', 'blog', 'admin', 'wp', 'wp-login', 'sitemap', 'xmlrpc',
        'privacy', 'terms', 'ref', 'aff', 'go', 'out', 'lp', 'landing', 'trk', 'track',
    ];
}

function mnaff_slug_exists($slug) {
    global $wpdb;

    $in_links = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}mnaff_links WHERE slug = %s", $slug
    ));
    if ($in_links > 0) {
        return true;
    }

    // تداخل با صفحات/محصولات/برگه‌های وردپرس (با هر وضعیتی)
    $in_posts = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_status != 'auto-draft'", $slug
    ));
    return $in_posts > 0;
}

function mnaff_generate_unique_slug() {
    $reserved = array_flip(mnaff_reserved_slugs());
    for ($i = 0; $i < 40; $i++) {
        $slug = mnaff_random_slug(12);
        if (isset($reserved[$slug])) {
            continue;
        }
        if (!mnaff_slug_exists($slug)) {
            return $slug;
        }
    }
    return false;
}

function mnaff_get_link_by_slug($slug) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mnaff_links WHERE LOWER(slug) = LOWER(%s) LIMIT 1", $slug
    ));
}

/* ------------------------------------------------------------------ *
 *  کمک‌ابزارهای احراز هویت بازاریاب
 * ------------------------------------------------------------------ */
function mnaff_current_user_is_affiliate() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }
    return !empty(get_user_meta($user_id, 'mnaffiliate_code', true));
}

function mnaff_link_count_for_user($user_id) {
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}mnaff_links WHERE wp_user_id = %d", $user_id
    ));
}

function mnaff_link_for_product_exists($user_id, $product_id) {
    global $wpdb;
    return (bool)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}mnaff_links WHERE wp_user_id = %d AND product_id = %d",
        $user_id, $product_id
    ));
}

/* ------------------------------------------------------------------ *
 *  ریدایرکت لینک کوتاه + ثبت بازدید + کوکی‌ها
 * ------------------------------------------------------------------ */
add_action('template_redirect', 'mnaff_handle_short_link');
function mnaff_handle_short_link() {
    if (is_admin() || is_feed() || is_robots()) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        return;
    }
    if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
        return; // لینک باید تمیز باشد (بدون query)
    }

    $path = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    }
    if ($path === '' || strpos($path, '/') !== false) {
        return;
    }
    // بهینه‌سازی: فقط مسیرهای با پیشوند mna به دیتابیس بروند، بقیه توسط وردپرس هندل شوند
    if (strtolower(substr($path, 0, strlen(MNAFF_LINK_PREFIX))) !== MNAFF_LINK_PREFIX) {
        return;
    }
    if (!preg_match('/^[a-z0-9]{10,16}$/i', $path)) {
        return;
    }
    // مسیرهای رزرو شده (مثل mnaffiliate) را به وردپرس بسپار
    if (in_array(strtolower($path), mnaff_reserved_slugs(), true)) {
        return;
    }

    $link = mnaff_get_link_by_slug($path);
    if (!$link) {
        return; // اجازه بده وردپرس 404 / ترکیب صفحه بدهد
    }

    $product = wc_get_product((int)$link->product_id);
    if (!$product) {
        return;
    }

    $product_url = get_permalink((int)$link->product_id);

    // کراولر شبکه‌های اجتماعی → صفحه‌ی OG (بدون شمردن بازدید و بدون کوکی)
    if (mnaff_is_social_bot()) {
        mnaff_render_og_page($product, $link->slug, $product_url);
        exit;
    }

    // بازدید انسانی: ثبت + کوکی‌ها + ریدایرکت ۳۰۲
    mnaff_record_link_view($link);
    mnaff_set_referral_cookie($link->affiliate_code);
    mnaff_set_link_cookie((int)$link->id);

    wp_redirect($product_url, 302);
    exit;
}

function mnaff_set_referral_cookie($affiliate_code) {
    setcookie('mnaffiliate_code', $affiliate_code, time() + (60 * 60 * 24 * 180), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
}

function mnaff_set_link_cookie($link_id) {
    setcookie('mnaff_link_id', (string)$link_id, time() + (60 * 60 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
}

function mnaff_record_link_view($link) {
    global $wpdb;

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $hash = md5($ip . '|' . $ua);

    // dedup: در ۲۴ ساعت اخیر این بازدیدکننده برای این لینک ثبت نشده باشد
    $recent = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}mnaff_link_views
         WHERE link_id = %d AND visitor_hash = %s AND created_at > NOW() - INTERVAL 1 DAY",
        $link->id, $hash
    ));
    if ($recent > 0) {
        return;
    }

    $wpdb->insert($wpdb->prefix . 'mnaff_link_views', [
        'link_id'       => $link->id,
        'visitor_hash'  => $hash,
        'created_at'    => current_time('mysql'),
    ]);
}

function mnaff_is_social_bot() {
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bots = [
        'telegrambot', 'whatsapp', 'facebookexternalhit', 'twitterbot', 'discordbot',
        'linkedinbot', 'pinterest', 'slackbot', 'viber', 'linebot', 'skypeuripreview',
        'redditbot', 'googlebot', 'bingbot', 'yandex', 'duckduckbot',
    ];
    $ua = strtolower($ua);
    foreach ($bots as $b) {
        if (strpos($ua, $b) !== false) {
            return true;
        }
    }
    return false;
}

function mnaff_render_og_page($product, $slug, $product_url) {
    $name  = $product->get_name();
    $desc  = wp_trim_words($product->get_short_description() ?: $product->get_description(), 20, '…');
    $img   = wp_get_attachment_image_url($product->get_image_id(), 'large');
    $short = home_url('/' . $slug . '/');

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="robots" content="noindex">';
    echo '<meta property="og:type" content="product">';
    echo '<meta property="og:title" content="' . esc_attr($name) . '">';
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">';
    echo '<meta property="og:url" content="' . esc_url($short) . '">';
    if ($img) {
        echo '<meta property="og:image" content="' . esc_url($img) . '">';
    }
    echo '<meta http-equiv="refresh" content="0;url=' . esc_url($product_url) . '">';
    echo '<title>' . esc_html($name) . '</title>';
    echo '</head><body></body></html>';
}

/* ------------------------------------------------------------------ *
 *  اتصال سفارش به لینک (هنگام ساخت سفارش در تسویه‌حساب)
 * ------------------------------------------------------------------ */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    if (empty($_COOKIE['mnaff_link_id'])) {
        return;
    }
    $link_id = (int)$_COOKIE['mnaff_link_id'];
    if ($link_id <= 0) {
        return;
    }
    $order->update_meta_data('mnaff_link_id', (string)$link_id);
}, 10, 2);

/* ------------------------------------------------------------------ *
 *  AJAX: جستجوی محصولات
 * ------------------------------------------------------------------ */
add_action('wp_ajax_mnaff_search_products', 'mnaff_ajax_search_products');
function mnaff_ajax_search_products() {
    check_ajax_referer('mnaff_links_nonce', 'nonce');
    if (!mnaff_current_user_is_affiliate()) {
        wp_send_json_error(['message' => 'شما بازاریاب نیستید.'], 403);
    }

    $term = sanitize_text_field(wp_unslash($_REQUEST['term'] ?? ''));
    if ($term === '') {
        wp_send_json_error(['message' => 'عبارت جستجو را وارد کنید.'], 400);
    }

    $products = wc_get_products([
        'status' => 'publish',
        'limit'  => 10,
        's'      => $term,
        'return' => 'objects',
    ]);

    $out = [];
    foreach ($products as $p) {
        if (!$p || !$p->is_visible()) {
            continue;
        }
        $out[] = [
            'id'    => $p->get_id(),
            'name'  => $p->get_name(),
            'price' => wp_strip_all_tags($p->get_price_html()),
            'image' => $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'thumbnail') : '',
            'url'   => get_permalink($p->get_id()),
        ];
    }

    wp_send_json_success(['products' => $out]);
}

/* ------------------------------------------------------------------ *
 *  AJAX: ساخت لینک
 * ------------------------------------------------------------------ */
add_action('wp_ajax_mnaff_create_link', 'mnaff_ajax_create_link');
function mnaff_ajax_create_link() {
    check_ajax_referer('mnaff_links_nonce', 'nonce');
    if (!mnaff_current_user_is_affiliate()) {
        wp_send_json_error(['message' => 'شما بازاریاب نیستید.'], 403);
    }

    $user_id    = get_current_user_id();
    $code       = get_user_meta($user_id, 'mnaffiliate_code', true);
    $product_id = absint($_REQUEST['product_id'] ?? 0);

    if (mnaff_link_count_for_user($user_id) >= MNAFF_MAX_LINKS_PER_AFFILIATE) {
        wp_send_json_error(['message' => 'سقف ' . MNAFF_MAX_LINKS_PER_AFFILIATE . ' لینک برای هر بازاریاب است.'], 400);
    }

    if (mnaff_link_for_product_exists($user_id, $product_id)) {
        wp_send_json_error(['message' => 'برای این محصول قبلاً لینک ساخته‌اید.'], 400);
    }

    $product = wc_get_product($product_id);
    if (!$product || $product->get_status() !== 'publish') {
        wp_send_json_error(['message' => 'محصول نامعتبر است.'], 400);
    }

    $slug = mnaff_generate_unique_slug();
    if (!$slug) {
        wp_send_json_error(['message' => 'ساخت اسلاگ یکتا ممکن نشد.'], 500);
    }

    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'mnaff_links', [
        'slug'           => $slug,
        'affiliate_code' => $code,
        'wp_user_id'     => $user_id,
        'product_id'     => $product_id,
        'created_at'     => current_time('mysql'),
    ]);
    $id = (int)$wpdb->insert_id;
    if (!$id) {
        wp_send_json_error(['message' => 'خطا در ذخیره لینک.'], 500);
    }

    wp_send_json_success([
        'id'      => $id,
        'slug'    => $slug,
        'url'     => home_url('/' . $slug . '/'),
        'product' => ['id' => $product_id, 'name' => $product->get_name()],
    ]);
}

/* ------------------------------------------------------------------ *
 *  AJAX: لیست لینک + آمار
 * ------------------------------------------------------------------ */
add_action('wp_ajax_mnaff_get_links', 'mnaff_ajax_get_links');
function mnaff_ajax_get_links() {
    check_ajax_referer('mnaff_links_nonce', 'nonce');
    if (!mnaff_current_user_is_affiliate()) {
        wp_send_json_error(['message' => 'شما بازاریاب نیستید.'], 403);
    }

    $links = mnaff_get_links_with_stats(get_current_user_id());
    wp_send_json_success(['links' => $links]);
}

function mnaff_get_links_with_stats($user_id) {
    global $wpdb;
    $links_t = $wpdb->prefix . 'mnaff_links';
    $views_t = $wpdb->prefix . 'mnaff_link_views';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*,
            (SELECT COUNT(*) FROM {$views_t} v WHERE v.link_id = l.id)                        AS clicks,
            (SELECT COUNT(DISTINCT v.visitor_hash) FROM {$views_t} v WHERE v.link_id = l.id)  AS unique_visitors
         FROM {$links_t} l
         WHERE l.wp_user_id = %d
         ORDER BY l.id DESC",
        $user_id
    ));

    $out = [];
    foreach ($rows as $r) {
        $product = wc_get_product((int)$r->product_id);
        $orders  = mnaff_orders_for_link((int)$r->id);

        $clicks = (int)$r->clicks;
        $unique = (int)$r->unique_visitors;
        $buy    = $orders['count'];

        $out[] = [
            'id'               => (int)$r->id,
            'slug'             => $r->slug,
            'url'              => home_url('/' . $r->slug . '/'),
            'product_id'       => (int)$r->product_id,
            'product_name'     => $product ? $product->get_name() : '(محصول حذف‌شده)',
            'product_image'    => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
            'clicks'           => $clicks,
            'unique_visitors'  => $unique,
            'purchases'        => $buy,
            'sales'            => $orders['total'],
            'conversion_rate'  => $unique > 0 ? round($buy / $unique * 100, 2) : 0,
            'created_at'       => mnaff_to_jalali($r->created_at),
        ];
    }
    return $out;
}

function mnaff_orders_for_link($link_id) {
    $order_ids = wc_get_orders([
        'limit'      => -1,
        'type'       => 'shop_order',
        'meta_key'   => 'mnaff_link_id',
        'meta_value' => (string)$link_id,
        'return'     => 'ids',
    ]);
    if (empty($order_ids)) {
        return ['count' => 0, 'total' => 0];
    }

    $allowed = ['processing', 'completed', 'receipt-card'];
    $count   = 0;
    $total   = 0.0;
    foreach ($order_ids as $oid) {
        $order = wc_get_order($oid);
        if (!$order) {
            continue;
        }
        if (!in_array($order->get_status(), $allowed, true)) {
            continue;
        }
        $count++;
        $total += (float)$order->get_total();
    }
    return ['count' => $count, 'total' => round($total, 2)];
}
