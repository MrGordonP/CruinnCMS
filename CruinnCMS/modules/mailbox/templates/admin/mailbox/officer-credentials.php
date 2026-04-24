<?php
/**
 * Mailbox Module — Officer IMAP/SMTP Credentials
 *
 * Standalone credential form for a single officer position.
 * Passwords are write-only: the existing encrypted value is never echoed.
 * Leave a password field blank to keep the existing stored value.
 */
?>
<div class="admin-section">
    <div class="admin-section-header">
        <h1>Mailbox Credentials</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/officers" class="btn btn-secondary btn-sm">← Officers</a>
            <a href="/admin/mailbox" class="btn btn-secondary btn-sm">Mailbox Overview</a>
        </div>
    </div>

    <div class="admin-card">
        <p>
            <strong>Position:</strong> <?= e($officer['position']) ?>
            <?php if ($officer['email']): ?>
                &nbsp;·&nbsp; <strong>Address:</strong> <?= e($officer['email']) ?>
            <?php endif; ?>
        </p>
    </div>

    <form method="post" action="/admin/mailbox/officer/<?= (int)$officer['id'] ?>/credentials" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <!-- ── Mailbox enabled ─────────────────────────────── -->
        <div class="admin-card">
            <div class="form-group">
                <label class="form-checkbox-label">
                    <input type="checkbox" name="imap_enabled" value="1"
                           <?= $officer['imap_enabled'] ? 'checked' : '' ?>>
                    Enable mailbox for this officer position
                </label>
                <small class="form-help">When enabled, the linked user can access this inbox in Cruinn.</small>
            </div>
        </div>

        <!-- ── IMAP ───────────────────────────────────────── -->
        <fieldset class="acp-fieldset">
            <legend>IMAP (Incoming Mail)</legend>

            <div class="form-row">
                <div class="form-group" style="flex:3">
                    <label for="imap_host">Host</label>
                    <input type="text" id="imap_host" name="imap_host" class="form-input"
                           value="<?= e($officer['imap_host'] ?? '') ?>"
                           placeholder="mail.example.com">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="imap_port">Port</label>
                    <input type="number" id="imap_port" name="imap_port" class="form-input"
                           value="<?= (int)($officer['imap_port'] ?? 993) ?>"
                           min="1" max="65535">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="imap_encryption">Encryption</label>
                    <select id="imap_encryption" name="imap_encryption" class="form-select">
                        <?php $enc = $officer['imap_encryption'] ?? 'ssl'; ?>
                        <option value="ssl"  <?= $enc === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                        <option value="tls"  <?= $enc === 'tls'  ? 'selected' : '' ?>>TLS</option>
                        <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="imap_user">Username</label>
                    <input type="text" id="imap_user" name="imap_user" class="form-input"
                           value="<?= e($officer['imap_user'] ?? '') ?>"
                           placeholder="president@example.com" autocomplete="off">
                </div>
                <div class="form-group form-group-grow">
                    <label for="imap_pass">Password</label>
                    <input type="password" id="imap_pass" name="imap_pass" class="form-input"
                           value=""
                           placeholder="<?= $officer['has_imap_pass'] ? '••••••••  (stored)' : 'Enter password' ?>"
                           autocomplete="new-password">
                    <small class="form-help">Leave blank to keep the current stored password.</small>
                </div>
            </div>
        </fieldset>

        <!-- ── SMTP ───────────────────────────────────────── -->
        <fieldset class="acp-fieldset">
            <legend>SMTP (Outgoing Mail)</legend>

            <div class="form-row">
                <div class="form-group" style="flex:3">
                    <label for="smtp_host">Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" class="form-input"
                           value="<?= e($officer['smtp_host'] ?? '') ?>"
                           placeholder="mail.example.com">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="smtp_port">Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" class="form-input"
                           value="<?= (int)($officer['smtp_port'] ?? 587) ?>"
                           min="1" max="65535">
                </div>
                <div class="form-group" style="flex:1">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                        <?php $senc = $officer['smtp_encryption'] ?? 'tls'; ?>
                        <option value="tls"  <?= $senc === 'tls'  ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl"  <?= $senc === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= $senc === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="smtp_user">Username</label>
                    <input type="text" id="smtp_user" name="smtp_user" class="form-input"
                           value="<?= e($officer['smtp_user'] ?? '') ?>"
                           placeholder="president@example.com" autocomplete="off">
                </div>
                <div class="form-group form-group-grow">
                    <label for="smtp_pass">Password</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" class="form-input"
                           value=""
                           placeholder="<?= $officer['has_smtp_pass'] ? '••••••••  (stored)' : 'Enter password' ?>"
                           autocomplete="new-password">
                    <small class="form-help">Leave blank to keep the current stored password.</small>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Credentials</button>
            <a href="/admin/organisation/officers" class="btn btn-link">Cancel</a>
        </div>
    </form>
</div>

<style>
.form-group-grow { flex: 1; }
</style>
