<?php \Cruinn\Template::requireCss('admin-members.css'); ?>
<div class="admin-users">
    <div class="admin-page-header">
        <h1>Users</h1>
        <div class="header-actions">
            <a href="/admin/users/new" class="btn btn-primary btn-small">New User</a>
        </div>
    </div>

    <!-- Role Summary -->
    <div class="role-summary">
        <?php foreach ($roleCounts as $rc): ?>
            <a href="?role=<?= e($rc['role']) ?>"
               class="role-chip <?= $role === $rc['role'] ? 'role-chip-active' : '' ?>">
                <span class="role-chip-label"><?= e(ucfirst($rc['role'])) ?></span>
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
                <?php foreach (['admin', 'council', 'member', 'public'] as $r): ?>
                    <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
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
                        <span class="badge badge-role-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span>
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
</div>
