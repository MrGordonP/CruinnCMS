<?php
/**
 * User Profile Page
 * Variables: $user, $errors (optional)
 */
?>

<div class="profile-page">
    <div class="container">
        <h1>My Profile</h1>

        <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success"><?= e($flash['success']) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/profile" class="profile-form">
            <?= csrf_field() ?>

            <section class="profile-section">
                <h2>Account Details</h2>

                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" name="display_name" id="display_name" class="form-input"
                           value="<?= e($user['display_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-input"
                           value="<?= e($user['email'] ?? '') ?>" required>
                </div>
            </section>

            <section class="profile-section">
                <h2>Change Password</h2>
                <p class="form-help">Leave blank to keep your current password.</p>

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
            </section>

            <section class="profile-section">
                <h2>Account Information</h2>
                <dl class="info-list">
                    <dt>Account Created</dt>
                    <dd><?= format_date($user['created_at'], 'j F Y') ?></dd>

                    <?php if (!empty($user['last_login'])): ?>
                    <dt>Last Login</dt>
                    <dd><?= format_date($user['last_login'], 'j F Y \a\t H:i') ?></dd>
                    <?php endif; ?>
                </dl>
            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
.profile-page {
    padding: 2rem 0;
}

.profile-page .container {
    max-width: 600px;
    margin: 0 auto;
    padding: 0 1rem;
}

.profile-page h1 {
    margin-bottom: 1.5rem;
}

.profile-section {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, #ddd);
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.profile-section h2 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border, #eee);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.35rem;
}

.form-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 1rem;
    border: 1px solid var(--border, #ccc);
    border-radius: 0.375rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary, #1d9e75);
    box-shadow: 0 0 0 2px rgba(29, 158, 117, 0.2);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 500px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-help {
    font-size: 0.875rem;
    color: var(--text-muted, #666);
    margin-bottom: 1rem;
}

.info-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.5rem 1rem;
    margin: 0;
}

.info-list dt {
    font-weight: 500;
    color: var(--text-muted, #666);
}

.info-list dd {
    margin: 0;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.alert-error ul {
    margin: 0;
    padding-left: 1.25rem;
}
</style>
