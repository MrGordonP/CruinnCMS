<?php
/**
 * Default site header partial — included via php-include block on _header page.
 *
 * Renders: optional banner, site title, main navigation, utility bar.
 * All dynamic values come from site settings and menu tables.
 */
$site_logo   = \Cruinn\App::config('site.logo', '');
$site_banner = \Cruinn\App::config('site.banner', '');
$site_name   = \Cruinn\App::config('site.name', 'Portal');
$site_tagline = \Cruinn\App::config('site.tagline', '');
?>
<?php if ($site_banner): ?>
<div class="header-banner" style="background-image:url('<?= e($site_banner) ?>')">
    <?php if ($site_logo): ?>
    <div class="header-logo">
        <a href="<?= url('/') ?>"><img src="<?= e($site_logo) ?>" alt="<?= e($site_name) ?>" loading="eager"></a>
    </div>
    <?php endif; ?>
    <div class="header-titles">
        <a href="<?= url('/') ?>" class="site-logo">
            <span class="logo-text"><?= e($site_name) ?></span>
            <?php if ($site_tagline): ?>
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
            <?= e($site_name) ?>
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
<div class="utility-bar">
    <div class="nav-inner">
        <ul class="utility-nav">
            <?php foreach (get_menu('topbar') as $menuItem): ?>
            <li>
                <a href="<?= url($menuItem['href']) ?>"<?= $menuItem['target'] ? ' target="' . e($menuItem['target']) . '"' : '' ?><?= $menuItem['css_class'] ? ' class="' . e($menuItem['css_class']) . '"' : '' ?>><?= e($menuItem['label']) ?></a>
            </li>
            <?php endforeach; ?>
            <?php if (\Cruinn\Auth::check()): ?>
            <li class="utility-nav-user">
                <a href="<?= url('/profile') ?>" class="utility-nav-user-link">&#x1F464; <?= e(\Cruinn\Auth::user()['display_name'] ?? \Cruinn\Auth::user()['email'] ?? 'My Account') ?></a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>
