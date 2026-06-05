<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout no-detail" id="dashboards-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header"><h3>Site Builder</h3></div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Content</div>
        <a class="pl-nav-item" href="<?= url('/admin/site-builder') ?>">Structure</a>
        <a class="pl-nav-item" href="<?= url('/admin/pages') ?>">Pages</a>
        <a class="pl-nav-item" href="<?= url('/admin/templates') ?>">Templates</a>
        <a class="pl-nav-item" href="<?= url('/admin/site-builder/zones') ?>">Zones</a>
        <div class="pl-nav-section">Custom</div>
        <a class="pl-nav-item active" href="<?= url('/admin/site-builder/dashboards') ?>">Widget Dashboards</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Widget Dashboards</span>
    </div>
    <div class="pl-main-scroll">

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Dashboards are block-based canvases that can be assigned to roles, positions, or individual users.
    Build them with layout blocks and <code>module-widget</code> data cards (quick links + status summaries per module).
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
        <li>Add layout blocks (sections, columns, etc.) and module data cards via <code>module-widget</code></li>
        <li>Publish the dashboard when ready</li>
        <li>Assign the dashboard to roles via <strong>Admin → Roles → {Role} → Dashboard</strong></li>
        <li>Users with that role will see the dashboard when they visit <code>/admin/dashboard</code></li>
    </ol>
    <p class="text-muted">
        Dashboard resolution order: user-specific → position → role → default admin dashboard.
    </p>
</div>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
