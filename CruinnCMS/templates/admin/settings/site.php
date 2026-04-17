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
                <button type="button" class="btn btn-small" onclick="Cruinn.openMediaBrowser(function(url){ document.getElementById('site_logo').value=url; var p=document.getElementById('site_logo_preview'); p.src=url; p.style.display='block'; })">Browse…</button>
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
                <button type="button" class="btn btn-small" onclick="Cruinn.openMediaBrowser(function(url){ document.getElementById('site_banner').value=url; var p=document.getElementById('site_banner_preview'); p.src=url; p.style.display='block'; })">Browse…</button>
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
