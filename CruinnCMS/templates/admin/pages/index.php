<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

// Group pages by status and mode for sidebar counts
$byStatus = ['published' => 0, 'draft' => 0, 'archived' => 0];
$byMode   = ['block' => 0, 'html' => 0, 'file' => 0];
foreach ($pages as $pg) {
    $s = $pg['status'] ?? 'published';
    $m = $pg['render_mode'] ?? 'block';
    if (isset($byStatus[$s])) $byStatus[$s]++;
    if (isset($byMode[$m])) $byMode[$m]++;
}
?>

<div class="panel-layout" id="pages-layout">

    <!-- ── Left: Filters ──────────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Pages</h3>
            <a href="/admin/pages/new" class="btn btn-sm btn-primary">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <span class="pl-nav-section">Status</span>
            <a class="pl-nav-item active" data-filter="all" href="#">
                All <span class="pl-nav-count"><?= count($pages) ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:published" href="#">
                Published <span class="pl-nav-count"><?= $byStatus['published'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:draft" href="#">
                Draft <span class="pl-nav-count"><?= $byStatus['draft'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:archived" href="#">
                Archived <span class="pl-nav-count"><?= $byStatus['archived'] ?></span>
            </a>

            <span class="pl-nav-section">Mode</span>
            <a class="pl-nav-item" data-filter="mode:block" href="#">
                Block <span class="pl-nav-count"><?= $byMode['block'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="mode:html" href="#">
                HTML <span class="pl-nav-count"><?= $byMode['html'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="mode:file" href="#">
                File <span class="pl-nav-count"><?= $byMode['file'] ?></span>
            </a>
        </div>
    </div>

    <!-- ── Middle: Page list ──────────────────────────────────── -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="pages-filter-label">All Pages</span>
            <div class="pl-main-toolbar-actions">
                <a href="/admin/pages/new" class="btn btn-sm btn-primary">+ New Page</a>
            </div>
        </div>
        <div class="pl-main-search">
            <input type="search" class="pl-search-input" id="pages-search" placeholder="Search pages…" autocomplete="off">
        </div>
        <div class="pl-main-scroll">
            <?php if (empty($pages)): ?>
                <div class="pl-empty">No pages yet. <a href="/admin/pages/new">Create your first page</a>.</div>
            <?php else: ?>
            <table class="pl-table" id="pages-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>URL</th>
                        <th>Mode</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $pg):
                    $mode   = $pg['render_mode'] ?? 'block';
                    $status = $pg['status'] ?? 'published';
                    $modeColour = match($mode) {
                        'file'  => 'background:#d97706;color:#fff',
                        'html'  => 'background:#7c3aed;color:#fff',
                        default => 'background:#1d9e75;color:#fff',
                    };
                ?>
                <tr data-id="<?= (int)$pg['id'] ?>"
                    data-status="<?= e($status) ?>"
                    data-mode="<?= e($mode) ?>"
                    data-title="<?= e(strtolower($pg['title'])) ?>"
                    data-slug="<?= e(strtolower($pg['slug'])) ?>"
                    data-page='<?= e(json_encode([
                        'id'       => (int)$pg['id'],
                        'title'    => $pg['title'],
                        'slug'     => $pg['slug'],
                        'mode'     => $mode,
                        'status'   => $status,
                        'template' => $pg['template'] ?? 'default',
                        'author'   => $pg['author_name'] ?? '—',
                        'updated'  => format_date($pg['updated_at'], 'j M Y'),
                    ])) ?>'>
                    <td>
                        <?= e($pg['title']) ?>
                        <?php if ($status !== 'published'): ?>
                        <span class="badge" style="background:#d97706;color:#fff;font-size:.68rem;margin-left:.3rem"><?= e($status) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code style="font-size:.8rem">/<?= e($pg['slug']) ?></code></td>
                    <td><span class="badge" style="<?= $modeColour ?>;font-size:.68rem"><?= e($mode) ?></span></td>
                    <td style="color:#888;font-size:.8rem"><?= format_date($pg['updated_at'], 'j M Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Page detail ─────────────────────────────────── -->
    <div class="pl-detail" id="pages-detail">
        <div class="pl-detail-header"><h3>Details</h3></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="pages-detail-placeholder">
                <div class="pl-detail-placeholder-icon">📄</div>
                <span>Select a page to see details</span>
            </div>
            <div id="pages-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<script>
(function () {
    const rows        = document.querySelectorAll('#pages-table tbody tr');
    const filterLinks = document.querySelectorAll('.pl-nav-item[data-filter]');
    const searchInput = document.getElementById('pages-search');
    const filterLabel = document.getElementById('pages-filter-label');
    const placeholder = document.getElementById('pages-detail-placeholder');
    const detailContent = document.getElementById('pages-detail-content');

    let activeFilter = 'all';
    let activeRow    = null;

    // ── Filter sidebar ──
    filterLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            filterLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            activeFilter = link.dataset.filter;
            filterLabel.textContent = link.textContent.trim().replace(/\d+$/, '').trim();
            applyFilters();
            clearDetail();
        });
    });

    // ── Search ──
    searchInput.addEventListener('input', () => { applyFilters(); clearDetail(); });

    function applyFilters() {
        const q = searchInput.value.toLowerCase();
        rows.forEach(row => {
            let show = true;
            if (activeFilter !== 'all') {
                const [type, val] = activeFilter.split(':');
                show = row.dataset[type] === val;
            }
            if (show && q) {
                show = row.dataset.title.includes(q) || row.dataset.slug.includes(q);
            }
            row.style.display = show ? '' : 'none';
        });
    }

    // ── Row click → detail ──
    rows.forEach(row => {
        row.addEventListener('click', () => {
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
        const modeColour = p.mode === 'file' ? '#d97706' : p.mode === 'html' ? '#7c3aed' : '#1d9e75';
        const editUrl  = p.mode === 'html'
            ? `/admin/pages/${p.id}/html`
            : `/admin/editor/${p.id}/edit`;

        let actionsHtml = `<a href="${editUrl}" class="btn btn-primary">Edit Content</a>
            <a href="/admin/pages/${p.id}/edit" class="btn btn-outline">Settings</a>
            <a href="/${escHtml(p.slug)}" target="_blank" class="btn btn-outline">View ↗</a>`;
        if (p.mode === 'block') {
            actionsHtml += `
            <form method="POST" action="/admin/pages/${p.id}/export-html" style="flex:1">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <button class="btn btn-outline" style="width:100%" title="Export to static HTML">↓ Export HTML</button>
            </form>`;
        }
        if (p.mode === 'html') {
            actionsHtml += `
            <form method="POST" action="/admin/pages/${p.id}/convert-to-blocks" style="flex:1"
                  onsubmit="return confirm('Convert this page to block editor? HTML will be parsed into blocks.')">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <button class="btn btn-outline" style="width:100%">Convert to blocks</button>
            </form>`;
        }

        detailContent.innerHTML = `
            <div class="pl-detail-icon">📄</div>
            <div class="pl-detail-title">${escHtml(p.title)}</div>
            <div class="pl-detail-subtitle">/${escHtml(p.slug)}</div>
            <div class="pl-detail-actions">${actionsHtml}</div>
            <table class="pl-meta">
                <tr><th>Status</th><td><span class="badge" style="background:${p.status==='published'?'#1d9e75':'#d97706'};color:#fff;font-size:.72rem">${escHtml(p.status)}</span></td></tr>
                <tr><th>Mode</th><td><span class="badge" style="background:${modeColour};color:#fff;font-size:.72rem">${escHtml(p.mode)}</span></td></tr>
                <tr><th>Template</th><td>${escHtml(p.template)}</td></tr>
                <tr><th>Author</th><td>${escHtml(p.author)}</td></tr>
                <tr><th>Updated</th><td>${escHtml(p.updated)}</td></tr>
            </table>`;
        detailContent.style.display = '';
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }
})();
</script>
