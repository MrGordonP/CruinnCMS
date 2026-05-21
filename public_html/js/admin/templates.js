// Template-type radio toggle — hide per-page fields for content templates
(function () {
    var radios = document.querySelectorAll('input[name="template_type"]');
    var pageFields = document.getElementById('tpl_page_fields');
    var layoutSelect = document.getElementById('tpl_layout_page_id');
    if (!pageFields) { return; }
    function toggle() {
        var checked = document.querySelector('input[name="template_type"]:checked');
        if (!checked) { return; }
        var isPageTemplate = checked.value !== 'content';
        pageFields.style.display = isPageTemplate ? '' : 'none';
        if (layoutSelect) {
            layoutSelect.required = isPageTemplate;
            layoutSelect.disabled = !isPageTemplate;
        }
    }
    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    toggle();
}());

// Page templates right-panel mode switching (details vs create)
(function () {
    var openBtn = document.getElementById('tpl-open-create-btn');
    var cancelBtn = document.getElementById('tpl-cancel-create-btn');
    var detailMode = document.getElementById('tpl-page-template-detail-mode');
    var createMode = document.getElementById('tpl-page-template-create-mode');
    if (!openBtn || !detailMode || !createMode) { return; }

    function showCreateMode() {
        detailMode.style.display = 'none';
        createMode.style.display = '';
    }

    function showDetailMode() {
        detailMode.style.display = '';
        createMode.style.display = 'none';
    }

    openBtn.addEventListener('click', function () {
        showCreateMode();
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            showDetailMode();
        });
    }

    showDetailMode();
}());

// Selected page template details panel
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.js-page-template-row[data-template]'));
    var panel = document.getElementById('tpl-selected-panel');
    if (!panel || rows.length === 0) { return; }

    var emptyEl = document.getElementById('tpl-selected-empty');
    var contentEl = document.getElementById('tpl-selected-content');
    var nameEl = document.getElementById('tpl-selected-name');
    var slugEl = document.getElementById('tpl-selected-slug');
    var descEl = document.getElementById('tpl-selected-desc');
    var typeEl = document.getElementById('tpl-selected-type');
    var layoutEl = document.getElementById('tpl-selected-layout');
    var zonesEl = document.getElementById('tpl-selected-zones');
    var pagesEl = document.getElementById('tpl-selected-pages');
    var editLinkEl = document.getElementById('tpl-selected-edit-link');

    function parseTemplate(row) {
        try {
            return JSON.parse(row.getAttribute('data-template') || '{}');
        } catch (e) {
            return null;
        }
    }

    function selectRow(row) {
        rows.forEach(function (r) { r.classList.remove('selected'); });
        row.classList.add('selected');

        var data = parseTemplate(row);
        if (!data) { return; }

        if (emptyEl) { emptyEl.style.display = 'none'; }
        if (contentEl) { contentEl.style.display = ''; }
        if (nameEl) { nameEl.textContent = data.name || ''; }
        if (slugEl) { slugEl.textContent = data.slug ? ('/' + data.slug) : ''; }
        if (descEl) { descEl.textContent = data.description || ''; }
        if (typeEl) {
            typeEl.textContent = data.template_type === 'content' ? 'Content' : 'Page';
        }
        if (layoutEl) {
            layoutEl.textContent = data.layout_title || (data.layout_page_id ? ('Layout #' + data.layout_page_id) : 'Not set');
        }
        if (zonesEl) {
            zonesEl.textContent = Array.isArray(data.zones) && data.zones.length ? data.zones.join(', ') : 'main';
        }
        if (pagesEl) { pagesEl.textContent = String(data.page_count || 0); }

        if (editLinkEl) {
            if (data.canvas_page_id) {
                editLinkEl.style.display = '';
                editLinkEl.setAttribute('href', '/admin/editor/' + data.canvas_page_id + '/edit');
            } else {
                editLinkEl.style.display = 'none';
                editLinkEl.setAttribute('href', '#');
            }
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            selectRow(row);
        });
    });

    selectRow(rows[0]);
}());

// Selected template layout details panel
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.js-template-layout-row[data-layout]'));
    var panel = document.getElementById('tpl-layout-selected-panel');
    if (!panel || rows.length === 0) { return; }

    var emptyEl = document.getElementById('tpl-layout-selected-empty');
    var contentEl = document.getElementById('tpl-layout-selected-content');
    var titleEl = document.getElementById('tpl-layout-selected-title');
    var slugEl = document.getElementById('tpl-layout-selected-slug');
    var statusEl = document.getElementById('tpl-layout-selected-status');
    var usageEl = document.getElementById('tpl-layout-selected-usage');
    var updatedEl = document.getElementById('tpl-layout-selected-updated');
    var editLinkEl = document.getElementById('tpl-layout-selected-edit-link');
    var deleteFormEl = document.getElementById('tpl-layout-selected-delete-form');
    var deleteBtnEl = document.getElementById('tpl-layout-selected-delete-btn');

    function parseLayout(row) {
        try {
            return JSON.parse(row.getAttribute('data-layout') || '{}');
        } catch (e) {
            return null;
        }
    }

    function selectRow(row) {
        rows.forEach(function (r) { r.classList.remove('selected'); });
        row.classList.add('selected');

        var data = parseLayout(row);
        if (!data) { return; }

        if (emptyEl) { emptyEl.style.display = 'none'; }
        if (contentEl) { contentEl.style.display = ''; }
        if (titleEl) { titleEl.textContent = data.title || ''; }
        if (slugEl) { slugEl.textContent = data.slug ? ('/' + data.slug) : ''; }
        if (statusEl) { statusEl.textContent = data.status || 'published'; }
        if (usageEl) { usageEl.textContent = String(data.usage_count || 0); }
        if (updatedEl) {
            updatedEl.textContent = data.updated_at ? data.updated_at.replace('T', ' ') : '—';
        }

        if (editLinkEl) {
            editLinkEl.setAttribute('href', '/admin/editor/' + data.id + '/edit');
        }

        if (deleteFormEl && deleteBtnEl) {
            if ((data.usage_count || 0) === 0) {
                deleteFormEl.style.display = 'inline';
                deleteFormEl.setAttribute('action', '/admin/templates/layouts/' + data.id + '/delete');
                deleteBtnEl.setAttribute('data-confirm', "Delete template layout '" + (data.title || '') + "'?");
            } else {
                deleteFormEl.style.display = 'none';
                deleteFormEl.setAttribute('action', '#');
                deleteBtnEl.removeAttribute('data-confirm');
            }
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            selectRow(row);
        });
    });

    selectRow(rows[0]);
}());
