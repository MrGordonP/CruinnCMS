<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<style>
.ml-workspace {
    display: flex;
    height: calc(100vh - 200px);
    gap: 0;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    overflow: hidden;
}

.ml-panel {
    display: flex;
    flex-direction: column;
    background: var(--color-bg-light);
    border-right: 1px solid var(--color-border);
}

.ml-panel:last-child {
    border-right: none;
}

.ml-panel-left {
    width: 280px;
    flex-shrink: 0;
}

.ml-panel-center {
    flex: 1;
    min-width: 0;
}

.ml-panel-right {
    width: 360px;
    flex-shrink: 0;
}

.ml-panel-header {
    padding: var(--space-md);
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(to bottom, #f9f9f9, #f3f3f3);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.ml-panel-title {
    font-weight: 600;
    font-size: 0.95rem;
}

.ml-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-md);
}

.ml-source-selector {
    margin-bottom: var(--space-md);
}

.ml-source-selector label {
    display: block;
    margin-bottom: var(--space-xs);
    font-weight: 600;
    font-size: 0.9rem;
}

.ml-source-selector select {
    width: 100%;
    padding: var(--space-xs) var(--space-sm);
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

.ml-filter-group {
    margin-bottom: var(--space-md);
}

.ml-filter-group label {
    display: block;
    margin-bottom: var(--space-xs);
    font-size: 0.85rem;
    font-weight: 500;
}

.ml-filter-group select,
.ml-filter-group input {
    width: 100%;
    padding: var(--space-xs) var(--space-sm);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 0.85rem;
}

.ml-user-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.ml-user-item {
    padding: var(--space-sm);
    border-bottom: 1px solid var(--color-border);
    cursor: pointer;
    transition: background 0.15s;
}

.ml-user-item:hover {
    background: var(--color-bg);
}

.ml-user-item.selected {
    background: #e6f7ff;
    border-left: 3px solid var(--color-primary);
}

.ml-user-name {
    font-weight: 500;
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.ml-user-email {
    font-size: 0.8rem;
    color: var(--color-text-light);
    margin-bottom: 4px;
}

.ml-user-meta {
    font-size: 0.75rem;
    color: var(--color-text-light);
}

.ml-member-item {
    padding: var(--space-sm);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.ml-member-info {
    flex: 1;
    min-width: 0;
}

.ml-member-actions {
    flex-shrink: 0;
    margin-left: var(--space-sm);
}

.ml-actions-bar {
    padding: var(--space-sm) var(--space-md);
    background: #f9f9f9;
    border-top: 1px solid var(--color-border);
    display: flex;
    gap: var(--space-sm);
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.ml-empty-state {
    padding: var(--space-xl);
    text-align: center;
    color: var(--color-text-light);
}
</style>

<div class="card-header-row" style="margin-bottom: var(--space-lg);">
    <div>
        <h1><?= e($list['name']) ?></h1>
        <p class="text-muted" style="margin: 0.25rem 0 0;"><?= e($list['description'] ?? 'Manage mailing list members') ?></p>
    </div>
    <a href="<?= url('/admin/mailout/lists') ?>" class="btn btn-outline">← Back to Lists</a>
</div>

<div class="ml-workspace">
    <!-- LEFT PANEL: Source & Filters -->
    <div class="ml-panel ml-panel-left">
        <div class="ml-panel-header">
            <span class="ml-panel-title">Source & Filters</span>
        </div>
        <div class="ml-panel-body">
            <form method="GET" action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/members') ?>" id="source-filter-form">
                <div class="ml-source-selector">
                    <label for="source">Member Source</label>
                    <select name="source" id="source" onchange="this.form.submit()">
                        <option value="users" <?= $source === 'users' ? 'selected' : '' ?>>Users (All)</option>
                        <option value="members" <?= $source === 'members' ? 'selected' : '' ?>>Members Only</option>
                        <option value="groups" <?= $source === 'groups' ? 'selected' : '' ?>>Groups</option>
                        <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>>Manual Entry</option>
                    </select>
                </div>

                <?php if ($source === 'groups'): ?>
                    <div class="ml-filter-group">
                        <label for="group_id">Select Group</label>
                        <select name="group_id" id="group_id" onchange="this.form.submit()">
                            <option value="">-- Choose a group --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= (int)$g['id'] ?>" <?= (int)$filterGroupId === (int)$g['id'] ? 'selected' : '' ?>>
                                    <?= e($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (in_array($source, ['users', 'members'], true)): ?>
                    <div class="ml-filter-group">
                        <label for="status">Member Status</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <?php foreach (['active','applicant','lapsed','suspended','resigned','archived'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ml-filter-group">
                        <label for="year">Membership Year</label>
                        <select name="year" id="year" onchange="this.form.submit()">
                            <option value="">All years</option>
                            <?php foreach ($years as $yr): ?>
                                <option value="<?= (int)$yr ?>" <?= (string)$filterYear === (string)$yr ? 'selected' : '' ?>>
                                    <?= (int)$yr ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ml-filter-group">
                        <label for="active">Account Active</label>
                        <select name="active" id="active" onchange="this.form.submit()">
                            <option value="">Any</option>
                            <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-small" style="width: 100%;">Apply Filters</button>
                <a href="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/members') ?>" class="btn btn-outline btn-small" style="width: 100%; margin-top: var(--space-xs);">Clear All</a>
            </form>
        </div>
    </div>

    <!-- CENTER PANEL: Available Users -->
    <div class="ml-panel ml-panel-center">
        <div class="ml-panel-header">
            <span class="ml-panel-title">
                <?php if ($source === 'manual'): ?>
                    Manual Entry
                <?php elseif ($source === 'groups' && $filterGroupId): ?>
                    Group Members
                <?php else: ?>
                    Available <?= $source === 'members' ? 'Members' : 'Users' ?>
                <?php endif; ?>
                <span class="badge badge-muted"><?= count($availableUsers) ?></span>
            </span>
            <div>
                <span class="badge badge-info" id="selected-count-badge">0 selected</span>
            </div>
        </div>
        <div class="ml-panel-body" style="padding: 0;">
            <?php if ($source === 'manual'): ?>
                <!-- Manual Entry Form -->
                <div style="padding: var(--space-md);">
                    <form method="POST" action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/subscribers/add') ?>" id="manual-add-form">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="form-group">
                            <label>Display Name <span style="font-weight:400;">(optional)</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Display name">
                        </div>
                        <button type="submit" class="btn btn-primary">Add to List</button>
                    </form>
                </div>
            <?php elseif (empty($availableUsers)): ?>
                <div class="ml-empty-state">
                    <p>No users match the current filters<br>or all are already on this list.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/subscribers/add') ?>" id="bulk-add-form">
                    <?= csrf_field() ?>
                    <ul class="ml-user-list">
                        <?php foreach ($availableUsers as $u): ?>
                            <li class="ml-user-item" data-user-id="<?= (int)$u['id'] ?>" onclick="toggleUserSelection(this)">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" style="display:none;" class="user-checkbox">
                                <div class="ml-user-name"><?= e($u['display_name']) ?></div>
                                <div class="ml-user-email"><?= e($u['email']) ?></div>
                                <?php if (!empty($u['member_status']) || !empty($u['membership_year'])): ?>
                                    <div class="ml-user-meta">
                                        <?php if ($u['member_status']): ?>
                                            <span class="badge badge-<?= $u['member_status'] === 'active' ? 'success' : 'muted' ?>" style="font-size: 0.7rem;">
                                                <?= e($u['member_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($u['membership_year']): ?>
                                            <span style="margin-left: 0.5em;">Year: <?= (int)$u['membership_year'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($source !== 'manual' && !empty($availableUsers)): ?>
            <div class="ml-actions-bar">
                <div>
                    <button type="button" class="btn btn-outline btn-small" onclick="selectAllUsers()">Select All</button>
                    <button type="button" class="btn btn-outline btn-small" onclick="clearSelection()">Clear</button>
                </div>
                <button type="button" class="btn btn-primary btn-small" onclick="addSelectedUsers()">Add Selected →</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT PANEL: Current Members -->
    <div class="ml-panel ml-panel-right">
        <div class="ml-panel-header">
            <span class="ml-panel-title">Current Members <span class="badge badge-muted"><?= count($members) ?></span></span>
        </div>
        <div class="ml-panel-body" style="padding: 0;">
            <?php if (!empty($pendingMembers)): ?>
                <div style="background: #fffbeb; border-bottom: 2px solid #f59e0b; padding: var(--space-sm);">
                    <strong style="color: #f59e0b;">⚠ <?= count($pendingMembers) ?> Pending Approval</strong>
                </div>
                <?php foreach ($pendingMembers as $p): ?>
                    <div class="ml-member-item" style="background: #fffbeb;">
                        <div class="ml-member-info">
                            <div class="ml-user-name"><?= e($p['display_name'] ?? $p['name'] ?? '') ?></div>
                            <div class="ml-user-email"><?= e($p['email']) ?></div>
                            <div class="ml-user-meta"><?= format_date($p['subscribed_at'], 'j M Y') ?></div>
                        </div>
                        <div class="ml-member-actions">
                            <form action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/subscribers/' . (int)$p['sub_id'] . '/approve') ?>" method="POST" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-primary" title="Approve">✓</button>
                            </form>
                            <form action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/subscribers/' . (int)$p['sub_id'] . '/reject') ?>" method="POST" style="display:inline"
                                onsubmit="return confirm('Reject this request?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger btn-outline" title="Reject">×</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (empty($members)): ?>
                <div class="ml-empty-state">
                    <p>No members yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($members as $m): ?>
                    <div class="ml-member-item">
                        <div class="ml-member-info">
                            <div class="ml-user-name"><?= e($m['display_name'] ?? $m['name'] ?? '') ?></div>
                            <div class="ml-user-email"><?= e($m['email']) ?></div>
                            <div class="ml-user-meta">
                                <span class="badge badge-<?= $m['status'] === 'active' ? 'success' : 'muted' ?>" style="font-size: 0.7rem;">
                                    <?= e($m['status']) ?>
                                </span>
                                <span style="margin-left: 0.5em;"><?= format_date($m['subscribed_at'], 'j M Y') ?></span>
                            </div>
                        </div>
                        <div class="ml-member-actions">
                            <form action="<?= url('/admin/mailout/lists/' . (int)$list['id'] . '/subscribers/' . (int)$m['sub_id'] . '/remove') ?>" method="POST" style="display:inline"
                                onsubmit="return confirm('Remove this member?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger btn-outline" title="Remove">×</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleUserSelection(item) {
    item.classList.toggle('selected');
    const checkbox = item.querySelector('.user-checkbox');
    checkbox.checked = !checkbox.checked;
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.user-checkbox:checked').length;
    document.getElementById('selected-count-badge').textContent = count + ' selected';
}

function selectAllUsers() {
    document.querySelectorAll('.ml-user-item').forEach(item => {
        item.classList.add('selected');
        item.querySelector('.user-checkbox').checked = true;
    });
    updateSelectedCount();
}

function clearSelection() {
    document.querySelectorAll('.ml-user-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('.user-checkbox').checked = false;
    });
    updateSelectedCount();
}

function addSelectedUsers() {
    const form = document.getElementById('bulk-add-form');
    const checked = form.querySelectorAll('.user-checkbox:checked').length;
    if (checked === 0) {
        alert('No users selected');
        return;
    }
    if (confirm(`Add ${checked} user(s) to the mailing list?`)) {
        form.submit();
    }
}
</script>
