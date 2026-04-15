<?php \Cruinn\Template::requireCss('admin-acp.css'); \Cruinn\Template::requireCss('admin-site-builder.css'); ?>
<div class="admin-page-header">
    <h1>Roles & Permissions</h1>
    <div class="header-actions">
        <a href="/admin/roles/new" class="btn btn-primary">+ New Role</a>
    </div>
</div>

<p class="text-muted" style="margin-bottom: var(--space-lg);">
    Manage user roles, their permissions, and dashboard configurations.
    System roles cannot be deleted but can be reconfigured.
</p>

<table class="admin-table">
    <thead>
        <tr>
            <th>Role</th>
            <th>Slug</th>
            <th>Level</th>
            <th>Users</th>
            <th>Redirect</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $role): ?>
        <tr>
            <td>
                <span class="role-badge" style="background: <?= e($role['colour']) ?>">
                    <?= e($role['name']) ?>
                </span>
                <?php if ($role['description']): ?>
                    <br><small class="text-muted"><?= e($role['description']) ?></small>
                <?php endif; ?>
            </td>
            <td><code><?= e($role['slug']) ?></code></td>
            <td><?= (int)$role['level'] ?></td>
            <td><?= (int)$role['user_count'] ?></td>
            <td><code><?= e($role['default_redirect']) ?></code></td>
            <td>
                <?php if ($role['is_system']): ?>
                    <span class="badge badge-muted">System</span>
                <?php else: ?>
                    <span class="badge badge-info">Custom</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="action-buttons">
                    <a href="/admin/roles/<?= (int)$role['id'] ?>/edit" class="btn btn-outline btn-small">Edit</a>
                    <a href="/admin/roles/<?= (int)$role['id'] ?>/dashboard" class="btn btn-outline btn-small">Dashboard</a>
                    <a href="/admin/roles/<?= (int)$role['id'] ?>/navigation" class="btn btn-outline btn-small">Nav</a>
                    <form method="post" action="/admin/roles/<?= (int)$role['id'] ?>/clone" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-small">Clone</button>
                    </form>
                    <?php if (!$role['is_system']): ?>
                    <form method="post" action="/admin/roles/<?= (int)$role['id'] ?>/delete"
                          style="display:inline"
                          onsubmit="return confirm('Delete role &quot;<?= e($role['name']) ?>&quot;? Users will be moved to Public.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="detail-card" style="margin-top: var(--space-xl);">
    <h2>Role Hierarchy</h2>
    <p class="text-muted">
        Roles are ranked by <strong>level</strong> (0–100). Higher-level roles inherit access to areas
        gated by lower levels. For example, a role at level 50 can access anything requiring level 50 or below.
    </p>
    <div class="role-hierarchy-bar">
        <?php foreach ($roles as $role): ?>
        <div class="role-hierarchy-item" style="--role-level: <?= (int)$role['level'] ?>; --role-colour: <?= e($role['colour']) ?>">
            <span class="role-hierarchy-label"><?= e($role['name']) ?></span>
            <span class="role-hierarchy-level"><?= (int)$role['level'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
