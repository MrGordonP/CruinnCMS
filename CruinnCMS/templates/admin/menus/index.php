<?php $tab = 'menus'; include __DIR__ . '/../site-builder/_tabs.php'; ?>
<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-menus.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout" id="menus-layout"
     data-csrf="<?= e(\Cruinn\CSRF::getToken()) ?>"
     data-locations="<?= e(json_encode(array_map(fn($slug, $loc) => ['slug' => $slug, 'label' => $loc['label']], array_keys($locations ?? []), array_values($locations ?? [])), JSON_THROW_ON_ERROR)) ?>">

    <!-- ── Left: Menu list ───────────────────────────────────── -->
    <div class="pl-sidebar" id="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Menus</h3>
            <button type="button" class="pl-panel-toggle" id="pl-sidebar-toggle" title="Collapse">&#x25C0;</button>
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
        <div class="pl-detail-header"><h3>Details</h3><button type="button" class="pl-panel-toggle" id="pl-detail-toggle" title="Collapse">&#x25B6;</button></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="menus-detail-placeholder">
                <div class="pl-detail-placeholder-icon">☰</div>
                <span>Select a menu to edit its settings</span>
            </div>
            <div id="menus-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<?php \Cruinn\Template::requireJs('menus.js'); ?>

<?php include __DIR__ . '/../site-builder/_tabs_close.php'; ?>
