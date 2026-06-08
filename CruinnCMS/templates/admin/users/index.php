<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-members.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout no-detail" id="users-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header"><span class="pl-panel-title">Users</span><button type="button" class="pl-panel-toggle" title="Collapse">&#x25C0;</button></div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item active" href="<?= url('/admin/users') ?>">All Users</a>
        <a class="pl-nav-item" href="<?= url('/admin/users/new') ?>">New User</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Users</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/users/new') ?>" class="btn btn-small btn-primary">+ New User</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <!-- Role Summary -->
    <div class="role-summary">
        <?php foreach ($roleCounts as $rc): ?>
            <a href="?role=<?= e((string) ($rc['role_slug'] ?? '')) ?>"
               class="role-chip <?= $role === (string) ($rc['role_slug'] ?? '') ? 'role-chip-active' : '' ?>">
                <span class="role-chip-label"><?= e((string) ($rc['role_name'] ?? 'Unassigned')) ?></span>
                <span class="role-chip-count"><?= (int)$rc['cnt'] ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($role): ?>
            <a href="/admin/users" class="role-chip">All</a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form class="filter-bar" method="get" action="/admin/users">
        <div class="filter-group">
            <select name="role" class="form-select">
                <option value="">All Roles</option>
                <?php foreach ($roleOptions as $r): ?>
                    <option value="<?= e($r['slug']) ?>" <?= $role === $r['slug'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="filter-group">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name or email…" class="form-input">
        </div>
        <button type="submit" class="btn btn-secondary btn-small">Filter</button>
        <?php if ($role || $status || $search): ?>
            <a href="/admin/users" class="btn btn-link btn-small">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <p class="empty-state">No users found.</p>
    <?php else: ?>
        <p class="result-count"><?= (int)$total ?> user<?= $total != 1 ? 's' : '' ?></p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['active'] ? 'row-inactive' : '' ?>">
                    <td>
                        <a href="/admin/users/<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></a>
                    </td>
                    <td class="text-muted"><?= e($u['email']) ?></td>
                    <td>
                        <?php $roleSlug = $u['primary_role_slug'] ?? null; ?>
                        <span class="badge badge-role-<?= e($roleSlug ?: 'none') ?>"><?= e($u['primary_role_name'] ?? 'Unassigned') ?></span>
                    </td>
                    <td>
                        <?php if ($u['active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['last_login']): ?>
                            <time datetime="<?= e($u['last_login']) ?>"><?= format_date($u['last_login'], 'j M Y H:i') ?></time>
                        <?php else: ?>
                            <span class="text-muted">Never</span>
                        <?php endif; ?>
                    </td>
                    <td><time datetime="<?= e($u['created_at']) ?>"><?= format_date($u['created_at'], 'j M Y') ?></time></td>
                    <td class="actions-cell">
                        <a href="/admin/users/<?= (int)$u['id'] ?>/edit" class="btn btn-outline btn-xs">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&role=<?= e($role) ?>&status=<?= e($status) ?>&q=<?= e($search) ?>"
                   class="btn btn-secondary btn-sm">&laquo; Previous</a>
            <?php endif; ?>
            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&role=<?= e($role) ?>&status=<?= e($status) ?>&q=<?= e($search) ?>"
                   class="btn btn-secondary btn-sm">Next &raquo;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
