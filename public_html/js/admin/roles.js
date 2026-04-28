(function () {
    // Reads data-role-id from the nearest .panel-layout element.
    var layout = document.querySelector('.panel-layout[data-role-id]');
    if (!layout) return;

    var roleId    = parseInt(layout.dataset.roleId, 10);
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function post(url, userId) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('user_id', userId);
        return fetch(url, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderMembers(users) {
        var list = document.getElementById('role-members-list');
        if (!list) return;
        if (!users.length) {
            list.innerHTML = '<p class="text-muted" id="no-members-msg" style="font-size:0.85rem">No users assigned to this role.</p>';
            return;
        }
        list.innerHTML = users.map(function (u) {
            return '<div class="role-member-row" data-user-id="' + u.id + '">'
                + '<span class="role-member-name">' + escHtml(u.display_name) + '</span>'
                + '<button type="button" class="btn btn-danger btn-small remove-user-btn"'
                + ' data-user-id="' + u.id + '" data-name="' + escHtml(u.display_name) + '">✕</button>'
                + '</div>';
        }).join('');
        bindRemoveButtons();
    }

    function bindRemoveButtons() {
        document.querySelectorAll('.remove-user-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid  = this.dataset.userId;
                var name = this.dataset.name;
                if (!confirm('Remove ' + name + ' from this role?')) return;
                post('/admin/roles/' + roleId + '/users/remove', uid).then(function (res) {
                    if (res.ok) renderMembers(res.users);
                    else alert(res.error || 'Error removing user.');
                });
            });
        });
    }

    bindRemoveButtons();

    var addBtn = document.getElementById('add-user-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var sel = document.getElementById('add-user-select');
            var uid = sel.value;
            if (!uid) return;
            post('/admin/roles/' + roleId + '/users/add', uid).then(function (res) {
                if (res.ok) {
                    renderMembers(res.users);
                    var opt = sel.querySelector('option[value="' + uid + '"]');
                    if (opt) opt.remove();
                    sel.value = '';
                } else {
                    alert(res.error || 'Error adding user.');
                }
            });
        });
    }

    // Colour preview
    var colourInput = document.getElementById('colour');
    if (colourInput) {
        colourInput.addEventListener('input', function () {
            document.getElementById('colour-preview').style.background = this.value;
        });
    }

    // Toggle all permissions in a category
    document.querySelectorAll('.permission-toggle-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cat       = this.dataset.category;
            var checkboxes = document.querySelectorAll('input[data-category="' + cat + '"]');
            var allChecked = Array.from(checkboxes).every(function (cb) { return cb.checked; });
            checkboxes.forEach(function (cb) { cb.checked = !allChecked; });
        });
    });
}());
