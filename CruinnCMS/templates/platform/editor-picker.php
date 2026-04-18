<?php
/**
 * Platform Editor
 *
 * Renders the Cruinn block editor directly inside the platform chrome.
 * When $editorReady is true, all editor template variables are in scope
 * (extracted by Template::renderPartial before this file is included).
 * Variables: $editorReady (bool), $editorError (?string)
 */
?>
<?php ob_start(); ?>
<?php if (!$editorReady): ?>

<div class="platform-editor-empty">
    <?php if ($editorError): ?>
    <div class="platform-editor-msg platform-editor-msg--error">
        <?= htmlspecialchars($editorError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php else: ?>
    <div class="platform-editor-msg">
        Select an instance and page from the sidebar to open the editor.
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<link rel="stylesheet" href="/css/editor.css?v=<?= file_exists(CRUINN_PUBLIC . '/css/editor.css') ? filemtime(CRUINN_PUBLIC . '/css/editor.css') : 0 ?>">
<?php include __DIR__ . '/../admin/editor.php'; ?>

<!-- Cruinn Media Browser -->
<div class="media-modal-overlay" id="media-modal" style="display:none">
    <div class="media-modal">
        <div class="media-modal-header">
            <h2>Media Library</h2>
            <span id="media-modal-path" class="media-modal-path"></span>
            <button type="button" class="media-modal-close" id="media-modal-close-btn" aria-label="Close">&times;</button>
        </div>
        <div class="media-modal-toolbar">
            <label class="btn btn-small btn-primary media-upload-btn">
                Upload <input type="file" id="media-modal-upload" accept="image/*" hidden>
            </label>
            <button type="button" class="btn btn-small btn-outline" id="media-modal-new-folder-btn">+ Folder</button>
            <button type="button" class="btn btn-small btn-danger" id="media-modal-delete-btn" style="display:none">Delete Folder</button>
            <input type="search" id="media-search" class="form-input" placeholder="Search all…" style="max-width:180px">
        </div>
        <div class="media-modal-grid" id="media-grid">
            <p class="media-loading">Loading…</p>
        </div>
        <div class="media-modal-footer">
            <button type="button" class="btn btn-primary" id="media-modal-select-btn">Upload</button>
            <button type="button" class="btn btn-outline" id="media-modal-cancel-btn">Cancel</button>
        </div>
    </div>
</div>
<?php
$_pickerJsBase = CRUINN_PUBLIC . '/js/admin/';
foreach (['utils.js', 'media-browser.js'] as $_pickerMod):
    $_pickerMtime = file_exists($_pickerJsBase . $_pickerMod) ? filemtime($_pickerJsBase . $_pickerMod) : 0;
?>
<script src="<?= url('/js/admin/' . $_pickerMod) ?>?v=<?= $_pickerMtime ?>"></script>
<?php endforeach; ?>

<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
