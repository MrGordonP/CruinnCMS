<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php \Cruinn\Template::requireJs('database-browse.js'); ?>

<h2>Browse: <code><?= e($table) ?></code></h2>

<p style="margin-bottom:1rem;">
    <a href="<?= url('/admin/settings/database') ?>" class="btn btn-secondary">← Back to Database</a>
    <a href="<?= url('/admin/settings/database/query') ?>" class="btn btn-secondary">Query Runner</a>
</p>

<p style="color:var(--text-muted); font-size:0.9rem;">
    <?= number_format($total) ?> rows total
    — showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
    <?php if ($pkCol): ?><span style="margin-left:1rem;">PK: <code><?= e($pkCol) ?></code></span><?php endif; ?>
</p>

<?php if (!empty($rows)): ?>
<div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table class="admin-table" style="font-size:0.82rem; white-space:nowrap; width:max-content; min-width:100%;">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?= e($col) ?></th>
                <?php endforeach; ?>
                <?php if ($pkCol): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td title="<?= e((string)($row[$col] ?? '')) ?>">
                            <?php
                                $val = $row[$col] ?? null;
                                if ($val === null) {
                                    echo '<span style="color:var(--text-muted);font-style:italic;">NULL</span>';
                                } else {
                                    $str = (string)$val;
                                    echo e(mb_strlen($str) > 80 ? mb_substr($str, 0, 80) . '…' : $str);
                                }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if ($pkCol): ?>
                        <?php $rowPk = $row[$pkCol] ?? null; ?>
                        <td style="white-space:nowrap;">
                            <?php if ($rowPk !== null): ?>
                            <a href="<?= url('/admin/settings/database/browse/' . urlencode($table) . '/edit') ?>?pk=<?= urlencode((string)$rowPk) ?>&page=<?= $page ?>"
                               class="btn btn-secondary" style="padding:0.15rem 0.5rem; font-size:0.78rem;">Edit</a>
                            <form method="post" action="<?= url('/admin/settings/database/browse/' . urlencode($table) . '/delete') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                                <input type="hidden" name="_page" value="<?= $page ?>">
                                <button type="submit" class="btn btn-danger admin-db-delete-btn" style="padding:0.15rem 0.5rem; font-size:0.78rem;">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">← Prev</a>
    <?php endif; ?>
    <span style="font-size:0.9rem; color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
    <p style="color:var(--text-muted);">No rows found.</p>
<?php endif; ?>

<?php include __DIR__ . '/_tabs_end.php'; ?>
