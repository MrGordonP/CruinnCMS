<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireJs('structure.js');
$GLOBALS['admin_flush_layout'] = true;

// ── Status counts for left sidebar filters ──────────────────────
$byStatus = ['published' => 0, 'draft' => 0, 'archived' => 0];
foreach ($contentPages as $pg) {
    $s = $pg['status'] ?? 'published';
    if (isset($byStatus[$s])) $byStatus[$s]++;
}

// ── Template settings map (chrome + zones) ──────────────────────
$tplSettingsMap = [];
foreach ($templates as $tpl) {
    $settings = is_string($tpl['settings']) ? json_decode($tpl['settings'], true) : ($tpl['settings'] ?? []);
    $zones    = is_string($tpl['zones'])    ? json_decode($tpl['zones'], true)    : ($tpl['zones']    ?? ['main']);
    $tplSettingsMap[$tpl['slug']] = [
        'name'     => $tpl['name'],
        'settings' => $settings ?? [],
        'zones'    => $zones ?? ['main'],
    ];
}

// ── Which slugs have children (for tree collapse toggles) ────────
$slugsWithChildren = [];
foreach ($contentPages as $pg) {
    $parts = explode('/', $pg['slug']);
    for ($i = 1; $i < count($parts); $i++) {
        $slugsWithChildren[implode('/', array_slice($parts, 0, $i))] = true;
    }
}

// ── page_id → [menu_id, …] for right-panel navigation section ───
$pageInMenus = [];
foreach ($menuItemsByMenu as $menuId => $items) {
    foreach ($items as $item) {
        if (!empty($item['page_id'])) {
            $pageInMenus[(int)$item['page_id']][] = (int)$menuId;
        }
    }
}

// ── Location labels ──────────────────────────────────────────────
$locationLabels = [
    'main'    => 'Primary Navigation',
    'footer'  => 'Footer',
    'sidebar' => 'Sidebar',
    'topbar'  => 'Utility Bar',
    'mobile'  => 'Mobile',
    'custom'  => 'Custom',
];

// ── Full menus + items for JS (right-panel navigation section) ───
$menusForJs = [];
foreach ($menus as $mn) {
    $mnId = (int)$mn['id'];
    $menusForJs[] = [
        'id'       => $mnId,
        'name'     => $mn['name'],
        'location' => $mn['location'],
        'locLabel' => $locationLabels[$mn['location']] ?? ucfirst($mn['location']),
        'items'    => array_values($menuItemsByMenu[$mnId] ?? []),
    ];
}

include __DIR__ . '/_tabs.php';
?>
<style>
/* Flush panel layout inside .acp-panel */
.acp-panel:has(#structure-layout) {
    padding: 0;
    border-radius: 0 0 6px 6px;
    overflow: hidden;
}
#structure-layout {
    border-radius: 0 0 6px 6px;
    height: calc(100vh - 160px);
}

/* ── Left: page list rows ──────────────────────────────── */
.st-list-row {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .32rem .75rem;
    cursor: pointer;
    font-size: .82rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background .1s;
    min-width: 0;
}
.st-list-row:hover    { background: #f5f9f7; }
.st-list-row.selected { background: #e6f7f2; font-weight: 600; }
.st-list-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.st-status-dot {
    width: .42rem;
    height: .42rem;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-block;
}

/* ── Filter tabs at top of left sidebar ───────────────── */
.st-filter-bar {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
}
.st-filter-btn {
    flex: 1;
    background: none;
    border: none;
    border-right: 1px solid #e5e7eb;
    padding: .32rem .2rem;
    font-size: .7rem;
    cursor: pointer;
    color: #666;
    line-height: 1.2;
    transition: color .1s;
}
.st-filter-btn:last-child { border-right: none; }
.st-filter-btn.active { color: #1d9e75; font-weight: 700; }

/* ── Middle: tree rows ────────────────────────────────── */
.st-tree-row {
    display: flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .5rem .3rem 0;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background .1s;
}
.st-tree-row:hover    { background: #f5f9f7; }
.st-tree-row.selected { background: #e6f7f2; }
.st-tree-toggle {
    background: none;
    border: none;
    cursor: pointer;
    font-size: .68rem;
    color: #bbb;
    padding: 0;
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    line-height: 1;
}
.st-tree-toggle:hover { color: #1d9e75; }
.st-tree-label {
    flex: 1;
    font-size: .82rem;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.st-tree-slug {
    font-size: .7rem;
    color: #bbb;
    font-family: monospace;
    flex-shrink: 0;
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.st-tree-add-btn {
    background: none;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    font-size: .67rem;
    color: #999;
    cursor: pointer;
    padding: .1rem .35rem;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity .1s;
    margin-right: .3rem;
}
.st-tree-row:hover .st-tree-add-btn { opacity: 1; }
.st-tree-add-btn:hover { background: #1d9e75; color: #fff; border-color: #1d9e75; }

/* ── Right: navigation section ───────────────────────── */
.st-nav-menu-block {
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    margin-bottom: .45rem;
    overflow: hidden;
}
.st-nav-menu-header {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .38rem .6rem;
    background: #f9fafb;
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
}
.st-nav-menu-header:hover { background: #f0f0f0; }
.st-nav-in-badge {
    background: #1d9e75;
    color: #fff;
    font-size: .63rem;
    font-weight: 700;
    padding: .05rem .3rem;
    border-radius: 10px;
    margin-left: auto;
}
.st-nav-menu-body {
    padding: .5rem .6rem;
    border-top: 1px solid #e5e7eb;
    display: none;
}
.st-nav-menu-body.open { display: block; }
.st-nav-existing {
    font-size: .75rem;
    color: #444;
    padding: .18rem 0;
    border-bottom: 1px dashed #eee;
    margin-bottom: .3rem;
}
.st-nav-form-row {
    display: flex;
    flex-direction: column;
    gap: .25rem;
    margin-bottom: .3rem;
}
.st-nav-form-row label { font-size: .7rem; color: #888; }
.st-nav-form-row input,
.st-nav-form-row select {
    font-size: .8rem;
    padding: .28rem .4rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    width: 100%;
    box-sizing: border-box;
}
.st-nav-add-btn {
    display: inline-block;
    margin-top: .35rem;
    background: #1d9e75;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: .78rem;
    padding: .3rem .75rem;
    cursor: pointer;
}
.st-nav-add-btn:hover { background: #178a64; }
.st-nav-add-btn:disabled { opacity: .6; cursor: default; }

/* ── Drag-and-drop ───────────────────────────────── */
.st-tree-row[draggable] { cursor: grab; }
.st-tree-row.drag-over  { background: #d1fae5; outline: 2px dashed #1d9e75; outline-offset: -2px; }
.st-tree-row.drag-source { opacity: .4; }
.st-tree-drop-root {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .4rem .75rem;
    margin: .3rem .5rem;
    border: 2px dashed #d1d5db;
    border-radius: 4px;
    font-size: .75rem;
    color: #aaa;
    cursor: default;
    transition: background .1s, border-color .1s;
}
.st-tree-drop-root.drag-over { background: #d1fae5; border-color: #1d9e75; color: #1d9e75; }
</style>

<div class="panel-layout" id="structure-layout" data-menus='<?= e(json_encode($menusForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>

    <!-- ══ LEFT: Page Inventory ══════════════════════════════════ -->
    <div class="pl-sidebar" id="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Pages</h3>
            <button type="button" class="pl-panel-toggle" id="pl-sidebar-toggle" title="Collapse">&#x25C0;</button>
        </div>
        <div class="pl-sidebar-scroll" style="padding: 0">

            <!-- Search -->
            <div style="padding: .5rem .65rem .4rem; border-bottom: 1px solid #e5e7eb">
                <input type="search" id="st-search" class="pl-search-input"
                       placeholder="Search…" autocomplete="off"
                       style="width: 100%; box-sizing: border-box">
            </div>

            <!-- Status filter tabs -->
            <div class="st-filter-bar">
                <button class="st-filter-btn active" data-filter="all">All&nbsp;<?= count($contentPages) ?></button>
                <button class="st-filter-btn" data-filter="published">Pub&nbsp;<?= $byStatus['published'] ?></button>
                <button class="st-filter-btn" data-filter="draft">Draft&nbsp;<?= $byStatus['draft'] ?></button>
                <button class="st-filter-btn" data-filter="archived">Arch&nbsp;<?= $byStatus['archived'] ?></button>
            </div>

            <?php if (empty($contentPages)): ?>
                <div class="pl-empty">No pages yet.</div>
            <?php else: ?>
            <div id="st-list">
            <?php foreach ($contentPages as $pg):
                $status    = $pg['status'] ?? 'published';
                $statusClr = $status === 'published' ? '#1d9e75' : ($status === 'draft' ? '#d97706' : '#9ca3af');
            ?>
            <div class="st-list-row"
                 data-id="<?= (int)$pg['id'] ?>"
                 data-status="<?= e($status) ?>"
                 data-search="<?= e(strtolower($pg['title'] . ' ' . $pg['slug'])) ?>">
                <span class="st-status-dot" style="background: <?= $statusClr ?>"></span>
                <span class="st-list-title"><?= e($pg['title']) ?></span>
                <?php if ($pg['slug'] === 'home'): ?><span style="font-size:.7rem">🏠</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div><!-- /pl-sidebar -->

    <!-- ══ MIDDLE: Hierarchy Tree ════════════════════════════════ -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title">Site Hierarchy</span>
            <div class="pl-main-toolbar-actions">
                <a href="<?= url('/admin/pages/new') ?>" class="btn btn-sm btn-primary">+ New Page</a>
            </div>
        </div>
        <div class="pl-main-scroll" style="padding: 0" id="st-tree">
            <div class="st-tree-drop-root" id="st-drop-root" data-parent-slug="">↑ Drop here to make top-level</div>

        <?php if (empty($contentPages)): ?>
            <div class="pl-empty">No pages yet. <a href="<?= url('/admin/pages/new') ?>">Create your first page</a>.</div>
        <?php else: ?>
        <?php foreach ($contentPages as $pg):
            $status      = $pg['status'] ?? 'published';
            $slugParts   = explode('/', $pg['slug']);
            $depth       = count($slugParts) - 1;
            $parentSlug  = $depth > 0 ? implode('/', array_slice($slugParts, 0, $depth)) : '';
            $isParent    = isset($slugsWithChildren[$pg['slug']]);
            $tplInfo     = $tplSettingsMap[$pg['template'] ?? ''] ?? null;
            $tplSettings = $tplInfo['settings'] ?? [];
            $tplZones    = $tplInfo['zones'] ?? ['main'];
            $showHeader  = $tplSettings['show_header'] ?? true;
            $showFooter  = $tplSettings['show_footer'] ?? true;
            $hasSidebar  = in_array('sidebar', $tplZones, true);
            $menuIds     = $pageInMenus[(int)$pg['id']] ?? [];
            $pageData    = [
                'id'         => (int)$pg['id'],
                'title'      => $pg['title'],
                'slug'       => $pg['slug'],
                'status'     => $status,
                'template'   => $pg['template'] ?? 'default',
                'tplName'    => $tplInfo['name'] ?? ucfirst($pg['template'] ?? 'default'),
                'author'     => $pg['author_name'] ?? '—',
                'updated'    => format_date($pg['updated_at'], 'j M Y'),
                'showHeader' => $showHeader,
                'showFooter' => $showFooter,
                'hasSidebar' => $hasSidebar,
                'renderMode' => $pg['render_mode'] ?? 'block',
                'menuIds'    => $menuIds,
            ];
        ?>
        <div class="st-tree-row"
             draggable="true"
             data-id="<?= (int)$pg['id'] ?>"
             data-slug="<?= e($pg['slug']) ?>"
             data-parent="<?= e($parentSlug) ?>"
             data-depth="<?= $depth ?>"
             data-is-parent="<?= $isParent ? '1' : '0' ?>"
             data-page='<?= e(json_encode($pageData)) ?>'
             style="padding-left: calc(.4rem + <?= $depth * 1.25 ?>rem)">
            <?php if ($isParent): ?>
            <button class="st-tree-toggle" title="Collapse/expand">▾</button>
            <?php else: ?>
            <span style="width:1rem;flex-shrink:0;display:inline-block"></span>
            <?php endif; ?>
            <span class="st-tree-label">
                <?php if ($depth > 0): ?><span style="color:#ccc;margin-right:.15rem">└</span><?php endif; ?>
                <?= e($pg['title']) ?>
                <?php if ($status !== 'published'): ?>
                <span class="badge" style="background:<?= $status === 'draft' ? '#d97706' : '#9ca3af' ?>;color:#fff;font-size:.62rem;margin-left:.2rem"><?= e($status) ?></span>
                <?php endif; ?>
                <?php if ($pg['slug'] === 'home'): ?><span style="font-size:.7rem;margin-left:.2rem">🏠</span><?php endif; ?>
            </span>
            <span class="st-tree-slug">/<?= e($pg['slug']) ?></span>
            <button class="st-tree-add-btn" title="Add to menu">+ Menu</button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        </div>
    </div><!-- /pl-main -->

    <!-- ══ RIGHT: Detail ══════════════════════════════════════════ -->
    <div class="pl-detail" id="structure-detail">
        <div class="pl-detail-header"><h3>Details</h3><button type="button" class="pl-panel-toggle" id="pl-detail-toggle" title="Collapse">&#x25B6;</button></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="st-placeholder">
                <div class="pl-detail-placeholder-icon">🗺️</div>
                <span>Select a page to see details</span>
            </div>
            <div id="st-detail-content" style="display:none"></div>
        </div>
    </div>

</div><!-- /panel-layout -->
    const placeholder = document.getElementById('st-placeholder');
    const detailEl    = document.getElementById('st-detail-content');
    const searchInput = document.getElementById('st-search');
    const listEl      = document.getElementById('st-list');
    const treeEl      = document.getElementById('st-tree');

    let activeListRow = null;
    let activeTreeRow = null;

    // ── Left sidebar: filter buttons ─────────────────────────────
    document.querySelectorAll('.st-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.st-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyListFilter();
        });
    });

    searchInput?.addEventListener('input', applyListFilter);

    function applyListFilter() {
        const filter = document.querySelector('.st-filter-btn.active')?.dataset.filter ?? 'all';
        const q = searchInput.value.toLowerCase();
        document.querySelectorAll('#st-list .st-list-row').forEach(row => {
            const statusOk = filter === 'all' || row.dataset.status === filter;
            const searchOk = !q || row.dataset.search.includes(q);
            row.style.display = statusOk && searchOk ? '' : 'none';
        });
    }

    // ── Left list: row click ──────────────────────────────────────
    listEl?.addEventListener('click', e => {
        const row = e.target.closest('.st-list-row');
        if (!row) return;
        const id = parseInt(row.dataset.id, 10);
        document.querySelectorAll('#st-list .st-list-row').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        activeListRow = row;
        const treeRow = treeEl.querySelector(`.st-tree-row[data-id="${id}"]`);
        if (treeRow) {
            selectTreeRow(treeRow, false);
            treeRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    // ── Middle tree: clicks ───────────────────────────────────────
    treeEl.addEventListener('click', e => {
        const toggle = e.target.closest('.st-tree-toggle');
        if (toggle) {
            e.stopPropagation();
            collapseToggle(toggle.closest('.st-tree-row'));
            return;
        }
        const addBtn = e.target.closest('.st-tree-add-btn');
        if (addBtn) {
            e.stopPropagation();
            const treeRow = addBtn.closest('.st-tree-row');
            selectTreeRow(treeRow);
            setTimeout(() => {
                const navSection = document.getElementById('st-nav-section');
                if (navSection) navSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Open first closed accordion
                const firstClosed = detailEl.querySelector('.st-nav-menu-body:not(.open)');
                if (firstClosed) {
                    firstClosed.classList.add('open');
                    firstClosed.previousElementSibling.querySelector('.st-nav-chevron').textContent = '▾';
                }
            }, 60);
            return;
        }
        const treeRow = e.target.closest('.st-tree-row');
        if (treeRow) selectTreeRow(treeRow);
    });

    // ── Collapse/expand ───────────────────────────────────────────
    function collapseToggle(parentRow) {
        const slug        = parentRow.dataset.slug;
        const isCollapsed = parentRow.dataset.collapsed === '1';
        parentRow.dataset.collapsed = isCollapsed ? '0' : '1';
        parentRow.querySelector('.st-tree-toggle').textContent = isCollapsed ? '▾' : '▸';

        treeEl.querySelectorAll('.st-tree-row').forEach(row => {
            if (!isDescendantSlug(row.dataset.slug, slug)) return;
            if (isCollapsed) {
                if (!hasCollapsedAncestor(row.dataset.slug)) row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function isDescendantSlug(slug, ancestor) {
        return slug !== ancestor && slug.startsWith(ancestor + '/');
    }

    function hasCollapsedAncestor(slug) {
        const parts = slug.split('/');
        for (let i = 1; i < parts.length; i++) {
            const anc = parts.slice(0, i).join('/');
            const ancRow = treeEl.querySelector(`.st-tree-row[data-slug="${CSS.escape(anc)}"]`);
            if (ancRow && ancRow.dataset.collapsed === '1') return true;
        }
        return false;
    }

    // ── Select a tree row ─────────────────────────────────────────
    function selectTreeRow(treeRow, syncList = true) {
        if (activeTreeRow) activeTreeRow.classList.remove('selected');
        treeRow.classList.add('selected');
        activeTreeRow = treeRow;

        if (syncList && listEl) {
            const id = parseInt(treeRow.dataset.id, 10);
            document.querySelectorAll('#st-list .st-list-row').forEach(r => r.classList.remove('selected'));
            const listRow = listEl.querySelector(`.st-list-row[data-id="${id}"]`);
            if (listRow) { listRow.classList.add('selected'); activeListRow = listRow; }
        }

        showDetail(JSON.parse(treeRow.dataset.page));
    }

    // ── Detail render ─────────────────────────────────────────────
    function showDetail(p) {
        placeholder.style.display = 'none';
        detailEl.style.display    = '';

        const statusClr  = p.status === 'published' ? '#1d9e75' : p.status === 'draft' ? '#d97706' : '#9ca3af';
        const modeClr    = p.renderMode === 'file' ? '#d97706' : p.renderMode === 'html' ? '#7c3aed' : '#1d9e75';
        const editUrl    = p.renderMode === 'html' ? `/admin/pages/${p.id}/html` : `/admin/editor/${p.id}/edit`;

        detailEl.innerHTML = `
            <div class="pl-detail-icon">📄</div>
            <div class="pl-detail-title">${esc(p.title)}</div>
            <div class="pl-detail-subtitle">/${esc(p.slug)}</div>
            <div class="pl-detail-actions">
                <a href="${editUrl}" class="btn btn-primary btn-sm">Edit</a>
                <a href="/${esc(p.slug)}" target="_blank" class="btn btn-outline btn-sm">View ↗</a>
            </div>
            <table class="pl-meta">
                <tr><th>Status</th><td>${badge(statusClr, p.status)}</td></tr>
                <tr><th>Mode</th><td>${badge(modeClr, p.renderMode)}</td></tr>
                <tr><th>Template</th><td>${esc(p.tplName)}</td></tr>
                <tr><th>Author</th><td>${esc(p.author)}</td></tr>
                <tr><th>Updated</th><td>${esc(p.updated)}</td></tr>
            </table>
            <div class="pl-detail-section-title">Chrome</div>
            <table class="pl-meta">
                <tr><th>Header</th><td>${p.showHeader ? badge('#1d9e75','Site Header') : badge('#9ca3af','Hidden')}</td></tr>
                <tr><th>Footer</th><td>${p.showFooter ? badge('#1d9e75','Site Footer') : badge('#9ca3af','Hidden')}</td></tr>
                <tr><th>Sidebar</th><td>${p.hasSidebar ? badge('#5dcaa5','Present') : badge('#9ca3af','None')}</td></tr>
            </table>
            <p style="font-size:.7rem;color:#aaa;margin-top:-.75rem;margin-bottom:1rem">
                Controlled by the page template. Edit template settings to change.
            </p>
            <div class="pl-detail-section-title" id="st-nav-section">Navigation</div>
            ${buildNavHtml(p)}`;

        detailEl.querySelectorAll('.st-nav-menu-header').forEach(hdr => {
            hdr.addEventListener('click', () => {
                const body = hdr.nextElementSibling;
                body.classList.toggle('open');
                hdr.querySelector('.st-nav-chevron').textContent = body.classList.contains('open') ? '▾' : '▸';
            });
        });

        detailEl.querySelectorAll('.st-nav-form').forEach(form => {
            form.addEventListener('submit', handleNavAdd);
        });
    }

    function buildNavHtml(p) {
        if (!menus.length) return '<p style="font-size:.8rem;color:#aaa">No menus defined.</p>';

        return menus.map(menu => {
            const inMenu    = (p.menuIds || []).includes(menu.id);
            const pageItems = menu.items.filter(it => parseInt(it.page_id, 10) === p.id);
            const topItems  = menu.items.filter(it => !it.parent_id);

            let bodyHtml = '';
            if (inMenu && pageItems.length) {
                bodyHtml += pageItems.map(it =>
                    `<div class="st-nav-existing">✓ <strong>${esc(it.label)}</strong></div>`
                ).join('');
            }

            const parentOpts = topItems.map(it =>
                `<option value="${parseInt(it.id, 10)}">${esc(it.label)}</option>`
            ).join('');

            bodyHtml += `
                <form class="st-nav-form" data-menu-id="${menu.id}">
                    <div class="st-nav-form-row">
                        <label>Label</label>
                        <input type="text" name="label" value="${esc(p.title)}" required>
                    </div>
                    ${topItems.length ? `<div class="st-nav-form-row">
                        <label>Parent item <span style="color:#bbb">(optional)</span></label>
                        <select name="parent_id">
                            <option value="">— Top level —</option>
                            ${parentOpts}
                        </select>
                    </div>` : ''}
                    <input type="hidden" name="page_id" value="${p.id}">
                    <input type="hidden" name="link_type" value="page">
                    <button type="submit" class="st-nav-add-btn">${inMenu ? 'Add again' : 'Add to menu'}</button>
                </form>`;

            const inBadge = inMenu ? '<span class="st-nav-in-badge">✓ in menu</span>' : '';

            return `<div class="st-nav-menu-block">
                <div class="st-nav-menu-header">
                    <span class="st-nav-chevron">${inMenu ? '▾' : '▸'}</span>
                    <span style="flex:1">${esc(menu.name)}</span>
                    <span style="font-size:.68rem;color:#aaa">${esc(menu.locLabel)}</span>
                    ${inBadge}
                </div>
                <div class="st-nav-menu-body${inMenu ? ' open' : ''}">
                    ${bodyHtml}
                </div>
            </div>`;
        }).join('');
    }

    async function handleNavAdd(e) {
        e.preventDefault();
        const form     = e.currentTarget;
        const menuId   = form.dataset.menuId;
        const btn      = form.querySelector('.st-nav-add-btn');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding…';

        const fd = new FormData(form);
        fd.append('csrf_token', csrfToken);

        try {
            const res  = await fetch(`/admin/menus/${menuId}/items`, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                btn.textContent = '✓ Added';
                btn.style.background = '#178a64';
                const menu = menus.find(m => m.id === parseInt(menuId, 10));
                if (menu) {
                    menu.items.push({
                        id: json.item_id, menu_id: parseInt(menuId, 10),
                        parent_id: fd.get('parent_id') || null,
                        label: fd.get('label'), link_type: 'page',
                        page_id: parseInt(fd.get('page_id'), 10),
                    });
                }
                setTimeout(() => { btn.disabled = false; btn.textContent = 'Add again'; btn.style.background = ''; }, 1500);
            } else {
                btn.textContent = json.error || 'Error';
                btn.style.background = '#dc2626';
                setTimeout(() => { btn.disabled = false; btn.textContent = origText; btn.style.background = ''; }, 2500);
            }
        } catch {
            btn.textContent = 'Request failed';
            btn.style.background = '#dc2626';
            setTimeout(() => { btn.disabled = false; btn.textContent = origText; btn.style.background = ''; }, 2500);
        }
    }

    function badge(color, text) {
        return `<span class="badge" style="background:${color};color:#fff;font-size:.7rem">${esc(String(text))}</span>`;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    // ── Drag-and-drop reparenting ─────────────────────────────────
    let dragSourceRow = null;

    treeEl.addEventListener('dragstart', e => {
        const row = e.target.closest('.st-tree-row');
        if (!row) return;
        dragSourceRow = row;
        row.classList.add('drag-source');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.id);
    });

    treeEl.addEventListener('dragend', () => {
        dragSourceRow?.classList.remove('drag-source');
        dragSourceRow = null;
        clearDragOver();
    });

    function clearDragOver() {
        treeEl.querySelectorAll('.drag-over').forEach(r => r.classList.remove('drag-over'));
        document.getElementById('st-drop-root')?.classList.remove('drag-over');
    }

    // Drop onto another row = make it a child of that row's slug
    treeEl.addEventListener('dragover', e => {
        e.preventDefault();
        clearDragOver();
        const target = e.target.closest('.st-tree-row, .st-tree-drop-root');
        if (!target || target === dragSourceRow) return;
        // Prevent dropping onto own descendant
        if (target.classList.contains('st-tree-row')) {
            const targetSlug = target.dataset.slug;
            const srcSlug    = dragSourceRow?.dataset.slug ?? '';
            if (targetSlug === srcSlug || targetSlug.startsWith(srcSlug + '/')) return;
        }
        target.classList.add('drag-over');
        e.dataTransfer.dropEffect = 'move';
    });

    treeEl.addEventListener('dragleave', e => {
        if (!treeEl.contains(e.relatedTarget)) clearDragOver();
    });

    treeEl.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('.st-tree-row, .st-tree-drop-root');
        clearDragOver();
        if (!target || !dragSourceRow || target === dragSourceRow) return;

        const pageId       = parseInt(dragSourceRow.dataset.id, 10);
        const newParent    = target.classList.contains('st-tree-drop-root')
            ? ''
            : target.dataset.slug;
        const srcSlug      = dragSourceRow.dataset.slug;
        if (newParent && (newParent === srcSlug || newParent.startsWith(srcSlug + '/'))) return;

        doReparent(pageId, srcSlug, newParent);
    });

    async function doReparent(pageId, oldSlug, newParentSlug) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('new_parent_slug', newParentSlug);

        try {
            const res  = await fetch(`/admin/pages/${pageId}/reparent`, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                // Reload the tab to reflect new slug/tree
                window.location.reload();
            } else {
                alert('Could not reparent: ' + (json.error ?? 'Unknown error'));
            }
        } catch {
            alert('Request failed. Please try again.');
        }
<?php include __DIR__ . '/_tabs_close.php'; ?>
