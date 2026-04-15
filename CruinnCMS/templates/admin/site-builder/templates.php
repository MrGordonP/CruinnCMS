<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<h2>Page Templates</h2>

<p class="sb-subtitle sb-full-width">Templates define the layout structure for pages — which zones are available and how content is arranged.</p>

<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Zones</th>
            <th>Pages Using</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($templates as $tpl): ?>
        <tr>
            <td>
                <strong><?= e($tpl['name']) ?></strong>
                <?php if ($tpl['description']): ?>
                    <br><small class="text-muted"><?= e($tpl['description']) ?></small>
                <?php endif; ?>
            </td>
            <td><code><?= e($tpl['slug']) ?></code></td>
            <td>
                <?php
                $zones = json_decode($tpl['zones'] ?? '["main"]', true);
                if (is_array($zones)):
                    foreach ($zones as $z): ?>
                        <span class="badge badge-info"><?= e($z) ?></span>
                    <?php endforeach;
                endif; ?>
            </td>
            <td><?= (int)$tpl['page_count'] ?></td>
            <td>
                <?php if ($tpl['is_system']): ?>
                    <span class="badge badge-published">System</span>
                <?php else: ?>
                    <span class="badge badge-draft">Custom</span>
                <?php endif; ?>
            </td>
            <td class="sb-actions">
                <a href="<?= url('/admin/templates/' . (int)$tpl['id'] . '/edit') ?>" class="btn btn-small">Edit</a>
                <?php if (!$tpl['is_system']): ?>
                    <form method="post" action="<?= url('/admin/templates/' . (int)$tpl['id'] . '/delete') ?>"
                          style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger"
                                onclick="return confirm('Delete template \'<?= e($tpl['name']) ?>\'?')">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="sb-create-section">
    <h3>Create Custom Template</h3>
    <form method="post" action="<?= url('/admin/templates') ?>" class="sb-create-form">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="tpl_name">Name</label>
                <input type="text" id="tpl_name" name="name" required class="form-input" placeholder="e.g. Two Sidebar">
            </div>
            <div class="form-group">
                <label for="tpl_slug">Slug</label>
                <input type="text" id="tpl_slug" name="slug" required class="form-input"
                       placeholder="e.g. two-sidebar" pattern="[a-z0-9\-]+">
            </div>
        </div>
        <div class="form-group">
            <label for="tpl_description">Description</label>
            <input type="text" id="tpl_description" name="description" class="form-input"
                   placeholder="Brief description of this template layout">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tpl_zones">Zones <small>(JSON array)</small></label>
                <input type="text" id="tpl_zones" name="zones" class="form-input"
                       value='["main"]' placeholder='["main", "sidebar"]'>
                <small class="form-help">e.g. ["main"], ["main", "sidebar"], ["header", "main", "footer"]</small>
            </div>
            <div class="form-group">
                <label for="tpl_css_class">CSS Class</label>
                <input type="text" id="tpl_css_class" name="css_class" class="form-input"
                       placeholder="Optional class for template wrapper">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Template</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
