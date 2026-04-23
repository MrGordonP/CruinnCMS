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
<div style="display:grid;grid-template-columns:1fr 300px;gap:0;height:100%;overflow:hidden">

    <!-- Left: account form -->
    <div style="overflow-y:auto;padding:1.5rem;border-right:1px solid var(--color-border,#e5e7eb)">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h1 style="margin:0;font-size:1.3rem"><?= e($user['display_name'] ?? '') ?></h1>
            <div style="display:flex;gap:0.5rem">
                <a href="/admin/users/<?= (int)$user['id'] ?>" class="btn btn-outline btn-small">View Profile</a>
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

    </div>
</div>

<script>
(function() {
    const userId    = <?= (int)$user['id'] ?>;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function post(url, payload) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));
        return fetch(url, { method: 'POST', body: fd }).then(r => r.json());
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Roles ──
    function renderRoles(roles) {
        const list = document.getElementById('user-roles-list');
        if (!roles.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No roles assigned.</p>';
            return;
        }
        list.innerHTML = roles.map(r => `
            <div class="role-member-row" data-role-id="${r.id}">
                <span class="role-member-name">${r.colour
                    ? `<span class="role-badge" style="background:${esc(r.colour)};font-size:0.7rem;padding:1px 5px;margin-right:0.4rem">${esc(r.name)}</span>`
                    : esc(r.name)}</span>
                <button type="button" class="btn btn-danger btn-small remove-role-btn"
                        data-role-id="${r.id}" data-name="${esc(r.name)}">✕</button>
            </div>`).join('');
        bindRoleRemove();
    }

    function bindRoleRemove() {
        document.querySelectorAll('.remove-role-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const roleId = this.dataset.roleId;
                const name   = this.dataset.name;
                if (!confirm(`Remove role "${name}" from this user?`)) return;
                post(`/admin/users/${userId}/roles/remove`, { role_id: roleId }).then(res => {
                    if (res.ok) {
                        renderRoles(res.roles);
                        const sel = document.getElementById('add-role-select');
                        if (sel && !sel.querySelector(`option[value="${roleId}"]`)) {
                            const opt = document.createElement('option');
                            opt.value = roleId; opt.textContent = name;
                            sel.appendChild(opt);
                        }
                    } else { alert(res.error || 'Error removing role.'); }
                });
            });
        });
    }

    bindRoleRemove();

    document.getElementById('add-role-btn').addEventListener('click', function() {
        const sel = document.getElementById('add-role-select');
        const roleId = sel.value;
        if (!roleId) return;
        post(`/admin/users/${userId}/roles/add`, { role_id: roleId }).then(res => {
            if (res.ok) {
                renderRoles(res.roles);
                sel.querySelector(`option[value="${roleId}"]`)?.remove();
                sel.value = '';
            } else { alert(res.error || 'Error adding role.'); }
        });
    });

    // ── Groups ──
    function renderGroups(groups) {
        const list = document.getElementById('user-groups-list');
        if (!groups.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No groups assigned.</p>';
            return;
        }
        list.innerHTML = groups.map(g => `
            <div class="role-member-row" data-group-id="${g.id}">
                <span class="role-member-name">${esc(g.name)}</span>
                <button type="button" class="btn btn-danger btn-small remove-group-btn"
                        data-group-id="${g.id}" data-name="${esc(g.name)}">✕</button>
            </div>`).join('');
        bindGroupRemove();
    }

    function bindGroupRemove() {
        document.querySelectorAll('.remove-group-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const groupId = this.dataset.groupId;
                const name    = this.dataset.name;
                if (!confirm(`Remove group "${name}" from this user?`)) return;
                post(`/admin/users/${userId}/groups/remove`, { group_id: groupId }).then(res => {
                    if (res.ok) {
                        renderGroups(res.groups);
                        const sel = document.getElementById('add-group-select');
                        if (sel && !sel.querySelector(`option[value="${groupId}"]`)) {
                            const opt = document.createElement('option');
                            opt.value = groupId; opt.textContent = name;
                            sel.appendChild(opt);
                        }
                    } else { alert(res.error || 'Error removing group.'); }
                });
            });
        });
    }

    bindGroupRemove();

    document.getElementById('add-group-btn').addEventListener('click', function() {
        const sel = document.getElementById('add-group-select');
        const groupId = sel.value;
        if (!groupId) return;
        post(`/admin/users/${userId}/groups/add`, { group_id: groupId }).then(res => {
            if (res.ok) {
                renderGroups(res.groups);
                sel.querySelector(`option[value="${groupId}"]`)?.remove();
                sel.value = '';
            } else { alert(res.error || 'Error adding group.'); }
        });
    });
})();
</script>

<?php endif; ?>
