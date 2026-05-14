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
    <?php
    $_activeTheme = \Cruinn\App::config('site.active_theme', 'default');
    $_themeFile = CRUINN_PUBLIC . '/css/themes/' . preg_replace('/[^a-z0-9_-]/i', '', $_activeTheme) . '.css';
    if (file_exists($_themeFile)):
    ?>
    <link rel="stylesheet" href="<?= url('/css/themes/' . e(preg_replace('/[^a-z0-9_-]/i', '', $_activeTheme)) . '.css') ?>">
    <?php endif; ?>
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
$_headerHtml = $tpl_header_html ?? '';
$_headerCss  = $tpl_header_css  ?? '';
$_footerHtml = $tpl_footer_html ?? '';
$_footerCss  = $tpl_footer_css  ?? '';
?>

<?php if ($_showHeader && !empty($_headerHtml)): ?>
<?php if (!empty($_headerCss)): ?>
<style id="cruinn-header-styles"><?= $_headerCss ?></style>
<?php endif; ?>
<header class="site-header site-header-cruinn">
    <div class="cruinn-zone-output"><?= $_headerHtml ?></div>
</header>
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
if (!empty($_bodyLayout['maxWidth'])) {
    $val = $_bodyLayout['maxWidth'];
    $unit = $_bodyLayout['maxWidthUnit'] ?? 'px';
    $_bodyWrapStyle .= 'max-width:' . ($unit === 'none' ? 'none' : e($val) . e($unit)) . ';';
}
if (!empty($_bodyLayout['padding'])) {
    $_bodyWrapStyle .= 'padding:' . e($_bodyLayout['padding']) . ';';
}
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

<?php if ($_showFooter && !empty($_footerHtml)): ?>
<?php if (!empty($_footerCss)): ?>
<style id="cruinn-footer-styles"><?= $_footerCss ?></style>
<?php endif; ?>
<footer class="site-footer site-footer-cruinn">
    <div class="cruinn-zone-output"><?= $_footerHtml ?></div>
</footer>
<?php endif; /* $_showFooter */ ?>


<script src="<?= url('/js/main.js') ?>"></script>
</body>
</html>
