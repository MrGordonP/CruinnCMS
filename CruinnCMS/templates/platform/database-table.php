<?php
/**
 * Platform DB Editor — Table Row View
 * Variables: $table, $columns, $columnMeta, $rows, $total, $page, $perPage,
 *            $pages, $pkCol, $sortCol, $sortDir, $dbName, $instanceFolder,
 *            $instanceLabel, $error, $username
 */
$columnMeta = $columnMeta ?? [];
$sortCol    = $sortCol    ?? '';
$sortDir    = $sortDir    ?? 'ASC';
$instParam  = $instanceFolder ? '&instance=' . urlencode($instanceFolder) : '';
$baseUrl    = '/cms/database/browse/' . urlencode($table);

function db_sort_url(string $base, string $col, string $curSort, string $curDir, int $page, string $inst): string {
    $dir = ($curSort === $col && $curDir === 'ASC') ? 'DESC' : 'ASC';
    return $base . '?page=' . $page . '&sort=' . urlencode($col) . '&dir=' . $dir . $inst;
}
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
        <?php if ($sortCol !== ''): ?>
        <span style="margin-left:1rem;">Sorted by <code><?= e($sortCol) ?></code> <?= e($sortDir) ?>
            — <a href="<?= $baseUrl . '?page=' . $page . $instParam ?>">clear sort</a>
        </span>
        <?php endif; ?>
    </p>

    <?php if ($error): ?>
        <div class="platform-alert platform-alert-error"><?= e($error) ?></div>
    <?php elseif (empty($rows)): ?>
        <p style="color:var(--text-muted);">No rows found.</p>
    <?php else: ?>

    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:0.5rem;">
        <?= number_format($total) ?> rows total
        — showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
        <span style="margin-left:1rem; color:var(--text-muted); font-size:0.82rem;">Click a column header to sort. Click <strong>Edit</strong> to edit inline.</span>
    </p>

    <section class="platform-section">
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table id="db-table" style="width:max-content; min-width:100%; border-collapse:collapse; font-size:0.82rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <?php foreach ($columns as $col): ?>
                        <th style="text-align:left; padding:0.4rem 0.6rem; white-space:nowrap; user-select:none;">
                            <a href="<?= db_sort_url($baseUrl, $col, $sortCol, $sortDir, $page, $instParam) ?>"
                               style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:0.3rem;">
                                <?= e($col) ?>
                                <?php if ($sortCol === $col): ?>
                                    <span style="font-size:0.75rem; opacity:0.7;"><?= $sortDir === 'ASC' ? '▲' : '▼' ?></span>
                                <?php else: ?>
                                    <span style="font-size:0.75rem; opacity:0.25;">⇅</span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php endforeach; ?>
                        <?php if ($pkCol): ?><th style="padding:0.4rem 0.6rem; min-width:120px;"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $rowIdx => $row): ?>
                    <?php $rowPk = $pkCol ? ($row[$pkCol] ?? null) : null; ?>
                    <?php $rowId = 'dbrow-' . $rowIdx; ?>

                    <!-- Display row -->
                    <tr id="<?= $rowId ?>-display" class="db-display-row" style="border-bottom:1px solid var(--border);">
                        <?php foreach ($columns as $col): ?>
                        <td style="padding:0.3rem 0.6rem; white-space:nowrap;"
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
                        <?php if ($pkCol && $rowPk !== null): ?>
                        <td style="padding:0.3rem 0.6rem; white-space:nowrap;">
                            <button type="button" class="platform-btn platform-btn-secondary db-edit-btn"
                                    data-rowid="<?= $rowId ?>"
                                    style="padding:0.15rem 0.5rem; font-size:0.78rem;">Edit</button>
                            <form method="post" action="<?= $baseUrl ?>/delete" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                                <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                                <input type="hidden" name="_page" value="<?= $page ?>">
                                <input type="hidden" name="_instance" value="<?= e($instanceFolder) ?>">
                                <button type="submit" class="platform-btn platform-btn-danger db-delete-btn"
                                        style="padding:0.15rem 0.5rem; font-size:0.78rem;">Delete</button>
                            </form>
                        </td>
                        <?php elseif ($pkCol): ?>
                        <td></td>
                        <?php endif; ?>
                    </tr>

                    <!-- Inline edit row (hidden until Edit clicked) -->
                    <?php if ($pkCol && $rowPk !== null): ?>
                    <tr id="<?= $rowId ?>-edit" class="db-edit-row" style="display:none; background:var(--bg-subtle,#f8fafc); border-bottom:2px solid var(--accent,#3b82f6);">
                        <td colspan="<?= count($columns) + 1 ?>" style="padding:0.6rem 0.75rem;">
                            <form method="post" action="<?= $baseUrl ?>/edit"
                                  style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:0.5rem 0.75rem; align-items:end;">
                                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                                <input type="hidden" name="_pk" value="<?= e((string)$rowPk) ?>">
                                <input type="hidden" name="_page" value="<?= $page ?>">
                                <input type="hidden" name="_instance" value="<?= e($instanceFolder) ?>">
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
                                    <button type="submit" class="platform-btn platform-btn-primary"
                                            style="padding:0.25rem 0.75rem; font-size:0.82rem;">Save</button>
                                    <button type="button" class="platform-btn platform-btn-secondary db-cancel-btn"
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
    </section>

    <?php if ($pages > 1): ?>
    <div style="margin-top:1rem; display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
        <?php $sortParam = $sortCol !== '' ? '&sort=' . urlencode($sortCol) . '&dir=' . urlencode($sortDir) : ''; ?>
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= $instParam . $sortParam ?>" class="platform-btn platform-btn-secondary">← Prev</a>
        <?php endif; ?>
        <span style="font-size:0.9rem; color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $instParam . $sortParam ?>" class="platform-btn platform-btn-secondary">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script src="/js/platform/database-table.js"></script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
