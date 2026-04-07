<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CruinnCMS Setup</title>
    <link rel="icon" type="image/svg+xml" href="/brand/cruinn-favicon.svg">
    <link rel="stylesheet" href="/css/platform.css">
    <style>
        .install-wrap  { max-width: 620px; }
        .install-title { font-size: 1rem; font-weight: 700; text-transform: uppercase;
                         letter-spacing: .08em; color: var(--plat-accent-lt); opacity: .65;
                         margin: 1.8rem 0 1rem; border-bottom: 1px solid rgba(29,158,117,.2);
                         padding-bottom: .4rem; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .hint { font-size: .8rem; color: rgba(232,228,218,.4); margin-top: .25rem; }
        code  { background: rgba(29,158,117,.15); color: var(--plat-accent-lt);
                padding: .1rem .35rem; border-radius: 4px; font-size: .85em; }
        .platform-field label { display: block; margin-bottom: .4rem;
                                font-size: .85rem; font-weight: 600; color: var(--plat-cream); }
        .platform-field input { width: 100%; padding: .55rem .85rem; background: #0c1614;
                                border: 1px solid rgba(29,158,117,.3); border-radius: 6px;
                                color: var(--plat-cream); font-size: .95rem; outline: none;
                                transition: border-color .15s; }
        .platform-field input::placeholder { color: rgba(232,228,218,.3); }
        .platform-field input:focus { border-color: var(--plat-accent);
                                      box-shadow: 0 0 0 3px rgba(29,158,117,.18); }
        .footer-note { text-align: center; font-size: .8rem;
                       color: rgba(232,228,218,.25); margin-top: 1.5rem; }
    </style>
</head>
<body class="platform-body platform-login-body platform-dark-login">

<div class="platform-login-wrap install-wrap">
    <div class="platform-login-card">

        <div class="platform-login-logo">
            <img src="/brand/cruinn-logo-banner.svg" alt="Cruinn CMS" class="platform-login-logo-img">
        </div>
        <h1>Platform Setup</h1>
        <p class="platform-login-sub">Configure your CruinnCMS platform database</p>

        <?php if (!empty($errors)): ?>
            <div class="platform-alert platform-alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/cms/install">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

            <p style="color:rgba(232,228,218,.65); font-size:.9rem; margin-bottom:1.2rem;">
                Enter the credentials for the <strong style="color:var(--plat-cream)">CruinnCMS platform database</strong>.
                This stores platform settings and the instance registry � not any instance content.
                The database will be created automatically if your user has sufficient privileges.
            </p>

            <div class="install-title">Platform Account</div>

            <div class="platform-field">
                <label>Username</label>
                <input type="text" name="username" value="platform" required autocomplete="username">
            </div>
            <div class="row2">
                <div class="platform-field">
                    <label>Password</label>
                    <input type="password" name="password" required autocomplete="new-password" placeholder="Min. 8 characters">
                </div>
                <div class="platform-field">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required autocomplete="new-password">
                </div>
            </div>

            <div class="install-title">Database</div>

            <div class="row2">
                <div class="platform-field">
                    <label>Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                    <div class="hint">Usually <code>localhost</code></div>
                </div>
                <div class="platform-field">
                    <label>Port</label>
                    <input type="number" name="db_port" value="3306" required>
                </div>
            </div>

            <div class="platform-field">
                <label>Database Name</label>
                <input type="text" name="db_name" placeholder="cruinn_platform" required>
            </div>
            <div class="platform-field">
                <label>Database User</label>
                <input type="text" name="db_user" placeholder="root" required>
            </div>
            <div class="platform-field">
                <label>Database Password</label>
                <input type="password" name="db_pass" autocomplete="new-password">
                <div class="hint">Leave blank for local installs (XAMPP/MAMP root).</div>
            </div>

            <button type="submit" class="platform-btn-primary" style="margin-top:1.5rem;">
                Install CruinnCMS &rarr;
            </button>
        </form>

    </div>
</div>
<p class="footer-note">CruinnCMS Platform Setup</p>

</body>
</html>
