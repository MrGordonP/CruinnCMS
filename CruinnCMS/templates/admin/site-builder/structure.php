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
<?php include __DIR__ . '/_tabs_close.php'; ?>
