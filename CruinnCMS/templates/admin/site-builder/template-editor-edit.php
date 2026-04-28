<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<div class="sb-full-width">
    <div class="admin-list-header" style="margin-bottom: var(--space-md)">
        <div style="display:flex; align-items:center; gap: var(--space-sm)">
            <a href="<?= url($returnUrl ?? '/admin/template-editor') ?>" class="btn btn-outline btn-small">&larr; <?= isset($returnUrl) ? 'Back to Editor' : 'All Templates' ?></a>
            <span style="color: var(--color-text-muted); font-size: 0.875rem"><code><?= e($rel) ?></code></span>
        </div>
    </div>

    <form id="tpl-edit-form" method="post" action="<?= url('/admin/template-editor/edit?f=' . rawurlencode($rel) . (isset($returnUrl) ? '&return=' . rawurlencode($returnUrl) : '')) ?>" style="display:flex; flex-direction:column">
        <?= csrf_field() ?>
        <textarea
            name="content"
            id="tpl-editor-textarea"
            data-tab-insert="true"
            spellcheck="false"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            style="
                display:block;
                width:100%;
                min-height:70vh;
                font-family: 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
                font-size: 0.85rem;
                line-height: 1.55;
                padding: var(--space-md);
                background: #0d1117;
                color: #e6edf3;
                border: 1px solid var(--color-border);
                border-radius: var(--radius-md);
                resize: vertical;
                tab-size: 4;
                box-sizing: border-box;
            "><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="form-actions" style="margin-top: var(--space-md)">
            <button type="submit" class="btn btn-primary">Save Template</button>
            <a href="<?= url($returnUrl ?? '/admin/template-editor') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php \Cruinn\Template::requireJs('tab-insert.js'); ?>

<?php include __DIR__ . '/_tabs_close.php'; ?>
