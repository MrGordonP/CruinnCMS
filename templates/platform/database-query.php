<?php
/**
 * Platform DB Query Runner
 * Variables: $instanceFolder, $sql, $results, $affected, $error, $username
 */
?>
<?php ob_start(); ?>

<div class="platform-page">
    <div class="platform-page-header">
        <h1>Query Runner</h1>
        <a href="/cms/database<?= $instanceFolder ? '?instance='.urlencode($instanceFolder) : '' ?>"
           class="platform-btn platform-btn-secondary">← Table List</a>
    </div>

    <?php if ($instanceFolder): ?>
    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:1rem;">
        Instance: <code><?= e($instanceFolder) ?></code>
    </p>
    <?php endif; ?>

    <section class="platform-section">
        <form method="POST" action="/cms/database/query">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
            <input type="hidden" name="instance" value="<?= e($instanceFolder) ?>">
            <div style="margin-bottom:0.75rem;">
                <textarea name="sql" rows="6"
                          style="width:100%; font-family:monospace; font-size:0.9rem; padding:0.5rem;
                                 border:1px solid var(--border); border-radius:4px; resize:vertical;
                                 background:var(--surface,#fff); color:var(--text,#111);"><?= e($sql) ?></textarea>
            </div>
            <button type="submit" class="platform-btn platform-btn-primary">Run Query</button>
        </form>
    </section>

    <?php if ($error !== null): ?>
        <div class="platform-alert platform-alert-error" style="margin-top:1rem;">
            <strong>Error:</strong> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($affected !== null && $error === null): ?>
        <div class="platform-alert platform-alert-success" style="margin-top:1rem;">
            <?= number_format($affected) ?> row(s) affected.
        </div>
    <?php endif; ?>

    <?php if ($results !== null && $error === null): ?>
        <?php if (empty($results)): ?>
            <p style="margin-top:1rem; color:var(--text-muted);">Query returned no rows.</p>
        <?php else: ?>
            <p style="margin-top:1rem; font-size:0.88rem; color:var(--text-muted);"><?= number_format(count($results)) ?> row(s) returned</p>
            <section class="platform-section">
                <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
                    <table style="width:max-content; min-width:100%; border-collapse:collapse; font-size:0.82rem; white-space:nowrap;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);">
                                <?php foreach (array_keys($results[0]) as $col): ?>
                                    <th style="text-align:left; padding:0.4rem 0.6rem;"><?= e($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr style="border-bottom:1px solid var(--border);">
                                    <?php foreach ($row as $val): ?>
                                        <td style="padding:0.3rem 0.6rem;"
                                            title="<?= e((string)($val ?? '')) ?>">
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
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
