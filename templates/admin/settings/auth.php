<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Authentication</h2>

<form method="post" action="<?= url('/admin/settings/auth') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>Session</legend>

        <div class="form-row">
            <div class="form-group">
                <label for="session_lifetime">Session Lifetime (seconds)</label>
                <input type="number" id="session_lifetime" name="session_lifetime" class="form-input"
                       value="<?= e($settings['session.lifetime'] ?? '3600') ?>"
                       min="300" step="60">
                <small class="form-help">How long a login session lasts before requiring re-authentication. Default: 3600 (1 hour).</small>
            </div>
            <div class="form-group">
                <label for="session_name">Cookie Name</label>
                <input type="text" id="session_name" name="session_name" class="form-input"
                       value="<?= e($settings['session.name'] ?? '') ?>"
                       placeholder="cms_sess" pattern="[a-zA-Z0-9_-]+">
                <small class="form-help">Session cookie name. Only letters, numbers, hyphens, underscores.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Password Policy</legend>

        <div class="form-group">
            <label for="auth_password_min_length">Minimum Password Length</label>
            <input type="number" id="auth_password_min_length" name="auth_password_min_length" class="form-input"
                   value="<?= e($settings['auth.password_min_length'] ?? '8') ?>"
                   min="6" max="128" style="max-width: 120px;">
        </div>

        <div class="form-group">
            <label for="auth_reset_token_expiry">Password Reset Token Expiry (seconds)</label>
            <input type="number" id="auth_reset_token_expiry" name="auth_reset_token_expiry" class="form-input"
                   value="<?= e($settings['auth.reset_token_expiry'] ?? '3600') ?>"
                   min="300" step="60" style="max-width: 180px;">
            <small class="form-help">How long a "forgot password" link remains valid. Default: 3600 (1 hour).</small>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Login Protection</legend>

        <div class="form-row">
            <div class="form-group">
                <label for="auth_max_login_attempts">Max Failed Login Attempts</label>
                <input type="number" id="auth_max_login_attempts" name="auth_max_login_attempts" class="form-input"
                       value="<?= e($settings['auth.max_login_attempts'] ?? '5') ?>"
                       min="1" max="100" style="max-width: 120px;">
            </div>
            <div class="form-group">
                <label for="auth_lockout_duration">Lockout Duration (seconds)</label>
                <input type="number" id="auth_lockout_duration" name="auth_lockout_duration" class="form-input"
                       value="<?= e($settings['auth.lockout_duration'] ?? '900') ?>"
                       min="60" step="60" style="max-width: 180px;">
                <small class="form-help">How long an account is locked after too many failures. Default: 900 (15 minutes).</small>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Authentication Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
