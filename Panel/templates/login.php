<?php
// templates/login.php
// جلوگیری از دسترسی مستقیم — این فایل فقط از طریق روتر index.php لود می‌شود
if (!defined('MNAFF_PANEL')) {
    header('Location: /mnaffiliate/login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #312e81 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        .login-container .card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        .login-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 14px;
        }
        .login-title { font-weight: 800; font-size: 1.1rem; }
        .form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
        .btn-primary { background: #4f46e5; border-color: #4f46e5; }
        .btn-primary:hover { background: #4338ca; border-color: #4338ca; }
    </style>
</head>
<body>
    <div class="login-container card p-4">
        <div class="login-logo"><i class="bi bi-graph-up-arrow"></i></div>
        <h2 class="text-center mb-1 login-title">پنل مدیریت بازاریابی</h2>
        <p class="text-center text-muted mb-4" style="font-size:.82rem">برای ورود، اطلاعات حساب خود را وارد کنید</p>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger text-center" style="border-radius:10px;font-size:.85rem">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form action="/mnaffiliate/login" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">نام کاربری:</label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">رمز عبور:</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary py-2 fw-bold">ورود به پنل</button>
            </div>
        </form>
    </div>
</body>
</html>