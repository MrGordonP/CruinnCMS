(function () {
    var table    = document.getElementById('db-datasheet');
    var toggle   = document.getElementById('db-edit-toggle');
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
        // state: '' | 'saving' | 'ok' | 'error'
        tr.style.outline = state === 'saving' ? '2px solid #93c5fd'
                         : state === 'ok'     ? '2px solid #86efac'
                         : state === 'error'  ? '2px solid #fca5a5'
                         : '';
    }

    // ── Toggle edit mode ───────────────────────────────────────────────────

    function setEditing(on) {
        editing = on;
        if (toggle) toggle.textContent = on ? 'Exit Editing' : 'Edit Table';

        table.querySelectorAll('tr.db-row').forEach(function (tr) {
            tr.querySelectorAll('td.db-cell').forEach(function (td) {
                var display = td.querySelector('.db-cell-display');
                var input   = td.querySelector('.db-cell-input');
                if (!display || !input) return;
                if (on) {
                    // Ghost: invisible but in flow, anchors column width
                    display.style.visibility = 'hidden';
                    input.style.display = 'block';
                } else {
                    display.style.visibility = '';
                    input.style.display = 'none';
                    rowStatus(tr, '');
                }
            });
            var viewAct = tr.querySelector('.db-view-actions');
            var editAct = tr.querySelector('.db-edit-actions');
            if (viewAct) viewAct.style.display = on ? 'none'  : '';
            if (editAct) editAct.style.display = on ? 'inline' : 'none';
        });
    }

    if (toggle) {
        toggle.addEventListener('click', function () { setEditing(!editing); });
    }

    // ── Per-row Save ───────────────────────────────────────────────────────

    table.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var tr = btn.closest('tr.db-row');
        if (!tr) return;

        // ── Save ──
        if (btn.classList.contains('db-save-btn')) {
            var pkVal = tr.dataset.pk;
            var body  = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('_pk',   pkVal);
            body.set('_page', page);
            if (sort) { body.set('_sort', sort); body.set('_dir', dir); }
            Object.keys(filters).forEach(function (k) {
                body.set('_filter[' + k + ']', filters[k]);
            });

            tr.querySelectorAll('td.db-cell').forEach(function (td) {
                var col   = td.dataset.col;
                var input = td.querySelector('.db-cell-input');
                if (!input || td.classList.contains('db-cell-pk')) return;
                body.set(col, input.tagName === 'TEXTAREA' ? input.value : input.value);
            });

            rowStatus(tr, 'saving');
            btn.disabled = true;

            fetch(saveUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.ok) {
                    rowStatus(tr, 'ok');
                    // Update data-orig on each cell so Revert reflects saved state
                    tr.querySelectorAll('td.db-cell').forEach(function (td) {
                        var input = td.querySelector('.db-cell-input');
                        if (input) td.dataset.orig = input.tagName === 'TEXTAREA' ? input.value : input.value;
                        // Also update the display span text
                        var display = td.querySelector('.db-cell-display');
                        if (display && input) {
                            var v = input.tagName === 'TEXTAREA' ? input.value : input.value;
                            display.innerHTML = v === '' ? '<span style="color:var(--text-muted);font-style:italic;">NULL</span>'
                                              : escHtml(v.length > 80 ? v.slice(0, 80) + '…' : v);
                        }
                    });
                    setTimeout(function () { rowStatus(tr, ''); }, 1500);
                } else {
                    rowStatus(tr, 'error');
                    alert('Save failed: ' + (json.error || 'Unknown error'));
                }
            })
            .catch(function () {
                rowStatus(tr, 'error');
                alert('Save failed: network error.');
            })
            .finally(function () { btn.disabled = false; });
        }

        // ── Revert ──
        if (btn.classList.contains('db-revert-btn')) {
            tr.querySelectorAll('td.db-cell').forEach(function (td) {
                var input = td.querySelector('.db-cell-input');
                if (!input) return;
                var orig = td.dataset.orig;
                if (input.tagName === 'TEXTAREA') input.value = orig;
                else input.value = orig;
            });
            rowStatus(tr, '');
        }

        // ── Delete ──
        if (btn.classList.contains('db-delete-btn')) {
            if (!confirm('Delete this row?')) return;
            var pkVal = tr.dataset.pk;
            var body  = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('_pk',   pkVal);
            body.set('_page', page);

            fetch(deleteUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            })
            .then(function () { tr.remove(); })
            .catch(function () { alert('Delete failed: network error.'); });
        }
    });

    // ── Utility ────────────────────────────────────────────────────────────

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
