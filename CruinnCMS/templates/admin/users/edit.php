<?php
$isNew = empty($user['id']);
\Cruinn\Template::requireCss('admin-members.css');
if (!$isNew) {
    \Cruinn\Template::requireCss('admin-panel-layout.css');
    $GLOBALS['admin_flush_layout'] = true;
}
$formAction = $isNew ? '/admin/users' : '/admin/users/' . (int)$user['id'];
?>

<?php if ($isNew): ?>
<!-- ─── NEW USER: simple form ─── -->
<div class="admin-page-header">
    <h1>New User</h1>
    <div class="header-actions">
        <a href="/admin/users" class="btn btn-outline btn-small">Back to Users</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
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
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" class="form-input" required minlength="8"
                       placeholder="Minimum 8 characters">
            </div>
            <div class="form-group">
                <label><strong>Roles</strong></label>
                <p class="form-help" style="margin-bottom:0.5rem">Assign one or more roles.</p>
                <?php foreach ($allRoles as $r): $defaultChecked = $r['slug'] === 'editor'; ?>
                <label class="checkbox-label" style="display:block;margin-bottom:0.4rem">
                    <input type="checkbox" name="role_ids[]" value="<?= (int)$r['id'] ?>"
                           <?= $defaultChecked ? 'checked' : '' ?>>
                    <strong><?= e($r['name']) ?></strong>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" <?= ($user['active'] ?? 1) ? 'checked' : '' ?>>
                Account is active
            </label>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create User</button>
        <a href="/admin/users" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php else: ?>
<!-- ─── EDIT USER: 2-column panel layout ─── -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:0;height:100%;overflow:hidden" data-user-id="<?= (int)$user['id'] ?>">

    <!-- Left: account form -->
    <div style="overflow-y:auto;padding:1.5rem;border-right:1px solid var(--color-border,#e5e7eb)">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h1 style="margin:0;font-size:1.3rem"><?= e($user['display_name'] ?? '') ?></h1>
            <div style="display:flex;gap:0.5rem">
                <a href="/admin/users" class="btn btn-outline btn-small">Back</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
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
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-input"
                               minlength="8" placeholder="Leave blank to keep current">
                        <p class="form-help">Leave blank to keep the current password.</p>
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
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Right: roles + groups panels -->
    <div style="overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:1rem">

        <!-- Roles panel -->
        <div class="detail-card" style="padding:0.75rem">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em">Roles</h3>
            <div id="user-roles-list" style="margin-bottom:0.75rem">
                <?php foreach ($userRoles as $r): ?>
                <div class="role-member-row" data-role-id="<?= (int)$r['id'] ?>">
                    <span class="role-member-name">
                        <?php if (!empty($r['colour'])): ?>
                        <span class="role-badge" style="background:<?= e($r['colour']) ?>;font-size:0.7rem;padding:1px 5px;margin-right:0.4rem"><?= e($r['name']) ?></span>
                        <?php else: ?>
                        <?= e($r['name']) ?>
                        <?php endif; ?>
                    </span>
                    <button type="button" class="btn btn-danger btn-small remove-role-btn"
                            data-role-id="<?= (int)$r['id'] ?>"
                            data-name="<?= e($r['name']) ?>">✕</button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($userRoles)): ?>
                <p class="text-muted" style="font-size:0.85rem">No roles assigned.</p>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:0.4rem;align-items:center">
                <select id="add-role-select" class="form-input" style="flex:1;font-size:0.85rem">
                    <option value="">— add role —</option>
                    <?php foreach ($rolesNotAssigned as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="add-role-btn" class="btn btn-primary btn-small">Add</button>
            </div>
        </div>

        <!-- Groups panel -->
        <div class="detail-card" style="padding:0.75rem">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em">Groups</h3>
            <div id="user-groups-list" style="margin-bottom:0.75rem">
                <?php foreach ($userGroups as $g): ?>
                <div class="role-member-row" data-group-id="<?= (int)$g['id'] ?>">
                    <span class="role-member-name"><?= e($g['name']) ?></span>
                    <button type="button" class="btn btn-danger btn-small remove-group-btn"
                            data-group-id="<?= (int)$g['id'] ?>"
                            data-name="<?= e($g['name']) ?>">✕</button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($userGroups)): ?>
                <p class="text-muted" style="font-size:0.85rem">No groups assigned.</p>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:0.4rem;align-items:center">
                <select id="add-group-select" class="form-input" style="flex:1;font-size:0.85rem">
                    <option value="">— add group —</option>
                    <?php foreach ($groupsNotAssigned as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="add-group-btn" class="btn btn-primary btn-small">Add</button>
            </div>
        </div>

        <!-- Linked Member -->
        <div class="detail-card" style="padding:0.75rem">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em">Linked Member</h3>
            <?php if (!empty($member)): ?>
            <p style="margin:0 0 0.5rem;font-size:0.85rem">
                <a href="<?= url('/admin/membership?member=' . (int)$member['id']) ?>">
                    <?= e(trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? ''))) ?>
                </a>
                <?php if (!empty($member['membership_number'])): ?>
                <span class="text-muted"> #<?= e($member['membership_number']) ?></span>
                <?php endif; ?>
                <br><span class="badge badge-<?= e($member['status']) ?>" style="font-size:0.75rem"><?= e(ucfirst($member['status'])) ?></span>
            </p>
            <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/unlink-member">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-small"
                        data-confirm="Remove the link between this user and their member record?">Unlink</button>
            </form>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.85rem;margin:0 0 0.5rem">No member record linked.</p>
            <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/link-member" style="display:flex;gap:0.4rem;position:relative">
                <?= csrf_field() ?>
                <div style="flex:1;position:relative">
                    <input class="form-input" type="text" id="member-search-input" name="member_search"
                           data-search-url="<?= url('/admin/membership/members/search') ?>"
                           placeholder="Name, number or email" style="width:100%;font-size:0.85rem" required autocomplete="off">
                    <ul id="member-search-list" style="display:none;position:absolute;z-index:999;top:100%;left:0;right:0;
                        margin:2px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #ccd9d3;
                        border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.12);max-height:180px;overflow-y:auto;font-size:0.82rem"></ul>
                </div>
                <button type="submit" class="btn btn-primary btn-small">Link</button>
            </form>
            <?php \Cruinn\Template::requireJs('member-search.js'); ?>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="detail-card" style="padding:0.75rem">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em">Actions</h3>
            <?php if ((int)$user['id'] !== \Cruinn\Auth::userId()): ?>
            <div style="display:flex;flex-direction:column;gap:0.4rem">
                <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/toggle">
                    <?= csrf_field() ?>
                    <?php if ($user['active']): ?>
                    <button type="submit" class="btn btn-secondary btn-small" style="width:100%"
                            data-confirm="Deactivate this user?">Deactivate Account</button>
                    <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-small" style="width:100%">Activate Account</button>
                    <?php endif; ?>
                </form>
                <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/delete"
                      data-confirm="Permanently delete <?= e($user['email']) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small" style="width:100%">Delete User</button>
                </form>
            </div>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.82rem">Cannot modify your own account here.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php if (!empty($activity)): ?>
<div style="padding:1.25rem;border-top:1px solid var(--color-border,#e5e7eb)">
    <h2 style="font-size:0.9rem;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.75rem">Recent Activity</h2>
    <div style="overflow-x:auto">
    <table class="admin-table">
        <thead><tr><th>When</th><th>Action</th><th>What</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($activity as $a): ?>
        <tr>
            <td><time datetime="<?= e($a['created_at']) ?>"><?= format_date($a['created_at'], 'j M H:i') ?></time></td>
            <td><span class="badge badge-<?= e($a['action']) ?>"><?= e(ucfirst($a['action'])) ?></span></td>
            <td><?= e(ucfirst($a['entity_type'])) ?><?= $a['entity_id'] ? ' #'.$a['entity_id'] : '' ?></td>
            <td><?= e(truncate($a['details'] ?? '', 80)) ?></td>
            <td class="text-muted"><?= e($a['ip_address'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php \Cruinn\Template::requireJs('user-profile.js'); ?>

<?php endif; ?>
