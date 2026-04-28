<?php
/**
 * Platform DB Editor — Edit Row
 * Variables: $table, $pkCol, $pkVal, $row, $instanceFolder, $page, $username
 */
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Edit row — <code><?= e($table) ?></code></h1>
        <a href="/cms/database/browse/<?= urlencode($table) ?>?page=<?= $page ?><?= $instanceFolder ? '&instance='.urlencode($instanceFolder) : '' ?>"
           class="platform-btn platform-btn-secondary">← Back to <?= e($table) ?></a>
    </div>

    <section class="platform-section">
        <div class="platform-section-header">
            <p style="font-size:0.88rem; color:var(--text-muted);">
                PK: <code><?= e($pkCol) ?></code> = <code><?= e((string)$pkVal) ?></code>
                <?php if ($instanceFolder): ?> &mdash; Instance: <code><?= e($instanceFolder) ?></code><?php endif; ?>
            </p>
        </div>

        <form method="post" action="/cms/database/browse/<?= urlencode($table) ?>/edit">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
            <input type="hidden" name="_pk" value="<?= e((string)$pkVal) ?>">
            <input type="hidden" name="_page" value="<?= $page ?>">
            <input type="hidden" name="_instance" value="<?= e($instanceFolder) ?>">

            <?php foreach ($row as $col => $val): ?>
                <div class="platform-field" style="margin-bottom:0.75rem;">
                    <label for="field_<?= e($col) ?>" style="font-weight:600; font-size:0.88rem;">
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
                                      style="width:100%; font-family:monospace; font-size:0.85rem; padding:0.4rem; resize:vertical;
                                             border:1px solid var(--border,#ccc); border-radius:3px;"><?= e($strVal) ?></textarea>
                        <?php else: ?>
                            <input type="text" id="field_<?= e($col) ?>" name="<?= e($col) ?>"
                                   value="<?= e($strVal) ?>"
                                   style="width:100%; font-family:monospace; font-size:0.88rem; padding:0.35rem 0.5rem;
                                          border:1px solid var(--border,#ccc); border-radius:3px;">
                        <?php endif; ?>
                        <?php if ($val === null): ?>
                            <small style="color:var(--text-muted);">(was NULL — save blank to store empty string)</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div style="margin-top:1.25rem; display:flex; gap:0.75rem;">
                <button type="submit" class="platform-btn platform-btn-primary">Save Changes</button>
                <a href="/cms/database/browse/<?= urlencode($table) ?>?page=<?= $page ?><?= $instanceFolder ? '&instance='.urlencode($instanceFolder) : '' ?>"
                   class="platform-btn platform-btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
