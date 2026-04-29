<!DOCTYPE html>
<html lang="en">
<head>
<?php
// Protect the page content HTML from being clobbered by block.php includes
// (block.php assigns $content = $block['content'] which is an array).
$_pageContent = $content ?? '';
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $site_name ?? 'Portal') ?> — <?= e($site_name ?? 'Portal') ?></title>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?= e($meta_description) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($og_title ?? $title ?? $site_name ?? 'Portal') ?>">
    <meta property="og:site_name" content="<?= e($site_name ?? 'Portal') ?>">
    <meta property="og:type" content="<?= e($og_type ?? 'website') ?>">
    <?php if (!empty($og_url) || !empty($canonical_url)): ?>
    <meta property="og:url" content="<?= e($og_url ?? $canonical_url) ?>">
    <?php endif; ?>
    <?php if (!empty($og_description) || !empty($meta_description)): ?>
    <meta property="og:description" content="<?= e($og_description ?? $meta_description) ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?= e($og_image) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= !empty($og_image) ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($og_title ?? $title ?? $site_name ?? 'Portal') ?>">
    <?php if (!empty($og_description) || !empty($meta_description)): ?>
    <meta name="twitter:description" content="<?= e($og_description ?? $meta_description) ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= url('/css/style.css') ?>">
    <?php if (!empty($cruinn_css)): ?>
    <style id="cruinn-page-styles"><?= $cruinn_css ?></style>
    <?php endif; ?>
</head>
<body>

<!-- Skip to content for accessibility -->
<a href="#main-content" class="skip-link">Skip to content</a>

<?php
// Per-template layout settings (set by PageController for page routes)
$_tplSettings = ($page_tpl ?? [])['settings'] ?? [];
$_showHeader = $_tplSettings['show_header'] ?? true;
$_showFooter = $_tplSettings['show_footer'] ?? true;
$_tplZones = ($page_tpl ?? [])['zones'] ?? [];
$_hasSidebarZone = in_array('sidebar', $_tplZones, true);
$_sidebarHtml = $tpl_sidebar_html ?? '';
$_sidebarCss = $tpl_sidebar_css ?? '';
$_renderSidebar = $_hasSidebarZone && !empty($_sidebarHtml);
?>

<?php if ($_showHeader): ?>
<?php
$_headerBlocks = $tpl_header_blocks ?? [];
?>
<?php if (!empty($_headerBlocks)): ?>
<!-- Template-driven header (custom or global default) -->
<?php
    $_headerZoneSettings = $_tplSettings['zone_header'] ?? [];
    $_headerZoneStyle = '';
    if (!empty($_headerZoneSettings['maxWidth'])) $_headerZoneStyle .= 'max-width:' . (int)$_headerZoneSettings['maxWidth'] . 'px;';
    if (!empty($_headerZoneSettings['ml'])) $_headerZoneStyle .= 'margin-left:' . ($_headerZoneSettings['ml'] === 'auto' ? 'auto' : (float)$_headerZoneSettings['ml'] . ($_headerZoneSettings['marginUnit'] ?? 'px')) . ';';
    if (!empty($_headerZoneSettings['mr'])) $_headerZoneStyle .= 'margin-right:' . ($_headerZoneSettings['mr'] === 'auto' ? 'auto' : (float)$_headerZoneSettings['mr'] . ($_headerZoneSettings['marginUnit'] ?? 'px')) . ';';
    if (!empty($_headerZoneSettings['pt'])) $_headerZoneStyle .= 'padding-top:' . (float)$_headerZoneSettings['pt'] . ($_headerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_headerZoneSettings['pr'])) $_headerZoneStyle .= 'padding-right:' . (float)$_headerZoneSettings['pr'] . ($_headerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_headerZoneSettings['pb'])) $_headerZoneStyle .= 'padding-bottom:' . (float)$_headerZoneSettings['pb'] . ($_headerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_headerZoneSettings['pl'])) $_headerZoneStyle .= 'padding-left:' . (float)$_headerZoneSettings['pl'] . ($_headerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_headerZoneSettings['bgColor'])) $_headerZoneStyle .= 'background-color:' . e($_headerZoneSettings['bgColor']) . ';';
    if (!empty($_headerZoneSettings['bgImage'])) {
        $_headerZoneStyle .= 'background-image:url(' . e($_headerZoneSettings['bgImage']) . ');';
        $_headerZoneStyle .= 'background-size:' . e($_headerZoneSettings['bgSize'] ?? 'cover') . ';';
        $_headerZoneStyle .= 'background-position:' . e($_headerZoneSettings['bgPos'] ?? 'center center') . ';';
        $_headerZoneStyle .= 'background-repeat:no-repeat;';
    }
?>
<header class="site-header site-header-custom"<?= $_headerZoneStyle ? ' style="' . $_headerZoneStyle . '"' : '' ?>>
    <div class="header-blocks">
        <?php foreach ($_headerBlocks as $block): ?>
            <?php include __DIR__ . '/components/block.php'; ?>
        <?php endforeach; ?>
    </div>
</header>
<?php else: ?>
<?php
$_cruinnHeader = (new \Cruinn\Services\CruinnRenderService())->buildZone('header');
?>
<?php if ($_cruinnHeader): ?>
<?php if (!empty($_cruinnHeader['css'])): ?>
<style id="cruinn-header-styles"><?= $_cruinnHeader['css'] ?></style>
<?php endif; ?>
<header class="site-header site-header-cruinn">
    <div class="cruinn-zone-output"><?= $_cruinnHeader['html'] ?></div>
</header>
<?php endif; /* cruinn header */?>
<?php endif; /* custom vs default header */ ?>
<?php endif; /* $_showHeader */ ?>

<?php if (!empty($flashes)): ?>
<div class="flash-messages container">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
            <button class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
// Template layout settings for .site-body-wrap
$_bodyWrapStyle = '';
$_bodyLayout = $_tplSettings['body_layout'] ?? [];
// DEBUG
error_log('[Cruinn Layout] $_tplSettings keys: ' . implode(', ', array_keys($_tplSettings)));
error_log('[Cruinn Layout] $_bodyLayout: ' . json_encode($_bodyLayout));
if (!empty($_bodyLayout['maxWidth'])) {
    $val = $_bodyLayout['maxWidth'];
    $unit = $_bodyLayout['maxWidthUnit'] ?? 'px';
    $_bodyWrapStyle .= 'max-width:' . ($unit === 'none' ? 'none' : e($val) . e($unit)) . ';';
    error_log('[Cruinn Layout] Applied max-width: ' . $val . $unit);
}
if (!empty($_bodyLayout['padding'])) {
    $_bodyWrapStyle .= 'padding:' . e($_bodyLayout['padding']) . ';';
    error_log('[Cruinn Layout] Applied padding: ' . $_bodyLayout['padding']);
}
error_log('[Cruinn Layout] Final style: ' . $_bodyWrapStyle);
?>
<div class="site-body-wrap"<?= $_bodyWrapStyle ? ' style="' . $_bodyWrapStyle . '"' : '' ?>>
    <main id="main-content">
        <?= $_pageContent ?>
    </main>
    <?php if ($_renderSidebar): ?>
        <?php if (!empty($_sidebarCss)): ?>
        <style id="cruinn-sidebar-styles"><?= $_sidebarCss ?></style>
        <?php endif; ?>
        <aside class="site-sidebar site-sidebar-cruinn" aria-label="Sidebar">
            <div class="cruinn-zone-output"><?= $_sidebarHtml ?></div>
        </aside>
    <?php endif; ?>
</div>

<?php if ($_showFooter): ?>
<?php
$_footerBlocks = $tpl_footer_blocks ?? [];
?>
<?php if (!empty($_footerBlocks)): ?>
<!-- Template-driven footer -->
<?php
    $_footerZoneSettings = $_tplSettings['zone_footer'] ?? [];
    $_footerZoneStyle = '';
    if (!empty($_footerZoneSettings['maxWidth'])) $_footerZoneStyle .= 'max-width:' . (int)$_footerZoneSettings['maxWidth'] . 'px;';
    if (!empty($_footerZoneSettings['ml'])) $_footerZoneStyle .= 'margin-left:' . ($_footerZoneSettings['ml'] === 'auto' ? 'auto' : (float)$_footerZoneSettings['ml'] . ($_footerZoneSettings['marginUnit'] ?? 'px')) . ';';
    if (!empty($_footerZoneSettings['mr'])) $_footerZoneStyle .= 'margin-right:' . ($_footerZoneSettings['mr'] === 'auto' ? 'auto' : (float)$_footerZoneSettings['mr'] . ($_footerZoneSettings['marginUnit'] ?? 'px')) . ';';
    if (!empty($_footerZoneSettings['pt'])) $_footerZoneStyle .= 'padding-top:' . (float)$_footerZoneSettings['pt'] . ($_footerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_footerZoneSettings['pr'])) $_footerZoneStyle .= 'padding-right:' . (float)$_footerZoneSettings['pr'] . ($_footerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_footerZoneSettings['pb'])) $_footerZoneStyle .= 'padding-bottom:' . (float)$_footerZoneSettings['pb'] . ($_footerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_footerZoneSettings['pl'])) $_footerZoneStyle .= 'padding-left:' . (float)$_footerZoneSettings['pl'] . ($_footerZoneSettings['paddingUnit'] ?? 'px') . ';';
    if (!empty($_footerZoneSettings['bgColor'])) $_footerZoneStyle .= 'background-color:' . e($_footerZoneSettings['bgColor']) . ';';
    if (!empty($_footerZoneSettings['bgImage'])) {
        $_footerZoneStyle .= 'background-image:url(' . e($_footerZoneSettings['bgImage']) . ');';
        $_footerZoneStyle .= 'background-size:' . e($_footerZoneSettings['bgSize'] ?? 'cover') . ';';
        $_footerZoneStyle .= 'background-position:' . e($_footerZoneSettings['bgPos'] ?? 'center center') . ';';
        $_footerZoneStyle .= 'background-repeat:no-repeat;';
    }
?>
<footer class="site-footer site-footer-custom"<?= $_footerZoneStyle ? ' style="' . $_footerZoneStyle . '"' : '' ?>>
    <div class="footer-blocks">
        <?php foreach ($_footerBlocks as $block): ?>
            <?php include __DIR__ . '/components/block.php'; ?>
        <?php endforeach; ?>
    </div>
</footer>
<?php else: ?>
<?php
$_cruinnFooter = (new \Cruinn\Services\CruinnRenderService())->buildZone('footer');
?>
<?php if ($_cruinnFooter): ?>
<?php if (!empty($_cruinnFooter['css'])): ?>
<style id="cruinn-footer-styles"><?= $_cruinnFooter['css'] ?></style>
<?php endif; ?>
<footer class="site-footer site-footer-cruinn">
    <div class="cruinn-zone-output"><?= $_cruinnFooter['html'] ?></div>
</footer>
<?php endif; /* cruinn footer */ ?>
<?php endif; /* custom vs default footer */ ?>
<?php endif; /* $_showFooter */ ?>

<script src="<?= url('/js/main.js') ?>"></script>
</body>
</html>

</body>
</html>
