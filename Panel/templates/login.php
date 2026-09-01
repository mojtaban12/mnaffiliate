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
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="login-container card p-4">
        <h2 class="text-center mb-4">ورود به پنل مدیریت</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger text-center">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form action="/mnaffiliate/login" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">نام کاربری:</label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور:</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">ورود</button>
            </div>
        </form>
    </div>
</body>
</html>