<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Edit row — <code><?= e($table) ?></code></h2>

<p style="margin-bottom:1rem;">
    <a href="<?= url('/admin/settings/database/browse/' . urlencode($table)) ?>?page=<?= $page ?>"
       class="btn btn-secondary">← Back to <?= e($table) ?></a>
</p>

<form method="post" action="<?= url('/admin/settings/database/browse/' . urlencode($table) . '/edit') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_pk" value="<?= e((string)$pkVal) ?>">
    <input type="hidden" name="_page" value="<?= $page ?>">

    <fieldset class="acp-fieldset">
        <legend>PK: <code><?= e($pkCol) ?></code> = <code><?= e((string)$pkVal) ?></code></legend>

        <?php foreach ($row as $col => $val): ?>
            <div class="acp-field" style="margin-bottom:0.75rem;">
                <label for="field_<?= e($col) ?>" style="display:block; font-weight:600; margin-bottom:0.25rem; font-size:0.88rem;">
                    <?= e($col) ?>
                    <?php if ($col === $pkCol): ?>
                        <span style="font-weight:normal; color:var(--text-muted); font-size:0.8rem;">(primary key — read only)</span>
                    <?php endif; ?>
                </label>
                <?php if ($col === $pkCol): ?>
                    <input type="text" id="field_<?= e($col) ?>" value="<?= e((string)$val) ?>"
                           disabled style="width:100%; font-family:monospace; font-size:0.88rem; opacity:0.6;">
                <?php else: ?>
                    <?php
                        $strVal = $val === null ? '' : (string)$val;
                        $isLong = mb_strlen($strVal) > 100 || str_contains($strVal, "\n");
                    ?>
                    <?php if ($isLong): ?>
                        <textarea id="field_<?= e($col) ?>" name="<?= e($col) ?>" rows="5"
                                  style="width:100%; font-family:monospace; font-size:0.85rem; padding:0.4rem; resize:vertical; border:1px solid var(--border); border-radius:3px; background:var(--surface); color:var(--text);"><?= e($strVal) ?></textarea>
                    <?php else: ?>
                        <input type="text" id="field_<?= e($col) ?>" name="<?= e($col) ?>"
                               value="<?= e($strVal) ?>"
                               style="width:100%; font-family:monospace; font-size:0.88rem; padding:0.35rem 0.5rem; border:1px solid var(--border); border-radius:3px; background:var(--surface); color:var(--text);">
                    <?php endif; ?>
                    <?php if ($val === null): ?>
                        <span style="font-size:0.78rem; color:var(--text-muted);">(was NULL — save blank to store empty string)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <div style="margin-top:1.25rem; display:flex; gap:0.75rem;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= url('/admin/settings/database/browse/' . urlencode($table)) ?>?page=<?= $page ?>"
           class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
