<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>GDPR &amp; Privacy</h2>

<form method="post" action="<?= url('/admin/settings/gdpr') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>General Data Protection</legend>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="gdpr_enabled" value="0">
                <input type="checkbox" name="gdpr_enabled" value="1"
                    <?= ($settings['gdpr.enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                Enable GDPR Compliance Features
            </label>
            <small class="form-help">When enabled, users see cookie consent banners, data export/deletion options, and privacy notices.</small>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Data Controller Details</legend>

        <div class="form-group">
            <label for="gdpr_org_name">Organisation Name</label>
            <input type="text" id="gdpr_org_name" name="gdpr_org_name" class="form-input"
                   value="<?= e($settings['gdpr.org_name'] ?? '') ?>"
                   placeholder="e.g. Irish Encyclopaedia Association">
            <small class="form-help">Legal name of the data controller shown in privacy policy.</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="gdpr_contact_email">Contact Email</label>
                <input type="email" id="gdpr_contact_email" name="gdpr_contact_email" class="form-input"
                       value="<?= e($settings['gdpr.contact_email'] ?? '') ?>"
                       placeholder="info@example.ie">
            </div>
            <div class="form-group">
                <label for="gdpr_dpo_email">DPO Email</label>
                <input type="email" id="gdpr_dpo_email" name="gdpr_dpo_email" class="form-input"
                       value="<?= e($settings['gdpr.dpo_email'] ?? '') ?>"
                       placeholder="dpo@example.ie">
                <small class="form-help">Data Protection Officer email, if applicable.</small>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save GDPR Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
