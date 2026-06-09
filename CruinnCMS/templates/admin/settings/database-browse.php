<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php \Cruinn\Template::requireJs('database-browse.js'); ?>
<?php
$columnMeta = $columnMeta ?? [];
$sortCol    = $sortCol    ?? '';
$sortDir    = $sortDir    ?? 'ASC';
$baseUrl    = url('/admin/settings/database/browse/' . urlencode($table));

function acp_sort_url(string $base, string $col, string $curSort, string $curDir, int $page): string {
    $dir = ($curSort === $col && $curDir === 'ASC') ? 'DESC' : 'ASC';
    return $base . '?page=' . $page . '&sort=' . urlencode($col) . '&dir=' . $dir;
}
?>

<h2>Browse: <code><?= e($table) ?></code></h2>

<p style="margin-bottom:1rem;">
    <a href="<?= url('/admin/settings/database') ?>" class="btn btn-secondary">← Back to Database</a>
    <a href="<?= url('/admin/settings/database/query') ?>" class="btn btn-secondary">Query Runner</a>
    <?php if ($sortCol !== ''): ?>
    <a href="<?= $baseUrl . '?page=' . $page ?>" class="btn btn-secondary">Clear Sort</a>
    <?php endif; ?>
</p>

<p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">
    <?= number_format($total) ?> rows total
    — showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
    <?php if ($pkCol): ?><span style="margin-left:1rem;">PK: <code><?= e($pkCol) ?></code></span><?php endif; ?>
    <?php if ($sortCol !== ''): ?><span style="margin-left:1rem;">Sorted by <code><?= e($sortCol) ?></code> <?= e($sortDir) ?></span><?php endif; ?>
    <span style="margin-left:1rem; font-size:0.82rem;">Click a column header to sort. Click <strong>Edit</strong> to edit inline.</span>
</p>

<?php if (!empty($rows)): ?>
<div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table class="admin-table" style="font-size:0.82rem; width:max-content; min-width:100%;">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                <th style="user-select:none;">
                    <a href="<?= acp_sort_url($baseUrl, $col, $sortCol, $sortDir, $page) ?>"
                       style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:0.3rem; white-space:nowrap;">
                        <?= e($col) ?>
                        <?php if ($sortCol === $col): ?>
                            <span style="font-size:0.75rem; opacity:0.7;"><?= $sortDir === 'ASC' ? '▲' : '▼' ?></span>
                        <?php else: ?>
                            <span style="font-size:0.75rem; opacity:0.25;">⇅</span>
                        <?php endif; ?>
                    </a>
                </th>
                <?php endforeach; ?>
                <?php if ($pkCol): ?><th style="min-width:120px;"></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $rowIdx => $row): ?>
            <?php $rowPk = $pkCol ? ($row[$pkCol] ?? null) : null; ?>
            <?php $rowId = 'acp-dbrow-' . $rowIdx; ?>

            <!-- Display row -->
            <tr id="<?= $rowId ?>-display" class="acp-db-display-row">
                <?php foreach ($columns as $col): ?>
                <td title="<?= e((string)($row[$col] ?? '')) ?>" style="white-space:nowrap;">
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
                <?php if ($pkCol && $rowPk !== null): ?>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn btn-secondary admin-db-edit-btn"
                            data-rowid="<?= $rowId ?>"
                            style="padding:0.15rem 0.5rem; font-size:0.78rem;">Edit</button>
                    <form method="post" action="<?= url('/admin/settings/database/browse/' . urlencode($table) . '/delete') ?>" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                        <input type="hidden" name="_page" value="<?= $page ?>">
                        <button type="submit" class="btn btn-danger admin-db-delete-btn" style="padding:0.15rem 0.5rem; font-size:0.78rem;">Delete</button>
                    </form>
                </td>
                <?php elseif ($pkCol): ?>
                <td></td>
                <?php endif; ?>
            </tr>

            <!-- Inline edit row -->
            <?php if ($pkCol && $rowPk !== null): ?>
            <tr id="<?= $rowId ?>-edit" class="acp-db-edit-row" style="display:none; background:var(--bg-subtle,#f8fafc);">
                <td colspan="<?= count($columns) + 1 ?>" style="padding:0.6rem 0.75rem;">
                    <form method="post" action="<?= url('/admin/settings/database/browse/' . urlencode($table) . '/edit') ?>"
                          style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:0.5rem 0.75rem; align-items:end;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                        <input type="hidden" name="_page" value="<?= $page ?>">
                        <?php if ($sortCol !== ''): ?>
                        <input type="hidden" name="_sort" value="<?= e($sortCol) ?>">
                        <input type="hidden" name="_dir" value="<?= e($sortDir) ?>">
                        <?php endif; ?>
                        <?php foreach ($columns as $col): ?>
                        <?php
                            $val    = $row[$col] ?? null;
                            $strVal = $val === null ? '' : (string)$val;
                            $meta   = $columnMeta[$col] ?? ['type' => 'varchar', 'nullable' => true, 'maxlen' => null];
                            $isPk   = ($col === $pkCol);
                            $isLong = in_array($meta['type'], ['text', 'mediumtext', 'longtext', 'json'], true)
                                      || mb_strlen($strVal) > 100
                                      || str_contains($strVal, "\n");
                        ?>
                        <div style="display:flex; flex-direction:column; gap:0.2rem;">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted);">
                                <?= e($col) ?>
                                <?php if ($isPk): ?><span style="font-weight:normal;">(PK)</span><?php endif; ?>
                                <?php if ($meta['nullable'] && !$isPk): ?><span style="font-weight:normal; opacity:0.6;">nullable</span><?php endif; ?>
                            </label>
                            <?php if ($isPk): ?>
                            <input type="text" value="<?= e($strVal) ?>" disabled
                                   style="font-family:monospace; font-size:0.82rem; padding:0.3rem 0.4rem; opacity:0.55; border:1px solid var(--border); border-radius:3px; background:#f3f4f6; width:100%;">
                            <?php elseif ($isLong): ?>
                            <textarea name="<?= e($col) ?>" rows="3"
                                      style="font-family:monospace; font-size:0.82rem; padding:0.3rem 0.4rem; border:1px solid var(--border); border-radius:3px; resize:vertical; width:100%;"><?= e($strVal) ?></textarea>
                            <?php else: ?>
                            <input type="text" name="<?= e($col) ?>" value="<?= e($strVal) ?>"
                                   style="font-family:monospace; font-size:0.82rem; padding:0.3rem 0.4rem; border:1px solid var(--border); border-radius:3px; width:100%;">
                            <?php endif; ?>
                            <?php if ($val === null): ?>
                            <small style="font-size:0.72rem; color:var(--text-muted);">was NULL</small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <div style="grid-column: 1 / -1; display:flex; gap:0.5rem; padding-top:0.4rem; border-top:1px solid var(--border);">
                            <button type="submit" class="btn btn-primary" style="padding:0.25rem 0.75rem; font-size:0.82rem;">Save</button>
                            <button type="button" class="btn btn-secondary admin-db-cancel-btn"
                                    data-rowid="<?= $rowId ?>"
                                    style="padding:0.25rem 0.75rem; font-size:0.82rem;">Cancel</button>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endif; ?>

            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
    <?php $sortParam = $sortCol !== '' ? '&sort=' . urlencode($sortCol) . '&dir=' . urlencode($sortDir) : ''; ?>
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $sortParam ?>" class="btn btn-secondary">← Prev</a>
    <?php endif; ?>
    <span style="font-size:0.9rem; color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?><?= $sortParam ?>" class="btn btn-secondary">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
    <p style="color:var(--text-muted);">No rows found.</p>
<?php endif; ?>

<?php include __DIR__ . '/_tabs_end.php'; ?>
