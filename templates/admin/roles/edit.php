$isNew = empty($role['id']);
\Cruinn\Template::requireCss('admin-acp.css'); \Cruinn\Template::requireCss('admin-site-builder.css');
$formAction = $isNew ? '/admin/roles' : '/admin/roles/' . (int)$role['id'];
$isSystem = !$isNew && ($role['is_system'] ?? false);
?>

<div class="admin-page-header">
    <h1><?= $isNew ? 'New Role' : 'Edit Role — ' . e($role['name']) ?></h1>
    <div class="header-actions">
        <?php if (!$isNew): ?>
            <a href="/admin/roles/<?= (int)$role['id'] ?>/dashboard" class="btn btn-outline btn-small">Dashboard Config</a>
            <a href="/admin/roles/<?= (int)$role['id'] ?>/navigation" class="btn btn-outline btn-small">Navigation Config</a>
        <?php endif; ?>
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="form-errors">
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="admin-form">
    <?= csrf_field() ?>

    <!-- Role Details -->
    <div class="detail-card">
        <h2>Role Details</h2>

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Role Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-input"
                       value="<?= e($role['name'] ?? '') ?>" required
                       placeholder="e.g. Editor, Moderator">
            </div>

            <div class="form-group">
                <label for="slug">Slug <span class="required">*</span></label>
                <input type="text" name="slug" id="slug" class="form-input"
                       value="<?= e($role['slug'] ?? '') ?>" required
                       pattern="[a-z0-9\-]+" placeholder="e.g. editor"
                       <?= $isSystem ? 'readonly' : '' ?>>
                <?php if ($isSystem): ?>
                    <p class="form-help">System role slugs cannot be changed.</p>
                <?php else: ?>
                    <p class="form-help">Lowercase letters, numbers, and hyphens only.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" name="description" id="description" class="form-input"
                   value="<?= e($role['description'] ?? '') ?>"
                   placeholder="Brief description of this role's purpose">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="level">Hierarchy Level <span class="required">*</span></label>
                <input type="number" name="level" id="level" class="form-input"
                       value="<?= (int)($role['level'] ?? 10) ?>"
                       min="0" max="100" required>
                <p class="form-help">
                    0–100. Higher = more access. Standard levels: Public=0, Member=20, Council=50, Admin=100.
                    Custom roles should slot between these (e.g. Editor=30, Moderator=40).
                </p>
            </div>

            <div class="form-group">
                <label for="colour">Badge Colour</label>
                <div style="display: flex; align-items: center; gap: var(--space-sm);">
                    <input type="color" name="colour" id="colour"
                           value="<?= e($role['colour'] ?? '#6c757d') ?>"
                           style="width: 50px; height: 38px; padding: 2px; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer;">
                    <span class="role-badge" id="colour-preview" style="background: <?= e($role['colour'] ?? '#6c757d') ?>">
                        <?= e($role['name'] ?? 'Preview') ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="default_redirect">Default Redirect After Login</label>
            <input type="text" name="default_redirect" id="default_redirect" class="form-input"
                   value="<?= e($role['default_redirect'] ?? '/') ?>"
                   placeholder="/">
            <p class="form-help">Where users with this role are redirected after logging in. e.g. /admin, /council, /users/profile</p>
        </div>
    </div>

    <!-- Permissions -->
    <div class="detail-card">
        <h2>Permissions</h2>
        <p class="text-muted" style="margin-bottom: var(--space-md);">
            Select which permissions this role grants. Users with this role will be able to access features matching these permissions.
        </p>

        <?php foreach ($permissions as $category => $perms): ?>
        <div class="permission-category">
            <h3 class="permission-category-title">
                <?= e($category) ?>
                <button type="button" class="btn btn-outline btn-small permission-toggle-all"
                        data-category="<?= e($category) ?>">Toggle All</button>
            </h3>
            <div class="permission-grid">
                <?php foreach ($perms as $perm): ?>
                <label class="permission-item">
                    <input type="checkbox" name="permissions[]" value="<?= (int)$perm['id'] ?>"
                           <?= in_array((int)$perm['id'], $rolePermissions) ? 'checked' : '' ?>
                           data-category="<?= e($category) ?>">
                    <span class="permission-info">
                        <strong><?= e($perm['name']) ?></strong>
                        <small><?= e($perm['description']) ?></small>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Role' : 'Save Changes' ?></button>
        <a href="/admin/roles" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
// Auto-generate slug from name (new roles only)
<?php if ($isNew): ?>
document.getElementById('name').addEventListener('input', function() {
    const slug = this.value.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
});
<?php endif; ?>

// Colour preview
document.getElementById('colour').addEventListener('input', function() {
    document.getElementById('colour-preview').style.background = this.value;
});

// Toggle all permissions in a category
document.querySelectorAll('.permission-toggle-all').forEach(btn => {
    btn.addEventListener('click', function() {
        const cat = this.dataset.category;
        const checkboxes = document.querySelectorAll(`input[data-category="${cat}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    });
});
</script>
