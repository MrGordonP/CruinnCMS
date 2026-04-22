<?php
/**
 * Content Sets — Schema editor (new or edit)
 *
 * Left:   set metadata (name, slug, description, type)
 * Middle: field list (manual) OR query builder (query)
 * Right:  field type reference / query help
 */
\Cruinn\Template::requireCss('admin-content.css');
$GLOBALS['admin_flush_layout'] = true;

$isNew       = empty($set['id']);
$action      = $isNew ? '/admin/content' : '/admin/content/' . (int)$set['id'];
$fields      = $set['fields']       ?? [];
$setType     = $set['type']         ?? 'manual';
$queryConfig = $set['query_config'] ?? [];
$dbTables    = $dbTables            ?? [];

$DL_FILTER_OPS = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
?>

<div class="content-set-editor" id="set-editor">

    <!-- ── Left: Set metadata ─────────────────────────────────── -->
    <div class="cse-meta-panel">
        <div class="cse-panel-header">
            <h3><?= $isNew ? 'New Content Set' : 'Set Details' ?></h3>
        </div>
        <div class="cse-panel-scroll">
            <form method="post" action="<?= e($action) ?>" id="set-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name" class="form-input"
                           value="<?= e($set['name'] ?? '') ?>" required
                           <?= $isNew ? 'oninput="autoSlug(this)"' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="slug">Slug <span class="required">*</span></label>
                    <input type="text" name="slug" id="slug" class="form-input"
                           value="<?= e($set['slug'] ?? '') ?>" required
                           pattern="[a-z0-9\-]+" title="Lowercase letters, numbers and hyphens only">
                    <p class="form-help">Used to reference this set in dynamic blocks.</p>
                </div>

                <?php if ($isNew): ?>
                <div class="form-group">
                    <label for="set-type">Type <span class="required">*</span></label>
                    <select name="type" id="set-type" class="form-select" onchange="toggleSetType(this.value)">
                        <option value="manual">Manual — editors enter rows</option>
                        <option value="query">Query — pulls live data from a table</option>
                    </select>
                    <p class="form-help">Cannot be changed after creation.</p>
                </div>
                <?php else: ?>
                <input type="hidden" name="type" value="<?= e($setType) ?>">
                <div class="form-group">
                    <label>Type</label>
                    <p class="form-help" style="margin:0"><?= $setType === 'query' ? '🔍 Query set — pulls live data' : '✏️ Manual set — editors enter rows' ?></p>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-input" rows="3"><?= e($set['description'] ?? '') ?></textarea>
                </div>

                <div class="cse-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $isNew ? 'Create Set' : 'Save Changes' ?>
                    </button>
                    <a href="/admin/content" class="btn btn-outline">Cancel</a>
                </div>
            </form>

            <?php if (!$isNew): ?>
            <hr class="form-divider">
            <form method="post" action="/admin/content/<?= (int)$set['id'] ?>/delete"
                  onsubmit="return confirm('Delete this content set and all its rows? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-small">Delete Set</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Middle: Field definitions / Query builder ────────── -->
    <div class="cse-fields-panel">

        <!-- Manual mode -->
        <div id="cse-manual-panel" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden<?= ($setType === 'query') ? ';display:none' : '' ?>">
            <div class="cse-panel-header">
                <h3>Fields</h3>
                <button type="button" class="btn btn-sm btn-primary" onclick="addField()">+ Add Field</button>
            </div>
            <div class="cse-panel-scroll">
                <p class="form-help" style="padding:0.6rem 1rem 0">
                    Fields define the columns of data in this set. Drag to reorder.
                    Changes take effect when you save the set above.
                </p>
                <div id="field-list">
                    <?php foreach ($fields as $i => $field): ?>
                    <div class="cse-field-row" draggable="true">
                        <span class="cse-drag-handle" title="Drag to reorder">⠿</span>
                        <div class="cse-field-inputs">
                            <input type="text" name="field_name[]" class="form-input form-input-sm"
                                   placeholder="field_name" value="<?= e($field['name'] ?? '') ?>"
                                   form="set-form">
                            <input type="text" name="field_label[]" class="form-input form-input-sm"
                                   placeholder="Label" value="<?= e($field['label'] ?? '') ?>"
                                   form="set-form">
                            <select name="field_type[]" class="form-select form-input-sm" form="set-form">
                                <?php foreach (['text','richtext','image','url','date'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($field['type'] ?? 'text') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="cse-field-remove" onclick="removeField(this)" title="Remove field">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="cse-field-add-hint" id="field-empty" <?= !empty($fields) ? 'style="display:none"' : '' ?>>
                    <p>No fields yet. Click <strong>+ Add Field</strong> to define the columns of this set.</p>
                </div>
            </div>
        </div>

        <!-- Query mode -->
        <div id="cse-query-panel" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden<?= ($setType !== 'query') ? ';display:none' : '' ?>">
            <div class="cse-panel-header">
                <h3>Query</h3>
            </div>
            <div class="cse-panel-scroll" style="padding:0.75rem 1rem">
                <input type="hidden" name="query_config" id="cse-query-config-input" form="set-form" value="<?= htmlspecialchars(json_encode($queryConfig), ENT_QUOTES, 'UTF-8') ?>">

                <!-- Primary table -->
                <div class="form-group">
                    <label>Primary Table</label>
                    <select class="form-select" id="cse-table">
                        <option value="">— select table —</option>
                        <?php foreach ($dbTables as $tbl): ?>
                        <option value="<?= e($tbl) ?>" <?= ($queryConfig['table'] ?? '') === $tbl ? 'selected' : '' ?>><?= e($tbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Joins -->
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
                        <label style="margin:0">Joins</label>
                        <button type="button" class="btn btn-small btn-outline" onclick="cseAddJoin()">+ Join</button>
                    </div>
                    <div id="cse-joins"></div>
                </div>

                <!-- Filters -->
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
                        <label style="margin:0">Filters</label>
                        <button type="button" class="btn btn-small btn-outline" onclick="cseAddFilter()">+ Filter</button>
                    </div>
                    <div id="cse-filters"></div>
                </div>

                <!-- Fields -->
                <div class="form-group">
                    <label>Fields (tokens)</label>
                    <div id="cse-fields" style="font-size:0.82rem;color:#6b7280">Select a table to see fields.</div>
                </div>

                <!-- Order / Limit -->
                <div style="display:flex;gap:0.5rem;align-items:flex-end">
                    <div style="flex:1">
                        <label style="font-size:0.82rem">Order by</label>
                        <select id="cse-orderby" class="form-select" style="font-size:0.82rem">
                            <option value="">— none —</option>
                        </select>
                    </div>
                    <div style="width:70px">
                        <label style="font-size:0.82rem">Dir</label>
                        <select id="cse-orderdir" class="form-select" style="font-size:0.82rem">
                            <option value="ASC">ASC</option>
                            <option value="DESC">DESC</option>
                        </select>
                    </div>
                    <div style="width:70px">
                        <label style="font-size:0.82rem">Limit</label>
                        <input type="number" id="cse-limit" class="form-input" value="100" min="1" max="1000" style="font-size:0.82rem">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Right: Reference ───────────────────────────────────── -->
    <div class="cse-ref-panel">
        <div class="cse-panel-header">
            <h3 id="cse-ref-title">Field Types</h3>
        </div>
        <div class="cse-panel-scroll cse-ref-body" id="cse-ref-manual">
            <dl class="cse-type-list">
                <dt>text</dt><dd>Single-line plain text</dd>
                <dt>richtext</dt><dd>Multi-line formatted text (rendered as HTML)</dd>
                <dt>image</dt><dd>URL to an image (stored as path/URL string)</dd>
                <dt>url</dt><dd>A hyperlink URL</dd>
                <dt>date</dt><dd>ISO date (YYYY-MM-DD)</dd>
            </dl>
            <hr class="form-divider">
            <p class="form-help"><strong>Using in the editor</strong><br>
            Drop a <em>Data List</em> block onto the canvas, pick this set, and use <code>{{field_name}}</code> tokens in the card template.</p>
        </div>
        <div class="cse-panel-scroll cse-ref-body" id="cse-ref-query" style="display:none">
            <p class="form-help"><strong>Query Set</strong><br>
            This set pulls live rows directly from the database. No manual data entry needed.</p>
            <p class="form-help">Pick a primary table, optionally join other tables, add filters, and choose which fields to expose as tokens.</p>
            <p class="form-help">In the editor, drop a <em>Data List</em> block, select this set, and use <code>{{column_name}}</code> tokens in the card template.</p>
            <hr class="form-divider">
            <div id="cse-preview-area" style="font-size:0.78rem"></div>
        </div>
    </div>

</div>

<template id="field-row-template">
    <div class="cse-field-row" draggable="true">
        <span class="cse-drag-handle" title="Drag to reorder">⠿</span>
        <div class="cse-field-inputs">
            <input type="text" name="field_name[]" class="form-input form-input-sm" placeholder="field_name" form="set-form">
            <input type="text" name="field_label[]" class="form-input form-input-sm" placeholder="Label" form="set-form">
            <select name="field_type[]" class="form-select form-input-sm" form="set-form">
                <option value="text">Text</option>
                <option value="richtext">Richtext</option>
                <option value="image">Image</option>
                <option value="url">URL</option>
                <option value="date">Date</option>
            </select>
        </div>
        <button type="button" class="cse-field-remove" onclick="removeField(this)" title="Remove field">✕</button>
    </div>
</template>

<script>
// ── Type toggle ──────────────────────────────────────────────────
function toggleSetType(type) {
    var manualPanel = document.getElementById('cse-manual-panel');
    var queryPanel  = document.getElementById('cse-query-panel');
    manualPanel.style.display = type === 'manual' ? 'flex' : 'none';
    queryPanel.style.display  = type === 'query'  ? 'flex' : 'none';
    document.getElementById('cse-ref-manual').style.display   = type === 'manual' ? '' : 'none';
    document.getElementById('cse-ref-query').style.display    = type === 'query'  ? '' : 'none';
    document.getElementById('cse-ref-title').textContent      = type === 'query' ? 'About Query Sets' : 'Field Types';
    if (type === 'query') { cseInitQuery(); }
}

// ── Manual fields ────────────────────────────────────────────────
function addField() {
    const tmpl = document.getElementById('field-row-template').content.cloneNode(true);
    document.getElementById('field-list').appendChild(tmpl);
    document.getElementById('field-empty').style.display = 'none';
    document.getElementById('field-list').lastElementChild.querySelector('input').focus();
}
function removeField(btn) {
    btn.closest('.cse-field-row').remove();
    if (!document.querySelector('.cse-field-row')) {
        document.getElementById('field-empty').style.display = '';
    }
}

// Auto-generate slug from name (new sets only)
function autoSlug(input) {
    const slugEl = document.getElementById('slug');
    if (!slugEl || slugEl.dataset.edited) { return; }
    slugEl.value = input.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
document.getElementById('slug')?.addEventListener('input', function () { this.dataset.edited = '1'; });

// Drag-to-reorder field rows
(function () {
    const list = document.getElementById('field-list');
    let dragging = null;
    if (!list) { return; }
    list.addEventListener('dragstart', e => { dragging = e.target.closest('.cse-field-row'); dragging?.classList.add('dragging'); });
    list.addEventListener('dragend',   () => { dragging?.classList.remove('dragging'); dragging = null; });
    list.addEventListener('dragover',  e => {
        e.preventDefault();
        const over = e.target.closest('.cse-field-row');
        if (over && over !== dragging) {
            const after = e.clientY > over.getBoundingClientRect().top + over.getBoundingClientRect().height / 2;
            list.insertBefore(dragging, after ? over.nextSibling : over);
        }
    });
}());

// ── Query builder ────────────────────────────────────────────────
var CSE_TABLES   = <?= json_encode($dbTables, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var CSE_COLS     = {};
var CSE_QC       = <?= json_encode($queryConfig, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var CSE_FILTER_OPS = <?= json_encode($DL_FILTER_OPS) ?>;

function cseInitQuery() {
    var tblSel = document.getElementById('cse-table');
    if (tblSel && tblSel.value) {
        cseFetchCols(cseActiveTables(), function () { cseRefreshFields(); cseHydrate(); });
    } else {
        cseHydrate();
    }
}

function cseActiveTables() {
    var t = document.getElementById('cse-table')?.value;
    var tables = t ? [t] : [];
    document.querySelectorAll('.cse-join-table').forEach(function (s) { if (s.value) tables.push(s.value); });
    return tables.filter(function (v, i, a) { return a.indexOf(v) === i; });
}

function cseFetchCols(tables, cb) {
    var needed = tables.filter(function (t) { return t && !CSE_COLS[t]; });
    if (!needed.length) { if (cb) cb(); return; }
    var qs = needed.map(function (t) { return 'tables[]=' + encodeURIComponent(t); }).join('&');
    fetch('/admin/editor/db-columns?' + qs, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var cols = d.columns || {};
            Object.keys(cols).forEach(function (t) { CSE_COLS[t] = cols[t]; });
            if (cb) cb();
        })
        .catch(function () { if (cb) cb(); });
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
    var opts  = cseAllColOpts();
    var qc    = cseReadConfig();
    var box   = document.getElementById('cse-fields');
    var ob    = document.getElementById('cse-orderby');
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
        // Preview button
        var previewBtn = document.createElement('button');
        previewBtn.type = 'button'; previewBtn.textContent = '👁';
        previewBtn.title = 'Preview values';
        previewBtn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:0.85rem;opacity:0.5;padding:0';
        var hint = document.createElement('span');
        hint.style.cssText = 'font-size:0.72rem;color:#6b7280;font-style:italic;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px';
        previewBtn.addEventListener('click', function () {
            if (hint.dataset.loaded) { hint.textContent = ''; hint.dataset.loaded = ''; previewBtn.style.opacity = '0.5'; return; }
            previewBtn.textContent = '⏳';
            var parts = o.split('.'); var tbl = parts[0]; var col = parts[1];
            fetch('/admin/editor/db-preview?table=' + encodeURIComponent(tbl) + '&column=' + encodeURIComponent(col), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    previewBtn.textContent = '👁'; previewBtn.style.opacity = '1';
                    hint.textContent = (d.values || []).join(', ') || '(no values)';
                    hint.dataset.loaded = '1';
                })
                .catch(function () { previewBtn.textContent = '👁'; hint.textContent = '(error)'; });
        });
        wrap.appendChild(lbl); wrap.appendChild(previewBtn); wrap.appendChild(hint);
        box.appendChild(wrap);
    });
    csePopulateSelect(ob, opts, qc.order_by || '', '— none —');
    // Refresh filter field dropdowns
    document.querySelectorAll('.cse-filter-field').forEach(function (s) { csePopulateSelect(s, opts, s.value, '— field —'); });
    // Refresh join col dropdowns
    document.querySelectorAll('.cse-join-row').forEach(function (row) { cseRefreshJoinCols(row); });
}

function cseAddJoin(def) {
    def = def || {};
    var container = document.getElementById('cse-joins');
    var row = document.createElement('div');
    row.className = 'cse-join-row';
    row.style.cssText = 'border:1px solid #e5e7eb;border-radius:4px;padding:0.5rem;margin-bottom:0.4rem;font-size:0.8rem';

    // Type
    var r1 = document.createElement('div'); r1.style.cssText = 'display:flex;gap:0.3rem;align-items:center;margin-bottom:0.25rem';
    var typeSel = document.createElement('select'); typeSel.className = 'cse-join-type form-select';
    ['LEFT','INNER','RIGHT'].forEach(function (t) { var o = document.createElement('option'); o.value = t; o.textContent = t + ' JOIN'; typeSel.appendChild(o); });
    typeSel.value = def.type || 'LEFT';
    r1.appendChild(Object.assign(document.createElement('span'), { textContent: 'Type', style: 'width:40px' }));
    r1.appendChild(typeSel); row.appendChild(r1);

    // Table
    var r2 = document.createElement('div'); r2.style.cssText = 'display:flex;gap:0.3rem;align-items:center;margin-bottom:0.25rem';
    var tblSel = document.createElement('select'); tblSel.className = 'cse-join-table form-select';
    csePopulateSelect(tblSel, CSE_TABLES, def.table || '', '— table —');
    tblSel.addEventListener('change', function () { cseFetchCols([tblSel.value], function () { cseRefreshJoinCols(row); cseRefreshFields(); cseSerialise(); }); });
    r2.appendChild(Object.assign(document.createElement('span'), { textContent: 'Table', style: 'width:40px' }));
    r2.appendChild(tblSel); row.appendChild(r2);

    // ON
    var r3 = document.createElement('div'); r3.style.cssText = 'display:flex;gap:0.3rem;align-items:center';
    var leftSel  = document.createElement('select'); leftSel.className  = 'cse-join-left form-select';
    var rightSel = document.createElement('select'); rightSel.className = 'cse-join-right form-select';
    var opts = cseAllColOpts();
    csePopulateSelect(leftSel,  opts, def.on_left  || '', '— left col —');
    csePopulateSelect(rightSel, opts, def.on_right || '', '— right col —');
    r3.appendChild(leftSel);
    r3.appendChild(Object.assign(document.createElement('span'), { textContent: '=' }));
    r3.appendChild(rightSel); row.appendChild(r3);

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button'; removeBtn.textContent = '✕ Remove'; removeBtn.style.cssText = 'margin-top:0.35rem;font-size:0.72rem;color:#dc2626;background:none;border:none;cursor:pointer;padding:0';
    removeBtn.addEventListener('click', function () { row.remove(); cseRefreshFields(); cseSerialise(); });
    row.appendChild(removeBtn);
    [typeSel, leftSel, rightSel].forEach(function (s) { s.addEventListener('change', cseSerialise); });
    container.appendChild(row);
}

function cseRefreshJoinCols(row) {
    var opts = cseAllColOpts();
    var l = row.querySelector('.cse-join-left'); var r = row.querySelector('.cse-join-right');
    if (l) csePopulateSelect(l, opts, l.value, '— left col —');
    if (r) csePopulateSelect(r, opts, r.value, '— right col —');
}

function cseAddFilter(def) {
    def = def || {};
    var container = document.getElementById('cse-filters');
    var row = document.createElement('div');
    row.className = 'cse-filter-row';
    row.style.cssText = 'display:flex;gap:0.25rem;align-items:center;margin-bottom:0.3rem;flex-wrap:wrap';
    var opts = cseAllColOpts();
    var fieldSel = document.createElement('select'); fieldSel.className = 'cse-filter-field form-select'; fieldSel.style.flex = '2';
    csePopulateSelect(fieldSel, opts, def.field || '', '— field —');
    var opSel = document.createElement('select'); opSel.className = 'cse-filter-op form-select'; opSel.style.flex = '1';
    CSE_FILTER_OPS.forEach(function (op) { var o = document.createElement('option'); o.value = op; o.textContent = op; opSel.appendChild(o); });
    opSel.value = def.op || '=';
    var valInput = document.createElement('input'); valInput.type = 'text'; valInput.className = 'cse-filter-val form-input'; valInput.placeholder = 'value'; valInput.value = def.value || ''; valInput.style.flex = '2';
    function toggleVal() { valInput.style.display = (opSel.value === 'IS NULL' || opSel.value === 'IS NOT NULL') ? 'none' : ''; }
    toggleVal(); opSel.addEventListener('change', toggleVal);
    var rmBtn = document.createElement('button'); rmBtn.type = 'button'; rmBtn.textContent = '✕'; rmBtn.style.cssText = 'background:none;border:none;cursor:pointer;color:#dc2626';
    rmBtn.addEventListener('click', function () { row.remove(); cseSerialise(); });
    [fieldSel, opSel].forEach(function (s) { s.addEventListener('change', cseSerialise); });
    valInput.addEventListener('input', cseSerialise);
    row.appendChild(fieldSel); row.appendChild(opSel); row.appendChild(valInput); row.appendChild(rmBtn);
    container.appendChild(row);
}

function cseReadConfig() {
    try { return JSON.parse(document.getElementById('cse-query-config-input')?.value || '{}'); } catch (e) { return {}; }
}

function cseSerialise() {
    var config = {};
    var tbl = document.getElementById('cse-table')?.value;
    if (tbl) config.table = tbl;
    config.joins = [];
    document.querySelectorAll('.cse-join-row').forEach(function (row) {
        var t = row.querySelector('.cse-join-table')?.value;
        if (t) config.joins.push({ type: row.querySelector('.cse-join-type')?.value || 'LEFT', table: t, on_left: row.querySelector('.cse-join-left')?.value || '', on_right: row.querySelector('.cse-join-right')?.value || '' });
    });
    config.filters = [];
    document.querySelectorAll('.cse-filter-row').forEach(function (row) {
        var f = row.querySelector('.cse-filter-field')?.value;
        if (f) config.filters.push({ field: f, op: row.querySelector('.cse-filter-op')?.value || '=', value: row.querySelector('.cse-filter-val')?.value || '' });
    });
    config.fields = [];
    document.querySelectorAll('#cse-fields input[type=checkbox]:checked').forEach(function (cb) { config.fields.push(cb.value); });
    var ob = document.getElementById('cse-orderby'); var od = document.getElementById('cse-orderdir'); var lm = document.getElementById('cse-limit');
    if (ob) config.order_by  = ob.value;
    if (od) config.order_dir = od.value;
    if (lm) config.limit     = parseInt(lm.value) || 100;
    document.getElementById('cse-query-config-input').value = JSON.stringify(config);
}

function cseHydrate() {
    var qc = cseReadConfig();
    var tblSel = document.getElementById('cse-table');
    if (tblSel && qc.table) { tblSel.value = qc.table; }
    // Joins
    var joinsEl = document.getElementById('cse-joins'); if (joinsEl) joinsEl.innerHTML = '';
    (qc.joins || []).forEach(function (j) { cseAddJoin(j); });
    // Filters
    var filtsEl = document.getElementById('cse-filters'); if (filtsEl) filtsEl.innerHTML = '';
    (qc.filters || []).forEach(function (f) { cseAddFilter(f); });
    cseRefreshFields();
    var od = document.getElementById('cse-orderdir'); var lm = document.getElementById('cse-limit'); var ob = document.getElementById('cse-orderby');
    if (od) od.value = qc.order_dir || 'ASC';
    if (lm) lm.value = qc.limit    || 100;
    if (ob) ob.value = qc.order_by || '';
}

// Wire events
document.getElementById('cse-table')?.addEventListener('change', function () {
    var t = this.value;
    if (t) cseFetchCols([t], function () { cseRefreshFields(); cseSerialise(); });
    else { cseRefreshFields(); cseSerialise(); }
});
document.getElementById('cse-orderby')?.addEventListener('change', cseSerialise);
document.getElementById('cse-orderdir')?.addEventListener('change', cseSerialise);
document.getElementById('cse-limit')?.addEventListener('input', cseSerialise);

// Init on load for query sets
(function () {
    var typeSel = document.getElementById('set-type');
    var initType = typeSel ? typeSel.value : <?= json_encode($setType) ?>;
    if (initType === 'query') {
        var allTables = [<?= json_encode($queryConfig['table'] ?? '') ?>].concat(<?= json_encode(array_column($queryConfig['joins'] ?? [], 'table')) ?>).filter(Boolean);
        if (allTables.length) {
            cseFetchCols(allTables, function () { cseRefreshFields(); cseHydrate(); });
        }
        // Show correct panels (already handled by PHP, but ensure ref panel is right)
        document.getElementById('cse-ref-manual').style.display = 'none';
        document.getElementById('cse-ref-query').style.display  = '';
        document.getElementById('cse-ref-title').textContent    = 'About Query Sets';
    }
}());
</script>

