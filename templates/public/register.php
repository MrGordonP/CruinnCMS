<div class="container">
    <div class="register-page">
        <h1>Join <?= e(\Cruinn\App::config('site.name', 'Us')) ?></h1>

        <?php if (!empty($closed)): ?>
        <div class="detail-card">
            <p>Registration is currently closed. Please check back later or <a href="/contact">contact us</a> for more information about membership.</p>
        </div>
        <?php else: ?>

        <p class="register-intro">Create an account to become a member of <?= e(\Cruinn\App::config('site.name', 'our organisation')) ?>. Membership gives you access to events, field trips, and our community.</p>

        <form method="post" action="/register" class="form-register">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="forenames">First Name <span class="required">*</span></label>
                    <input type="text" id="forenames" name="forenames" required autofocus class="form-input" autocomplete="given-name">
                </div>
                <div class="form-group">
                    <label for="surnames">Last Name <span class="required">*</span></label>
                    <input type="text" id="surnames" name="surnames" required class="form-input" autocomplete="family-name">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required class="form-input" autocomplete="email">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required minlength="8" class="form-input" autocomplete="new-password">
                    <small class="form-help">At least 8 characters</small>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8" class="form-input" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <?php if (!empty($oauth_providers)): ?>
        <div class="oauth-divider"><span>or sign up with</span></div>
        <div class="oauth-buttons">
            <?php foreach ($oauth_providers as $key => $label): ?>
            <a href="/auth/<?= htmlspecialchars($key) ?>" class="btn-oauth btn-oauth-<?= htmlspecialchars($key) ?>">
                <span class="btn-oauth-icon"><?= oauth_icon($key) ?></span>
                <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="register-login">Already have an account? <a href="/login">Log in</a></p>

        <?php endif; ?>
    </div>
</div>
