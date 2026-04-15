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
<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
