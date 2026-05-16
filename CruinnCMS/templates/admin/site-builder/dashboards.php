<?php
/**
 * Site Builder — Widget Dashboards
 *
 * Variables:
 * - $canvases: Array of dashboard canvas pages (canvas_type='widget-dashboard')
 */
\Cruinn\Template::requireCss('admin-site-builder.css');
?>

<div class="admin-page-header">
    <h1>Widget Dashboards</h1>
    <div class="header-actions">
        <a href="/admin/site-builder" class="btn btn-outline btn-small">Back to Site Builder</a>
    </div>
</div>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Widget dashboards are block-based canvases that can be assigned to roles, positions, or individual users.
    Build dashboards using the block editor with <code>module-widget</code> blocks and layout blocks.
</p>

<div class="form-section">
    <form method="post" action="/admin/site-builder/dashboards/new" style="max-width: 500px;">
        <?= csrf_field() ?>
        <label>
            <strong>Create New Dashboard</strong>
            <input type="text" name="title" class="form-input" placeholder="Dashboard Title" required>
        </label>
        <button type="submit" class="btn btn-primary">Create Dashboard</button>
    </form>
</div>

<div class="form-section">
    <h3>Existing Dashboards</h3>

    <?php if (empty($canvases)): ?>
        <p class="text-muted"><em>No dashboards created yet.</em></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Assignments</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($canvases as $canvas): ?>
                    <tr>
                        <td>
                            <strong><?= e($canvas['title']) ?></strong>
                        </td>
                        <td>
                            <code><?= e($canvas['slug']) ?></code>
                        </td>
                        <td>
                            <?= (int) $canvas['assignment_count'] ?> context(s)
                        </td>
                        <td>
                            <span class="text-muted"><?= date('j M Y', strtotime($canvas['created_at'])) ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="/admin/editor/<?= $canvas['id'] ?>/edit" class="btn btn-small btn-primary">Edit Blocks</a>
                                <form method="post" action="/admin/site-builder/dashboards/<?= $canvas['id'] ?>/delete" style="display: inline;" onsubmit="return confirm('Delete this dashboard? All assignments will be removed.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="form-section">
    <h3>How to Use</h3>
    <ol>
        <li>Create a new dashboard using the form above</li>
        <li>Edit the dashboard canvas using the block editor</li>
        <li>Add <code>module-widget</code> blocks and layout blocks (sections, columns, etc.)</li>
        <li>Publish the dashboard when ready</li>
        <li>Assign the dashboard to roles via <strong>Admin → Roles → {Role} → Dashboard</strong></li>
        <li>Users with that role will see the dashboard when they visit <code>/admin/dashboard</code></li>
    </ol>
    <p class="text-muted">
        Dashboard resolution order: user-specific → position → role → default admin dashboard.
    </p>
</div>

<style>
.action-buttons {
    display: flex;
    gap: var(--space-sm);
}
</style>
