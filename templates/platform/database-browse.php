<?php
/**
 * Platform DB Browser — Table List
 * Variables: $tables, $dbName, $instanceFolder, $instanceLabel, $error, $username
 */
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Database Browser</h1>
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <a href="/cms/database/query<?= $instanceFolder ? '?instance='.urlencode($instanceFolder) : '' ?>"
               class="platform-btn platform-btn-secondary">Query Runner</a>
            <a href="/cms/dashboard" class="platform-btn platform-btn-secondary">← Dashboard</a>
        </div>
    </div>

    <?php if ($instanceLabel !== '(active)'): ?>
    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">
        Instance: <code><?= e($instanceLabel) ?></code> &mdash; DB: <code><?= e($dbName) ?></code>
    </p>
    <?php else: ?>
    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">
        Active instance &mdash; DB: <code><?= e($dbName) ?></code>
    </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="platform-alert platform-alert-error"><?= e($error) ?></div>
    <?php elseif (empty($tables)): ?>
        <p style="color:var(--text-muted);">No tables found.</p>
    <?php else: ?>
    <section class="platform-section">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <th style="text-align:left; padding:0.5rem 0.75rem;">Table</th>
                        <th style="text-align:left; padding:0.5rem 0.75rem;">Engine</th>
                        <th style="text-align:right; padding:0.5rem 0.75rem;">Rows</th>
                        <th style="text-align:right; padding:0.5rem 0.75rem;">Size</th>
                        <th style="text-align:left; padding:0.5rem 0.75rem;">Collation</th>
                        <th style="padding:0.5rem 0.75rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $t): ?>
                        <?php $tName = $t['table_name'] ?? $t['TABLE_NAME']; ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="padding:0.4rem 0.75rem;"><code><?= e($tName) ?></code></td>
                            <td style="padding:0.4rem 0.75rem;"><?= e($t['engine'] ?? $t['ENGINE'] ?? '') ?></td>
                            <td style="padding:0.4rem 0.75rem; text-align:right;"><?= number_format($t['table_rows'] ?? $t['TABLE_ROWS'] ?? 0) ?></td>
                            <td style="padding:0.4rem 0.75rem; text-align:right;"><?= number_format($t['total_kb'] ?? 0, 1) ?> KB</td>
                            <td style="padding:0.4rem 0.75rem;"><?= e($t['table_collation'] ?? $t['TABLE_COLLATION'] ?? '') ?></td>
                            <td style="padding:0.4rem 0.75rem;">
                                <?php
                                    $browseUrl = '/cms/database/browse/' . urlencode($tName);
                                    if ($instanceFolder) $browseUrl .= '?instance=' . urlencode($instanceFolder);
                                ?>
                                <a href="<?= $browseUrl ?>" class="platform-btn platform-btn-secondary"
                                   style="padding:0.2rem 0.5rem; font-size:0.8rem;">Browse</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--border); font-weight:600;">
                        <td style="padding:0.4rem 0.75rem;"><?= count($tables) ?> tables</td>
                        <td></td>
                        <td style="padding:0.4rem 0.75rem; text-align:right;"><?= number_format(array_sum(array_column($tables, 'table_rows'))) ?></td>
                        <td style="padding:0.4rem 0.75rem; text-align:right;"><?= number_format(array_sum(array_column($tables, 'total_kb')), 1) ?> KB</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
