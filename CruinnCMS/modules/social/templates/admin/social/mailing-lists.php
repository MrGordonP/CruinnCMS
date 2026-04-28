<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
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
            <div style="display:flex; gap: var(--space-lg); flex-wrap: wrap; margin-top: var(--space-sm);">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="is_public" value="1" checked>
                    Visible to members
                </label>
            </div>
            <div class="form-group" style="margin-top: var(--space-md);">
                <label>Join Mode</label>
                <div style="display:flex; gap: var(--space-lg);">
                    <label class="checkbox-label"><input type="radio" name="subscription_mode" value="open" checked> Open (self-subscribe)</label>
                    <label class="checkbox-label"><input type="radio" name="subscription_mode" value="request"> By Request (admin approval)</label>
                </div>
            </div>
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
                <th>Visibility</th>
                <th>Join Mode</th>
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
                <td>
                    <span class="badge badge-<?= $list['is_public'] ? 'success' : 'muted' ?>">
                        <?= $list['is_public'] ? 'Public' : 'Hidden' ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $list['subscription_mode'] === 'open' ? 'success' : 'warning' ?>">
                        <?= $list['subscription_mode'] === 'open' ? 'Open' : 'By Request' ?>
                    </span>
                </td>
                <td><?= format_date($list['created_at'], 'j M Y') ?></td>
                <td>
                    <a href="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members') ?>" class="btn btn-small btn-outline">Members</a>
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
                            <div class="form-group form-group-half">
                                <input type="text" name="name" class="form-control" value="<?= e($list['name']) ?>" required placeholder="Name">
                            </div>
                            <div class="form-group form-group-half">
                                <input type="text" name="description" class="form-control" value="<?= e($list['description'] ?? '') ?>" placeholder="Description">
                            </div>
                        </div>
                        <div style="display:flex; gap: var(--space-lg); flex-wrap:wrap; margin-bottom: var(--space-sm);">
                            <label class="checkbox-label" style="margin:0;">
                                <input type="checkbox" name="is_active" value="1" <?= $list['is_active'] ? 'checked' : '' ?>>
                                Active
                            </label>
                            <label class="checkbox-label" style="margin:0;">
                                <input type="checkbox" name="is_public" value="1" <?= $list['is_public'] ? 'checked' : '' ?>>
                                Visible to members
                            </label>
                            <label class="checkbox-label" style="margin:0;">
                                <input type="radio" name="subscription_mode" value="open" <?= ($list['subscription_mode'] ?? 'open') === 'open' ? 'checked' : '' ?>>
                                Open
                            </label>
                            <label class="checkbox-label" style="margin:0;">
                                <input type="radio" name="subscription_mode" value="request" <?= ($list['subscription_mode'] ?? '') === 'request' ? 'checked' : '' ?>>
                                By Request
                            </label>
                            <button type="submit" class="btn btn-primary btn-small">Save</button>
                            <button type="button" class="btn btn-outline btn-small cancel-edit-btn" data-id="<?= (int)$list['id'] ?>">Cancel</button>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<script>
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('edit-list-btn')) {
        var id = e.target.dataset.id;
        document.querySelectorAll('.edit-list-row').forEach(function (r) { r.style.display = 'none'; });
        document.getElementById('edit-list-' + id).style.display = '';
    }
    if (e.target.classList.contains('cancel-edit-btn')) {
        document.getElementById('edit-list-' + e.target.dataset.id).style.display = 'none';
    }
});
</script>
