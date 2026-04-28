<?php
include __DIR__ . '/_tabs.php';
\Cruinn\Template::requireCss('admin-acp.css');

// Config values may be arrays or integers — normalise for form display
$maxSize = $settings['uploads.max_size'] ?? '10';
if (is_numeric($maxSize) && $maxSize > 1024) {
    $maxSize = (string) round($maxSize / 1024 / 1024);
}
$allowedExt = $settings['uploads.allowed'] ?? '';
if (is_array($allowedExt)) {
    $allowedExt = implode(',', $allowedExt);
}
$imageTypes = $settings['uploads.image_types'] ?? '';
if (is_array($imageTypes)) {
    $imageTypes = implode(',', $imageTypes);
}
?>

<h2>Security</h2>

<form method="post" action="<?= url('/admin/settings/security') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>File Uploads</legend>

        <div class="form-group">
            <label for="upload_max_size">Max Upload Size (MB)</label>
            <input type="number" id="upload_max_size" name="upload_max_size" class="form-input"
                   value="<?= e($maxSize) ?>"
                   min="1" max="1024" style="max-width: 120px;">
            <small class="form-help">PHP <code>upload_max_filesize</code> is currently <strong><?= ini_get('upload_max_filesize') ?></strong>. The effective limit is whichever is lower.</small>
        </div>

        <div class="form-group">
            <label for="upload_allowed_extensions">Allowed File Extensions</label>
            <input type="text" id="upload_allowed_extensions" name="upload_allowed_extensions" class="form-input"
                   value="<?= e($allowedExt) ?>"
                   placeholder="jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip">
            <small class="form-help">Comma-separated list of permitted file extensions. Leave empty for defaults.</small>
        </div>

        <div class="form-group">
            <label for="upload_image_types">Image MIME Types</label>
            <input type="text" id="upload_image_types" name="upload_image_types" class="form-input"
                   value="<?= e($imageTypes) ?>"
                   placeholder="image/jpeg,image/png,image/gif,image/webp">
            <small class="form-help">Comma-separated list of image MIME types accepted for image uploads.</small>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Security Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
