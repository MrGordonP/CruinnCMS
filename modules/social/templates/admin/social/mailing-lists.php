<?php \IGA\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>Mailing Lists</h1>
        <div class="social-hub-actions">
            <a href="<?= url('/admin/social') ?>" class="btn btn-outline">Back to Hub</a>
            <button class="btn btn-primary" onclick="document.getElementById('newListForm').style.display='block'">+ New List</button>
        </div>
    </div>

    <!-- New List Form (hidden) -->
    <div id="newListForm" class="social-section" style="display: none; margin-bottom: var(--space-lg);">
        <h2>Create Mailing List</h2>
        <form action="<?= url('/admin/social/mailing-lists') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group form-group-half">
                    <label>List Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Monthly Newsletter">
                </div>
                <div class="form-group form-group-half">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Brief description...">
                </div>
            </div>
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>
            <div style="margin-top: var(--space-md);">
                <button type="submit" class="btn btn-primary">Create List</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('newListForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>

    <?php if (empty($lists)): ?>
        <div class="empty-state">
            <p>No mailing lists created yet.</p>
        </div>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lists as $list): ?>
            <tr>
                <td><strong><?= e($list['name']) ?></strong></td>
                <td><?= e($list['description'] ?? '') ?></td>
                <td>
                    <span class="badge badge-<?= $list['is_active'] ? 'success' : 'muted' ?>">
                        <?= $list['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td><?= format_date($list['created_at'], 'j M Y') ?></td>
                <td>
                    <button class="btn btn-small btn-outline edit-list-btn"
                        data-id="<?= (int)$list['id'] ?>"
                        data-name="<?= e($list['name']) ?>"
                        data-description="<?= e($list['description'] ?? '') ?>"
                        data-active="<?= $list['is_active'] ?>">Edit</button>
                    <form action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/delete') ?>" method="POST" style="display:inline"
                        onsubmit="return confirm('Delete this mailing list?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger btn-outline">Delete</button>
                    </form>
                </td>
            </tr>
            <!-- Inline edit form (hidden) -->
            <tr class="edit-list-row" id="edit-list-<?= (int)$list['id'] ?>" style="display: none;">
                <td colspan="5">
                    <form action="<?= url('/admin/social/mailing-lists') ?>" method="POST" class="inline-edit-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$list['id'] ?>">
                        <div class="form-row">
                            <div class="form-group form-group-third">
                                <input type="text" name="name" class="form-control" value="<?= e($list['name']) ?>" required>
                            </div>
                            <div class="form-group form-group-third">
                                <input type="text" name="description" class="form-control" value="<?= e($list['description'] ?? '') ?>">
                            </div>
                            <div class="form-group form-group-third" style="display:flex; align-items:center; gap: var(--space-sm);">
                                <label class="checkbox-label" style="margin:0;">
                                    <input type="checkbox" name="is_active" value="1" <?= $list['is_active'] ? 'checked' : '' ?>>
                                    Active
                                </label>
                                <button type="submit" class="btn btn-primary btn-small">Save</button>
                                <button type="button" class="btn btn-outline btn-small cancel-edit-btn" data-id="<?= (int)$list['id'] ?>">Cancel</button>
                            </div>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
