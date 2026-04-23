<?php
/**
 * Admin — Broken Link Scanner
 * Variables: $results (null = not yet run, array = scan complete)
 */
?>
<?php
// Reuse the ACP tab layout — set $tab so the Maintenance tab lights up
$tab = 'maintenance';
include dirname(__DIR__) . '/settings/_tabs.php';
\Cruinn\Template::requireCss('admin-acp.css');
?>

<nav class="sub-tabs" style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:.75rem;">
    <a href="/admin/maintenance/links"      class="sub-tab sub-tab-active" style="padding:.35rem .85rem;border-radius:4px;background:#1d9e75;color:#fff;text-decoration:none;font-size:.875rem;">🔗 Link Checker</a>
    <a href="/admin/maintenance/migrations" class="sub-tab"                style="padding:.35rem .85rem;border-radius:4px;color:#374151;text-decoration:none;font-size:.875rem;">🗄️ Migrations</a>
</nav>

<div class="admin-page-header">
    <h1>Broken Link Scanner</h1>
</div>

<div style="max-width:900px;">
    <p style="color:#6b7280;margin-bottom:1.5rem;">
        Scans all pages, Cruinn blocks, and settings for internal links and file references,
        then checks each one against the page table and file system.
        External links are flagged but not fetched.
    </p>

    <form method="POST" action="/admin/maintenance/links" style="margin-bottom:2rem;">
        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
        <button type="submit" class="btn btn-primary">Run Scan</button>
    </form>

    <?php if ($results !== null): ?>

    <?php
        $broken   = array_filter($results, fn($r) => $r['status'] === 'broken');
        $ok       = array_filter($results, fn($r) => $r['status'] === 'ok');
        $external = array_filter($results, fn($r) => $r['status'] === 'external');
        $skipped  = array_filter($results, fn($r) => $r['status'] === 'skipped');
    ?>

    <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <div style="background:#fef2f2;border:1px solid #fca5a5;padding:0.75rem 1.25rem;border-radius:6px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#dc2626;"><?= count($broken) ?></div>
            <div style="font-size:0.8rem;color:#dc2626;">Broken</div>
        </div>
        <div style="background:#f0fdf4;border:1px solid #86efac;padding:0.75rem 1.25rem;border-radius:6px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#16a34a;"><?= count($ok) ?></div>
            <div style="font-size:0.8rem;color:#16a34a;">OK</div>
        </div>
        <div style="background:#fefce8;border:1px solid #fde047;padding:0.75rem 1.25rem;border-radius:6px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#ca8a04;"><?= count($external) ?></div>
            <div style="font-size:0.8rem;color:#ca8a04;">External</div>
        </div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;padding:0.75rem 1.25rem;border-radius:6px;text-align:center;">
            <div style="font-size:1.75rem;font-weight:700;color:#9ca3af;"><?= count($skipped) ?></div>
            <div style="font-size:0.8rem;color:#9ca3af;">Skipped</div>
        </div>
    </div>

    <?php if (empty($broken) && empty($results)): ?>
        <p style="color:#16a34a;">✓ No links found to check.</p>
    <?php elseif (empty($broken)): ?>
        <p style="color:#16a34a;">✓ No broken internal links found.</p>
    <?php endif; ?>

    <?php if (!empty($broken)): ?>
    <h3 style="color:#dc2626;margin-bottom:0.75rem;">Broken Links (<?= count($broken) ?>)</h3>
    <table class="admin-table" style="margin-bottom:2rem;">
        <thead>
            <tr>
                <th>Source</th>
                <th>Href / Path</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($broken as $r): ?>
            <tr style="background:#fff5f5;">
                <td style="font-size:0.85rem;"><?= e($r['source']) ?></td>
                <td><code style="color:#dc2626;"><?= e($r['href']) ?></code></td>
                <td style="font-size:0.8rem;color:#9ca3af;"><?= e($r['type']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($external)): ?>
    <details style="margin-bottom:1rem;">
        <summary style="cursor:pointer;color:#ca8a04;font-weight:600;">External links (<?= count($external) ?>) — not checked</summary>
        <table class="admin-table" style="margin-top:0.5rem;">
            <thead><tr><th>Source</th><th>URL</th></tr></thead>
            <tbody>
                <?php foreach ($external as $r): ?>
                <tr>
                    <td style="font-size:0.85rem;"><?= e($r['source']) ?></td>
                    <td><code><?= e($r['href']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php endif; ?>

    <?php endif; /* results !== null */ ?>
</div>
