<?php \IGA\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>Social Account Settings</h1>
        <a href="<?= url('/admin/social') ?>" class="btn btn-outline">Back to Hub</a>
    </div>

    <div class="accounts-info">
        <p class="text-muted">
            Connect your social media accounts using the buttons below. Simply log in to each platform
            and authorise access — no developer credentials needed.
        </p>
    </div>

    <div class="accounts-grid">
        <?php foreach ($accounts as $acct): ?>
        <?php
            $pf = $acct['platform'];
            $hasToken = !empty($acct['access_token']);
            $isActive = !empty($acct['is_active']);
        ?>
        <div class="account-card account-card-<?= e($pf) ?> <?= $hasToken && $isActive ? 'connected' : '' ?>">
            <div class="account-card-header">
                <div class="platform-icon-lg">
                    <?php if ($pf === 'facebook'): ?>
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <?php elseif ($pf === 'twitter'): ?>
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <h2><?= ucfirst($pf) === 'Twitter' ? 'Twitter / X' : ucfirst($pf) ?></h2>
                    <?php if ($hasToken && $isActive): ?>
                        <span class="badge badge-success">Connected</span>
                        <?php if ($acct['connected_at']): ?>
                            <small class="text-muted">since <?= format_date($acct['connected_at'], 'j M Y') ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-muted">Not Connected</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($hasToken && $isActive): ?>
                <!-- Connected account info -->
                <div class="account-connected-info">
                    <?php if ($acct['account_name']): ?>
                        <p><strong>Account:</strong> <?= e($acct['account_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($acct['account_id']): ?>
                        <p><strong>ID:</strong> <?= e($acct['account_id']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($acct['token_expires'])): ?>
                        <p><strong>Token expires:</strong> <?= format_date($acct['token_expires'], 'j M Y, H:i') ?></p>
                    <?php endif; ?>
                </div>

                <div class="account-form-actions" style="margin-top: 1rem;">
                    <a href="<?= url('/admin/social/connect/' . e($pf)) ?>" class="btn btn-primary">
                        Reconnect <?= ucfirst($pf) === 'Twitter' ? 'Twitter / X' : ucfirst($pf) ?>
                    </a>
                    <form action="<?= url('/admin/social/accounts/' . (int)$acct['id'] . '/disconnect') ?>" method="POST" style="display:inline"
                        onsubmit="return confirm('Disconnect this account? Tokens will be removed.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-outline">Disconnect</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- OAuth connect button -->
                <div class="account-connect-oauth" style="margin: 1.5rem 0;">
                    <a href="<?= url('/admin/social/connect/' . e($pf)) ?>" class="btn btn-primary btn-lg btn-connect-<?= e($pf) ?>">
                        <?php if ($pf === 'facebook'): ?>
                            Connect with Facebook
                        <?php elseif ($pf === 'twitter'): ?>
                            Connect with Twitter / X
                        <?php else: ?>
                            Connect with Instagram
                        <?php endif; ?>
                    </a>
                    <p class="text-muted" style="margin-top: 0.5rem; font-size: 0.85rem;">
                        You'll be redirected to <?= ucfirst($pf) === 'Twitter' ? 'Twitter' : ucfirst($pf) ?> to log in and authorise access.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Advanced: manual token entry -->
            <details class="account-setup-help">
                <summary>Advanced: Manual Configuration</summary>
                <p class="text-muted" style="margin: 0.75rem 0; font-size: 0.85rem;">
                    Social connections use the central auth proxy by default — no developer credentials needed.
                    To use your own developer app instead, set <code>custom_<?= e($pf) ?>_app_id</code> and
                    <code>custom_<?= e($pf) ?>_app_secret</code> in <code>config.local.php</code>,
                    or paste tokens manually below.
                </p>
                <form action="<?= url('/admin/social/accounts') ?>" method="POST" class="account-form" style="margin-top: 1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="platform" value="<?= e($pf) ?>">

                    <div class="form-group">
                        <label>Account / Page Name</label>
                        <input type="text" name="account_name" class="form-control" value="<?= e($acct['account_name'] ?? '') ?>"
                            placeholder="e.g. My Organisation">
                    </div>

                    <div class="form-group">
                        <label>Account / Page ID</label>
                        <input type="text" name="account_id" class="form-control" value="<?= e($acct['account_id'] ?? '') ?>"
                            placeholder="<?= $pf === 'facebook' ? 'Facebook Page ID' : ($pf === 'twitter' ? 'Twitter User ID' : 'Instagram Business Account ID') ?>">
                    </div>

                    <div class="form-group">
                        <label>Access Token</label>
                        <input type="password" name="access_token" class="form-control" value="<?= e($acct['access_token'] ?? '') ?>"
                            placeholder="Paste your access token here" autocomplete="off">
                    </div>

                    <?php if ($pf === 'facebook'): ?>
                    <div class="form-group">
                        <label>Page Access Token</label>
                        <input type="password" name="page_token" class="form-control" value="<?= e($acct['page_token'] ?? '') ?>"
                            placeholder="Facebook Page Access Token (separate from user token)" autocomplete="off">
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                            Active (enable posting and fetching)
                        </label>
                    </div>

                    <div class="account-form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </details>
        </div>
        <?php endforeach; ?>
    </div>
</div>
