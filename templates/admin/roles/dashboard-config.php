$roleId = (int) $role['id'];
\Cruinn\Template::requireCss('admin-acp.css'); \Cruinn\Template::requireCss('admin-site-builder.css');
?>

<div class="admin-page-header">
    <h1>Dashboard Configuration — <?= e($role['name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline btn-small">Edit Role</a>
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Configure which widgets appear on the <strong><?= e($role['name']) ?></strong> dashboard and their layout.
    Drag widgets to reorder, toggle visibility, and set column widths.
</p>

<form method="post" action="/admin/roles/<?= $roleId ?>/dashboard" id="dashboard-config-form">
    <?= csrf_field() ?>

    <div class="widget-config-list" id="widget-config-list">
        <?php foreach ($widgets as $i => $widget): ?>
        <div class="widget-config-item<?= $widget['is_visible'] ? ' is-active' : '' ?>" data-widget-id="<?= (int)$widget['id'] ?>">
            <div class="widget-config-drag-handle" title="Drag to reorder">⠿</div>

            <input type="hidden" name="widget_id[]" value="<?= (int)$widget['id'] ?>">
            <input type="hidden" name="sort_order[]" value="<?= $i ?>" class="sort-order-input">

            <div class="widget-config-details">
                <div class="widget-config-name"><?= e($widget['name']) ?></div>
                <div class="widget-config-meta">
                    <span class="badge badge-muted"><?= e($widget['category']) ?></span>
                    <span class="text-muted"><?= e($widget['template_path']) ?></span>
                </div>
            </div>

            <div class="widget-config-controls">
                <select name="grid_width[]" class="form-select form-select-small">
                    <option value="full" <?= ($widget['grid_width'] === 'full') ? 'selected' : '' ?>>Full Width</option>
                    <option value="half" <?= ($widget['grid_width'] === 'half') ? 'selected' : '' ?>>Half Width</option>
                </select>

                <label class="toggle-switch">
                    <input type="checkbox"
                           name="is_visible[<?= (int)$widget['id'] ?>]"
                           value="1"
                           class="widget-visibility-toggle"
                           <?= $widget['is_visible'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions" style="margin-top: var(--space-lg);">
        <button type="submit" class="btn btn-primary">Save Configuration</button>
        <a href="/admin/roles" class="btn btn-outline">Cancel</a>
    </div>
</form>

<div class="detail-card" style="margin-top: var(--space-xl);">
    <h2>Dashboard Preview</h2>
    <p class="text-muted">A preview of how the dashboard will appear for users with the <strong><?= e($role['name']) ?></strong> role.</p>
    <div class="dashboard-preview" id="dashboard-preview">
        <?php foreach ($widgets as $widget): ?>
            <?php if ($widget['is_visible']): ?>
            <div class="preview-widget preview-<?= e($widget['grid_width']) ?>">
                <div class="preview-widget-label"><?= e($widget['name']) ?></div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!array_filter($widgets, fn($w) => $w['is_visible'])): ?>
            <p class="text-muted" style="text-align: center; padding: var(--space-lg);">No widgets enabled. Toggle some widgets on above.</p>
        <?php endif; ?>
    </div>
</div>
