<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform Login — Cruinn CMS</title>
    <link rel="icon" type="image/svg+xml" href="/brand/cruinn-favicon.svg">
    <link rel="stylesheet" href="/css/platform.css">
</head>
<body class="platform-body platform-login-body platform-dark-login">

<div class="platform-login-wrap">
    <div class="platform-login-card">
        <div class="platform-login-logo">
            <img src="/brand/cruinn-logo-banner.svg" alt="Cruinn CMS" class="platform-login-logo-img">
        </div>
        <h1>Platform Administration</h1>
        <p class="platform-login-sub">Sign in to manage your Cruinn instance</p>

        <?php if (!empty($error)): ?>
        <div class="platform-alert platform-alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/cms/login" class="platform-login-form">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

            <div class="platform-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       autocomplete="username" placeholder="platform">
            </div>

            <div class="platform-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password">
            </div>

            <button type="submit" class="platform-btn-primary">Sign In</button>
        </form>
    </div>
</div>

</body>
</html>
