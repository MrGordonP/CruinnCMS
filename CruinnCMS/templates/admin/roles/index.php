<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
$selectedId  = $role ? (int)$role['id'] : 0;
$isNew       = false;
$isSystem    = $role && ($role['is_system'] ?? false);
$formAction  = $selectedId ? '/admin/roles/' . $selectedId : '/admin/roles';
?>

<div class="panel-layout" id="roles-layout">

    <!-- Left: role list -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Roles</h3>
            <a href="/admin/roles/new" class="btn btn-primary btn-small">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <?php foreach ($allRoles as $r): ?>
            <a href="/admin/roles?role=<?= (int)$r['id'] ?>"
               class="pl-sidebar-item<?= $r['id'] == $selectedId ? ' active' : '' ?>">
                <span class="role-badge" style="background:<?= e($r['colour'] ?? '#6c757d') ?>;font-size:0.7rem;padding:1px 6px;margin-right:0.5rem"><?= e($r['name']) ?></span>
                <small style="color:var(--color-text-muted);font-size:0.75rem">Lv.<?= (int)$r['level'] ?></small>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main: role form or placeholder -->
    <div class="pl-main">
        <?php if (!$role): ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--color-text-muted);gap:0.5rem">
            <div style="font-size:2rem">🔑</div>
            <p>Select a role to edit, or <a href="/admin/roles/new">create a new one</a>.</p>
        </div>
        <?php else: ?>
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($role['name']) ?></span>
            <div class="pl-main-toolbar-actions">
                <a href="/admin/roles/<?= $selectedId ?>/dashboard" class="btn btn-outline btn-small">Dashboard Config</a>
                <a href="/admin/roles/<?= $selectedId ?>/navigation" class="btn btn-outline btn-small">Nav Config</a>
                <?php if (!$isSystem): ?>
                <form method="post" action="/admin/roles/<?= $selectedId ?>/clone" style="display:inline"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-small">Clone</button>
                </form>
                <form method="post" action="/admin/roles/<?= $selectedId ?>/delete" style="display:inline"
                      data-confirm="Delete role '<?= e($role['name']) ?>'?"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="pl-main-body">

            <?php if (!empty($errors)): ?>
            <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="<?= e($formAction) ?>" class="admin-form">
                <?= csrf_field() ?>
                <?php include __DIR__ . '/_role-fields.php'; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

        </div>
        <?php endif; ?>
    </div>

    <!-- Right: members panel -->
    <div class="pl-detail">
        <div class="pl-detail-header"><h3>Role Members</h3></div>
        <div class="pl-detail-scroll" style="padding:0.75rem">
        <?php if (!$role): ?>
            <p class="text-muted" style="font-size:0.85rem">Select a role to manage its members.</p>
        <?php else: ?>
            <!-- Add user -->
            <div class="detail-card" style="margin-bottom:1rem;padding:0.75rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.82rem;text-transform:uppercase;letter-spacing:0.05em">Add User</h4>
                <div style="display:flex;gap:0.4rem;align-items:center">
                    <select id="add-user-select" class="form-input" style="flex:1;font-size:0.85rem">
                        <option value="">— select user —</option>
                        <?php foreach ($usersNotInRole as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="add-user-btn" class="btn btn-primary btn-small">Add</button>
                </div>
            </div>

            <!-- Current members -->
            <div id="role-members-list">
                <?php foreach ($roleUsers as $u): ?>
                <div class="role-member-row" data-user-id="<?= (int)$u['id'] ?>">
                    <span class="role-member-name"><?= e($u['display_name']) ?></span>
                    <button type="button" class="btn btn-danger btn-small remove-user-btn"
                            data-user-id="<?= (int)$u['id'] ?>"
                            data-name="<?= e($u['display_name']) ?>">✕</button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($roleUsers)): ?>
                <p class="text-muted" id="no-members-msg" style="font-size:0.85rem">No users assigned to this role.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

</div><!-- /.panel-layout -->

<?php if ($role): ?>
<script>
(function() {
    const roleId    = <?= $selectedId ?>;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function post(url, userId) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('user_id', userId);
        return fetch(url, { method: 'POST', body: fd }).then(r => r.json());
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderMembers(users) {
        const list = document.getElementById('role-members-list');
        if (!users.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No users assigned to this role.</p>';
            return;
        }
        list.innerHTML = users.map(u => `
            <div class="role-member-row" data-user-id="${u.id}">
                <span class="role-member-name">${escHtml(u.display_name)}</span>
                <button type="button" class="btn btn-danger btn-small remove-user-btn"
                        data-user-id="${u.id}" data-name="${escHtml(u.display_name)}">✕</button>
            </div>`).join('');
        bindRemoveButtons();
    }

    function bindRemoveButtons() {
        document.querySelectorAll('.remove-user-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const uid  = this.dataset.userId;
                const name = this.dataset.name;
                if (!confirm(`Remove ${name} from this role?`)) return;
                post(`/admin/roles/${roleId}/users/remove`, uid).then(res => {
                    if (res.ok) renderMembers(res.users);
                    else alert(res.error || 'Error removing user.');
                });
            });
        });
    }

    bindRemoveButtons();

    document.getElementById('add-user-btn').addEventListener('click', function() {
        const sel = document.getElementById('add-user-select');
        const uid = sel.value;
        if (!uid) return;
        post(`/admin/roles/${roleId}/users/add`, uid).then(res => {
            if (res.ok) {
                renderMembers(res.users);
                sel.querySelector(`option[value="${uid}"]`)?.remove();
                sel.value = '';
            } else {
                alert(res.error || 'Error adding user.');
            }
        });
    });

    // Colour preview
    const colourInput = document.getElementById('colour');
    if (colourInput) {
        colourInput.addEventListener('input', function() {
            document.getElementById('colour-preview').style.background = this.value;
        });
    }

    // Toggle all permissions in a category
    document.querySelectorAll('.permission-toggle-all').forEach(btn => {
        btn.addEventListener('click', function() {
            const cat = this.dataset.category;
            const checkboxes = document.querySelectorAll(`input[data-category="${cat}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        });
    });
})();
</script>
<?php endif; ?>
