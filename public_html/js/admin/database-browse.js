(function () {
    var table    = document.getElementById('db-datasheet');
    var toggle   = document.getElementById('db-edit-toggle');
    var saveAll  = document.getElementById('db-save-all-btn');
    var editing  = false;

    if (!table) return;

    var saveUrl   = table.dataset.saveUrl;
    var deleteUrl = table.dataset.deleteUrl;
    var csrf      = table.dataset.csrf;
    var page      = table.dataset.page;
    var sort      = table.dataset.sort || '';
    var dir       = table.dataset.dir  || '';
    var filters   = JSON.parse(table.dataset.filters || '{}');

    // ── Helpers ────────────────────────────────────────────────────────────

    function rowStatus(tr, state) {
        tr.style.outline = state === 'saving' ? '2px solid #93c5fd'
                         : state === 'ok'     ? '2px solid #86efac'
                         : state === 'error'  ? '2px solid #fca5a5'
                         : '';
    }

    function isDirty(tr) {
        var dirty = false;
        tr.querySelectorAll('td.db-cell:not(.db-cell-pk)').forEach(function (td) {
            var input = td.querySelector('.db-cell-input');
            if (input && input.value !== (td.dataset.orig || '')) dirty = true;
        });
        return dirty;
    }

    function markDirty(tr) {
        if (isDirty(tr)) {
            tr.classList.add('db-row-dirty');
        } else {
            tr.classList.remove('db-row-dirty');
        }
        updateSaveAllBtn();
    }

    function updateSaveAllBtn() {
        if (!saveAll) return;
        var count = table.querySelectorAll('tr.db-row-dirty').length;
        saveAll.textContent = count > 0 ? 'Save All (' + count + ')' : 'Save All';
        saveAll.disabled = count === 0;
    }

    function buildRowBody(tr) {
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('_pk',   tr.dataset.pk);
        body.set('_page', page);
        if (sort) { body.set('_sort', sort); body.set('_dir', dir); }
        Object.keys(filters).forEach(function (k) {
            body.set('_filter[' + k + ']', filters[k]);
        });
        tr.querySelectorAll('td.db-cell').forEach(function (td) {
            var input = td.querySelector('.db-cell-input');
            if (!input || td.classList.contains('db-cell-pk')) return;
            body.set(td.dataset.col, input.value);
        });
        return body;
    }

    function commitRow(tr) {
        tr.querySelectorAll('td.db-cell').forEach(function (td) {
            var input = td.querySelector('.db-cell-input');
            if (!input) return;
            td.dataset.orig = input.value;
            var display = td.querySelector('.db-cell-display');
            if (display) {
                var v = input.value;
                display.innerHTML = v === ''
                    ? '<span style="color:var(--text-muted);font-style:italic;">NULL</span>'
                    : escHtml(v.length > 80 ? v.slice(0, 80) + '\u2026' : v);
            }
        });
        tr.classList.remove('db-row-dirty');
    }

    function saveRow(tr) {
        return fetch(saveUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: buildRowBody(tr)
        }).then(function (r) { return r.json(); });
    }

    // ── Toggle edit mode ───────────────────────────────────────────────────

    function setEditing(on) {
        editing = on;
        if (toggle) toggle.textContent = on ? 'Exit Editing' : 'Edit Table';
        if (saveAll) saveAll.style.display = on ? '' : 'none';

        table.querySelectorAll('tr.db-row').forEach(function (tr) {
            tr.querySelectorAll('td.db-cell').forEach(function (td) {
                var display = td.querySelector('.db-cell-display');
                var input   = td.querySelector('.db-cell-input');
                if (!display || !input) return;
                if (on) {
                    display.style.visibility = 'hidden';
                    input.style.display = 'block';
                } else {
                    display.style.visibility = '';
                    input.style.display = 'none';
                    rowStatus(tr, '');
                    tr.classList.remove('db-row-dirty');
                }
            });
            var viewAct = tr.querySelector('.db-view-actions');
            var editAct = tr.querySelector('.db-edit-actions');
            if (viewAct) viewAct.style.display = on ? 'none'  : '';
            if (editAct) editAct.style.display = on ? 'inline' : 'none';
        });
        if (on) updateSaveAllBtn();
    }

    if (toggle) {
        toggle.addEventListener('click', function () { setEditing(!editing); });
    }

    // ── Dirty tracking ─────────────────────────────────────────────────────

    table.addEventListener('input', function (e) {
        var input = e.target.closest('.db-cell-input');
        if (!input) return;
        var tr = input.closest('tr.db-row');
        if (tr) markDirty(tr);
    });

    // ── Save All ───────────────────────────────────────────────────────────

    if (saveAll) {
        saveAll.addEventListener('click', function () {
            var dirty = Array.from(table.querySelectorAll('tr.db-row-dirty'));
            if (!dirty.length) return;

            saveAll.disabled = true;
            saveAll.textContent = 'Saving\u2026';

            var saved = 0, failed = 0;

            dirty.reduce(function (chain, tr) {
                return chain.then(function () {
                    rowStatus(tr, 'saving');
                    return saveRow(tr).then(function (json) {
                        if (json.ok) {
                            commitRow(tr);
                            rowStatus(tr, 'ok');
                            saved++;
                            setTimeout(function () { rowStatus(tr, ''); }, 1500);
                        } else {
                            rowStatus(tr, 'error');
                            failed++;
                        }
                    }).catch(function () {
                        rowStatus(tr, 'error');
                        failed++;
                    });
                });
            }, Promise.resolve()).then(function () {
                updateSaveAllBtn();
                var msg = saved + ' row' + (saved !== 1 ? 's' : '') + ' saved';
                if (failed) msg += ', ' + failed + ' failed';
                saveAll.textContent = msg;
                setTimeout(function () { updateSaveAllBtn(); }, 2500);
            });
        });
    }

    // ── Per-row buttons ────────────────────────────────────────────────────

    table.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var tr = btn.closest('tr.db-row');
        if (!tr) return;

        if (btn.classList.contains('db-save-btn')) {
            rowStatus(tr, 'saving');
            btn.disabled = true;
            saveRow(tr).then(function (json) {
                if (json.ok) {
                    commitRow(tr);
                    rowStatus(tr, 'ok');
                    updateSaveAllBtn();
                    setTimeout(function () { rowStatus(tr, ''); }, 1500);
                } else {
                    rowStatus(tr, 'error');
                    alert('Save failed: ' + (json.error || 'Unknown error'));
                }
            }).catch(function () {
                rowStatus(tr, 'error');
                alert('Save failed: network error.');
            }).finally(function () { btn.disabled = false; });
        }

        if (btn.classList.contains('db-revert-btn')) {
            tr.querySelectorAll('td.db-cell').forEach(function (td) {
                var input = td.querySelector('.db-cell-input');
                if (!input) return;
                input.value = td.dataset.orig || '';
            });
            tr.classList.remove('db-row-dirty');
            rowStatus(tr, '');
            updateSaveAllBtn();
        }

        if (btn.classList.contains('db-delete-btn')) {
            if (!confirm('Delete this row?')) return;
            var body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('_pk',   tr.dataset.pk);
            body.set('_page', page);
            fetch(deleteUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function () {
                tr.remove();
                updateSaveAllBtn();
            }).catch(function () { alert('Delete failed: network error.'); });
        }
    });

    // ── Utility ────────────────────────────────────────────────────────────

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
