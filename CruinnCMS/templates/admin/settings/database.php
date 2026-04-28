<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Database</h2>

<div class="acp-db-actions" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <form method="post" action="<?= url('/admin/settings/database/optimize') ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary" data-confirm="Optimize all tables?">🔧 Optimize Tables</button>
    </form>
    <a href="<?= url('/admin/settings/database/export') ?>" class="btn btn-secondary">📥 Export Database (SQL)</a>
    <form method="post" action="<?= url('/admin/settings/database/export-instance') ?>" style="display:inline;">
        <?= csrf_field() ?>
        <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.9rem;">
            <input type="checkbox" name="include_media" value="1"> Include media files
        </label>
        <button type="submit" class="btn btn-primary">📦 Export Instance (ZIP)</button>
    </form>
    <form method="post" action="<?= url('/admin/settings/database/run-queue') ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-success"
                data-confirm="Run the email queue processor now? This will send up to 200 queued emails."
                title="Manually drain the email queue — use this when cron is not yet configured">
            ✉️ Run Email Queue Now
        </button>
    </form>
    <a href="<?= url('/admin/settings/database/query') ?>" class="btn btn-secondary">🔍 Query Runner</a>
</div>

<fieldset class="acp-fieldset">
    <legend>Tables</legend>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Engine</th>
                    <th>Rows</th>
                    <th>Size</th>
                    <th>Collation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tables)): ?>
                    <?php foreach ($tables as $t): ?>
                        <?php $tName = $t['table_name'] ?? $t['TABLE_NAME']; ?>
                        <tr>
                            <td><code><?= e($tName) ?></code></td>
                            <td><?= e($t['engine'] ?? $t['ENGINE'] ?? '') ?></td>
                            <td class="text-right"><?= number_format($t['table_rows'] ?? $t['TABLE_ROWS'] ?? 0) ?></td>
                            <td class="text-right"><?= number_format($t['total_kb'] ?? 0, 1) ?> KB</td>
                            <td><?= e($t['table_collation'] ?? $t['TABLE_COLLATION'] ?? '') ?></td>
                            <td><a href="<?= url('/admin/settings/database/browse/' . urlencode($tName)) ?>" class="btn btn-secondary" style="padding:0.2rem 0.6rem; font-size:0.8rem;">Browse</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No table information available.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($tables)): ?>
            <tfoot>
                <tr>
                    <th>Total: <?= count($tables) ?> tables</th>
                    <th></th>
                    <th class="text-right"><?= number_format(array_sum(array_column($tables, 'table_rows'))) ?></th>
                    <th class="text-right"><?= number_format(array_sum(array_column($tables, 'total_kb')), 1) ?> KB</th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</fieldset>

<?php include __DIR__ . '/_tabs_end.php'; ?>
