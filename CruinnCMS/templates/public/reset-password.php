<div class="container">
    <div class="login-page">
        <h1>Reset Password</h1>
        <p>Enter your new password below.</p>
        <form method="post" action="/reset-password/<?= e($token) ?>" class="form-login">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required minlength="8" autofocus autocomplete="new-password" class="form-input">
                <small class="form-help">At least 8 characters</small>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm New Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </form>
    </div>
</div>
