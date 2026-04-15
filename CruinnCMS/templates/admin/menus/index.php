<?php $tab = 'menus'; include __DIR__ . '/../site-builder/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-menus.css'); ?>
<div class="admin-menu-list">
    <div class="admin-list-header">
        <h1>Menus</h1>
        <a href="/admin/menus/new" class="btn btn-primary">+ New Menu</a>
    </div>

    <?php if (empty($menus)): ?>
        <p class="admin-empty">No menus created yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Location</th>
                <th>Items</th>
                <th>Description</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menus as $menu): ?>
            <tr>
                <td>
                    <a href="/admin/menus/<?= (int)$menu['id'] ?>/edit" class="strong-link"><?= e($menu['name']) ?></a>
                </td>
                <td>
                    <?php
                        $locSlug = $menu['location'];
                        $locLabel = $locations[$locSlug]['label'] ?? $locSlug;
                    ?>
                    <span title="<?= e($locSlug) ?>"><?= e($locLabel) ?></span>
                </td>
                <td><?= (int)$menu['item_count'] ?></td>
                <td><?= e($menu['description'] ?? '') ?></td>
                <td>
                    <a href="/admin/menus/<?= (int)$menu['id'] ?>/edit" class="btn btn-small">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../site-builder/_tabs_close.php'; ?>
