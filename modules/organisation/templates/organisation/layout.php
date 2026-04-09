<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Organisation') ?> — <?= e(\Cruinn\App::config('site.name', 'Organisation')) ?></title>
    <link rel="stylesheet" href="<?= url('/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/css/admin-base.css') ?>?v=<?= filemtime(__DIR__ . '/../../public/css/admin-base.css') ?>">
    <link rel="stylesheet" href="<?= url('/css/admin-organisation.css') ?>?v=<?= filemtime(__DIR__ . '/../../public/css/admin-organisation.css') ?>">
</head>
<body class="organisation-body">

<header class="admin-header organisation-header">
    <div class="admin-header-inner">
        <a href="<?= url('/organisation') ?>" class="admin-logo"><?= e(\Cruinn\App::config('site.name', 'Organisation')) ?></a>
        <nav class="admin-nav">
            <?php if (!empty($role_nav_items)): ?>
                <?php foreach ($role_nav_items as $item): ?>
                    <?php if (!empty($item['children'])): ?>
                    <div class="admin-nav-group">
                        <button class="admin-nav-trigger"><?= e($item['label']) ?> <span class="nav-caret">▾</span></button>
                        <div class="admin-nav-dropdown">
                            <?php foreach ($item['children'] as $child): ?>
                            <a href="<?= url($child['url']) ?>"<?= $child['css_class'] ? ' class="' . e($child['css_class']) . '"' : '' ?><?= $child['opens_new_tab'] ? ' target="_blank"' : '' ?>><?= e($child['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="<?= url($item['url']) ?>"<?= $item['css_class'] ? ' class="' . e($item['css_class']) . '"' : '' ?><?= $item['opens_new_tab'] ? ' target="_blank"' : '' ?>><?= e($item['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="admin-nav-sep"></div>
                <?php if (\Cruinn\Auth::hasRole('admin')): ?>
                    <a href="<?= url('/admin') ?>" class="admin-nav-organisation">Admin</a>
                <?php endif; ?>
                <span class="admin-user"><?= e($current_user['name'] ?? 'Organisation') ?></span>
                <a href="<?= url('/') ?>" class="admin-view-site" target="_blank">View Site</a>
                <a href="<?= url('/logout') ?>">Logout</a>
            <?php else: ?>
                <a href="<?= url('/organisation') ?>">Dashboard</a>
                <a href="<?= url('/organisation/documents') ?>">Documents</a>
                <a href="<?= url('/organisation/discussions') ?>">Discussions</a>
                <a href="<?= url('/organisation/inbox') ?>">Inbox</a>
                <div class="admin-nav-sep"></div>
                <?php if (\Cruinn\Auth::hasRole('admin')): ?>
                    <a href="<?= url('/admin') ?>" class="admin-nav-organisation">Admin</a>
                <?php endif; ?>
                <span class="admin-user"><?= e($current_user['name'] ?? 'Organisation') ?></span>
                <a href="<?= url('/') ?>" class="admin-view-site" target="_blank">View Site</a>
                <a href="<?= url('/logout') ?>">Logout</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if (!empty($flashes)): ?>
<div class="flash-messages admin-flash">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
            <button class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="admin-content">
    <?= $content ?>
</div>

<?php
$adminJsBase = __DIR__ . '/../../public/js/admin/';
$adminModules = [
    'utils.js',
    'api.js',
    'media-browser.js',
    'rte.js',
    'gallery.js',
    'block-editor/core.js',
    'block-editor/undo.js',
    'block-editor/properties.js',
    'block-editor/drag.js',
    'menu-editor.js',
    'social-hub.js',
    'dashboard-config.js',
    'nav-config.js',
    'site-editor.js',
    'template-editor.js',
    'index.js',
];
// Load block type registry first, then all registered type files
$blockTypesDir = $adminJsBase . 'block-types/';
$blockTypeFiles = [];
if (is_dir($blockTypesDir)) {
    $allBtFiles = glob($blockTypesDir . '*.js');
    if ($allBtFiles) {
        $blockTypeFiles = $allBtFiles;
    }
}
foreach ($blockTypeFiles as $btFile):
    $btMtime = filemtime($btFile);
    $btName  = basename($btFile);
?>
<script src="<?= url('/js/admin/block-types/' . $btName) ?>?v=<?= $btMtime ?>"></script>
<?php endforeach; ?>
<?php
foreach ($adminModules as $module):
    $mtime = file_exists($adminJsBase . $module) ? filemtime($adminJsBase . $module) : 0;
?>
<script src="<?= url('/js/admin/' . $module) ?>?v=<?= $mtime ?>"></script>
<?php endforeach; ?>
</body>
</html>
