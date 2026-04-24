<?php $tab = 'menus'; include __DIR__ . '/../site-builder/_tabs.php'; ?>
<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-menus.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout" id="menus-layout">

    <!-- ── Left: Menu list ───────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Menus</h3>
            <a href="/admin/menus/new" class="btn btn-sm btn-primary">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <?php if (empty($menus)): ?>
                <div class="pl-empty" style="padding:1rem">No menus yet.</div>
            <?php else: ?>
                <?php foreach ($menus as $m):
                    $locSlug  = $m['location'] ?? '';
                    $locLabel = $locations[$locSlug]['label'] ?? $locSlug;
                ?>
                <a class="pl-nav-item" href="#"
                   data-menu-id="<?= (int)$m['id'] ?>"
                   data-menu='<?= e(json_encode([
                       'id'          => (int)$m['id'],
                       'name'        => $m['name'],
                       'location'    => $locSlug,
                       'loc_label'   => $locLabel,
                       'description' => $m['description'] ?? '',
                       'item_count'  => (int)$m['item_count'],
                   ])) ?>'>
                    <span><?= e($m['name']) ?></span>
                    <span class="pl-nav-count"><?= (int)$m['item_count'] ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Middle: Items editor ──────────────────────────────── -->
    <div class="pl-main" id="menus-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="menus-main-title">Menus</span>
        </div>
        <div class="pl-main-scroll" id="menus-items-scroll">
            <div id="menus-main-placeholder" class="pl-empty" style="padding:2rem 1rem">
                Select a menu to edit its items.
            </div>
            <div id="menus-items-panel" style="display:none"></div>
        </div>
    </div>

    <!-- ── Right: Settings ───────────────────────────────────── -->
    <div class="pl-detail" id="menus-detail">
        <div class="pl-detail-header"><h3>Details</h3></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="menus-detail-placeholder">
                <div class="pl-detail-placeholder-icon">☰</div>
                <span>Select a menu to edit its settings</span>
            </div>
            <div id="menus-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
    const menuLinks         = document.querySelectorAll('.pl-nav-item[data-menu-id]');
    const mainTitle         = document.getElementById('menus-main-title');
    const mainPlaceholder   = document.getElementById('menus-main-placeholder');
    const itemsPanel        = document.getElementById('menus-items-panel');
    const detailPlaceholder = document.getElementById('menus-detail-placeholder');
    const detailContent     = document.getElementById('menus-detail-content');

    const csrfToken = <?= json_encode(\Cruinn\CSRF::getToken()) ?>;
    const locations = <?= json_encode(array_map(fn($slug, $loc) => ['slug' => $slug, 'label' => $loc['label']], array_keys($locations ?? []), array_values($locations ?? [])), JSON_THROW_ON_ERROR) ?>;

    let activeMenuId = null;

    // Override the reload hook so item mutations re-fetch the fragment
    // instead of triggering a full page reload
    Cruinn.menuPanelRefresh = function () {
        if (activeMenuId) loadItemsPanel(activeMenuId);
    };

    menuLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            menuLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            const m = JSON.parse(link.dataset.menu);
            activeMenuId = m.id;
            showDetail(m);
            loadItemsPanel(m.id);
        });
    });

    function loadItemsPanel(menuId) {
        mainTitle.textContent = '';
        mainPlaceholder.style.display = 'none';
        itemsPanel.style.display = '';
        itemsPanel.innerHTML = '<div class="pl-empty" style="padding:2rem 1rem">Loading…</div>';

        fetch('/admin/menus/' + menuId + '/items-panel', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.text())
            .then(html => {
                itemsPanel.innerHTML = html;
                // Update title from active sidebar link
                const activeLink = document.querySelector('.pl-nav-item[data-menu-id].active');
                if (activeLink) {
                    mainTitle.textContent = JSON.parse(activeLink.dataset.menu).name;
                }
                // Re-bind the menu editor JS
                Cruinn.initMenuEditor();
            })
            .catch(() => {
                itemsPanel.innerHTML = '<div class="pl-empty" style="padding:1rem;color:#dc2626">Failed to load items.</div>';
            });
    }

    function showDetail(m) {
        detailPlaceholder.style.display = 'none';

        const locationOptions = locations.map(l =>
            `<option value="${escHtml(l.slug)}"${m.location === l.slug ? ' selected' : ''}>${escHtml(l.label)}</option>`
        ).join('');

        detailContent.innerHTML = `
            <div class="pl-detail-icon">☰</div>
            <div class="pl-detail-title">${escHtml(m.name)}</div>
            <div class="pl-detail-subtitle">${escHtml(m.loc_label)}</div>
            <form method="POST" action="/admin/menus/${m.id}" class="pl-detail-settings">
                <input type="hidden" name="csrf_token" value="${escHtml(csrfToken)}">
                <div class="pl-detail-settings-section">Settings</div>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="${escHtml(m.name)}" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <select name="location" class="form-input">${locationOptions}</select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" value="${escHtml(m.description)}" class="form-input" placeholder="Where this menu appears">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Save Settings</button>
            </form>
            <div class="pl-detail-settings-section" style="margin-top:1rem;border-top-color:#f87171;color:#f87171">Danger</div>
            <form method="POST" action="/admin/menus/${m.id}/delete"
                  onsubmit="return confirm('Delete this menu and all its items?')">
                <input type="hidden" name="csrf_token" value="${escHtml(csrfToken)}">
                <button type="submit" class="btn btn-danger" style="width:100%">Delete Menu</button>
            </form>`;
        detailContent.style.display = '';
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }
})();
});
</script>

<?php include __DIR__ . '/../site-builder/_tabs_close.php'; ?>
