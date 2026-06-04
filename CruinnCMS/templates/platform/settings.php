<?php
/**
 * Platform Settings — Change platform admin password + linked log paths
 * Variables: $username, $saved, $logsSaved, $logsError, $linkedLogs
 */

$rcRoot = dirname(__DIR__, 2);
$parentRoot = realpath(dirname($rcRoot));
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Platform Settings</h1>
    </div>

    <?php if (!empty($saved)): ?>
    <div class="platform-alert platform-alert-success">Password updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($logsSaved)): ?>
    <div class="platform-alert platform-alert-success">Linked logs updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($logsError)): ?>
    <div class="platform-alert platform-alert-error"><?= e($logsError) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['_platform_settings_error'])): ?>
    <div class="platform-alert platform-alert-error"><?= e($_SESSION['_platform_settings_error']) ?></div>
    <?php unset($_SESSION['_platform_settings_error']); ?>
    <?php endif; ?>

    <section class="platform-section">
        <div class="platform-section-header"><h2>Platform Credentials</h2></div>

        <div class="platform-settings-card">
            <div class="platform-field">
                <label>Username</label>
                <code><?= e($username) ?></code>
                <small class="text-muted">Username is set in <code>config/platform.php</code>.</small>
            </div>

            <form method="POST" action="/cms/settings" class="platform-change-password">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

                <div class="platform-field">
                    <label for="new_password">New Password <small>(min. 12 characters)</small></label>
                    <input type="password" id="new_password" name="new_password"
                           required minlength="12" autocomplete="new-password">
                </div>

                <div class="platform-field">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           required minlength="12" autocomplete="new-password">
                </div>

                <button type="submit" class="platform-btn platform-btn-primary">Update Password</button>
            </form>
        </div>
    </section>

    <section class="platform-section">
        <div class="platform-section-header"><h2>Linked Error Logs</h2></div>

        <div class="platform-settings-card">
            <p class="text-muted" style="margin-top:0">Link one or more absolute log file paths from hosting so they can be viewed in-platform (read-only).</p>

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem">
                <button type="button" class="platform-btn platform-btn-primary" id="open-linked-logs-dialog">Manage Linked Logs</button>
            </div>

            <?php $linkedLogs = is_array($linkedLogs ?? null) ? $linkedLogs : []; ?>
            <?php if (!empty($linkedLogs)): ?>
            <div style="display:grid;gap:.5rem">
                <?php foreach ($linkedLogs as $i => $log): ?>
                <div style="border:1px solid #d1d5db;border-radius:6px;padding:.55rem .65rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                    <div>
                        <div style="font-weight:600"><?= e($log['label'] ?? 'Log') ?></div>
                        <div style="font-family:monospace;font-size:.78rem;color:#6b7280"><?= e($log['path'] ?? '') ?></div>
                    </div>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <?php
                        $logPath = (string) ($log['path'] ?? '');
                        $browsePath = null;
                        if (str_starts_with($logPath, 'host-parent/')) {
                            $browsePath = $logPath;
                        } elseif (str_starts_with($logPath, '/')) {
                            $realLogPath = @realpath($logPath);
                            if ($parentRoot && $realLogPath && str_starts_with($realLogPath, rtrim($parentRoot, '/') . '/')) {
                                $browsePath = 'host-parent/' . ltrim(substr($realLogPath, strlen(rtrim($parentRoot, '/'))), '/');
                            }
                        }
                        $browseHref = $browsePath !== null
                            ? '/cms/source?file=' . rawurlencode($browsePath)
                            : '/cms/source';
                        ?>
                        <a class="platform-btn platform-btn-secondary" href="<?= $browseHref ?>" target="_blank" rel="noopener">Browse</a>
                        <a class="platform-btn platform-btn-secondary" href="/cms/settings/logs/view?idx=<?= (int) $i ?>" target="_blank" rel="noopener">View</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="margin-bottom:0">No linked logs configured yet.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<dialog id="linked-logs-dialog" style="width:min(920px,96vw);border:1px solid #d1d5db;border-radius:10px;padding:0;box-shadow:0 25px 70px rgba(0,0,0,.35)">
    <form method="post" action="/cms/settings/logs" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= e(
            \Cruinn\CSRF::getToken()
        ) ?>">

        <div style="padding:1rem 1rem .75rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;gap:.5rem">
            <h3 style="margin:0;font-size:1rem">Linked Error Logs</h3>
            <button type="button" id="close-linked-logs-dialog" class="platform-btn platform-btn-secondary">Close</button>
        </div>

        <div style="padding:1rem;max-height:min(66vh,560px);overflow:auto">
            <p class="text-muted" style="margin-top:0">Use absolute file paths. Example: <code>/home/username/error_log</code></p>

            <div id="linked-logs-rows" style="display:grid;gap:.6rem">
                <?php if (!empty($linkedLogs)): ?>
                    <?php foreach ($linkedLogs as $log): ?>
                    <?php
                    $rowPath = trim((string) ($log['path'] ?? ''));
                    $rowBrowsePath = null;
                    if (str_starts_with($rowPath, 'host-parent/')) {
                        $rowBrowsePath = $rowPath;
                    } elseif (str_starts_with($rowPath, '/')) {
                        $rowReal = @realpath($rowPath);
                        if ($parentRoot && $rowReal && str_starts_with($rowReal, rtrim($parentRoot, '/') . '/')) {
                            $rowBrowsePath = 'host-parent/' . ltrim(substr($rowReal, strlen(rtrim($parentRoot, '/'))), '/');
                        }
                    }
                    $rowBrowseHref = $rowBrowsePath !== null
                        ? '/cms/source?file=' . rawurlencode($rowBrowsePath)
                        : '/cms/source';
                    ?>
                    <div class="linked-log-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:.5rem;align-items:center">
                        <input type="text" name="log_label[]" value="<?= e($log['label'] ?? '') ?>" placeholder="Label (e.g. PHP Error Log)">
                        <input type="text" name="log_path[]" value="<?= e($log['path'] ?? '') ?>" placeholder="/absolute/path/to/log/file.log">
                        <div style="display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap">
                            <a
                                href="<?= $rowBrowseHref ?>"
                                target="_blank"
                                rel="noopener"
                                class="platform-btn platform-btn-secondary"
                                data-browse-log-row
                            >Browse</a>
                            <button type="button" class="platform-btn platform-btn-secondary" data-remove-log-row>Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="linked-log-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:.5rem;align-items:center">
                        <input type="text" name="log_label[]" value="PHP Error Log" placeholder="Label (e.g. PHP Error Log)">
                        <input type="text" name="log_path[]" value="" placeholder="/absolute/path/to/log/file.log">
                        <div style="display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap">
                            <a href="/cms/source" target="_blank" rel="noopener" class="platform-btn platform-btn-secondary" data-browse-log-row>Browse</a>
                            <button type="button" class="platform-btn platform-btn-secondary" data-remove-log-row>Remove</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top:.75rem">
                <button type="button" class="platform-btn platform-btn-secondary" id="add-linked-log-row">Add Log Link</button>
            </div>
        </div>

        <div style="padding:.85rem 1rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.5rem">
            <button type="button" class="platform-btn platform-btn-secondary" id="cancel-linked-logs-dialog">Cancel</button>
            <button type="submit" class="platform-btn platform-btn-primary">Save Linked Logs</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var parentRoot = <?= json_encode((string) ($parentRoot ?: ''), JSON_UNESCAPED_SLASHES) ?>;
    var dialog = document.getElementById('linked-logs-dialog');
    var openBtn = document.getElementById('open-linked-logs-dialog');
    var closeBtn = document.getElementById('close-linked-logs-dialog');
    var cancelBtn = document.getElementById('cancel-linked-logs-dialog');
    var addBtn = document.getElementById('add-linked-log-row');
    var rows = document.getElementById('linked-logs-rows');

    if (!dialog || !openBtn || !rows) {
        return;
    }

    function toHostParentPath(path) {
        var value = String(path || '').trim();
        if (!value) { return null; }
        if (value.indexOf('host-parent/') === 0) {
            return value;
        }
        if (value.charAt(0) !== '/' || !parentRoot) {
            return null;
        }

        var root = String(parentRoot || '').replace(/\/+$/, '');
        if (!root) { return null; }
        if (value.indexOf(root + '/') !== 0) {
            return null;
        }

        return 'host-parent/' + value.slice(root.length + 1);
    }

    function refreshBrowseLink(row) {
        if (!row) { return; }
        var pathInput = row.querySelector('input[name="log_path[]"]');
        var browseLink = row.querySelector('[data-browse-log-row]');
        if (!pathInput || !browseLink) { return; }

        var browsePath = toHostParentPath(pathInput.value);
        if (browsePath) {
            browseLink.setAttribute('href', '/cms/source?file=' + encodeURIComponent(browsePath));
        } else {
            browseLink.setAttribute('href', '/cms/source');
        }
    }

    function bindRowRemoval(root) {
        root.querySelectorAll('[data-remove-log-row]').forEach(function (btn) {
            btn.onclick = function () {
                var row = btn.closest('.linked-log-row');
                if (!row) { return; }
                row.remove();
            };
        });
    }

    function bindRowBrowse(root) {
        root.querySelectorAll('.linked-log-row').forEach(function (row) {
            var pathInput = row.querySelector('input[name="log_path[]"]');
            if (pathInput) {
                pathInput.oninput = function () {
                    refreshBrowseLink(row);
                };
            }
            refreshBrowseLink(row);
        });
    }

    function addRow() {
        var row = document.createElement('div');
        row.className = 'linked-log-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '1fr 2fr auto';
        row.style.gap = '.5rem';
        row.style.alignItems = 'center';
        row.innerHTML = ''
            + '<input type="text" name="log_label[]" value="" placeholder="Label (e.g. PHP Error Log)">'
            + '<input type="text" name="log_path[]" value="" placeholder="/absolute/path/to/log/file.log">'
            + '<div style="display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap">'
            + '<a href="/cms/source" target="_blank" rel="noopener" class="platform-btn platform-btn-secondary" data-browse-log-row>Browse</a>'
            + '<button type="button" class="platform-btn platform-btn-secondary" data-remove-log-row>Remove</button>'
            + '</div>';
        rows.appendChild(row);
        bindRowRemoval(row);
        bindRowBrowse(row);
    }

    openBtn.addEventListener('click', function () {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    });

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    if (closeBtn) { closeBtn.addEventListener('click', closeDialog); }
    if (cancelBtn) { cancelBtn.addEventListener('click', closeDialog); }
    if (addBtn) { addBtn.addEventListener('click', addRow); }

    bindRowRemoval(rows);
    bindRowBrowse(rows);
}());
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
