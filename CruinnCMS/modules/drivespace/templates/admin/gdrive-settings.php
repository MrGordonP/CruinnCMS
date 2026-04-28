<?php
/**
 * Drivespace — Google Drive Settings
 */
\Cruinn\Template::requireCss('admin-drivespace.css');
?>
<div class="admin-section">
    <div class="admin-section-header">
        <h1>Drivespace — Google Drive</h1>
        <a href="/admin/drivespace" class="btn btn-outline btn-sm">← Quota</a>
    </div>

    <?php if ($configured): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem">
        ✅ Google Drive service account is connected.
        <button type="button" class="btn btn-sm btn-outline" id="gdrive-test-btn" style="margin-left:1rem">Test connection</button>
        <span id="gdrive-test-result" style="margin-left:0.75rem;font-size:0.875rem"></span>
    </div>
    <?php else: ?>
    <div class="alert alert-warn" style="margin-bottom:1.5rem">
        ⚠️ No service account configured. Upload a Google service account JSON key to enable Google Drive browsing.
    </div>
    <?php endif; ?>

    <div class="admin-card" style="max-width:640px">
        <h2 style="margin-bottom:1rem;font-size:1.05rem">Service Account Configuration</h2>
        <p style="font-size:0.875rem;color:#555;margin-bottom:1.25rem">
            Create a <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener">Google Cloud service account</a>
            with <strong>Drive API (read-only)</strong> scope, download the JSON key, and share the relevant Drive folder with the service account email.
        </p>

        <form method="POST" action="/admin/drivespace/gdrive" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-group">
                <label>Service Account JSON Key <?= $hasJson ? '<span style="color:#34a853">(uploaded)</span>' : '' ?></label>
                <input type="file" name="service_account" accept=".json,application/json" class="form-input">
                <?php if ($hasJson): ?>
                    <small class="form-help">Leave blank to keep the existing key.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Root Folder ID <small style="font-weight:400;color:#aaa">(optional)</small></label>
                <input type="text" name="root_folder_id" value="<?= e($rootFolderId) ?>"
                       class="form-input" placeholder="e.g. 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs">
                <small class="form-help">
                    The ID from a shared folder URL: <code>drive.google.com/drive/folders/<strong>THIS_PART</strong></code>.
                    Pin the browser to a single folder. Leave blank to show <em>all</em> folders shared with the service account as the top level.
                </small>
            </div>

            <div class="form-group">
                <label>Shared Drive ID <small style="font-weight:400;color:#aaa">(optional)</small></label>
                <input type="text" name="shared_drive_id" value="<?= e($sharedDriveId ?? '') ?>"
                       class="form-input" placeholder="e.g. 0AKxyz123ABC">
                <small class="form-help">
                    For Google Workspace Shared Drives (Team Drives). The ID appears in the URL when you open the Shared Drive:
                    <code>drive.google.com/drive/u/0/folders/<strong>THIS_PART</strong></code>.
                    Add the service account email as a member of the Shared Drive with at least Viewer access.
                    Leave blank to use a regular shared folder via Root Folder ID above.
                </small>
            </div>

            <div class="form-group">
                <label>Write Access — Minimum Role</label>
                <select name="write_role" class="form-input" style="width:auto">
                    <?php foreach (['public' => 'Public', 'member' => 'Member', 'editor' => 'Editor', 'council' => 'Council', 'admin' => 'Admin'] as $slug => $label): ?>
                    <option value="<?= $slug ?>"<?= ($writeRole ?? 'council') === $slug ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">Users at this role or above can upload, import, and push files.</small>
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>

        <?php if ($hasJson): ?>
        <form method="POST" action="/admin/drivespace/gdrive/clear"
              onsubmit="return confirm('Remove the service account credentials? Google Drive browsing will be disabled.')"
              style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #eee">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
            <button type="submit" class="btn btn-danger btn-sm">Remove credentials</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('gdrive-test-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var result = document.getElementById('gdrive-test-result');
        btn.disabled = true;
        result.textContent = 'Testing…';
        result.style.color = '';
        var fd = new FormData();
        fd.append('csrf_token', <?= json_encode(\Cruinn\CSRF::getToken()) ?>);
        fetch('/admin/drivespace/gdrive/test', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.textContent = data.success ? '✅ ' + data.message : '❌ ' + data.error;
                result.style.color = data.success ? '#34a853' : '#d93025';
                btn.disabled = false;
            })
            .catch(function () {
                result.textContent = '❌ Request failed.';
                result.style.color = '#d93025';
                btn.disabled = false;
            });
    });
})();
</script>
