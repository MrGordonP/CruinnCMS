<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php \Cruinn\Template::requireJs('database-browse.js'); ?>
<?php
$columnMeta = $columnMeta ?? [];
$sortCol    = $sortCol    ?? '';
$sortDir    = $sortDir    ?? 'ASC';
$filters    = $filters    ?? [];
$baseUrl    = url('/admin/settings/database/browse/' . urlencode($table));
$saveUrl    = url('/admin/settings/database/browse/' . urlencode($table) . '/edit');
$deleteUrl  = url('/admin/settings/database/browse/' . urlencode($table) . '/delete');

function acp_extra_params(string $sortCol, string $sortDir, array $filters): string {
    $p = '';
    if ($sortCol !== '') {
        $p .= '&sort=' . urlencode($sortCol) . '&dir=' . urlencode($sortDir);
    }
    foreach ($filters as $col => $val) {
        $p .= '&' . urlencode('filter[' . $col . ']') . '=' . urlencode($val);
    }
    return $p;
}

function acp_sort_url(string $base, string $col, string $curSort, string $curDir, int $page, array $filters): string {
    $dir = ($curSort === $col && $curDir === 'ASC') ? 'DESC' : 'ASC';
    $url = $base . '?page=' . $page . '&sort=' . urlencode($col) . '&dir=' . $dir;
    foreach ($filters as $fc => $fv) {
        $url .= '&' . urlencode('filter[' . $fc . ']') . '=' . urlencode($fv);
    }
    return $url;
}

$extraParams = acp_extra_params($sortCol, $sortDir, $filters);
?>

<h2>Browse: <code><?= e($table) ?></code></h2>

<p style="margin-bottom:1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
    <a href="<?= url('/admin/settings/database') ?>" class="btn btn-secondary">← Back to Database</a>
    <a href="<?= url('/admin/settings/database/query') ?>" class="btn btn-secondary">Query Runner</a>
    <?php if ($sortCol !== '' || !empty($filters)): ?>
    <a href="<?= $baseUrl ?>" class="btn btn-secondary">Clear All</a>
    <?php endif; ?>
    <?php if ($pkCol): ?>
    <button type="button" id="db-edit-toggle" class="btn btn-primary" style="margin-left:auto;">Edit Table</button>
    <?php endif; ?>
</p>

<p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">
    <?= number_format($total) ?> rows<?= !empty($filters) ? ' matching filters' : ' total' ?>
    — showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
    <?php if ($pkCol): ?><span style="margin-left:1rem;">PK: <code><?= e($pkCol) ?></code></span><?php endif; ?>
    <?php if ($sortCol !== ''): ?><span style="margin-left:1rem;">Sorted by <code><?= e($sortCol) ?></code> <?= e($sortDir) ?></span><?php endif; ?>
</p>

<?php if (!empty($rows) || !empty($filters)): ?>

<!-- Filter form (wraps only the filter header inputs, not the whole table) -->
<form method="get" action="<?= $baseUrl ?>" id="db-filter-form">
    <?php if ($sortCol !== ''): ?>
    <input type="hidden" name="sort" value="<?= e($sortCol) ?>">
    <input type="hidden" name="dir" value="<?= e($sortDir) ?>">
    <?php endif; ?>

<div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
<table id="db-datasheet"
       class="admin-table"
       style="font-size:0.82rem; width:max-content; min-width:100%;"
       data-save-url="<?= e($saveUrl) ?>"
       data-delete-url="<?= e($deleteUrl) ?>"
       data-csrf="<?= e(\Cruinn\CSRF::getToken()) ?>"
       data-page="<?= $page ?>"
       data-sort="<?= e($sortCol) ?>"
       data-dir="<?= e($sortDir) ?>"
       data-filters="<?= e(json_encode($filters)) ?>">
    <thead>
        <!-- Sort header row -->
        <tr>
            <?php foreach ($columns as $col): ?>
            <th style="user-select:none;">
                <a href="<?= acp_sort_url($baseUrl, $col, $sortCol, $sortDir, $page, $filters) ?>"
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
            <?php if ($pkCol): ?><th class="db-actions-col" style="min-width:100px;"></th><?php endif; ?>
        </tr>
        <!-- Filter row -->
        <tr style="border-bottom:2px solid var(--border);">
            <?php foreach ($columns as $col): ?>
            <th style="padding:0.25rem 0.4rem;">
                <input type="text"
                       name="<?= e('filter[' . $col . ']') ?>"
                       value="<?= e($filters[$col] ?? '') ?>"
                       placeholder="filter…"
                       style="width:100%; font-size:0.78rem; padding:0.2rem 0.35rem; border:1px solid <?= isset($filters[$col]) ? 'var(--accent,#3b82f6)' : 'var(--border)' ?>; border-radius:3px; font-family:monospace; background:<?= isset($filters[$col]) ? '#eff6ff' : 'inherit' ?>;">
            </th>
            <?php endforeach; ?>
            <?php if ($pkCol): ?>
            <th style="padding:0.25rem 0.4rem; white-space:nowrap; vertical-align:middle;">
                <button type="submit" class="btn btn-secondary" style="padding:0.15rem 0.5rem; font-size:0.78rem;">Apply</button>
                <?php if (!empty($filters)): ?>
                <a href="<?= $baseUrl . ($sortCol !== '' ? '?sort=' . urlencode($sortCol) . '&dir=' . urlencode($sortDir) : '') ?>"
                   class="btn btn-secondary" style="padding:0.15rem 0.5rem; font-size:0.78rem;">✕</a>
                <?php endif; ?>
            </th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $rowIdx => $row):
            $rowPk = $pkCol ? ($row[$pkCol] ?? null) : null;
        ?>
        <tr class="db-row" data-pk="<?= e((string)($rowPk ?? '')) ?>">
            <?php foreach ($columns as $col):
                $val    = $row[$col] ?? null;
                $strVal = $val === null ? '' : (string)$val;
                $meta   = $columnMeta[$col] ?? ['type' => 'varchar', 'nullable' => true, 'maxlen' => null];
                $isPk   = ($col === $pkCol);
                $isLong = in_array($meta['type'], ['text', 'mediumtext', 'longtext', 'json'], true)
                          || mb_strlen($strVal) > 255
                          || str_contains($strVal, "\n");
            ?>
            <td class="db-cell<?= $isPk ? ' db-cell-pk' : '' ?>"
                data-col="<?= e($col) ?>"
                data-orig="<?= e($strVal) ?>"
                data-null="<?= $val === null ? '1' : '0' ?>"
                style="white-space:nowrap; padding:0.3rem 0.6rem; width:1%; min-width:0;">
                <!-- Display span -->
                <span class="db-cell-display">
                <?php if ($val === null): ?>
                    <span style="color:var(--text-muted);font-style:italic;">NULL</span>
                <?php else:
                    $display = e(mb_strlen($strVal) > 80 ? mb_substr($strVal, 0, 80) . '…' : $strVal);
                    if (isset($filters[$col]) && $filters[$col] !== '') {
                        $display = preg_replace(
                            '/(' . preg_quote(e($filters[$col]), '/') . ')/i',
                            '<mark style="background:#fef08a;padding:0;">$1</mark>',
                            $display
                        );
                    }
                    echo $display;
                endif; ?>
                </span>
                <!-- Edit input (hidden until edit mode) -->
                <?php if ($isPk): ?>
                <input class="db-cell-input" type="text" value="<?= e($strVal) ?>" disabled
                       style="display:none; font-family:monospace; font-size:0.82rem; padding:0.2rem 0.35rem; border:1px solid var(--border); border-radius:3px; width:max-content; opacity:0.6; background:#f3f4f6;">
                <?php elseif ($isLong): ?>
                <textarea class="db-cell-input" rows="2"
                          style="display:none; font-family:monospace; font-size:0.82rem; padding:0.2rem 0.35rem; border:1px solid var(--border); border-radius:3px; width:max-content; resize:both;"><?= e($strVal) ?></textarea>
                <?php else: ?>
                <input class="db-cell-input" type="text" value="<?= e($strVal) ?>"
                       style="display:none; font-family:monospace; font-size:0.82rem; padding:0.2rem 0.35rem; border:1px solid var(--border); border-radius:3px; width:max-content;">
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <?php if ($pkCol): ?>
            <td class="db-actions-col" style="white-space:nowrap; padding:0.3rem 0.4rem;">
                <!-- View mode: just delete -->
                <span class="db-view-actions">
                    <?php if ($rowPk !== null): ?>
                    <button type="button" class="btn btn-danger db-delete-btn"
                            style="padding:0.15rem 0.5rem; font-size:0.78rem;">Delete</button>
                    <?php endif; ?>
                </span>
                <!-- Edit mode: save + revert (hidden until edit mode) -->
                <span class="db-edit-actions" style="display:none;">
                    <?php if ($rowPk !== null): ?>
                    <button type="button" class="btn btn-primary db-save-btn"
                            style="padding:0.15rem 0.5rem; font-size:0.78rem;">Save</button>
                    <button type="button" class="btn btn-secondary db-revert-btn"
                            style="padding:0.15rem 0.5rem; font-size:0.78rem;">Revert</button>
                    <?php endif; ?>
                </span>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</form>

<?php if ($pages > 1): ?>
<div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $extraParams ?>" class="btn btn-secondary">← Prev</a>
    <?php endif; ?>
    <span style="font-size:0.9rem; color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?><?= $extraParams ?>" class="btn btn-secondary">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
    <p style="color:var(--text-muted);">No rows found.</p>
<?php endif; ?>

<?php include __DIR__ . '/_tabs_end.php'; ?>
