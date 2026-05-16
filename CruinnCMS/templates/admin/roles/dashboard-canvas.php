<?php
/**
 * Role — Widget Dashboard Canvas Assignment
 *
 * Variables:
 * - $role: Role record
 * - $canvases: Array of widget dashboard canvases (pages with canvas_type='widget-dashboard')
 * - $currentDashboard: Currently assigned dashboard page ID (or null)
 */
$roleId = (int) $role['id'];
\Cruinn\Template::requireCss('admin-acp.css');
?>

<div class="admin-page-header">
    <h1>Widget Dashboard — <?= e($role['name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/roles/<?= $roleId ?>/dashboard" class="btn btn-outline btn-small">Legacy Widgets</a>
        <a href="/admin/roles/<?= $roleId ?>/navigation" class="btn btn-outline btn-small">Navigation</a>
        <a href="/admin/roles/<?= $roleId ?>/areas" class="btn btn-outline btn-small">Area Access</a>
        <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline btn-small">Edit Role</a>
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Assign a widget dashboard canvas to this role. Users with this role will see the assigned dashboard
    when they visit <code>/admin/dashboard</code>.
</p>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    <strong>Resolution order:</strong> user-specific → position → role → default admin dashboard.
    If a user has a personal dashboard assigned, it takes precedence over the role dashboard.
</p>

<?php if (empty($canvases)): ?>
    <div class="alert alert-info">
        <strong>No Dashboard Canvases Available</strong>
        <p>Create a widget dashboard canvas first in <a href="/admin/site-builder/dashboards">Site Builder → Dashboards</a>.</p>
    </div>
<?php else: ?>
    <form method="post" action="/admin/roles/<?= $roleId ?>/dashboard-canvas">
        <?= csrf_field() ?>

        <div class="form-section">
            <label>
                <strong>Assigned Dashboard Canvas</strong>
                <select name="dashboard_page_id" class="form-input">
                    <option value="0">— None (use fallback) —</option>
                    <?php foreach ($canvases as $canvas): ?>
                        <option value="<?= $canvas['id'] ?>"
                            <?= $currentDashboard == $canvas['id'] ? 'selected' : '' ?>>
                            <?= e($canvas['title']) ?> (<?= e($canvas['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Dashboard Assignment</button>
            <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline">Cancel</a>
        </div>
    </form>

    <?php if ($currentDashboard): ?>
        <div class="form-section" style="margin-top: var(--space-lg);">
            <h3>Current Dashboard</h3>
            <?php
            $current = null;
            foreach ($canvases as $c) {
                if ($c['id'] == $currentDashboard) {
                    $current = $c;
                    break;
                }
            }
            ?>
            <?php if ($current): ?>
                <p>
                    <strong><?= e($current['title']) ?></strong> —
                    <a href="/admin/editor/<?= $current['id'] ?>/edit" class="btn btn-small btn-primary">Edit    Blocks</a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="form-section" style="margin-top: var(--space-xl);">
    <h3>How to Build a Dashboard</h3>
    <ol>
        <li>Go to <a href="/admin/site-builder/dashboards">Site Builder → Dashboards</a></li>
        <li>Create a new dashboard canvas</li>
        <li>Edit the canvas in the block editor</li>
        <li>Add <code>module-widget</code> blocks (provided by modules) and layout blocks</li>
        <li>Publish the dashboard</li>
        <li>Return here to assign it to this role</li>
    </ol>
</div>

<style>
.form-input option {
    padding: var(--space-sm);
}
</style>
