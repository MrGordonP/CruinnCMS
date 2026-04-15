<?php
$isNew = empty($user['id']);
\Cruinn\Template::requireCss('admin-members.css');
$formAction = $isNew ? '/admin/users' : '/admin/users/' . (int)$user['id'];
?>

<div class="admin-page-header">
    <h1><?= $isNew ? 'New User' : 'Edit User' ?></h1>
    <div class="header-actions">
        <?php if (!$isNew): ?>
            <a href="/admin/users/<?= (int)$user['id'] ?>" class="btn btn-outline btn-small">View Profile</a>
        <?php endif; ?>
        <a href="/admin/users" class="btn btn-outline btn-small">Back to Users</a>
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
        <h2>Account Details</h2>

        <div class="form-grid">
            <div class="form-group">
                <label for="display_name">Display Name <span class="required">*</span></label>
                <input type="text" name="display_name" id="display_name" class="form-input"
                       value="<?= e($user['display_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" name="email" id="email" class="form-input"
                       value="<?= e($user['email'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="password">
                    Password
                    <?php if ($isNew): ?><span class="required">*</span><?php endif; ?>
                </label>
                <input type="password" name="password" id="password" class="form-input"
                       <?= $isNew ? 'required' : '' ?> minlength="8"
                       placeholder="<?= $isNew ? 'Minimum 8 characters' : 'Leave blank to keep current' ?>">
                <?php if (!$isNew): ?>
                    <p class="form-help">Leave blank to keep the current password.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label><strong>Roles</strong></label>
                <p class="form-help" style="margin-bottom:0.5rem;">Assign one or more roles. The highest-level role becomes the primary role. <a href="/admin/roles">Manage Roles</a></p>
                <?php
                $assignedRoleIds = $userRoleIds ?? ($user['role_id'] ? [(int)$user['role_id']] : []);
                foreach ($allRoles as $r):
                    $defaultChecked = $isNew && $r['slug'] === 'member';
                ?>
                <label class="checkbox-label" style="display:block; margin-bottom:0.4rem;">
                    <input type="checkbox" name="role_ids[]" value="<?= (int)$r['id'] ?>"
                           <?= in_array((int)$r['id'], $assignedRoleIds) || $defaultChecked ? 'checked' : '' ?>>
                    <strong><?= e($r['name']) ?></strong>
                    <?php if ($r['description']): ?><span class="form-help" style="margin-left:0.3rem;">— <?= e($r['description']) ?></span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1"
                       <?= ($user['active'] ?? 1) ? 'checked' : '' ?>>
                Account is active
            </label>
            <p class="form-help">Inactive accounts cannot log in.</p>
        </div>
    </div>

    <?php if (!$isNew && !empty($allGroups)): ?>
    <div class="detail-card">
        <h2>Group Membership</h2>
        <p class="form-help" style="margin-bottom:1rem;">Groups represent organisational units (committees, working groups). Users inherit permissions from any role linked to their groups.</p>
        <div class="form-group">
            <?php
            $userGroupIds = array_column($userGroups ?? [], 'id');
            foreach ($allGroups as $g):
            ?>
            <label class="checkbox-label" style="display:block; margin-bottom:0.4rem;">
                <input type="checkbox" name="group_ids[]" value="<?= (int)$g['id'] ?>"
                       <?= in_array((int)$g['id'], $userGroupIds) ? 'checked' : '' ?>>
                <strong><?= e($g['name']) ?></strong>
                <?php if ($g['description']): ?><span class="form-help" style="margin-left:0.3rem;"><?= e($g['description']) ?></span><?php endif; ?>
                <span class="badge badge-muted" style="margin-left:0.3rem;"><?= e($g['group_type']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create User' : 'Save Changes' ?></button>
        <a href="/admin/users" class="btn btn-secondary">Cancel</a>
    </div>
</form>
