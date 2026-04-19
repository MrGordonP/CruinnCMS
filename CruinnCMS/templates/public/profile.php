<?php
/**
 * User Profile Page
 * Variables: $user, $mySubscriptions, $availableLists, $errors (optional)
 */
?>

<div class="profile-page">
    <div class="profile-container">

        <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success"><?= e($flash['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['info'])): ?>
            <div class="alert alert-info"><?= e($flash['info']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['danger'])): ?>
            <div class="alert alert-error"><?= e($flash['danger']) ?></div>
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

        <h1 class="profile-heading">My Profile</h1>

        <div class="profile-grid">

            <!-- ── Left column: editable form ─────────────────────── -->
            <div class="profile-col-left">
                <form method="post" action="<?= url('/profile') ?>" class="profile-form">
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

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- ── Right column: info + subscriptions ─────────────── -->
            <div class="profile-col-right">

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

                <section class="profile-section" id="subscriptions">
                    <h2>Mailing Lists</h2>

                    <?php if (empty($mySubscriptions) && empty($availableLists)): ?>
                        <p class="text-muted">No mailing lists are currently available.</p>
                    <?php else: ?>

                    <?php if (!empty($mySubscriptions)): ?>
                    <h3>My Subscriptions</h3>
                    <ul class="sub-list">
                        <?php foreach ($mySubscriptions as $sub): ?>
                        <li class="sub-item">
                            <div class="sub-info">
                                <strong><?= e($sub['list_name']) ?></strong>
                                <?php if ($sub['status'] === 'pending'): ?>
                                    <span class="sub-status sub-status--pending">Awaiting approval</span>
                                <?php else: ?>
                                    <span class="sub-status sub-status--active">Subscribed</span>
                                <?php endif; ?>
                            </div>
                            <form action="<?= e(url('/profile/mailing-lists/' . (int)$sub['list_id'] . '/unsubscribe')) ?>" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-outline"
                                    onclick="return confirm('<?= $sub['status'] === 'pending' ? 'Cancel your request to join this list?' : 'Unsubscribe from this list?' ?>')">
                                    <?= $sub['status'] === 'pending' ? 'Cancel' : 'Unsubscribe' ?>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if (!empty($availableLists)): ?>
                    <?php if (!empty($mySubscriptions)): ?><hr class="sub-divider"><?php endif; ?>
                    <h3>Available to Join</h3>
                    <ul class="sub-list">
                        <?php foreach ($availableLists as $list): ?>
                        <li class="sub-item">
                            <div class="sub-info">
                                <strong><?= e($list['name']) ?></strong>
                                <?php if (!empty($list['description'])): ?>
                                    <span class="sub-desc"><?= e($list['description']) ?></span>
                                <?php endif; ?>
                            </div>
                            <form action="<?= e(url('/profile/mailing-lists/' . (int)$list['id'] . '/subscribe')) ?>" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small <?= $list['subscription_mode'] === 'request' ? 'btn-outline' : 'btn-primary' ?>">
                                    <?= $list['subscription_mode'] === 'request' ? 'Request to Join' : 'Subscribe' ?>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php endif; ?>
                </section>

            </div>
        </div>
    </div>
</div>

<style>
.profile-page {
    padding: 2rem 0;
}

.profile-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.profile-heading {
    margin-bottom: 1.5rem;
}

.profile-grid {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

.profile-col-left,
.profile-col-right {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.profile-section {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, #ddd);
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.profile-section h2 {
    font-size: 1.05rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border, #eee);
}

.profile-section h3 {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #666);
    margin: 0 0 0.6rem 0;
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
    box-sizing: border-box;
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
    .form-row { grid-template-columns: 1fr; }
}

.form-help {
    font-size: 0.875rem;
    color: var(--text-muted, #666);
    margin-bottom: 1rem;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
}

.info-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.4rem 1rem;
    margin: 0;
}

.info-list dt {
    font-weight: 500;
    color: var(--text-muted, #666);
    white-space: nowrap;
}

.info-list dd {
    margin: 0;
}

.sub-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.sub-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.sub-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sub-desc {
    font-size: 0.8rem;
    color: var(--text-muted, #666);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sub-status {
    font-size: 0.75rem;
    font-weight: 500;
}

.sub-status--active  { color: var(--color-success, #065f46); }
.sub-status--pending { color: var(--color-warning, #b45309); }

.sub-divider {
    border: none;
    border-top: 1px solid var(--border, #eee);
    margin: 1rem 0;
}

.text-muted {
    color: var(--text-muted, #666);
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

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
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
