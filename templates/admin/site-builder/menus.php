<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); \Cruinn\Template::requireCss('admin-menus.css'); ?>

<h2>Navigation Menus</h2>

<div class="sb-toolbar sb-full-width">
    <a href="<?= url('/admin/menus/new') ?>" class="btn btn-primary btn-small">+ New Menu</a>
    <span class="sb-count"><?= count($menus) ?> menu<?= count($menus) !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($menus)): ?>
    <div class="sb-empty">
        <p>No menus configured yet.</p>
        <a href="<?= url('/admin/menus/new') ?>" class="btn btn-primary">Create First Menu</a>
    </div>
<?php else: ?>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Name</th>
            <th>Location</th>
            <th>Items</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $locationLabels = [
            'main'    => '🔝 Primary Navigation',
            'footer'  => '🔻 Footer',
            'sidebar' => '📎 Sidebar',
            'topbar'  => '⬆ Utility Bar',
            'mobile'  => '📱 Mobile',
            'custom'  => '⚙ Custom',
        ];
        foreach ($menus as $menu): ?>
        <tr>
            <td><strong><?= e($menu['name']) ?></strong></td>
            <td>
                <span class="badge badge-info">
                    <?= $locationLabels[$menu['location']] ?? e(ucfirst($menu['location'])) ?>
                </span>
            </td>
            <td><?= (int)$menu['item_count'] ?> item<?= (int)$menu['item_count'] !== 1 ? 's' : '' ?></td>
            <td class="text-muted"><?= e($menu['description'] ?? '') ?></td>
            <td class="sb-actions">
                <a href="<?= url('/admin/menus/' . (int)$menu['id'] . '/edit') ?>" class="btn btn-small">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="sb-info-box">
    <h3>Menu Locations</h3>
    <p>Menus can be assigned to the following locations in your site layout:</p>
    <ul class="sb-location-list">
        <li><strong>Primary Navigation</strong> — Main header menu, visible on every page</li>
        <li><strong>Footer</strong> — Links in the site footer</li>
        <li><strong>Sidebar</strong> — Sidebar navigation widget</li>
        <li><strong>Utility Bar</strong> — Top bar with login/profile links</li>
        <li><strong>Mobile</strong> — Hamburger menu for mobile devices</li>
        <li><strong>Custom</strong> — Manually placed via template code</li>
    </ul>
</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
