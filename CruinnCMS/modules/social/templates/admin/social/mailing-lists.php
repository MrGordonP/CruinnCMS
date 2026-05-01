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
                <label class="checkbox-label">
                    <input type="checkbox" name="is_dynamic" value="1" id="new-is-dynamic" onchange="toggleDynamicFields('new')">
                    Dynamic (Auto-populate)
                </label>
            </div>
            <div class="form-group" style="margin-top: var(--space-md);" id="new-join-mode">
                <label>Join Mode</label>
                <div style="display:flex; gap: var(--space-lg);">
                    <label class="checkbox-label"><input type="radio" name="subscription_mode" value="open" checked> Open (self-subscribe)</label>
                    <label class="checkbox-label"><input type="radio" name="subscription_mode" value="request"> By Request (admin approval)</label>
                </div>
            </div>
            <div id="new-dynamic-config" style="display:none; margin-top: var(--space-md); padding: var(--space-md); background: #f9f9f9; border-radius: 4px;">
                <h3 style="margin: 0 0 var(--space-sm); font-size: 0.95rem;">Auto-Population Criteria</h3>
                <div class="form-group">
                    <label>Source</label>
                    <select name="source_table" class="form-control" id="new-source-table" onchange="toggleSourceFields('new')">
                        <option value="">-- Select source --</option>
                        <option value="members">Members</option>
                        <option value="users">Users</option>
                        <option value="groups">Groups</option>
                    </select>
                </div>
                <div id="new-members-criteria" style="display:none;">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label>Member Status</label>
                            <select name="criteria_status" class="form-control">
                                <option value="">Any</option>
                                <option value="active">Active</option>
                                <option value="applicant">Applicant</option>
                                <option value="lapsed">Lapsed</option>
                            </select>
                        </div>
                        <div class="form-group form-group-half">
                            <label>Membership Year</label>
                            <input type="number" name="criteria_year" class="form-control" placeholder="e.g. 2026" min="2000" max="2100">
                        </div>
                    </div>
                </div>
                <div id="new-users-criteria" style="display:none;">
                    <div class="form-group">
                        <label>Account Active</label>
                        <select name="criteria_active" class="form-control">
                            <option value="">Any</option>
                            <option value="1">Active only</option>
                            <option value="0">Inactive only</option>
                        </select>
                    </div>
                </div>
                <div id="new-groups-criteria" style="display:none;">
                    <div class="form-group">
                        <label>Group</label>
                        <select name="criteria_group_id" class="form-control">
                            <option value="">-- Select group --</option>
                            <?php foreach ($availableGroups ?? [] as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                <th>Type</th>
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
                    <?php if (!empty($list['is_dynamic'])): ?>
                        <span class="badge badge-info" title="Auto-populated from <?= e($list['source_table'] ?? 'query') ?>">
                            Dynamic
                        </span>
                        <?php if ($list['last_synced_at']): ?>
                            <div style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 2px;">
                                Synced: <?= format_date($list['last_synced_at'], 'j M, H:i') ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-muted">Manual</span>
                    <?php endif; ?>
                </td>
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
                    <?php if (!empty($list['is_dynamic'])): ?>
                        <form action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/sync') ?>" method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-small btn-primary" title="Sync members from source">↻ Sync</button>
                        </form>
                    <?php endif; ?>
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

function toggleDynamicFields(prefix) {
    const isDynamic = document.getElementById(prefix + '-is-dynamic').checked;
    const dynamicConfig = document.getElementById(prefix + '-dynamic-config');
    const joinMode = document.getElementById(prefix + '-join-mode');

    if (isDynamic) {
        dynamicConfig.style.display = 'block';
        if (joinMode) joinMode.style.display = 'none';
    } else {
        dynamicConfig.style.display = 'none';
        if (joinMode) joinMode.style.display = 'block';
    }
}

function toggleSourceFields(prefix) {
    const sourceTable = document.getElementById(prefix + '-source-table').value;
    const membersCriteria = document.getElementById(prefix + '-members-criteria');
    const usersCriteria = document.getElementById(prefix + '-users-criteria');
    const groupsCriteria = document.getElementById(prefix + '-groups-criteria');

    membersCriteria.style.display = 'none';
    usersCriteria.style.display = 'none';
    groupsCriteria.style.display = 'none';

    if (sourceTable === 'members') {
        membersCriteria.style.display = 'block';
    } else if (sourceTable === 'users') {
        usersCriteria.style.display = 'block';
    } else if (sourceTable === 'groups') {
        groupsCriteria.style.display = 'block';
    }
}
</script>
