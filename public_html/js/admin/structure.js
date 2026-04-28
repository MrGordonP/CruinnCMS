/**
 * Admin — Site Builder: Structure tab
 * Handles the 3-panel page hierarchy/structure view.
 * PHP data via: data-menus on #structure-layout
 * Extracted from templates/admin/site-builder/structure.php
 */
(function () {
    const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const menus       = JSON.parse(document.getElementById('structure-layout').dataset.menus);
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
    }

})();
