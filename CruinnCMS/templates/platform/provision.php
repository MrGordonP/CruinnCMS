<?php
/**
 * Platform — Provision Instance
 * Variables: $errors (array), $values (array — repopulate form on error)
 */
$v = fn(string $key, string $default = '') => e($values[$key] ?? $default);
?>
<?php ob_start(); ?>

<div class="platform-page">
    <section class="platform-section" style="max-width:680px">
        <div class="platform-section-header">
            <h2>Provision New Instance</h2>
            <a href="/cms/dashboard" class="platform-btn platform-btn-secondary">← Back</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="platform-alert platform-alert-error" style="margin-bottom:1.2rem">
            <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/cms/instances/new">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

            <p style="color:rgba(232,228,218,.65); font-size:.9rem; margin-bottom:1.4rem;">
                Each instance gets its own database and configuration.
                The database will be created automatically if your user has sufficient privileges.
            </p>

            <!-- ── Identity ──────────────────────────────────── -->
            <div class="install-title">Instance</div>

            <div class="row2">
                <div class="platform-field">
                    <label>Slug <span style="color:rgba(232,228,218,.4);font-weight:400">(folder name)</span></label>
                    <input type="text" name="slug" value="<?= $v('slug') ?>"
                           placeholder="my-site" pattern="[a-z0-9\-]+" required>
                    <div class="hint">Lowercase letters, numbers and hyphens only.</div>
                </div>
                <div class="platform-field">
                    <label>Display Name</label>
                    <input type="text" name="name" value="<?= $v('name') ?>"
                           placeholder="My Site" required>
                </div>
            </div>

            <div class="platform-field">
                <label>Site URL</label>
                <input type="url" name="site_url" value="<?= $v('site_url') ?>"
                       placeholder="https://example.com" required>
                <div class="hint">No trailing slash.</div>
            </div>

            <!-- ── Database ──────────────────────────────────── -->
            <div class="install-title">Database</div>

            <div class="row2">
                <div class="platform-field">
                    <label>Host</label>
                    <input type="text" name="db_host" value="<?= $v('db_host', 'localhost') ?>" required>
                </div>
                <div class="platform-field">
                    <label>Port</label>
                    <input type="number" name="db_port" value="<?= $v('db_port', '3306') ?>" required>
                </div>
            </div>

            <div class="platform-field">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= $v('db_name') ?>"
                       placeholder="my_site_db" required>
            </div>
            <div class="platform-field">
                <label>Database User</label>
                <input type="text" name="db_user" value="<?= $v('db_user') ?>"
                       placeholder="root" required>
            </div>
            <div class="platform-field">
                <label>Database Password</label>
                <input type="password" name="db_pass" autocomplete="new-password">
                <div class="hint">Leave blank for local installs with no password.</div>
            </div>

            <!-- ── First Admin ───────────────────────────────── -->
            <div class="install-title">First Admin Account</div>

            <div class="platform-field">
                <label>Admin Email</label>
                <input type="email" name="admin_email" value="<?= $v('admin_email') ?>"
                       placeholder="admin@example.com" required>
            </div>
            <div class="row2">
                <div class="platform-field">
                    <label>Admin Display Name</label>
                    <input type="text" name="admin_name" value="<?= $v('admin_name') ?>"
                           placeholder="Site Admin" required>
                </div>
                <div class="platform-field">
                    <label>Admin Password</label>
                    <input type="password" name="admin_password" autocomplete="new-password"
                           minlength="8" required>
                    <div class="hint">At least 8 characters.</div>
                </div>
            </div>

            <button type="submit" class="platform-btn-primary" style="margin-top:1.5rem;">
                Provision Instance &rarr;
            </button>
        </form>
    </section>
</div>

<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
