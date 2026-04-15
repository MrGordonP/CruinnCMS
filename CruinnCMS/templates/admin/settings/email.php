<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Email Settings</h2>

<form method="post" action="<?= url('/admin/settings/email') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>SMTP (Outgoing Mail)</legend>

        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label for="mail_host">SMTP Host</label>
                <input type="text" id="mail_host" name="mail_host" class="form-input"
                       value="<?= e($settings['mail.host'] ?? '') ?>"
                       placeholder="smtp.example.com">
            </div>
            <div class="form-group" style="flex:1">
                <label for="mail_port">Port</label>
                <input type="number" id="mail_port" name="mail_port" class="form-input"
                       value="<?= e($settings['mail.port'] ?? '') ?>"
                       placeholder="587">
            </div>
            <div class="form-group" style="flex:1">
                <label for="mail_encryption">Encryption</label>
                <select id="mail_encryption" name="mail_encryption" class="form-input">
                    <?php $enc = $settings['mail.encryption'] ?? 'tls'; ?>
                    <option value="tls"<?= $enc === 'tls' ? ' selected' : '' ?>>TLS</option>
                    <option value="ssl"<?= $enc === 'ssl' ? ' selected' : '' ?>>SSL</option>
                    <option value=""<?= $enc === '' ? ' selected' : '' ?>>None</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mail_username">Username</label>
                <input type="text" id="mail_username" name="mail_username" class="form-input"
                       value="<?= e($settings['mail.username'] ?? '') ?>"
                       placeholder="user@example.com" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="mail_password">Password</label>
                <input type="password" id="mail_password" name="mail_password" class="form-input"
                       value="" placeholder="<?= ($settings['mail.password'] ?? '') ? '••••••••' : '' ?>" autocomplete="new-password">
                <small class="form-help">Leave blank to keep the current password.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mail_from_email">From Email</label>
                <input type="email" id="mail_from_email" name="mail_from_email" class="form-input"
                       value="<?= e($settings['mail.from_email'] ?? '') ?>"
                       placeholder="noreply@example.com">
            </div>
            <div class="form-group">
                <label for="mail_from_name">From Name</label>
                <input type="text" id="mail_from_name" name="mail_from_name" class="form-input"
                       value="<?= e($settings['mail.from_name'] ?? '') ?>"
                       placeholder="My Organisation">
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>IMAP (Council Inbox)</legend>

        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label for="imap_host">IMAP Host</label>
                <input type="text" id="imap_host" name="imap_host" class="form-input"
                       value="<?= e($settings['imap.host'] ?? '') ?>"
                       placeholder="imap.example.com">
            </div>
            <div class="form-group" style="flex:1">
                <label for="imap_port">Port</label>
                <input type="number" id="imap_port" name="imap_port" class="form-input"
                       value="<?= e($settings['imap.port'] ?? '') ?>"
                       placeholder="993">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="imap_username">Username</label>
                <input type="text" id="imap_username" name="imap_username" class="form-input"
                       value="<?= e($settings['imap.username'] ?? '') ?>"
                       placeholder="council@example.com" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="imap_password">Password</label>
                <input type="password" id="imap_password" name="imap_password" class="form-input"
                       value="" placeholder="<?= ($settings['imap.password'] ?? '') ? '••••••••' : '' ?>" autocomplete="new-password">
                <small class="form-help">Leave blank to keep the current password.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="imap_mailbox">Mailbox</label>
                <input type="text" id="imap_mailbox" name="imap_mailbox" class="form-input"
                       value="<?= e($settings['imap.mailbox'] ?? '') ?>"
                       placeholder="INBOX">
            </div>
            <div class="form-group">
                <label for="roundcube_url">Roundcube URL</label>
                <input type="url" id="roundcube_url" name="roundcube_url" class="form-input"
                       value="<?= e($settings['roundcube_url'] ?? '') ?>"
                       placeholder="https://mail.example.com">
                <small class="form-help">"Open in Roundcube" link for council inbox.</small>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Email Settings</button>
    </div>
</form>

<form method="post" action="<?= url('/admin/settings/email/test') ?>" style="margin-top: var(--space-lg);">
    <?= csrf_field() ?>
    <fieldset class="acp-fieldset">
        <legend>Test Email</legend>
        <p>Send a test email to your account (<strong><?= e(\Cruinn\Auth::user()['email'] ?? '—') ?></strong>) to verify SMTP settings.</p>
        <button type="submit" class="btn btn-outline">Send Test Email</button>
    </fieldset>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
