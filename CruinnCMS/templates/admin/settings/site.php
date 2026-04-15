<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Site Settings</h2>
<form method="post" action="<?= url('/admin/settings/site') ?>">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="site_name">Site Name</label>
        <input type="text" id="site_name" name="site_name" class="form-input"
               value="<?= e($settings['site.name'] ?? '') ?>"
               placeholder="My Organisation">
    </div>

    <div class="form-group">
        <label for="site_tagline">Tagline</label>
        <input type="text" id="site_tagline" name="site_tagline" class="form-input"
               value="<?= e($settings['site.tagline'] ?? '') ?>"
               placeholder="Your organisation's tagline">
    </div>

    <div class="form-group">
        <label for="site_url">Site URL</label>
        <input type="url" id="site_url" name="site_url" class="form-input"
               value="<?= e($settings['site.url'] ?? '') ?>"
               placeholder="https://example.com">
        <small class="form-help">The canonical public URL (no trailing slash).</small>
    </div>

    <div class="form-group">
        <label for="site_timezone">Timezone</label>
        <select id="site_timezone" name="site_timezone" class="form-input">
            <option value="">— Use default —</option>
            <?php foreach ($timezones as $tz): ?>
            <option value="<?= e($tz) ?>"<?= ($settings['site.timezone'] ?? '') === $tz ? ' selected' : '' ?>><?= e($tz) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="site_logo">Logo Image</label>
            <div class="media-input-row">
                <input type="text" id="site_logo" name="site_logo" class="form-input"
                       value="<?= e($settings['site.logo'] ?? '') ?>"
                       placeholder="/storage/…/logo.png">
                <button type="button" class="btn btn-small" onclick="openSettingsMedia('site_logo')">Browse…</button>
            </div>
            <?php if (!empty($settings['site.logo'])): ?>
            <img src="<?= e($settings['site.logo']) ?>" alt="Logo preview" id="site_logo_preview" style="max-height:60px;margin-top:6px;display:block;">
            <?php else: ?>
            <img src="" alt="" id="site_logo_preview" style="max-height:60px;margin-top:6px;display:none;">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="site_banner">Banner Image</label>
            <div class="media-input-row">
                <input type="text" id="site_banner" name="site_banner" class="form-input"
                       value="<?= e($settings['site.banner'] ?? '') ?>"
                       placeholder="/storage/…/banner.jpg">
                <button type="button" class="btn btn-small" onclick="openSettingsMedia('site_banner')">Browse…</button>
            </div>
            <?php if (!empty($settings['site.banner'])): ?>
            <img src="<?= e($settings['site.banner']) ?>" alt="Banner preview" id="site_banner_preview" style="max-height:60px;margin-top:6px;display:block;">
            <?php else: ?>
            <img src="" alt="" id="site_banner_preview" style="max-height:60px;margin-top:6px;display:none;">
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label for="footer_text">Footer Text</label>
        <input type="text" id="footer_text" name="footer_text" class="form-input"
               value="<?= e($settings['footer_text'] ?? '') ?>"
               placeholder="© My Organisation. All rights reserved.">
    </div>

    <div class="form-group">
        <label class="form-checkbox">
            <input type="hidden" name="registration_open" value="0">
            <input type="checkbox" name="registration_open" value="1"
                   <?= ($settings['registration_open'] ?? '0') === '1' ? 'checked' : '' ?>>
            Allow public user registration
        </label>
    </div>

    <div class="form-group">
        <label class="form-checkbox">
            <input type="hidden" name="maintenance_mode" value="0">
            <input type="checkbox" name="maintenance_mode" value="1"
                   <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
            Enable maintenance mode
        </label>
        <small class="form-help">When enabled, only administrators can access the site. All other visitors see a maintenance page.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>

<!-- ── Settings Media Picker ────────────────────────────────────────── -->
<div id="settings-media-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:720px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid #dee2e6;">
            <strong>Choose Image</strong>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <label class="btn btn-small" style="cursor:pointer;">
                    Upload new…
                    <input type="file" id="settings-media-upload" accept="image/*" style="display:none">
                </label>
                <button type="button" class="btn btn-small btn-primary" id="settings-media-select-btn" disabled>Select</button>
                <button type="button" class="btn btn-small" onclick="closeSettingsMedia()">Cancel</button>
            </div>
        </div>
        <div id="settings-media-grid" style="display:flex;flex-wrap:wrap;gap:.5rem;padding:1rem;overflow-y:auto;flex:1;"></div>
    </div>
</div>

<script>
(function () {
    var modal       = document.getElementById('settings-media-modal');
    var grid        = document.getElementById('settings-media-grid');
    var selectBtn   = document.getElementById('settings-media-select-btn');
    var uploadInput = document.getElementById('settings-media-upload');
    var targetField = null;
    var selectedUrl = null;

    window.openSettingsMedia = function (fieldId) {
        targetField = fieldId;
        selectedUrl = null;
        selectBtn.disabled = true;
        modal.style.display = 'flex';
        loadGrid();
    };

    window.closeSettingsMedia = function () {
        modal.style.display = 'none';
        targetField = null;
        selectedUrl = null;
    };

    function loadGrid() {
        grid.innerHTML = '<p style="padding:.5rem;color:#666">Loading…</p>';
        fetch('/admin/media', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var files = data.files || data || [];
                grid.innerHTML = '';
                if (!files.length) {
                    grid.innerHTML = '<p style="padding:.5rem;color:#666">No media uploaded yet.</p>';
                    return;
                }
                files.forEach(function (file) {
                    var url  = file.url || file;
                    var name = url.split('/').pop();
                    var item = document.createElement('div');
                    item.style.cssText = 'width:120px;cursor:pointer;border:2px solid transparent;border-radius:4px;overflow:hidden;text-align:center;padding:4px;';
                    item.innerHTML = '<img src="' + url + '" alt="' + name + '" style="width:100%;height:80px;object-fit:cover;display:block;" loading="lazy">'
                        + '<div style="font-size:.7rem;color:#555;word-break:break-all;margin-top:2px;">' + name + '</div>';
                    item.addEventListener('click', function () {
                        grid.querySelectorAll('[data-selected]').forEach(function (i) {
                            i.style.borderColor = 'transparent';
                            delete i.dataset.selected;
                        });
                        item.style.borderColor = '#1d9e75';
                        item.dataset.selected = '1';
                        selectedUrl = url;
                        selectBtn.disabled = false;
                    });
                    item.addEventListener('dblclick', function () {
                        selectedUrl = url;
                        applySelection();
                    });
                    grid.appendChild(item);
                });
            })
            .catch(function () { grid.innerHTML = '<p style="padding:.5rem;color:#c00">Failed to load media.</p>'; });
    }

    function applySelection() {
        if (!selectedUrl || !targetField) { return; }
        var input   = document.getElementById(targetField);
        var preview = document.getElementById(targetField + '_preview');
        if (input)   { input.value = selectedUrl; }
        if (preview) { preview.src = selectedUrl; preview.style.display = 'block'; }
        closeSettingsMedia();
    }

    selectBtn.addEventListener('click', applySelection);

    uploadInput.addEventListener('change', function () {
        var file = uploadInput.files[0];
        if (!file) { return; }
        var fd = new FormData();
        fd.append('file', file);
        fd.append('csrf_token', document.querySelector('[name=csrf_token]').value);
        fetch('/admin/upload', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.url) { selectedUrl = data.url; loadGrid(); }
                else { alert(data.error || 'Upload failed.'); }
            })
            .catch(function () { alert('Upload failed.'); });
        uploadInput.value = '';
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { closeSettingsMedia(); }
    });
}());
</script>
