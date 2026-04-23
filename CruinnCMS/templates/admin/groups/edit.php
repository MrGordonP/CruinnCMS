<?php
\Cruinn\Template::requireCss('admin-members.css');
$formAction = '/admin/groups';
?>

<div class="admin-page-header">
    <h1>New Group</h1>
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
            <div class="form-group">
                <label for="slug">Slug <span class="required">*</span></label>
                <input type="text" name="slug" id="slug" class="form-input"
                       value="<?= e($group['slug'] ?? '') ?>" required
                       pattern="[a-z0-9\-]+" placeholder="e.g. field-trip-committee">
                <p class="form-help">Lowercase letters, numbers, and hyphens only.</p>
            </div>
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
                    <option value="custom" <?= ($group['group_type'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>Custom</option>
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
                <p class="form-help">Members inherit the linked role's permissions.</p>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create Group</button>
        <a href="/admin/groups" class="btn btn-secondary">Cancel</a>
    </div>
</form>
