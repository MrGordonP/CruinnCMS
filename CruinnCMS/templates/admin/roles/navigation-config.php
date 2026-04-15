$roleId = (int) $role['id'];
\Cruinn\Template::requireCss('admin-acp.css'); \Cruinn\Template::requireCss('admin-site-builder.css'); \Cruinn\Template::requireCss('admin-menus.css');

// Build a tree for display
$itemsById = [];
foreach ($navItems as $item) {
    $item['children'] = [];
    $itemsById[$item['id']] = $item;
}
$tree = [];
foreach ($itemsById as $id => &$item) {
    if ($item['parent_id'] && isset($itemsById[$item['parent_id']])) {
        $itemsById[$item['parent_id']]['children'][] = &$item;
    } else {
        $tree[] = &$item;
    }
}
unset($item);
?>

<div class="admin-page-header">
    <h1>Navigation Configuration — <?= e($role['name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/roles/<?= $roleId ?>/dashboard" class="btn btn-outline btn-small">Dashboard Config</a>
        <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline btn-small">Edit Role</a>
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Configure the navigation links for the <strong><?= e($role['name']) ?></strong> role.
    Items with children become dropdown menus. Drag to reorder, toggle visibility, and set permissions.
</p>

<form method="post" action="/admin/roles/<?= $roleId ?>/navigation" id="nav-config-form">
    <?= csrf_field() ?>

    <div class="nav-config-list" id="nav-config-list">
        <?php
        $counter = 0;
        function renderNavItem(array $item, int &$counter, ?string $parentTempId = null): void {
            $tempId = 'nav-' . $counter;
            $counter++;
        ?>
        <div class="nav-config-item<?= !$item['is_visible'] ? ' is-hidden' : '' ?>" data-temp-id="<?= e($tempId) ?>">
            <div class="nav-config-drag-handle" title="Drag to reorder">⠿</div>

            <input type="hidden" name="temp_id[]" value="<?= e($tempId) ?>">
            <input type="hidden" name="parent_temp_id[]" value="<?= e($parentTempId ?? '') ?>">
            <input type="hidden" name="sort_order[]" value="<?= $counter ?>" class="sort-order-input">
            <input type="hidden" name="is_visible[]" value="<?= $item['is_visible'] ? '1' : '0' ?>" class="visibility-input">

            <div class="nav-config-fields">
                <input type="text" name="label[]" value="<?= e($item['label']) ?>" placeholder="Label" class="form-input form-input-small" required>
                <input type="text" name="url[]" value="<?= e($item['url']) ?>" placeholder="URL (e.g. /admin/pages)" class="form-input form-input-small">
                <input type="text" name="css_class[]" value="<?= e($item['css_class'] ?? '') ?>" placeholder="CSS class" class="form-input form-input-small nav-field-narrow">
                <input type="text" name="permission_required[]" value="<?= e($item['permission_required'] ?? '') ?>" placeholder="Permission" class="form-input form-input-small nav-field-narrow">
                <label class="nav-config-checkbox" title="Opens in new tab">
                    <input type="checkbox" name="opens_new_tab[<?= $counter - 1 ?>]" value="1" <?= $item['opens_new_tab'] ? 'checked' : '' ?>>
                    <span>↗</span>
                </label>
            </div>

            <div class="nav-config-actions">
                <button type="button" class="btn btn-outline btn-small nav-toggle-visibility" title="Toggle visibility">
                    <?= $item['is_visible'] ? '👁' : '👁‍🗨' ?>
                </button>
                <button type="button" class="btn btn-danger btn-small nav-remove-item" title="Remove">&times;</button>
            </div>
        </div>

        <?php if (!empty($item['children'])): ?>
            <div class="nav-config-children" data-parent="<?= e($tempId) ?>">
                <?php foreach ($item['children'] as $child): ?>
                    <?php renderNavItem($child, $counter, $tempId); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
        }

        foreach ($tree as $item) {
            renderNavItem($item, $counter);
        }
        ?>
    </div>

    <div class="nav-config-add-bar" style="margin-top: var(--space-md);">
        <button type="button" class="btn btn-outline btn-small" id="nav-add-item">+ Add Item</button>
        <button type="button" class="btn btn-outline btn-small" id="nav-add-dropdown">+ Add Dropdown</button>
    </div>

    <div class="form-actions" style="margin-top: var(--space-lg);">
        <button type="submit" class="btn btn-primary">Save Navigation</button>
        <a href="/admin/roles" class="btn btn-outline">Cancel</a>
    </div>
</form>

<!-- Template for new nav item (used by JS) -->
<template id="nav-item-template">
    <div class="nav-config-item" data-temp-id="__TEMP_ID__">
        <div class="nav-config-drag-handle" title="Drag to reorder">⠿</div>

        <input type="hidden" name="temp_id[]" value="__TEMP_ID__">
        <input type="hidden" name="parent_temp_id[]" value="__PARENT_ID__">
        <input type="hidden" name="sort_order[]" value="0" class="sort-order-input">
        <input type="hidden" name="is_visible[]" value="1" class="visibility-input">

        <div class="nav-config-fields">
            <input type="text" name="label[]" value="" placeholder="Label" class="form-input form-input-small" required>
            <input type="text" name="url[]" value="" placeholder="URL" class="form-input form-input-small">
            <input type="text" name="css_class[]" value="" placeholder="CSS class" class="form-input form-input-small nav-field-narrow">
            <input type="text" name="permission_required[]" value="" placeholder="Permission" class="form-input form-input-small nav-field-narrow">
            <label class="nav-config-checkbox" title="Opens in new tab">
                <input type="checkbox" name="opens_new_tab[]" value="1">
                <span>↗</span>
            </label>
        </div>

        <div class="nav-config-actions">
            <button type="button" class="btn btn-outline btn-small nav-toggle-visibility" title="Toggle visibility">👁</button>
            <button type="button" class="btn btn-danger btn-small nav-remove-item" title="Remove">&times;</button>
        </div>
    </div>
</template>
