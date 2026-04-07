<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Query Runner</h2>

<p style="margin-bottom:1rem;">
    <a href="<?= url('/admin/settings/database') ?>" class="btn btn-secondary">← Back to Database</a>
</p>

<form method="post" action="<?= url('/admin/settings/database/query') ?>">
    <?= csrf_field() ?>
    <div style="margin-bottom:0.75rem;">
        <textarea name="sql" rows="6" style="width:100%; font-family:monospace; font-size:0.9rem; padding:0.5rem; border:1px solid var(--border); border-radius:4px; resize:vertical; background:var(--surface); color:var(--text);"><?= e($sql) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Run Query</button>
</form>

<?php if ($error !== null): ?>
    <div class="alert alert-danger" style="margin-top:1rem;">
        <strong>Error:</strong> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($affected !== null && $error === null): ?>
    <div class="alert alert-success" style="margin-top:1rem;">
        <?= number_format($affected) ?> row(s) affected.
    </div>
<?php endif; ?>

<?php if ($results !== null && $error === null): ?>
    <?php if (empty($results)): ?>
        <p style="margin-top:1rem; color:var(--text-muted);">Query returned no rows.</p>
    <?php else: ?>
        <p style="margin-top:1rem; font-size:0.88rem; color:var(--text-muted);"><?= number_format(count($results)) ?> row(s) returned</p>
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; margin-top:0.5rem;">
            <table class="admin-table" style="font-size:0.82rem; white-space:nowrap; width:max-content; min-width:100%;">
                <thead>
                    <tr>
                        <?php foreach (array_keys($results[0]) as $col): ?>
                            <th><?= e($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td title="<?= e((string)($val ?? '')) ?>">
                                    <?php
                                        if ($val === null) {
                                            echo '<span style="color:var(--text-muted);font-style:italic;">NULL</span>';
                                        } else {
                                            $str = (string)$val;
                                            echo e(mb_strlen($str) > 80 ? mb_substr($str, 0, 80) . '…' : $str);
                                        }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/_tabs_end.php'; ?>
