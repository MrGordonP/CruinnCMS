<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
include __DIR__ . '/_tabs.php';
?>

<div style="max-width: 860px; margin: 1.5rem auto; padding: 0 1rem">

    <!-- New zone form -->
    <div class="acp-card" style="margin-bottom: 1.5rem">
        <div class="acp-card-header">
            <h3 class="acp-card-title">New Zone Canvas</h3>
        </div>
        <div class="acp-card-body">
            <form method="post" action="<?= url('/admin/site-builder/zones/new') ?>">
                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                <div style="display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end">
                    <div style="flex: 1; min-width: 180px">
                        <label class="form-label" for="zone-title">Display Name</label>
                        <input type="text" id="zone-title" name="title" class="form-control"
                               placeholder="e.g. Main Header" required autocomplete="off">
                    </div>
                    <div style="flex: 1; min-width: 160px">
                        <label class="form-label" for="zone-name">Zone Name <span style="color:#9ca3af;font-size:.8em">(slug, lowercase)</span></label>
                        <input type="text" id="zone-name" name="zone_name" class="form-control"
                               placeholder="e.g. header" pattern="[a-z0-9_-]+" required autocomplete="off">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Create &amp; Edit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing zones list -->
    <div class="acp-card">
        <div class="acp-card-header">
            <h3 class="acp-card-title">Zone Canvases</h3>
        </div>
        <?php if (empty($zones)): ?>
        <div class="acp-card-body" style="color: #9ca3af">
            No zone canvases yet. Create one above.
        </div>
        <?php else: ?>
        <table class="acp-table">
            <thead>
                <tr>
                    <th>Display Name</th>
                    <th>Zone Name</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zones as $z): ?>
            <tr>
                <td><?= e($z['title']) ?></td>
                <td><code><?= e($z['zone_name']) ?></code></td>
                <td><?= e($z['status'] ?? '—') ?></td>
                <td style="white-space: nowrap; color: #6b7280; font-size: .85em"><?= format_date($z['updated_at'], 'j M Y') ?></td>
                <td style="text-align: right">
                    <a href="<?= url('/admin/editor/' . (int)$z['id'] . '/edit') ?>" class="btn btn-sm btn-secondary">Edit Canvas</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
