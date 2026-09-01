<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت بازاریابی</title>
    <link rel="stylesheet" href="https://puonak.com/mnaffiliate/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://puonak.com/mnaffiliate/assets/css/style.css">
    <script src="https://puonak.com/mnaffiliate/assets/js/jquery-3.7.1.min.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <i class="bi bi-graph-up-arrow"></i>
            <span>پنل بازاریابی پونک</span>
        </div>
        <nav>
            <a href="/mnaffiliate/dashboard"><i class="bi bi-person-plus-fill"></i> <span>تعریف بازاریاب</span></a>
            <a href="/mnaffiliate/list"><i class="bi bi-people-fill"></i> <span>لیست بازاریابان</span></a>
            <a href="/mnaffiliate/commissions"><i class="bi bi-cash-stack"></i> <span>کمیسیون‌ها</span></a>
            <a href="/mnaffiliate/payments"><i class="bi bi-wallet2"></i> <span>درخواست‌های تسویه</span></a>
        </nav>
        <div class="sidebar-footer">MN Affiliate &middot; v2.0</div>
    </aside>

    <div class="content">
    <nav class="topbar">
        <span class="persian-date" id="mn-date">تاریخ امروز</span>
        <div class="topbar-left">
            <span class="admin-chip"><i class="bi bi-person-circle"></i> <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'admin'; ?></span>
            <a href="/mnaffiliate/logout" class="btn-logout"><i class="bi bi-box-arrow-left"></i> خروج</a>
        </div>
    </nav>