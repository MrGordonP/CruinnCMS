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
$admin_user = \Cruinn\Auth::user();
$is_admin = $admin_user && ($admin_user['role'] ?? '') === 'admin';
?>
<?php if ($is_admin): ?>
<div class="admin-bar">
    <div class="admin-bar-inner">
        <a href="<?= url('/admin/dashboard') ?>" class="admin-bar-logo">Admin</a>
        <nav class="admin-bar-nav">
            <a href="<?= url('/admin/dashboard') ?>">Dashboard</a>
            <a href="<?= url('/admin/settings/site') ?>">ACP</a>
        </nav>
        <div class="admin-bar-right">
            <a href="<?= url('/admin/pages') ?>">Site Builder</a>
            <a href="<?= url('/logout') ?>">Logout</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Per-template layout settings (set by PageController for page routes)
$_tplSettings = ($page_tpl ?? [])['settings'] ?? [];
$_showHeader = $_tplSettings['show_header'] ?? true;
$_showFooter = $_tplSettings['show_footer'] ?? true;
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
<?php else: ?>
<!-- Default header -->
<header class="site-header">
    <?php
    $site_logo   = \Cruinn\App::config('site.logo', '');
    $site_banner = \Cruinn\App::config('site.banner', '');
    ?>

    <?php if ($site_banner): ?>
    <div class="header-banner" style="background-image:url('<?= e($site_banner) ?>')">
        <?php if ($site_logo): ?>
        <div class="header-logo">
            <a href="<?= url('/') ?>"><img src="<?= e($site_logo) ?>" alt="<?= e($site_name ?? 'Home') ?>" loading="eager"></a>
        </div>
        <?php endif; ?>
        <div class="header-titles">
            <a href="<?= url('/') ?>" class="site-logo">
                <span class="logo-text"><?= e($site_name ?? 'Portal') ?></span>
                <?php if (!empty($site_tagline)): ?>
                <span class="logo-tagline"><?= e($site_tagline) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    <nav class="site-nav-bar" aria-label="Main navigation">
        <div class="nav-inner">
            <?php if (!$site_banner): ?>
            <a href="<?= url('/') ?>" class="site-logo" style="color:#fff;padding:0 var(--space-md);line-height:38px;font-family:var(--font-heading);font-weight:700;font-size:1rem;">
                <?= e($site_name ?? 'Portal') ?>
            </a>
            <?php endif; ?>
            <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
            <div class="main-nav">
                <ul class="nav-list">
                    <?php foreach (get_menu('main') as $menuItem): ?>
                    <li>
                        <a href="<?= url($menuItem['href']) ?>"<?= $menuItem['target'] ? ' target="' . e($menuItem['target']) . '"' : '' ?><?= $menuItem['css_class'] ? ' class="' . e($menuItem['css_class']) . '"' : '' ?>><?= e($menuItem['label']) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Utility bar (account links) -->
    <div class="utility-bar">
        <div class="nav-inner">
            <ul class="utility-nav">
                <?php foreach (get_menu('topbar') as $menuItem): ?>
                        <li>
                    <a href="<?= url($menuItem['href']) ?>"<?= $menuItem['target'] ? ' target="' . e($menuItem['target']) . '"' : '' ?><?= $menuItem['css_class'] ? ' class="' . e($menuItem['css_class']) . '"' : '' ?><?= $menuItem['target'] ? ' target="' . e($menuItem['target']) . '"' : '' ?>>
                        <?= e($menuItem['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</header>
<?php endif; /* cruinn vs default header */ ?>
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

<div class="site-body-wrap">
    <main id="main-content">
        <?= $_pageContent ?>
    </main>
    <?php include __DIR__ . '/components/sidebar.php'; ?>
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
<?php else: ?>
<!-- Default footer -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <p>&copy; <?= date('Y') ?> <?= e($site_name ?? 'Portal') ?>. All rights reserved.</p>
            <nav class="footer-nav" aria-label="Footer navigation">
                <?php foreach (get_menu('footer') as $menuItem): ?>
                    <a href="<?= url($menuItem['href']) ?>"<?= $menuItem['target'] ? ' target="' . e($menuItem['target']) . '"' : '' ?>><?= e($menuItem['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            $socialFb = \Cruinn\App::config('social.facebook', '');
            $socialTw = \Cruinn\App::config('social.twitter', '');
            $socialIg = \Cruinn\App::config('social.instagram', '');
            ?>
            <?php if ($socialFb || $socialTw || $socialIg): ?>
            <div class="footer-social" aria-label="Social media links">
                <?php if ($socialFb): ?>
                <a href="<?= e($socialFb) ?>" target="_blank" rel="noopener noreferrer" class="social-link social-facebook" title="Facebook">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($socialTw): ?>
                <a href="<?= e($socialTw) ?>" target="_blank" rel="noopener noreferrer" class="social-link social-twitter" title="Twitter / X">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($socialIg): ?>
                <a href="<?= e($socialIg) ?>" target="_blank" rel="noopener noreferrer" class="social-link social-instagram" title="Instagram">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php endif; /* cruinn vs default footer */ ?>
<?php endif; /* custom vs default footer */ ?>
<?php endif; /* $_showFooter */ ?>

<script src="<?= url('/js/main.js') ?>"></script>
</body>
</html>

</body>
</html>
