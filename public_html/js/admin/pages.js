(function () {
    var layout = document.getElementById('pages-layout');
    if (!layout) return;

    var csrfToken = layout.dataset.csrf;
    var templates = JSON.parse(layout.dataset.templates || '[]');

    var rows          = document.querySelectorAll('#pages-table tbody tr');
    var filterLinks   = document.querySelectorAll('.pl-nav-item[data-filter]');
    var searchInput   = document.getElementById('pages-search');
    var filterLabel   = document.getElementById('pages-filter-label');
    var placeholder   = document.getElementById('pages-detail-placeholder');
    var detailContent = document.getElementById('pages-detail-content');

    var activeFilter = 'all';
    var activeRow    = null;

    // ── Filter sidebar ──
    filterLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            filterLinks.forEach(function (l) { l.classList.remove('active'); });
            link.classList.add('active');
            activeFilter = link.dataset.filter;
            filterLabel.textContent = link.textContent.trim().replace(/\d+$/, '').trim();
            applyFilters();
            clearDetail();
        });
    });

    // ── Search ──
    searchInput.addEventListener('input', function () { applyFilters(); clearDetail(); });

    function applyFilters() {
        var q = searchInput.value.toLowerCase();
        rows.forEach(function (row) {
            var show = true;
            if (activeFilter !== 'all') {
                var parts = activeFilter.split(':');
                show = row.dataset[parts[0]] === parts[1];
            }
            if (show && q) {
                show = row.dataset.title.includes(q) || row.dataset.slug.includes(q);
            }
            row.style.display = show ? '' : 'none';
        });
    }

    // ── Row click → detail ──
    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            if (activeRow) activeRow.classList.remove('selected');
            row.classList.add('selected');
            activeRow = row;
            showDetail(JSON.parse(row.dataset.page));
        });
    });

    function clearDetail() {
        if (activeRow) activeRow.classList.remove('selected');
        activeRow = null;
        placeholder.style.display = '';
        detailContent.style.display = 'none';
        detailContent.innerHTML = '';
    }

    function showDetail(p) {
        placeholder.style.display = 'none';

        var editUrl = p.mode === 'html'
            ? '/admin/pages/' + p.id + '/html'
            : '/admin/editor/' + p.id + '/edit';

        var actionsHtml = '<div class="pl-detail-actions-stack">'
            + '<a href="' + editUrl + '" class="btn btn-primary" style="width:100%">Edit Content</a>'
            + '<a href="/' + escHtml(p.slug) + '" target="_blank" class="btn btn-outline" style="width:100%">View \u2197</a>';

        if (p.mode === 'block') {
            actionsHtml += '<form method="POST" action="/admin/pages/' + p.id + '/export-html">'
                + '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">'
                + '<button class="btn btn-outline" style="width:100%">\u2193 Export HTML</button></form>';
        }
        if (p.mode === 'html') {
            actionsHtml += '<form method="POST" action="/admin/pages/' + p.id + '/convert-to-blocks"'
                + ' data-confirm="Convert this page to block editor? HTML will be parsed into blocks.">'
                + '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">'
                + '<button class="btn btn-outline" style="width:100%">Convert to blocks</button></form>';
        }
        actionsHtml += '</div>';

        var templateOptions = templates.map(function (t) {
            return '<option value="' + escHtml(t.slug) + '"' + (p.template === t.slug ? ' selected' : '') + '>' + escHtml(t.name) + '</option>';
        }).join('');

        var settingsHtml = '<form method="POST" action="/admin/pages/' + p.id + '" class="pl-detail-settings">'
            + '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">'
            + '<div class="pl-detail-settings-section">Settings</div>'
            + '<div class="form-group"><label>Title</label>'
            +   '<input type="text" name="title" value="' + escHtml(p.title) + '" class="form-input" required></div>'
            + '<div class="form-group"><label>URL Slug</label>'
            +   '<div class="input-with-prefix"><span class="input-prefix">/</span>'
            +   '<input type="text" name="slug" value="' + escHtml(p.slug) + '" class="form-input" pattern="[a-z0-9\\/\\-]+" required></div></div>'
            + '<div class="form-group"><label>Status</label>'
            +   '<select name="status" class="form-input">'
            +     '<option value="published"' + (p.status === 'published' ? ' selected' : '') + '>Published</option>'
            +     '<option value="draft"' + (p.status === 'draft' ? ' selected' : '') + '>Draft</option>'
            +     '<option value="archived"' + (p.status === 'archived' ? ' selected' : '') + '>Archived</option>'
            +   '</select></div>'
            + '<div class="form-group"><label>Render Mode</label>'
            +   '<select name="render_mode" class="form-input">'
            +     '<option value="block"' + (p.mode === 'block' ? ' selected' : '') + '>Cruinn (block editor)</option>'
            +     '<option value="html"' + (p.mode === 'html' ? ' selected' : '') + '>HTML (code editor)</option>'
            +     '<option value="file"' + (p.mode === 'file' ? ' selected' : '') + '>File</option>'
            +   '</select></div>'
            + '<div class="form-group"><label>Template</label>'
            +   '<select name="template" class="form-input">' + templateOptions + '</select></div>'
            + '<div class="form-group"><label>Meta Description</label>'
            +   '<input type="text" name="meta_description" value="' + escHtml(p.meta_description || '') + '" class="form-input"></div>'
            + '<table class="pl-meta" style="margin-bottom:.75rem">'
            +   '<tr><th>Author</th><td>' + escHtml(p.author) + '</td></tr>'
            +   '<tr><th>Updated</th><td>' + escHtml(p.updated) + '</td></tr>'
            + '</table>'
            + '<button type="submit" class="btn btn-primary" style="width:100%">Save Settings</button></form>';

        detailContent.innerHTML = ''
            + '<div class="pl-detail-icon">&#128196;</div>'
            + '<div class="pl-detail-title">' + escHtml(p.title) + '</div>'
            + '<div class="pl-detail-subtitle">/' + escHtml(p.slug) + '</div>'
            + actionsHtml
            + settingsHtml;
        detailContent.style.display = '';
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = String(s != null ? s : '');
        return d.innerHTML;
    }
}());

PanelCollapse.init([
    { panelId: 'pl-sidebar',   toggleId: 'pl-sidebar-toggle', storeKey: 'admin_pages_sidebar', side: 'left' },
    { panelId: 'pages-detail', toggleId: 'pl-detail-toggle',  storeKey: 'admin_pages_detail',  side: 'right' }
]);
