<?php
/**
 * Platform Settings — Change platform admin password
 * Variables: $username, $saved
 */
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Platform Settings</h1>
    </div>

    <?php if (!empty($saved)): ?>
    <div class="platform-alert platform-alert-success">Password updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['_platform_settings_error'])): ?>
    <div class="platform-alert platform-alert-error"><?= e($_SESSION['_platform_settings_error']) ?></div>
    <?php unset($_SESSION['_platform_settings_error']); ?>
    <?php endif; ?>

    <section class="platform-section">
        <div class="platform-section-header"><h2>Platform Credentials</h2></div>

        <div class="platform-settings-card">
            <div class="platform-field">
                <label>Username</label>
                <code><?= e($username) ?></code>
                <small class="text-muted">Username is set in <code>config/platform.php</code>.</small>
            </div>

            <form method="POST" action="/cms/settings" class="platform-change-password">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

                <div class="platform-field">
                    <label for="new_password">New Password <small>(min. 12 characters)</small></label>
                    <input type="password" id="new_password" name="new_password"
                           required minlength="12" autocomplete="new-password">
                </div>

                <div class="platform-field">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           required minlength="12" autocomplete="new-password">
                </div>

                <button type="submit" class="platform-btn platform-btn-primary">Update Password</button>
            </form>
        </div>
    </section>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
