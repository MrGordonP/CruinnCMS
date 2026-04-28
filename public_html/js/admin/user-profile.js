(function () {
    // Roles + Groups AJAX management on user show/edit pages.
    // Reads data-user-id from the nearest [data-user-id] ancestor element.
    var container = document.querySelector('[data-user-id]');
    if (!container) return;

    var userId    = parseInt(container.dataset.userId, 10);
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function post(url, payload) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        Object.entries(payload).forEach(function (kv) { fd.append(kv[0], kv[1]); });
        return fetch(url, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Roles ──

    function renderRoles(roles) {
        var list = document.getElementById('user-roles-list');
        if (!list) return;
        if (!roles.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No roles assigned.</p>';
            return;
        }
        list.innerHTML = roles.map(function (r) {
            var badge = r.colour
                ? '<span class="role-badge" style="background:' + esc(r.colour) + ';font-size:0.7rem;padding:1px 5px;margin-right:0.4rem">' + esc(r.name) + '</span>'
                : esc(r.name);
            return '<div class="role-member-row" data-role-id="' + r.id + '">'
                + '<span class="role-member-name">' + badge + '</span>'
                + '<button type="button" class="btn btn-danger btn-small remove-role-btn"'
                + ' data-role-id="' + r.id + '" data-name="' + esc(r.name) + '">✕</button>'
                + '</div>';
        }).join('');
        bindRoleRemove();
    }

    function bindRoleRemove() {
        document.querySelectorAll('.remove-role-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var roleId = this.dataset.roleId;
                var name   = this.dataset.name;
                if (!confirm('Remove role "' + name + '" from this user?')) return;
                post('/admin/users/' + userId + '/roles/remove', { role_id: roleId }).then(function (res) {
                    if (res.ok) {
                        renderRoles(res.roles);
                        var sel = document.getElementById('add-role-select');
                        if (sel && !sel.querySelector('option[value="' + roleId + '"]')) {
                            var opt = document.createElement('option');
                            opt.value = roleId;
                            opt.textContent = name;
                            sel.appendChild(opt);
                        }
                    } else { alert(res.error || 'Error removing role.'); }
                });
            });
        });
    }

    bindRoleRemove();

    var addRoleBtn = document.getElementById('add-role-btn');
    if (addRoleBtn) {
        addRoleBtn.addEventListener('click', function () {
            var sel    = document.getElementById('add-role-select');
            var roleId = sel.value;
            if (!roleId) return;
            post('/admin/users/' + userId + '/roles/add', { role_id: roleId }).then(function (res) {
                if (res.ok) {
                    renderRoles(res.roles);
                    var opt = sel.querySelector('option[value="' + roleId + '"]');
                    if (opt) opt.remove();
                    sel.value = '';
                } else { alert(res.error || 'Error adding role.'); }
            });
        });
    }

    // ── Groups ──

    function renderGroups(groups) {
        var list = document.getElementById('user-groups-list');
        if (!list) return;
        if (!groups.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No groups assigned.</p>';
            return;
        }
        list.innerHTML = groups.map(function (g) {
            return '<div class="role-member-row" data-group-id="' + g.id + '">'
                + '<span class="role-member-name">' + esc(g.name) + '</span>'
                + '<button type="button" class="btn btn-danger btn-small remove-group-btn"'
                + ' data-group-id="' + g.id + '" data-name="' + esc(g.name) + '">✕</button>'
                + '</div>';
        }).join('');
        bindGroupRemove();
    }

    function bindGroupRemove() {
        document.querySelectorAll('.remove-group-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var groupId = this.dataset.groupId;
                var name    = this.dataset.name;
                if (!confirm('Remove group "' + name + '" from this user?')) return;
                post('/admin/users/' + userId + '/groups/remove', { group_id: groupId }).then(function (res) {
                    if (res.ok) {
                        renderGroups(res.groups);
                        var sel = document.getElementById('add-group-select');
                        if (sel && !sel.querySelector('option[value="' + groupId + '"]')) {
                            var opt = document.createElement('option');
                            opt.value = groupId;
                            opt.textContent = name;
                            sel.appendChild(opt);
                        }
                    } else { alert(res.error || 'Error removing group.'); }
                });
            });
        });
    }

    bindGroupRemove();

    var addGroupBtn = document.getElementById('add-group-btn');
    if (addGroupBtn) {
        addGroupBtn.addEventListener('click', function () {
            var sel     = document.getElementById('add-group-select');
            var groupId = sel.value;
            if (!groupId) return;
            post('/admin/users/' + userId + '/groups/add', { group_id: groupId }).then(function (res) {
                if (res.ok) {
                    renderGroups(res.groups);
                    var opt = sel.querySelector('option[value="' + groupId + '"]');
                    if (opt) opt.remove();
                    sel.value = '';
                } else { alert(res.error || 'Error adding group.'); }
            });
        });
    }
}());
