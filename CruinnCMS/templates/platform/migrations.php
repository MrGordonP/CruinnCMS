<?php
/**
 * Platform — Database Migrations  (/cms/migrations)
 *
 * Variables: $rows (array), $pending (int), $results (?array), $errors (int), $csrfToken (string)
 */
?>
<?php ob_start(); ?>
<div class="plat-content-header">
    <h1 class="plat-content-title">Platform Migrations</h1>
</div>

<p style="color:var(--plat-text-muted);margin-bottom:1.5rem;">
    Platform schema migration files (<code>schema/migrate_*.sql</code>). These update the platform database
    for existing installs when new platform features are added.
</p>

<?php if ($results !== null):
    $ran     = array_filter($results, fn($r) => $r['status'] === 'ok');
    $failed  = array_values(array_filter($results, fn($r) => $r['status'] === 'failed'));
    $missing = array_values(array_filter($results, fn($r) => $r['status'] === 'missing'));
?>
<div style="margin-bottom:1.5rem;padding:.75rem 1rem;border-radius:6px;border:1px solid <?= count($failed) ? '#fca5a5' : '#86efac' ?>;background:<?= count($failed) ? '#fef2f2' : '#f0fdf4' ?>;color:<?= count($failed) ? '#991b1b' : '#166534' ?>;">
    <?php if (count($failed)): ?>
        ❌ <?= count($failed) ?> migration(s) failed. <?= count($ran) ?> applied. See details below.
    <?php elseif (count($ran)): ?>
        ✅ <?= count($ran) ?> migration(s) applied successfully.
    <?php else: ?>
        ✅ All migrations were already up to date.
    <?php endif ?>
</div>

<?php if (count($failed) || count($missing)): ?>
<div style="margin-bottom:1.5rem;">
<?php foreach (array_merge($failed, $missing) as $r): ?>
    <div style="padding:.5rem .75rem;background:#fef2f2;border-left:3px solid #ef4444;margin-bottom:.5rem;font-size:.875rem;border-radius:0 4px 4px 0;">
        <strong><?= htmlspecialchars($r['file'], ENT_QUOTES, 'UTF-8') ?></strong>: <?= htmlspecialchars($r['error'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endforeach ?>
</div>
<?php endif ?>
<?php endif ?>

<?php if ($pending > 0): ?>
<form method="post" action="/cms/migrations" style="margin-bottom:2rem;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="plat-btn plat-btn-primary"
            onclick="this.disabled=true;this.textContent='Applying…';this.form.submit();">
        Apply <?= (int)$pending ?> pending migration<?= $pending !== 1 ? 's' : '' ?>
    </button>
</form>
<?php else: ?>
<p style="margin-bottom:2rem;color:#1d9e75;font-weight:600;">✅ All migrations are up to date.</p>
<?php endif ?>

<?php if (!empty($rows)): ?>
<table style="width:100%;border-collapse:collapse;font-size:.875rem;">
    <thead>
        <tr style="border-bottom:2px solid var(--plat-border);">
            <th style="text-align:left;padding:.5rem .75rem;color:var(--plat-text-muted);">File</th>
            <th style="text-align:center;padding:.5rem .75rem;color:var(--plat-text-muted);width:130px;">Status</th>
            <th style="width:100px;"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row):
        $justRan = null;
        if ($results !== null) {
            foreach ($results as $r) {
                if ($r['file'] === $row['file']) { $justRan = $r['status']; break; }
            }
        }
    ?>
        <tr style="border-bottom:1px solid var(--plat-border);">
            <td style="padding:.5rem .75rem;font-family:monospace;"><?= htmlspecialchars($row['file'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="padding:.5rem .75rem;text-align:center;">
                <?php if ($justRan === 'ok'): ?>
                    <span style="color:#16a34a;font-weight:600;">✅ Applied now</span>
                <?php elseif ($justRan === 'failed'): ?>
                    <span style="color:#dc2626;font-weight:600;">❌ Failed</span>
                <?php elseif ($justRan === 'missing'): ?>
                    <span style="color:#d97706;font-weight:600;">⚠ File missing</span>
                <?php elseif ($row['applied']): ?>
                    <span style="color:var(--plat-text-muted);">✓ Applied</span>
                <?php else: ?>
                    <span style="color:#f59e0b;font-weight:600;">⏳ Pending</span>
                <?php endif ?>
            </td>
            <td style="padding:.5rem .75rem;text-align:right;">
                <?php if (!$row['applied'] && $justRan !== 'ok'): ?>
                <form method="post" action="/cms/migrations" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="file" value="<?= htmlspecialchars($row['file'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" style="font-size:.75rem;padding:.25rem .6rem;background:var(--plat-accent);color:#fff;border:none;border-radius:4px;cursor:pointer;"
                            onclick="this.disabled=true;this.textContent='…';this.form.submit();">Apply</button>
                </form>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:var(--plat-text-muted);">No migration files found in <code>schema/</code>.</p>
<?php endif ?>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
