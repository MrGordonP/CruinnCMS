<?php
// Partial: shared role form fields. Included by edit.php for both new and edit contexts.
// Expects: $role, $isSystem, $permissions, $rolePermissions
?>
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
                0–100. Higher = more access. Standard: Public=0, Editor=20, Council=50, Admin=100.
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
        <p class="form-help">Where users with this role are redirected after logging in.</p>
    </div>
</div>

<!-- Permissions -->
<div class="detail-card">
    <h2>Permissions</h2>
    <p class="text-muted" style="margin-bottom: var(--space-md);">
        Select which permissions this role grants.
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
