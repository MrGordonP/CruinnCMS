<?php \Cruinn\Template::requireCss('admin-members.css'); ?>
<div class="admin-page-header">
    <h1>Groups</h1>
    <div class="header-actions">
        <a href="/admin/groups/new" class="btn btn-primary btn-small">New Group</a>
    </div>
</div>

<p class="page-description">Groups represent organisational units — committees, working groups, and interest groups. Users can belong to multiple groups simultaneously. Groups with a linked role grant those role's permissions to their members.</p>

<?php if (empty($groups)): ?>
    <p class="block-empty">No groups defined yet.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Linked Role</th>
            <th>Members</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
            <td>
                <strong><?= e($g['name']) ?></strong>
                <?php if ($g['description']): ?><br><small class="text-muted"><?= e($g['description']) ?></small><?php endif; ?>
            </td>
            <td><span class="badge badge-muted"><?= e($g['group_type']) ?></span></td>
            <td><?= $g['role_id'] ? e($g['role_id']) : '<span class="text-muted">None</span>' ?></td>
            <td><?= (int)$g['member_count'] ?></td>
            <td>
                <a href="/admin/groups/<?= (int)$g['id'] ?>/edit" class="btn btn-small">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
