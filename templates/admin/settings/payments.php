<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Payments</h2>

<form method="post" action="<?= url('/admin/settings/payments') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>PayPal</legend>

        <div class="form-row">
            <div class="form-group">
                <label for="paypal_client_id">Client ID</label>
                <input type="text" id="paypal_client_id" name="paypal_client_id" class="form-input"
                       value="<?= e($settings['paypal.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Client Secret</label>
                <?php if ($paypal_secret_set): ?>
                    <p class="form-help" style="padding:0.5rem 0.75rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; margin:0;">
                        ✓ Configured via <code>config/config.local.php</code>
                    </p>
                <?php else: ?>
                    <p class="form-help" style="padding:0.5rem 0.75rem; background:#fef2f2; border:1px solid #fecaca; border-radius:4px; margin:0;">
                        ⚠ Not set. Add to <code>config/config.local.php</code>:<br>
                        <code>'paypal' =&gt; ['client_secret' =&gt; 'your-secret']</code>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="paypal_sandbox" value="0">
                <input type="checkbox" name="paypal_sandbox" value="1"
                    <?= ($settings['paypal.sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                Sandbox Mode (testing)
            </label>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Stripe</legend>

        <div class="form-row">
            <div class="form-group">
                <label for="stripe_public_key">Publishable Key</label>
                <input type="text" id="stripe_public_key" name="stripe_public_key" class="form-input"
                       value="<?= e($settings['stripe.public_key'] ?? '') ?>"
                       placeholder="pk_test_...">
            </div>
            <div class="form-group">
                <label>Secret Key</label>
                <?php if ($stripe_secret_set): ?>
                    <p class="form-help" style="padding:0.5rem 0.75rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; margin:0;">
                        ✓ Configured via <code>config/config.local.php</code>
                    </p>
                <?php else: ?>
                    <p class="form-help" style="padding:0.5rem 0.75rem; background:#fef2f2; border:1px solid #fecaca; border-radius:4px; margin:0;">
                        ⚠ Not set. Add to <code>config/config.local.php</code>:<br>
                        <code>'stripe' =&gt; ['secret_key' =&gt; 'sk_test_...']</code>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="stripe_sandbox" value="0">
                <input type="checkbox" name="stripe_sandbox" value="1"
                    <?= ($settings['stripe.sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                Test Mode
            </label>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Payment Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
