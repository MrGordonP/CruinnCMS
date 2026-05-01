<?php
/**
 * Mailout — Mailing Lists (3-panel layout)
 */
\Cruinn\Template::requireCss('admin-panel-layout.css');
?>

<div class="panel-layout" id="lists-layout">

    <!-- ── Left: List nav ────────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Mailing Lists</h3>
            <button class="btn btn-sm btn-primary" id="new-list-btn">+ New</button>
        </div>
        <div class="pl-sidebar-scroll" id="list-nav">
            <?php if (empty($lists)): ?>
                <div style="padding:.75rem .9rem;font-size:.83rem;color:#aaa">No lists yet.</div>
            <?php else: ?>
                <?php foreach ($lists as $ml): ?>
                <a class="pl-nav-item" href="#"
                   data-list-id="<?= (int)$ml['id'] ?>"
                   data-list='<?= e(json_encode([
                       'id'                => (int)$ml['id'],
                       'name'              => $ml['name'],
                       'slug'              => $ml['slug'],
                       'description'       => $ml['description'] ?? '',
                       'subscription_mode' => $ml['subscription_mode'],
                       'is_public'         => (int)$ml['is_public'],
                       'is_active'         => (int)$ml['is_active'],
                       'subscriber_count'  => (int)($ml['subscriber_count'] ?? 0),
                   ])) ?>'>
                    <?= e($ml['name']) ?>
                    <span class="pl-nav-count"><?= (int)($ml['subscriber_count'] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- New list form (hidden until button clicked) -->
        <div id="new-list-form" style="display:none;border-top:1px solid var(--color-border,#ccd9d3);padding:.75rem">
            <form method="POST" action="/admin/mailout/lists">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <div style="margin-bottom:.5rem">
                    <input type="text" name="name" class="pl-search-input" placeholder="List name" required style="width:100%;margin-bottom:.4rem" id="new-list-name">
                    <input type="text" name="slug" class="pl-search-input" placeholder="slug (auto)" style="width:100%;margin-bottom:.4rem" id="new-list-slug">
                    <select name="subscription_mode" class="pl-search-input" style="width:100%;margin-bottom:.4rem">
                        <option value="open">Open subscription</option>
                        <option value="request">Request only</option>
                    </select>
                    <label style="font-size:.8rem;display:flex;align-items:center;gap:.4rem">
                        <input type="checkbox" name="is_public" value="1" checked> Public (visible in subscription prefs)
                    </label>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button type="submit" class="btn btn-sm btn-primary">Create</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="new-list-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Middle: Subscribers ───────────────────────────────── -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="list-main-title">Select a list</span>
            <div class="pl-main-toolbar-actions" id="list-main-actions" style="display:none">
                <button class="btn btn-sm btn-primary" id="add-subscriber-btn" style="margin-right: 0.5rem;">+ Add Member</button>
                <select id="status-filter" class="pl-search-input" style="width:auto">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="unsubscribed">Unsubscribed</option>
                    <option value="bounced">Bounced</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>
        <div class="pl-main-search">
            <input type="search" class="pl-search-input" id="sub-search" placeholder="Search subscribers…" autocomplete="off" disabled>
        </div>
        
        <!-- Add Subscriber Form (hidden) -->
        <div id="add-subscriber-form" style="display:none; padding: 1rem; background: #f9f9f9; border-bottom: 1px solid var(--color-border,#ccd9d3);">
            <form id="add-sub-form-elem">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="font-size: 0.75rem; color: #666; display: block; margin-bottom: 0.25rem;">Email Address</label>
                        <input type="email" name="email" class="pl-search-input" placeholder="name@example.com" required style="width: 100%;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 0.75rem; color: #666; display: block; margin-bottom: 0.25rem;">Name (optional)</label>
                        <input type="text" name="name" class="pl-search-input" placeholder="Display name" style="width: 100%;">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="add-sub-cancel">Cancel</button>
                </div>
            </form>
        </div>
        
        <div class="pl-main-scroll">
            <div class="pl-empty" id="sub-placeholder">← Select a mailing list to view subscribers.</div>
            <table class="pl-table" id="sub-table" style="display:none">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Subscribed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="sub-tbody"></tbody>
            </table>
            <div class="pl-empty" id="sub-empty" style="display:none">No subscribers found.</div>
        </div>
    </div>

    <!-- ── Right: List detail / settings ─────────────────────── -->
    <div class="pl-detail">
        <div class="pl-detail-header"><h3>List Settings</h3></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="list-detail-placeholder">
                <div class="pl-detail-placeholder-icon">📋</div>
                <span>Select a list to manage it</span>
            </div>
            <div id="list-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<script>
(function () {
    const navItems      = document.querySelectorAll('.pl-nav-item[data-list-id]');
    const mainTitle     = document.getElementById('list-main-title');
    const mainActions   = document.getElementById('list-main-actions');
    const subSearch     = document.getElementById('sub-search');
    const statusFilter  = document.getElementById('status-filter');
    const subPlaceholder= document.getElementById('sub-placeholder');
    const subTable      = document.getElementById('sub-table');
    const subTbody      = document.getElementById('sub-tbody');
    const subEmpty      = document.getElementById('sub-empty');
    const detailPlaceholder = document.getElementById('list-detail-placeholder');
    const detailContent = document.getElementById('list-detail-content');
    const newListBtn    = document.getElementById('new-list-btn');
    const newListForm   = document.getElementById('new-list-form');
    const newListCancel = document.getElementById('new-list-cancel');
    const newListName   = document.getElementById('new-list-name');
    const newListSlug   = document.getElementById('new-list-slug');
    const addSubBtn     = document.getElementById('add-subscriber-btn');
    const addSubForm    = document.getElementById('add-subscriber-form');
    const addSubFormElem = document.getElementById('add-sub-form-elem');
    const addSubCancel  = document.getElementById('add-sub-cancel');

    let activeListId = null;
    let searchTimeout = null;

    // ── Auto-slug ──
    newListName.addEventListener('input', () => {
        if (newListSlug.dataset.edited) return;
        newListSlug.value = newListName.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
    newListSlug.addEventListener('input', () => { newListSlug.dataset.edited = '1'; });

    // ── New list panel ──
    newListBtn.addEventListener('click', () => { newListForm.style.display = ''; newListName.focus(); });
    newListCancel.addEventListener('click', () => { newListForm.style.display = 'none'; });

    // ── Add subscriber panel ──
    addSubBtn.addEventListener('click', () => { 
        addSubForm.style.display = ''; 
        addSubFormElem.querySelector('input[name="email"]').focus();
    });
    addSubCancel.addEventListener('click', () => { 
        addSubForm.style.display = 'none';
        addSubFormElem.reset();
    });

    addSubFormElem.addEventListener('submit', e => {
        e.preventDefault();
        if (!activeListId) return;
        
        const formData = new FormData(addSubFormElem);
        const email = formData.get('email');
        const name = formData.get('name');

        fetch(`/admin/mailout/lists/${activeListId}/subscribers/add`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
            } else if (data.success) {
                addSubForm.style.display = 'none';
                addSubFormElem.reset();
                loadSubscribers(activeListId);
                if (data.message) alert(data.message);
            }
        })
        .catch(err => {
            alert('Failed to add subscriber: ' + err.message);
        });
    });

    // ── Select list ──
    navItems.forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            navItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            const list = JSON.parse(item.dataset.list);
            activeListId = list.id;
            addSubForm.style.display = 'none';
            addSubFormElem.reset();
            loadSubscribers(list.id);
            showDetail(list);
            mainTitle.textContent = list.name;
            mainActions.style.display = '';
            subSearch.disabled = false;
        });
    });

    // ── Load subscribers ──
    function loadSubscribers(id) {
        subPlaceholder.style.display = 'none';
        subTable.style.display = 'none';
        subEmpty.style.display = 'none';
        subTbody.innerHTML = '<tr><td colspan="5" style="padding:2rem;text-align:center;color:#aaa">Loading…</td></tr>';
        subTable.style.display = '';

        const q = subSearch.value.trim();
        const s = statusFilter.value;
        let url = `/admin/mailout/lists/${id}/subscribers`;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (s) params.set('status', s);
        if (params.toString()) url += '?' + params;

        fetch(url)
            .then(r => r.json())
            .then(data => renderSubscribers(data.subscribers || [], id));
    }

    function renderSubscribers(subs, listId) {
        if (!subs.length) {
            subTable.style.display = 'none';
            subEmpty.style.display = '';
            return;
        }
        subEmpty.style.display = 'none';
        subTable.style.display = '';

        const statusColour = { active: '#1d9e75', unsubscribed: '#888', bounced: '#c0392b', pending: '#d97706' };

        subTbody.innerHTML = subs.map(s => `
            <tr>
                <td>${escHtml(s.email)}</td>
                <td style="color:#666">${escHtml(s.name || '—')}</td>
                <td><span class="badge" style="background:${statusColour[s.status]||'#888'};color:#fff;font-size:.68rem">${escHtml(s.status)}</span></td>
                <td style="color:#888;font-size:.8rem">${escHtml(s.subscribed_at?.split(' ')[0] || '—')}</td>
                <td>
                    <form method="POST" action="/admin/mailout/lists/${listId}/subscribers/${s.id}/remove" class="remove-sub-form">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <button class="btn btn-sm" style="background:#c0392b;color:#fff;font-size:.72rem;padding:.2rem .5rem" title="Remove">✕</button>
                    </form>
                </td>
            </tr>`).join('');

        subTbody.querySelectorAll('.remove-sub-form').forEach(form => {
            form.addEventListener('submit', e => {
                e.preventDefault();
                if (!confirm('Remove this subscriber?')) return;
                fetch(form.action, { method: 'POST', body: new FormData(form) })
                    .then(r => r.json())
                    .then(resp => { if (resp.success) form.closest('tr').remove(); });
            });
        });
    }

    // ── Search & filter ──
    subSearch.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { if (activeListId) loadSubscribers(activeListId); }, 300);
    });
    statusFilter.addEventListener('change', () => { if (activeListId) loadSubscribers(activeListId); });

    // ── Detail panel ──
    function showDetail(list) {
        detailPlaceholder.style.display = 'none';
        const modeLabel = list.subscription_mode === 'open' ? 'Open' : 'Request only';
        const pubLabel  = list.is_public ? 'Yes' : 'No';
        const activeLabel = list.is_active ? '<span style="color:#1d9e75">Active</span>' : '<span style="color:#888">Inactive</span>';

        detailContent.innerHTML = `
            <div class="pl-detail-icon">📋</div>
            <div class="pl-detail-title">${escHtml(list.name)}</div>
            <div class="pl-detail-subtitle">${escHtml(list.slug)} · ${list.subscriber_count} subscribers</div>

            <form method="POST" action="/admin/mailout/lists/${list.id}">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <div style="margin-bottom:.5rem">
                    <label style="font-size:.75rem;color:#888;display:block;margin-bottom:.2rem">Name</label>
                    <input type="text" name="name" class="pl-search-input" style="width:100%" value="${escAttr(list.name)}" required>
                </div>
                <div style="margin-bottom:.5rem">
                    <label style="font-size:.75rem;color:#888;display:block;margin-bottom:.2rem">Description</label>
                    <textarea name="description" class="pl-search-input" style="width:100%;height:60px;resize:vertical">${escHtml(list.description)}</textarea>
                </div>
                <div style="margin-bottom:.5rem">
                    <label style="font-size:.75rem;color:#888;display:block;margin-bottom:.2rem">Subscription mode</label>
                    <select name="subscription_mode" class="pl-search-input" style="width:100%">
                        <option value="open"${list.subscription_mode==='open'?' selected':''}>Open</option>
                        <option value="request"${list.subscription_mode==='request'?' selected':''}>Request only</option>
                    </select>
                </div>
                <div style="margin-bottom:.75rem;display:flex;gap:1rem">
                    <label style="font-size:.8rem;display:flex;align-items:center;gap:.35rem">
                        <input type="checkbox" name="is_public" value="1"${list.is_public?' checked':''}> Public
                    </label>
                    <label style="font-size:.8rem;display:flex;align-items:center;gap:.35rem">
                        <input type="checkbox" name="is_active" value="1"${list.is_active?' checked':''}> Active
                    </label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary" style="width:100%;margin-bottom:.5rem">Save Changes</button>
            </form>

            <form method="POST" action="/admin/mailout/lists/${list.id}/delete"
                  onsubmit="return confirm('Delete list \\'${escHtml(list.name)}\\'? All subscribers will be removed.')">
                <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                <button type="submit" class="btn btn-sm" style="width:100%;background:#c0392b;color:#fff">Delete List</button>
            </form>`;
        detailContent.style.display = '';
    }

    function escHtml(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
    function escAttr(s) { return String(s ?? '').replace(/"/g, '&quot;'); }
})();
</script>
