<div class="container">
    <div class="login-page">
        <h1>Login</h1>
        <form method="post" action="/login" class="form-login">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       autocomplete="email" class="form-input">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <?php if (!empty($oauth_providers)): ?>
        <div class="oauth-divider"><span>or continue with</span></div>
        <div class="oauth-buttons">
            <?php foreach ($oauth_providers as $key => $label): ?>
            <a href="/auth/<?= htmlspecialchars($key) ?>" class="btn-oauth btn-oauth-<?= htmlspecialchars($key) ?>">
                <span class="btn-oauth-icon"><?= oauth_icon($key) ?></span>
                <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="login-links">
            <a href="/forgot-password">Forgot your password?</a>
            <span class="login-links-divider">&bull;</span>
            <a href="/register">Create an account</a>
        </div>
    </div>
</div>
