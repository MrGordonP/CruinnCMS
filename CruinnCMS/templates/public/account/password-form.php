<?php
/**
 * Account: Password form partial
 * Variables: $errors (optional array)
 */
?>
<section class="profile-section">
    <h2>Change Password</h2>
    <p class="form-help">Leave all fields blank to keep your current password.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/profile/password" class="profile-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-input"
                   autocomplete="current-password">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-input"
                       autocomplete="new-password" minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-input"
                       autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
    </form>
</section>
