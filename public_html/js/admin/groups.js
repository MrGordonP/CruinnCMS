(function () {
    // Reads data-group-id from the .panel-layout element.
    var layout = document.querySelector('.panel-layout[data-group-id]');
    if (!layout) return;

    var groupId   = parseInt(layout.dataset.groupId, 10);
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

    // ── Positions ──────────────────────────────────────────────

    function renderPositions(positions) {
        var list = document.getElementById('positions-list');
        if (!positions.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.82rem" id="positions-empty">No positions defined.</p>';
            return;
        }
        list.innerHTML = positions.map(function (p) {
            return '<div class="role-member-row" style="gap:0.4rem" data-position-id="' + p.id + '">'
                + '<span style="flex:1;font-size:0.85rem">' + esc(p.name) + '</span>'
                + '<button type="button" class="btn btn-danger btn-small del-position-btn"'
                + ' data-position-id="' + p.id + '" data-name="' + esc(p.name) + '">✕</button>'
                + '</div>';
        }).join('');
        bindDeletePositions();
        rebuildAssignSelects(positions);
    }

    function bindDeletePositions() {
        document.querySelectorAll('.del-position-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var posId = this.dataset.positionId;
                var name  = this.dataset.name;
                if (!confirm('Delete position "' + name + '"? All assignments will be removed.')) return;
                post('/admin/groups/' + groupId + '/positions/' + posId + '/delete', {}).then(function (res) {
                    if (res.ok) {
                        renderPositions(res.positions);
                        fetch('/admin/groups/' + groupId + '/members-json', { headers: { Accept: 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (d) { if (d.ok) renderMembers(d.members); })
                            .catch(function () {});
                    } else { alert(res.error || 'Error.'); }
                });
            });
        });
    }

    document.getElementById('add-position-btn').addEventListener('click', function () {
        var input = document.getElementById('new-position-name');
        var name  = input.value.trim();
        if (!name) return;
        post('/admin/groups/' + groupId + '/positions/add', { name: name }).then(function (res) {
            if (res.ok) {
                renderPositions(res.positions);
                input.value = '';
                fetch('/admin/groups/' + groupId + '/members-json', { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { if (d.ok) renderMembers(d.members); })
                    .catch(function () {});
            } else { alert(res.error || 'Error.'); }
        });
    });

    document.getElementById('new-position-name').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('add-position-btn').click(); }
    });

    bindDeletePositions();

    // ── Members ────────────────────────────────────────────────

    function rebuildAssignSelects(positions) {
        document.querySelectorAll('.assign-position-select').forEach(function (sel) {
            var row     = sel.closest('[data-user-id]');
            var haveIds = currentPositionIds(row);
            var prev    = sel.value;
            sel.innerHTML = '<option value="">+ position</option>';
            positions.forEach(function (p) {
                if (!haveIds.includes(p.id)) {
                    sel.innerHTML += '<option value="' + p.id + '">' + esc(p.name) + '</option>';
                }
            });
            sel.value = prev;
        });
    }

    function currentPositionIds(memberEl) {
        return Array.from(memberEl.querySelectorAll('.position-chip')).map(function (c) { return parseInt(c.dataset.positionId); });
    }

    function renderMembers(members) {
        if (!members) return;
        var list = document.getElementById('group-members-list');
        var positionEls = document.querySelectorAll('#positions-list [data-position-id]');
        var positions = Array.from(positionEls).map(function (el) {
            return { id: parseInt(el.dataset.positionId), name: el.querySelector('span').textContent.trim() };
        });

        if (!members.length) {
            list.innerHTML = '<p class="text-muted" style="font-size:0.85rem">No members in this group.</p>';
            return;
        }

        list.innerHTML = members.map(function (m) {
            var haveIds = (m.positions || []).map(function (p) { return p.id; });
            var chips = (m.positions || []).map(function (p) {
                return '<span class="position-chip" data-position-id="' + p.id + '">'
                    + esc(p.position_name || p.name)
                    + '<button type="button" class="remove-position-btn"'
                    + ' data-user-id="' + m.id + '" data-position-id="' + p.id + '" title="Remove">✕</button>'
                    + '</span>';
            }).join('');
            var availablePositions = positions.filter(function (p) { return !haveIds.includes(p.id); });
            var assignSel = availablePositions.length
                ? '<select class="assign-position-select" data-user-id="' + m.id + '"'
                    + ' style="font-size:0.7rem;padding:1px 3px;border-radius:3px;border:1px solid #d1d5db;max-width:130px">'
                    + '<option value="">+ position</option>'
                    + availablePositions.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + '</option>'; }).join('')
                    + '</select>'
                : '';
            return '<div class="role-member-row" style="flex-direction:column;align-items:stretch;gap:0.3rem;padding:0.5rem 0.4rem" data-user-id="' + m.id + '">'
                + '<div style="display:flex;align-items:center;gap:0.4rem">'
                + '<span style="flex:1;font-size:0.85rem;font-weight:500">' + esc(m.display_name) + '</span>'
                + '<button type="button" class="btn btn-danger btn-small remove-user-btn"'
                + ' data-user-id="' + m.id + '" data-name="' + esc(m.display_name) + '">✕</button>'
                + '</div>'
                + '<div class="member-positions" style="display:flex;flex-wrap:wrap;gap:0.25rem;min-height:1.2rem">'
                + chips + assignSel
                + '</div></div>';
        }).join('');

        bindMemberEvents();
    }

    function bindMemberEvents() {
        document.querySelectorAll('.remove-user-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid  = this.dataset.userId;
                var name = this.dataset.name;
                if (!confirm('Remove ' + name + ' from this group?')) return;
                post('/admin/groups/' + groupId + '/users/remove', { user_id: uid }).then(function (res) {
                    if (res.ok) renderMembers(res.members);
                    else alert(res.error || 'Error removing user.');
                });
            });
        });

        document.querySelectorAll('.remove-position-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid   = this.dataset.userId;
                var posId = this.dataset.positionId;
                post('/admin/groups/' + groupId + '/users/' + uid + '/positions/' + posId + '/remove', {}).then(function (res) {
                    if (res.ok) renderMembers(res.members);
                    else alert(res.error || 'Error.');
                });
            });
        });

        document.querySelectorAll('.assign-position-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var uid   = this.dataset.userId;
                var posId = this.value;
                if (!posId) return;
                var self = this;
                post('/admin/groups/' + groupId + '/users/' + uid + '/positions/assign', { position_id: posId }).then(function (res) {
                    if (res.ok) renderMembers(res.members);
                    else { self.value = ''; alert(res.error || 'Error.'); }
                });
            });
        });
    }

    bindMemberEvents();

    document.getElementById('add-user-btn').addEventListener('click', function () {
        var sel = document.getElementById('add-user-select');
        var uid = sel.value;
        if (!uid) return;
        post('/admin/groups/' + groupId + '/users/add', { user_id: uid }).then(function (res) {
            if (res.ok) {
                renderMembers(res.members);
                var opt = sel.querySelector('option[value="' + uid + '"]');
                if (opt) opt.remove();
                sel.value = '';
            } else {
                alert(res.error || 'Error adding user.');
            }
        });
    });
}());
