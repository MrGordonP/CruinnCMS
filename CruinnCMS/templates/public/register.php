<div class="container">
    <div class="register-page">
        <h1>Create Account</h1>

        <?php if (!empty($closed)): ?>
        <div class="detail-card">
            <p>Registration is currently closed. Please check back later or <a href="/contact">contact us</a> for more information.</p>
        </div>
        <?php else: ?>

        <form method="post" action="/register" class="form-register">
            <?= csrf_field() ?>

            <div class="form-group<?= !empty($errors['display_name']) ? ' has-error' : '' ?>">
                <label for="display_name">Your Name <span class="required">*</span></label>
                <input type="text" id="display_name" name="display_name" required autofocus class="form-input"
                       autocomplete="name" value="<?= e($old['display_name'] ?? '') ?>">
                <?php if (!empty($errors['display_name'])): ?>
                    <small class="form-error"><?= e($errors['display_name']) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group<?= !empty($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required class="form-input"
                       autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <small class="form-error"><?= e($errors['email']) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group<?= !empty($errors['password']) ? ' has-error' : '' ?>">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required minlength="8" class="form-input"
                           autocomplete="new-password">
                    <?php if (!empty($errors['password'])): ?>
                        <small class="form-error"><?= e($errors['password']) ?></small>
                    <?php else: ?>
                        <small class="form-help">At least 8 characters.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group<?= !empty($errors['password_confirm']) ? ' has-error' : '' ?>">
                    <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                           class="form-input" autocomplete="new-password">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <small class="form-error"><?= e($errors['password_confirm']) ?></small>
                    <?php endif; ?>
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
