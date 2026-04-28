// Content Set Editor — query builder + manual fields
(function () {
    var editor = document.getElementById('set-editor');
    if (!editor) { return; }

    var CSE_TABLES     = JSON.parse(editor.dataset.tables    || '[]');
    var CSE_FILTER_OPS = JSON.parse(editor.dataset.filterOps || '[]');
    var CSE_PREVIEW_URL = editor.dataset.previewUrl || '';
    var CSE_COLS = {};

    // ── Type toggle ───────────────────────────────────────────────────────────
    function toggleSetType(type) {
        var manualPanel = document.getElementById('cse-manual-panel');
        var queryPanel  = document.getElementById('cse-query-panel');
        if (manualPanel) { manualPanel.style.display = type === 'manual' ? 'flex' : 'none'; }
        if (queryPanel)  { queryPanel.style.display  = type === 'query'  ? 'flex' : 'none'; }
        var refManual = document.getElementById('cse-ref-manual');
        var refQuery  = document.getElementById('cse-ref-query');
        if (refManual) { refManual.style.display = type === 'manual' ? '' : 'none'; }
        if (refQuery)  { refQuery.style.display  = type === 'query'  ? '' : 'none'; }
        var refTitle = document.getElementById('cse-ref-title');
        if (refTitle)  { refTitle.textContent = type === 'query' ? 'Results' : 'Rows'; }
        var previewBtn = document.getElementById('cse-preview-btn');
        if (previewBtn) { previewBtn.style.display = type === 'query' ? '' : 'none'; }
        if (type === 'query') { cseInitQuery(); }
    }

    var setTypeEl = document.getElementById('set-type');
    if (setTypeEl) {
        setTypeEl.addEventListener('change', function () { toggleSetType(this.value); });
    }

    // ── Manual fields ─────────────────────────────────────────────────────────
    function addField() {
        var tmpl = document.getElementById('field-row-template');
        if (!tmpl) { return; }
        var fieldList = document.getElementById('field-list');
        var clone = tmpl.content.cloneNode(true);
        fieldList.appendChild(clone);
        var emptyEl = document.getElementById('field-empty');
        if (emptyEl) { emptyEl.style.display = 'none'; }
        fieldList.lastElementChild.querySelector('input').focus();
    }

    function removeField(btn) {
        btn.closest('.cse-field-row').remove();
        if (!document.querySelector('.cse-field-row')) {
            var emptyEl = document.getElementById('field-empty');
            if (emptyEl) { emptyEl.style.display = ''; }
        }
    }

    var addFieldBtn = document.getElementById('cse-add-field-btn');
    if (addFieldBtn) { addFieldBtn.addEventListener('click', addField); }

    // Delegate .cse-field-remove clicks (covers both existing rows and template-cloned rows)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cse-field-remove');
        if (btn && editor.contains(btn)) { removeField(btn); }
    });

    // Auto-generate slug from name (guarded by data-edited on the slug input)
    function autoSlug(input) {
        var slugEl = document.getElementById('slug');
        if (!slugEl || slugEl.dataset.edited) { return; }
        slugEl.value = input.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    if (nameInput) { nameInput.addEventListener('input', function () { autoSlug(this); }); }
    if (slugInput) { slugInput.addEventListener('input', function () { this.dataset.edited = '1'; }); }

    // Drag-to-reorder field rows
    (function () {
        var list = document.getElementById('field-list');
        var dragging = null;
        if (!list) { return; }
        list.addEventListener('dragstart', function (e) {
            dragging = e.target.closest('.cse-field-row');
            if (dragging) { dragging.classList.add('dragging'); }
        });
        list.addEventListener('dragend', function () {
            if (dragging) { dragging.classList.remove('dragging'); }
            dragging = null;
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('.cse-field-row');
            if (over && over !== dragging) {
                var after = e.clientY > over.getBoundingClientRect().top + over.getBoundingClientRect().height / 2;
                list.insertBefore(dragging, after ? over.nextSibling : over);
            }
        });
    }());

    // ── Query builder ─────────────────────────────────────────────────────────
    function cseInitQuery() {
        var tblSel = document.getElementById('cse-table');
        if (tblSel && tblSel.value) {
            cseFetchCols(cseActiveTables(), function () { cseRefreshFields(); cseHydrate(); });
        } else {
            cseHydrate();
        }
    }

    function cseActiveTables() {
        var t = document.getElementById('cse-table');
        var tables = t && t.value ? [t.value] : [];
        document.querySelectorAll('.cse-join-table').forEach(function (s) { if (s.value) { tables.push(s.value); } });
        return tables.filter(function (v, i, a) { return a.indexOf(v) === i; });
    }

    function cseFetchCols(tables, cb) {
        var needed = tables.filter(function (t) { return t && !CSE_COLS[t]; });
        if (!needed.length) { if (cb) { cb(); } return; }
        var qs = needed.map(function (t) { return 'tables[]=' + encodeURIComponent(t); }).join('&');
        fetch('/admin/editor/db-columns?' + qs, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var cols = d.columns || {};
                Object.keys(cols).forEach(function (t) { CSE_COLS[t] = cols[t]; });
                if (cb) { cb(); }
            })
            .catch(function () { if (cb) { cb(); } });
    }

    function cseAllColOpts() {
        var opts = [];
        cseActiveTables().forEach(function (t) {
            (CSE_COLS[t] || []).forEach(function (c) { opts.push(t + '.' + c); });
        });
        return opts;
    }

    function csePopulateSelect(sel, opts, cur, emptyLabel) {
        var prev = cur !== undefined ? cur : sel.value;
        sel.innerHTML = '';
        if (emptyLabel !== undefined) {
            var e = document.createElement('option'); e.value = ''; e.textContent = emptyLabel; sel.appendChild(e);
        }
        opts.forEach(function (o) {
            var opt = document.createElement('option'); opt.value = o; opt.textContent = o; sel.appendChild(opt);
        });
        sel.value = prev;
    }

    function cseRefreshFields() {
        var opts = cseAllColOpts();
        var qc   = cseReadConfig();
        var box  = document.getElementById('cse-fields');
        var ob   = document.getElementById('cse-orderby');
        if (!box) { return; }
        box.innerHTML = '';
        if (!opts.length) { box.textContent = 'Select a table to see fields.'; }
        opts.forEach(function (o) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;align-items:center;gap:0.3rem;margin:0.15rem 0';
            var lbl = document.createElement('label');
            lbl.style.cssText = 'display:flex;align-items:center;gap:0.3rem;flex:1;cursor:pointer;font-size:0.82rem';
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = o;
            cb.checked = (qc.fields || []).indexOf(o) !== -1;
            cb.addEventListener('change', cseSerialise);
            lbl.appendChild(cb); lbl.appendChild(document.createTextNode(o));
            var previewBtn = document.createElement('button');
            previewBtn.type = 'button'; previewBtn.textContent = '\u{1F441}';
            previewBtn.title = 'Preview values';
            previewBtn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:0.85rem;opacity:0.5;padding:0';
            var hint = document.createElement('span');
            hint.style.cssText = 'font-size:0.72rem;color:#6b7280;font-style:italic;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px';
            previewBtn.addEventListener('click', function () {
                if (hint.dataset.loaded) { hint.textContent = ''; hint.dataset.loaded = ''; previewBtn.style.opacity = '0.5'; return; }
                previewBtn.textContent = '\u23F3';
                var parts = o.split('.'); var tbl = parts[0]; var col = parts[1];
                fetch('/admin/editor/db-preview?table=' + encodeURIComponent(tbl) + '&column=' + encodeURIComponent(col), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        previewBtn.textContent = '\u{1F441}'; previewBtn.style.opacity = '1';
                        hint.textContent = (d.values || []).join(', ') || '(no values)';
                        hint.dataset.loaded = '1';
                    })
                    .catch(function () { previewBtn.textContent = '\u{1F441}'; hint.textContent = '(error)'; });
            });
            wrap.appendChild(lbl); wrap.appendChild(previewBtn); wrap.appendChild(hint);
            box.appendChild(wrap);
        });
        if (ob) { csePopulateSelect(ob, opts, qc.order_by || '', '\u2014 none \u2014'); }
        document.querySelectorAll('.cse-filter-field').forEach(function (s) { csePopulateSelect(s, opts, s.value, '\u2014 field \u2014'); });
        document.querySelectorAll('.cse-join-row').forEach(function (row) { cseRefreshJoinCols(row); });
    }

    function cseAddJoin(def) {
        def = def || {};
        var container = document.getElementById('cse-joins');
        if (!container) { return; }
        var row = document.createElement('div');
        row.className = 'cse-join-row';
        row.style.cssText = 'border:1px solid #e5e7eb;border-radius:4px;padding:0.5rem;margin-bottom:0.4rem;font-size:0.8rem';

        var r1 = document.createElement('div'); r1.style.cssText = 'display:flex;gap:0.3rem;align-items:center;margin-bottom:0.25rem';
        var typeSel = document.createElement('select'); typeSel.className = 'cse-join-type form-select';
        ['LEFT', 'INNER', 'RIGHT'].forEach(function (t) { var o = document.createElement('option'); o.value = t; o.textContent = t + ' JOIN'; typeSel.appendChild(o); });
        typeSel.value = def.type || 'LEFT';
        r1.appendChild(Object.assign(document.createElement('span'), { textContent: 'Type', style: 'width:40px' }));
        r1.appendChild(typeSel); row.appendChild(r1);

        var r2 = document.createElement('div'); r2.style.cssText = 'display:flex;gap:0.3rem;align-items:center;margin-bottom:0.25rem';
        var tblSel = document.createElement('select'); tblSel.className = 'cse-join-table form-select';
        csePopulateSelect(tblSel, CSE_TABLES, def.table || '', '\u2014 table \u2014');
        tblSel.addEventListener('change', function () { cseFetchCols([tblSel.value], function () { cseRefreshJoinCols(row); cseRefreshFields(); cseSerialise(); }); });
        r2.appendChild(Object.assign(document.createElement('span'), { textContent: 'Table', style: 'width:40px' }));
        r2.appendChild(tblSel); row.appendChild(r2);

        var r3 = document.createElement('div'); r3.style.cssText = 'display:flex;gap:0.3rem;align-items:center';
        var leftSel  = document.createElement('select'); leftSel.className  = 'cse-join-left form-select';
        var rightSel = document.createElement('select'); rightSel.className = 'cse-join-right form-select';
        var activeTabs = cseActiveTables();
        var joinTab = def.table || tblSel.value || '';
        if (joinTab && activeTabs.indexOf(joinTab) === -1) { activeTabs.push(joinTab); }
        var joinOpts = [];
        activeTabs.forEach(function (t) { (CSE_COLS[t] || []).forEach(function (c) { joinOpts.push(t + '.' + c); }); });
        csePopulateSelect(leftSel,  joinOpts, def.on_left  || '', '\u2014 left col \u2014');
        csePopulateSelect(rightSel, joinOpts, def.on_right || '', '\u2014 right col \u2014');
        r3.appendChild(leftSel);
        r3.appendChild(Object.assign(document.createElement('span'), { textContent: '=' }));
        r3.appendChild(rightSel); row.appendChild(r3);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button'; removeBtn.textContent = '\u2715 Remove';
        removeBtn.style.cssText = 'margin-top:0.35rem;font-size:0.72rem;color:#dc2626;background:none;border:none;cursor:pointer;padding:0';
        removeBtn.addEventListener('click', function () { row.remove(); cseRefreshFields(); cseSerialise(); });
        row.appendChild(removeBtn);
        [typeSel, leftSel, rightSel].forEach(function (s) { s.addEventListener('change', cseSerialise); });
        container.appendChild(row);
    }

    function cseRefreshJoinCols(row) {
        var opts = cseAllColOpts();
        var l = row.querySelector('.cse-join-left'); var r = row.querySelector('.cse-join-right');
        if (l) { csePopulateSelect(l, opts, l.value, '\u2014 left col \u2014'); }
        if (r) { csePopulateSelect(r, opts, r.value, '\u2014 right col \u2014'); }
    }

    function cseAddFilter(def) {
        def = def || {};
        var container = document.getElementById('cse-filters');
        if (!container) { return; }
        var row = document.createElement('div');
        row.className = 'cse-filter-row';
        row.style.cssText = 'display:flex;gap:0.25rem;align-items:center;margin-bottom:0.3rem;flex-wrap:wrap';
        var opts = cseAllColOpts();
        var fieldSel = document.createElement('select'); fieldSel.className = 'cse-filter-field form-select'; fieldSel.style.flex = '2';
        csePopulateSelect(fieldSel, opts, def.field || '', '\u2014 field \u2014');
        var opSel = document.createElement('select'); opSel.className = 'cse-filter-op form-select'; opSel.style.flex = '1';
        CSE_FILTER_OPS.forEach(function (op) { var o = document.createElement('option'); o.value = op; o.textContent = op; opSel.appendChild(o); });
        opSel.value = def.op || '=';
        var valInput = document.createElement('input'); valInput.type = 'text'; valInput.className = 'cse-filter-val form-input'; valInput.placeholder = 'value'; valInput.value = def.value || ''; valInput.style.flex = '2';
        function toggleVal() { valInput.style.display = (opSel.value === 'IS NULL' || opSel.value === 'IS NOT NULL') ? 'none' : ''; }
        toggleVal(); opSel.addEventListener('change', toggleVal);
        var rmBtn = document.createElement('button'); rmBtn.type = 'button'; rmBtn.textContent = '\u2715'; rmBtn.style.cssText = 'background:none;border:none;cursor:pointer;color:#dc2626';
        rmBtn.addEventListener('click', function () { row.remove(); cseSerialise(); });
        [fieldSel, opSel].forEach(function (s) { s.addEventListener('change', cseSerialise); });
        valInput.addEventListener('input', cseSerialise);
        row.appendChild(fieldSel); row.appendChild(opSel); row.appendChild(valInput); row.appendChild(rmBtn);
        container.appendChild(row);
    }

    function cseReadConfig() {
        var inp = document.getElementById('cse-query-config-input');
        try { return JSON.parse(inp ? inp.value : '{}'); } catch (e) { return {}; }
    }

    function cseSerialise() {
        var config = {};
        var tbl = document.getElementById('cse-table');
        if (tbl && tbl.value) { config.table = tbl.value; }
        config.joins = [];
        document.querySelectorAll('.cse-join-row').forEach(function (row) {
            var t = row.querySelector('.cse-join-table');
            if (t && t.value) {
                config.joins.push({
                    type:      (row.querySelector('.cse-join-type')  || {}).value || 'LEFT',
                    table:     t.value,
                    on_left:   (row.querySelector('.cse-join-left')  || {}).value || '',
                    on_right:  (row.querySelector('.cse-join-right') || {}).value || ''
                });
            }
        });
        config.filters = [];
        document.querySelectorAll('.cse-filter-row').forEach(function (row) {
            var f = row.querySelector('.cse-filter-field');
            if (f && f.value) {
                config.filters.push({
                    field: f.value,
                    op:    (row.querySelector('.cse-filter-op')  || {}).value || '=',
                    value: (row.querySelector('.cse-filter-val') || {}).value || ''
                });
            }
        });
        config.fields = [];
        document.querySelectorAll('#cse-fields input[type=checkbox]:checked').forEach(function (cb) {
            if (/^[a-z0-9_]+\.[a-z0-9_]+$/i.test(cb.value)) { config.fields.push(cb.value); }
        });
        var ob = document.getElementById('cse-orderby');
        var od = document.getElementById('cse-orderdir');
        var lm = document.getElementById('cse-limit');
        if (ob) { config.order_by  = ob.value; }
        if (od) { config.order_dir = od.value; }
        if (lm) { config.limit     = parseInt(lm.value) || 100; }
        var inp = document.getElementById('cse-query-config-input');
        if (inp) { inp.value = JSON.stringify(config); }
    }

    function cseHydrate() {
        var qc = cseReadConfig();
        var tblSel = document.getElementById('cse-table');
        if (tblSel && qc.table) { tblSel.value = qc.table; }
        var joinsEl = document.getElementById('cse-joins');
        if (joinsEl) { joinsEl.innerHTML = ''; }
        (qc.joins || []).forEach(function (j) { cseAddJoin(j); });
        var filtsEl = document.getElementById('cse-filters');
        if (filtsEl) { filtsEl.innerHTML = ''; }
        (qc.filters || []).forEach(function (f) { cseAddFilter(f); });
        cseRefreshFields();
        var od = document.getElementById('cse-orderdir');
        var lm = document.getElementById('cse-limit');
        var ob = document.getElementById('cse-orderby');
        if (od) { od.value = qc.order_dir || 'ASC'; }
        if (lm) { lm.value = qc.limit    || 100; }
        if (ob) { ob.value = qc.order_by  || ''; }
    }

    // Wire query builder controls
    var cseTableEl  = document.getElementById('cse-table');
    var cseOrderby  = document.getElementById('cse-orderby');
    var cseOrderdir = document.getElementById('cse-orderdir');
    var cseLimit    = document.getElementById('cse-limit');
    if (cseTableEl) {
        cseTableEl.addEventListener('change', function () {
            var t = this.value;
            if (t) { cseFetchCols([t], function () { cseRefreshFields(); cseSerialise(); }); }
            else   { cseRefreshFields(); cseSerialise(); }
        });
    }
    if (cseOrderby)  { cseOrderby.addEventListener('change', cseSerialise); }
    if (cseOrderdir) { cseOrderdir.addEventListener('change', cseSerialise); }
    if (cseLimit)    { cseLimit.addEventListener('input', cseSerialise); }

    var addJoinBtn   = document.getElementById('cse-add-join-btn');
    var addFilterBtn = document.getElementById('cse-add-filter-btn');
    if (addJoinBtn)   { addJoinBtn.addEventListener('click', function () { cseAddJoin(); }); }
    if (addFilterBtn) { addFilterBtn.addEventListener('click', function () { cseAddFilter(); }); }

    // ── Query preview ─────────────────────────────────────────────────────────
    function cseRunPreview() {
        if (!CSE_PREVIEW_URL) { return; }
        var btn  = document.getElementById('cse-preview-btn');
        var area = document.getElementById('cse-preview-area');
        if (!area) { return; }
        if (btn) { btn.disabled = true; btn.textContent = '\u2026'; }
        area.innerHTML = '<p style="color:#6b7280;font-size:0.8rem;padding:0.5rem">Running\u2026</p>';
        fetch(CSE_PREVIEW_URL, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (btn) { btn.disabled = false; btn.innerHTML = '&#9654; Run'; }
                if (!d.ok) { area.innerHTML = '<p style="color:#dc2626;font-size:0.8rem;padding:0.5rem">Error: ' + (d.error || 'unknown') + '</p>'; return; }
                if (!d.rows.length) { area.innerHTML = '<p style="color:#6b7280;font-size:0.8rem;padding:0.5rem">Query returned 0 rows.</p>'; return; }
                var cols = d.columns;
                var html = '<div style="padding:4px 8px;font-size:0.72rem;color:#6b7280">' + d.count + ' row' + (d.count !== 1 ? 's' : '') + '</div>';
                html += '<div style="overflow-x:auto"><table style="font-size:0.72rem;border-collapse:collapse;width:100%">';
                html += '<thead><tr>';
                cols.forEach(function (c) { html += '<th style="text-align:left;padding:2px 6px;border-bottom:1px solid #e5e7eb;white-space:nowrap;color:#6b7280">' + c + '</th>'; });
                html += '</tr></thead><tbody>';
                d.rows.forEach(function (row) {
                    html += '<tr>';
                    cols.forEach(function (c) {
                        var v = row[c] !== null && row[c] !== undefined ? String(row[c]) : '';
                        var display = v.length > 40 ? v.slice(0, 40) + '\u2026' : v;
                        html += '<td style="padding:2px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap" title="' + v.replace(/"/g, '&quot;') + '">' + display + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                area.innerHTML = html;
            })
            .catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = '&#9654; Run'; }
                area.innerHTML = '<p style="color:#dc2626;font-size:0.8rem;padding:0.5rem">Request failed.</p>';
            });
    }

    var previewBtn = document.getElementById('cse-preview-btn');
    if (previewBtn) { previewBtn.addEventListener('click', cseRunPreview); }

    // Init: if this is a query set, hydrate the query builder
    if (editor.dataset.setType === 'query') { cseInitQuery(); }

}());
