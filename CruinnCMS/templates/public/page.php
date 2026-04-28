<?php
    $tpl = $page_tpl ?? ['slug' => 'default', 'zones' => ['main'], 'css_class' => 'layout-default', 'settings' => []];
    $tplSlug = $tpl['slug'] ?? 'default';
    $tplClass = $tpl['css_class'] ?? 'layout-default';
    $tplSettings = $tpl['settings'] ?? [];
    $zones = $tpl['zones'] ?? ['main'];
    $hasSidebar = in_array('sidebar', $zones, true);
    $isBlank = $tplSlug === 'blank';
    $showTitle = $tplSettings['show_title'] ?? true;
    $titleAlign = $tplSettings['title_align'] ?? 'left';
    $contentWidth = $tplSettings['content_width'] ?? 'default';
    $showBreadcrumbs = $tplSettings['show_breadcrumbs'] ?? false;

    // Content width class
    $widthClass = '';
    if ($contentWidth === 'narrow') $widthClass = ' content-narrow';
    elseif ($contentWidth === 'wide') $widthClass = ' content-wide';
    elseif ($contentWidth === 'full') $widthClass = ' content-full';

    // Split blocks into zones: blocks with role "sidebar" go to sidebar, rest to main
    $mainBlocks = [];
    $sidebarBlocks = [];
    if ($hasSidebar) {
        foreach ($blocks as $block) {
            $role = $block['settings']['role'] ?? '';
            if ($role === 'sidebar') {
                $sidebarBlocks[] = $block;
            } else {
                $mainBlocks[] = $block;
            }
        }
    } else {
        $mainBlocks = $blocks;
    }
?>
<?php if ($isBlank): ?>
    <!-- Blank template: raw block output -->
    <?php foreach ($mainBlocks as $block): ?>
        <?php include __DIR__ . '/../components/block.php'; ?>
    <?php endforeach; ?>
<?php else: ?>
<div class="page-content <?= e($tplClass) ?><?= $widthClass ?>">
    <div class="container">
        <?php if ($showBreadcrumbs): ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= url('/') ?>">Home</a> &rsaquo;
            <span><?= e($page['title'] ?? 'Page') ?></span>
        </nav>
        <?php endif; ?>

        <?php if ($showTitle): ?>
        <h1<?= $titleAlign !== 'left' ? ' style="text-align:' . e($titleAlign) . '"' : '' ?>><?= e($page['title'] ?? 'Page') ?></h1>
        <?php endif; ?>

        <?php if ($hasSidebar): ?>
        <div class="page-grid">
            <div class="page-main">
                <?php if (!empty($mainBlocks)): ?>
                    <?php foreach ($mainBlocks as $block): ?>
                        <?php include __DIR__ . '/../components/block.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>This page has no content yet.</p>
                <?php endif; ?>
            </div>
            <aside class="page-sidebar">
                <?php foreach ($sidebarBlocks as $block): ?>
                    <?php include __DIR__ . '/../components/block.php'; ?>
                <?php endforeach; ?>
            </aside>
        </div>
        <?php else: ?>
            <?php if (!empty($mainBlocks)): ?>
                <?php foreach ($mainBlocks as $block): ?>
                    <?php include __DIR__ . '/../components/block.php'; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>This page has no content yet.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
