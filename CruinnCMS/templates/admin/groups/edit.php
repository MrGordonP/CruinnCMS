<?php
$isNew = empty($group['id']);
\Cruinn\Template::requireCss('admin-members.css');
$formAction = $isNew ? '/admin/groups' : '/admin/groups/' . (int)$group['id'];
?>

<div class="admin-page-header">
    <h1><?= $isNew ? 'New Group' : 'Edit Group' ?></h1>
    <div class="header-actions">
        <a href="/admin/groups" class="btn btn-outline btn-small">Back to Groups</a>
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

    <div class="detail-card">
        <h2>Group Details</h2>

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Group Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-input"
                       value="<?= e($group['name'] ?? '') ?>" required>
            </div>

            <?php if ($isNew): ?>
            <div class="form-group">
                <label for="slug">Slug <span class="required">*</span></label>
                <input type="text" name="slug" id="slug" class="form-input"
                       value="<?= e($group['slug'] ?? '') ?>" required
                       pattern="[a-z0-9\-]+" placeholder="e.g. field-trip-committee">
                <p class="form-help">Lowercase letters, numbers, and hyphens only.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" name="description" id="description" class="form-input"
                   value="<?= e($group['description'] ?? '') ?>" placeholder="What this group is for">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="group_type">Group Type</label>
                <select name="group_type" id="group_type" class="form-select">
                    <option value="committee" <?= ($group['group_type'] ?? '') === 'committee' ? 'selected' : '' ?>>Committee</option>
                    <option value="working_group" <?= ($group['group_type'] ?? '') === 'working_group' ? 'selected' : '' ?>>Working Group</option>
                    <option value="interest" <?= ($group['group_type'] ?? '') === 'interest' ? 'selected' : '' ?>>Interest Group</option>
                    <option value="custom" <?= ($group['group_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>
            </div>

            <div class="form-group">
                <label for="role_id">Linked Role (optional)</label>
                <select name="role_id" id="role_id" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($allRoles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= ((int)($group['role_id'] ?? 0)) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">Members of this group inherit the linked role's permissions.</p>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Group' : 'Save Changes' ?></button>
        <a href="/admin/groups" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php if (!$isNew): ?>
<div class="detail-card" style="margin-top: 2rem;">
    <div class="card-header-row">
        <h2>Members (<?= count($members) ?>)</h2>
        <?php if (!empty($nonMembers)): ?>
        <form method="post" action="/admin/groups/<?= (int)$group['id'] ?>/members/add" class="inline-form">
            <?= csrf_field() ?>
            <select name="user_id" class="form-select form-select--sm" required>
                <option value="">Add a member…</option>
                <?php foreach ($nonMembers as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?> (<?= e($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-small">Add</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (empty($members)): ?>
        <p class="block-empty">No members assigned to this group yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Assigned</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
                <td><a href="/admin/users/<?= (int)$m['id'] ?>/edit"><?= e($m['display_name']) ?></a></td>
                <td><?= e($m['email']) ?></td>
                <td><span class="badge"><?= e($m['role']) ?></span></td>
                <td><?= format_date($m['assigned_at']) ?></td>
                <td>
                    <form method="post" action="/admin/groups/<?= (int)$group['id'] ?>/members/<?= (int)$m['id'] ?>/remove"
                          onsubmit="return confirm('Remove <?= e(addslashes($m['display_name'])) ?> from this group?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-small">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="detail-card danger-zone" style="margin-top: 2rem;">
    <h2>Danger Zone</h2>
    <form method="post" action="/admin/groups/<?= (int)$group['id'] ?>/delete" onsubmit="return confirm('Delete this group? Members will be unlinked.')">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">Delete Group</button>
    </form>
</div>
<?php endif; ?>
