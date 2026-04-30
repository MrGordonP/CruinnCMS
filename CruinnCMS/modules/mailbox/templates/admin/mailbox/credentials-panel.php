<?php
/**
 * Mailbox credentials form fragment — loaded into middle panel via fetch.
 * No layout (standalone HTML fragment).
 *
 * @var array  $mb          Mailbox row (or defaults for new)
 * @var bool   $is_new      True when creating a new mailbox
 * @var string $csrf_token
 */
$action = $is_new ? '/admin/mailbox' : '/admin/mailbox/' . (int)$mb['id'] . '/credentials';
?>
<style>
.cred-panel { display: flex; flex-direction: column; height: 100%; }
.cred-panel-toolbar {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 1rem; border-bottom: 1px solid var(--color-border, #ccd9d3);
    background: var(--color-surface, #f6f4ef); flex-shrink: 0;
}
.cred-panel-toolbar h3 { margin: 0; font-size: 0.95rem; flex: 1; }
.cred-panel-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
.cred-section { margin-bottom: 1.75rem; }
.cred-section h4 {
    margin: 0 0 0.75rem; font-size: 0.78rem; text-transform: uppercase;
    letter-spacing: .06em; opacity: .55;
}
.cred-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.cred-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.75rem; }
.form-group { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.75rem; }
.form-group label { font-size: 0.8rem; opacity: .65; }
.form-group input, .form-group select {
    border: 1px solid var(--color-border, #ccd9d3);
    border-radius: 4px; padding: 0.35rem 0.6rem; font-size: 0.85rem;
    background: #fff; width: 100%; box-sizing: border-box;
}
.pw-hint { font-size: 0.75rem; opacity: .5; margin-top: 0.15rem; }
.cred-panel-footer {
    padding: 0.75rem 1rem; border-top: 1px solid var(--color-border, #ccd9d3);
    display: flex; gap: 0.5rem; flex-shrink: 0;
    background: var(--color-surface, #f6f4ef);
}
</style>

<form method="post" action="<?= e($action) ?>" class="cred-panel">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

    <div class="cred-panel-toolbar">
        <h3><?= $is_new ? 'New Mailbox' : e($mb['label']) ?></h3>
        <?php if (!$is_new): ?>
        <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;cursor:pointer">
            <input type="hidden"   name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" <?= $mb['enabled'] ? 'checked' : '' ?>
                   style="width:auto;margin:0">
            Enabled
        </label>
        <?php endif; ?>
    </div>

    <div class="cred-panel-body">

        <!-- Identity -->
        <div class="cred-section">
            <h4>Identity</h4>
            <div class="cred-row">
                <div class="form-group">
                    <label for="cred-label">Label</label>
                    <input type="text" id="cred-label" name="label"
                           value="<?= e($mb['label']) ?>" placeholder="e.g. President" required>
                </div>
                <div class="form-group">
                    <label for="cred-email">Email address</label>
                    <input type="email" id="cred-email" name="email"
                           value="<?= e($mb['email']) ?>" placeholder="president@example.org">
                </div>
            </div>
            <?php if ($is_new): ?>
            <div class="form-group" style="margin-top:0.25rem">
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer">
                    <input type="hidden"   name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" style="width:auto;margin:0">
                    Enable this mailbox immediately
                </label>
            </div>
            <?php endif; ?>
        </div>

        <!-- IMAP -->
        <div class="cred-section">
            <h4>IMAP — Incoming Mail</h4>
            <div class="cred-row-3">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="imap_host" value="<?= e($mb['imap_host'] ?? '') ?>" placeholder="mail.example.org">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="imap_port" value="<?= (int)($mb['imap_port'] ?? 993) ?>" min="1" max="65535">
                </div>
                <div class="form-group">
                    <label>Encryption</label>
                    <select name="imap_encryption">
                        <?php foreach (['ssl','tls','none'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($mb['imap_encryption'] ?? 'ssl') === $opt ? 'selected' : '' ?>><?= strtoupper($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="cred-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="imap_user" value="<?= e($mb['imap_user'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="imap_pass" value="" autocomplete="new-password"
                           placeholder="<?= ($mb['has_imap_pass'] ?? false) ? '(leave blank to keep current)' : 'Enter password' ?>">
                    <?php if ($mb['has_imap_pass'] ?? false): ?>
                    <span class="pw-hint">Password saved — leave blank to keep it unchanged.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SMTP -->
        <div class="cred-section">
            <h4>SMTP — Outgoing Mail</h4>
            <div class="cred-row-3">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="smtp_host" value="<?= e($mb['smtp_host'] ?? '') ?>" placeholder="mail.example.org">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="<?= (int)($mb['smtp_port'] ?? 587) ?>" min="1" max="65535">
                </div>
                <div class="form-group">
                    <label>Encryption</label>
                    <select name="smtp_encryption">
                        <?php foreach (['tls','ssl','none'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($mb['smtp_encryption'] ?? 'tls') === $opt ? 'selected' : '' ?>><?= strtoupper($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="cred-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="smtp_user" value="<?= e($mb['smtp_user'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="smtp_pass" value="" autocomplete="new-password"
                           placeholder="<?= ($mb['has_smtp_pass'] ?? false) ? '(leave blank to keep current)' : 'Enter password' ?>">
                    <?php if ($mb['has_smtp_pass'] ?? false): ?>
                    <span class="pw-hint">Password saved — leave blank to keep it unchanged.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="cred-panel-footer">
        <button type="submit" class="btn btn-sm btn-primary">
            <?= $is_new ? 'Create Mailbox' : 'Save Credentials' ?>
        </button>
        <?php if (!$is_new): ?>
        <form method="post" action="/admin/mailbox/<?= (int)$mb['id'] ?>/sync"
              style="display:inline;margin:0"
              onsubmit="handleSync(this, <?= (int)$mb['id'] ?>); return false;">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <button type="submit" class="btn btn-sm btn-secondary" id="sync-btn-<?= (int)$mb['id'] ?>">⟳ Sync</button>
        </form>
        <a href="/mail/<?= (int)$mb['id'] ?>/INBOX" class="btn btn-sm btn-secondary">Open Mailbox →</a>
        <form method="post" action="/admin/mailbox/<?= (int)$mb['id'] ?>/delete"
              style="display:inline;margin:0"
              onsubmit="return confirm('Delete this mailbox and all its indexed messages? This cannot be undone.')">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <button type="submit" class="btn btn-sm btn-danger" style="margin-left:auto">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</form>

<script>
function handleSync(form, id) {
    const btn  = document.getElementById('sync-btn-' + id);
    const data = new URLSearchParams(new FormData(form));
    btn.disabled    = true;
    btn.textContent = '⟳ Syncing…';
    fetch('/admin/mailbox/' + id + '/sync', { method: 'POST', body: data })
        .then(r => r.json())
        .then(j => { btn.textContent = j.ok ? ('✓ ' + j.new_messages + ' new') : '✗ Error'; })
        .catch(() => { btn.textContent = '✗ Failed'; })
        .finally(() => { btn.disabled = false; setTimeout(() => { btn.textContent = '⟳ Sync'; }, 4000); });
}
</script>
