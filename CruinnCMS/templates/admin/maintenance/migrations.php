<?php
/**
 * Admin — Database Migrations
 * Variables: $rows, $pending, $slugRemapped, $results (null = not yet run), $errors
 */
$tab = 'maintenance';
include dirname(__DIR__) . '/settings/_tabs.php';
\Cruinn\Template::requireCss('admin-acp.css');
?>

<h2>Database Migrations</h2>
<p style="color:#6b7280;margin-bottom:1.5rem;">
    Tracked migrations for core and all modules. Apply pending migrations to update the database schema.
</p>

<?php if ($slugRemapped > 0): ?>
<div class="alert alert-info" style="margin-bottom:1.5rem;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#1e40af;">
    ℹ️ Housekeeping: <?= (int)$slugRemapped ?> migration record(s) renamed from module <code>articles</code> → <code>blog</code> to match current module slug.
</div>
<?php endif ?>

<?php if ($results !== null): ?>
<?php
    $ran     = array_filter($results, fn($r) => $r['status'] === 'ok');
    $failed  = array_values(array_filter($results, fn($r) => $r['status'] === 'failed'));
    $missing = array_values(array_filter($results, fn($r) => $r['status'] === 'missing'));
?>
<div class="alert <?= count($failed) ? 'alert-error' : 'alert-success' ?>" style="margin-bottom:1.5rem;padding:.75rem 1rem;border-radius:6px;border:1px solid <?= count($failed) ? '#fca5a5;background:#fef2f2;color:#991b1b' : '#86efac;background:#f0fdf4;color:#166534' ?>">
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
    <div style="padding:.5rem .75rem;background:#fef2f2;border-left:3px solid #ef4444;margin-bottom:.5rem;font-size:.875rem;">
        <strong>[<?= e($r['module']) ?>] <?= e($r['file']) ?></strong>: <?= e($r['error']) ?>
    </div>
<?php endforeach ?>
</div>
<?php endif ?>
<?php endif ?>

<?php if ($pending > 0): ?>
<form method="POST" action="/admin/maintenance/migrations" style="margin-bottom:2rem;">
    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
    <button type="submit" class="btn btn-primary" onclick="this.disabled=true;this.textContent='Applying…';this.form.submit();">
        Apply <?= (int)$pending ?> pending migration<?= $pending !== 1 ? 's' : '' ?>
    </button>
</form>
<?php else: ?>
<p style="margin-bottom:2rem;color:#16a34a;font-weight:600;">✅ All migrations are up to date.</p>
<?php endif ?>

<table class="data-table" style="width:100%;border-collapse:collapse;font-size:.875rem;">
    <thead>
        <tr style="border-bottom:2px solid #e5e7eb;">
            <th style="text-align:left;padding:.5rem .75rem;color:#6b7280;">Module</th>
            <th style="text-align:left;padding:.5rem .75rem;color:#6b7280;">File</th>
            <th style="text-align:center;padding:.5rem .75rem;color:#6b7280;">Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php
        $justRan  = null;
        if ($results !== null) {
            foreach ($results as $r) {
                if ($r['module'] === $row['module'] && $r['file'] === $row['file']) {
                    $justRan = $r['status'];
                    break;
                }
            }
        }
        ?>
        <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:.5rem .75rem;font-family:monospace;"><?= e($row['module']) ?></td>
            <td style="padding:.5rem .75rem;font-family:monospace;"><?= e($row['file']) ?></td>
            <td style="padding:.5rem .75rem;text-align:center;">
                <?php if ($justRan === 'ok'): ?>
                    <span style="color:#16a34a;font-weight:600;">✅ Applied now</span>
                <?php elseif ($justRan === 'failed'): ?>
                    <span style="color:#dc2626;font-weight:600;">❌ Failed</span>
                <?php elseif ($justRan === 'missing'): ?>
                    <span style="color:#d97706;font-weight:600;">⚠ File missing</span>
                <?php elseif ($row['applied']): ?>
                    <span style="color:#9ca3af;">✓ Applied</span>
                <?php else: ?>
                    <span style="color:#f59e0b;font-weight:600;">⏳ Pending</span>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
