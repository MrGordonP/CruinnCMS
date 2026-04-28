<?php
/**
 * Platform DB Editor — Table Row View
 * Variables: $table, $columns, $rows, $total, $page, $perPage, $pages,
 *            $pkCol, $dbName, $instanceFolder, $instanceLabel, $error, $username
 */
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Browse: <code><?= e($table) ?></code></h1>
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <a href="/cms/database<?= $instanceFolder ? '?instance='.urlencode($instanceFolder) : '' ?>"
               class="platform-btn platform-btn-secondary">← Table List</a>
            <a href="/cms/database/query<?= $instanceFolder ? '?instance='.urlencode($instanceFolder) : '' ?>"
               class="platform-btn platform-btn-secondary">Query Runner</a>
        </div>
    </div>

    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">
        <?= e($instanceLabel !== '(active)' ? "Instance: {$instanceLabel} — " : '') ?>DB: <code><?= e($dbName) ?></code>
        <?php if ($pkCol): ?><span style="margin-left:1rem;">PK: <code><?= e($pkCol) ?></code></span><?php endif; ?>
    </p>

    <?php if ($error): ?>
        <div class="platform-alert platform-alert-error"><?= e($error) ?></div>
    <?php elseif (empty($rows)): ?>
        <p style="color:var(--text-muted);">No rows found.</p>
    <?php else: ?>

    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:0.5rem;">
        <?= number_format($total) ?> rows total
        — showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
    </p>

    <section class="platform-section">
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:max-content; min-width:100%; border-collapse:collapse; font-size:0.82rem; white-space:nowrap;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <?php foreach ($columns as $col): ?>
                            <th style="text-align:left; padding:0.4rem 0.6rem;"><?= e($col) ?></th>
                        <?php endforeach; ?>
                        <?php if ($pkCol): ?><th style="padding:0.4rem 0.6rem;"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <?php foreach ($columns as $col): ?>
                                <td style="padding:0.3rem 0.6rem;"
                                    title="<?= e((string)($row[$col] ?? '')) ?>">
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
                                <td style="padding:0.3rem 0.6rem; white-space:nowrap;">
                                    <?php if ($rowPk !== null): ?>
                                    <?php
                                        $instParam = $instanceFolder ? '&instance=' . urlencode($instanceFolder) : '';
                                        $editUrl = '/cms/database/browse/' . urlencode($table) . '/edit?pk=' . urlencode((string)$rowPk) . '&page=' . $page . $instParam;
                                    ?>
                                    <a href="<?= $editUrl ?>" class="platform-btn platform-btn-secondary"
                                       style="padding:0.15rem 0.5rem; font-size:0.78rem;">Edit</a>
                                    <form method="post" action="/cms/database/browse/<?= urlencode($table) ?>/delete" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                                        <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                                        <input type="hidden" name="_page" value="<?= $page ?>">
                                        <input type="hidden" name="_instance" value="<?= e($instanceFolder) ?>">
                                        <button type="submit" class="platform-btn platform-btn-danger db-delete-btn"
                                                style="padding:0.15rem 0.5rem; font-size:0.78rem;">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($pages > 1): ?>
    <div style="margin-top:1rem; display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
        <?php
            $instParam = $instanceFolder ? '&instance=' . urlencode($instanceFolder) : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= $instParam ?>" class="platform-btn platform-btn-secondary">← Prev</a>
        <?php endif; ?>
        <span style="font-size:0.9rem; color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $instParam ?>" class="platform-btn platform-btn-secondary">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script src="/js/platform/database-table.js"></script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
