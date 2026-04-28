<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\Cruinn\CSRF::getToken()) ?>">
    <title><?= e($title ?? 'Admin') ?> — <?= e(\Cruinn\App::config('site.name', 'Admin')) ?></title>
    <link rel="stylesheet" href="<?= url('/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/css/admin-base.css') ?>?v=<?= file_exists(CRUINN_PUBLIC . '/css/admin-base.css') ? filemtime(CRUINN_PUBLIC . '/css/admin-base.css') : 0 ?>">
    <?php foreach (\Cruinn\Template::flushCss() as $_cssFile): ?>
    <link rel="stylesheet" href="<?= url('/css/' . e($_cssFile)) ?>?v=<?= file_exists(CRUINN_PUBLIC . '/css/' . $_cssFile) ? filemtime(CRUINN_PUBLIC . '/css/' . $_cssFile) : 0 ?>">
    <?php endforeach; ?>
    <script src="<?= url('/js/admin/boot.js') ?>?v=<?= file_exists(CRUINN_PUBLIC . '/js/admin/boot.js') ? filemtime(CRUINN_PUBLIC . '/js/admin/boot.js') : 0 ?>"></script>
</head>
<body class="admin-body">

<div class="admin-wrap">
    <!-- ── Sidebar ────────────────────────────────────────────── -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar-hero">
            <a href="<?= url('/admin/dashboard') ?>" class="admin-sidebar-logo">
                <?= e(\Cruinn\App::config('site.name', 'Admin')) ?>
            </a>
        </div>
        <nav class="admin-sidebar-nav">
            <?php if (!empty($acp_mode)): ?>
                <a href="<?= url('/admin/dashboard') ?>">← Dashboard</a>
            <?php elseif (!empty($role_nav_items) && ($current_user['role_level'] ?? 0) < 100): ?>
                <?php foreach ($role_nav_items as $item): ?>
                    <?php if (!empty($item['children'])): ?>
                    <div class="admin-sidebar-group">
                        <a href="<?= url($item['url'] ?? '#') ?>" class="admin-sidebar-parent"><?= e($item['label']) ?> <span class="sidebar-caret">▸</span></a>
                        <div class="admin-sidebar-flyout">
                            <?php foreach ($item['children'] as $child): ?>
                            <a href="<?= url($child['url']) ?>"<?= $child['opens_new_tab'] ? ' target="_blank"' : '' ?>><?= e($child['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="<?= url($item['url']) ?>"><?= e($item['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <span class="admin-sidebar-sep"></span>
                <a href="<?= url('/admin/settings/site') ?>">⚙ Settings</a>
            <?php else: ?>
                <a href="<?= url('/admin/dashboard') ?>">Dashboard</a>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/site-builder') ?>" class="admin-sidebar-parent">Site Builder <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <a href="<?= url('/admin/editor') ?>">Open Editor</a>
                        <a href="<?= url('/admin/pages') ?>">Pages</a>
                        <a href="<?= url('/admin/templates') ?>">Templates</a>
                        <a href="<?= url('/admin/menus') ?>">Menus</a>
                        <a href="<?= url('/admin/content') ?>">Dynamic Content</a>
                        <a href="<?= url('/admin/site-builder/structure') ?>">Structure</a>
                        <a href="<?= url('/admin/site-builder/global-header') ?>">Global Header</a>
                        <a href="<?= url('/admin/site-builder/global-footer') ?>">Global Footer</a>
                        <a href="<?= url('/admin/blocks/named') ?>">Named Blocks</a>
                        <a href="<?= url('/admin/template-editor') ?>">PHP Templates</a>
                        <a href="<?= url('/admin/media') ?>">Media</a>
                        <a href="<?= url('/admin/import') ?>">Import</a>
                    </div>
                </div>
                <?php
                // ── Dynamic module sidebar groups ─────────────────
                // Group all active modules' acp_sections by group name.
                // 'Settings' is a flat link below; 'People' is injected into the platform People flyout.
                $_sidebarGroups = [];
                foreach (\Cruinn\Modules\ModuleRegistry::acpSections() as $_s) {
                    $_g = $_s['group'] ?? 'Other';
                    if ($_g === 'Settings' || $_g === 'People') { continue; }
                    $_sidebarGroups[$_g][] = $_s;
                }
                foreach ($_sidebarGroups as $_groupName => $_groupItems):
                ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url($_groupItems[0]['url']) ?>" class="admin-sidebar-parent"><?= e($_groupName) ?> <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <?php foreach ($_groupItems as $_si): ?>
                        <a href="<?= url($_si['url']) ?>"><?= e($_si['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/users') ?>" class="admin-sidebar-parent">People <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <a href="<?= url('/admin/users') ?>">Users</a>
                        <a href="<?= url('/admin/roles') ?>">Roles</a>
                        <a href="<?= url('/admin/groups') ?>">Groups</a>
                        <?php foreach (\Cruinn\Modules\ModuleRegistry::acpSections() as $_s):
                            if (($_s['group'] ?? '') !== 'People') { continue; } ?>
                        <a href="<?= url($_s['url']) ?>"><?= e($_s['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <span class="admin-sidebar-sep"></span>
                <a href="<?= url('/admin/settings/site') ?>">⚙ Settings</a>
            <?php endif; ?>
        </nav>

        <?php if (!empty($admin_live_stats)): ?>
        <div class="admin-sidebar-stats">
            <?php foreach ($admin_live_stats as $key => $val): ?>
            <div class="admin-sidebar-stat">
                <span class="admin-sidebar-stat-num"><?= (int)$val ?></span> <?= e(ucfirst(str_replace('_', ' ', $key))) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="admin-sidebar-footer">
            <a href="<?= url('/') ?>" class="admin-sidebar-viewsite">View Site ↗</a>
        </div>
    </aside>

    <!-- ── Right column ───────────────────────────────────────── -->
    <div class="admin-right">
        <div class="admin-topbar">
            <div class="admin-topbar-inner">
                <?php if (!empty($breadcrumbs)): ?>
                <div class="admin-topbar-breadcrumb">
                    <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
                </div>
                <?php endif; ?>
                <div class="admin-topbar-right">
                    <a href="<?= url('/') ?>" class="admin-topbar-site-link" title="View live site" target="_blank">⬡ View Site</a>
                    <?php if (\Cruinn\Platform\PlatformAuth::check()): ?>
                    <a href="/cms/dashboard" class="admin-topbar-cms-link" title="Back to Cruinn CMS platform">← CMS</a>
                    <?php endif; ?>
                    <button class="admin-width-toggle" id="admin-width-btn" type="button" title="Toggle layout width">&#x229E;</button>
                    <a href="<?= url('/profile') ?>" class="admin-topbar-user" title="My Profile">&#x1F464; <?= e($current_user['display_name'] ?? $current_user['email'] ?? 'Admin') ?></a>
                    <a href="<?= url('/logout') ?>">Logout</a>
                </div>
            </div>
        </div>

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

        <div class="admin-main">
            <div class="admin-content<?= !empty($GLOBALS['admin_flush_layout']) ? ' admin-content--flush' : '' ?>">
                <?= $content ?>
            </div>
        </div>
    </div>
</div>

<!-- Cruinn Media Browser -->
<div class="media-modal-overlay" id="media-modal" style="display:none">
    <div class="media-modal">
        <div class="media-modal-header">
            <h2>Media Library</h2>
            <span id="media-modal-path" class="media-modal-path"></span>
            <button type="button" class="media-modal-close" id="media-modal-close-btn" aria-label="Close">&times;</button>
        </div>
        <div class="media-modal-toolbar">
            <label class="btn btn-small btn-primary media-upload-btn">
                Upload <input type="file" id="media-modal-upload" accept="image/*" hidden>
            </label>
            <button type="button" class="btn btn-small btn-outline" id="media-modal-new-folder-btn">+ Folder</button>
            <button type="button" class="btn btn-small btn-danger" id="media-modal-delete-btn" style="display:none">Delete Folder</button>
            <input type="search" id="media-search" class="form-input" placeholder="Search all…" style="max-width:180px">
        </div>
        <div class="media-modal-grid" id="media-grid">
            <p class="media-loading">Loading…</p>
        </div>
        <div class="media-modal-footer">
            <button type="button" class="btn btn-primary" id="media-modal-select-btn">Upload</button>
            <button type="button" class="btn btn-outline" id="media-modal-cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<?php
$adminJsBase = CRUINN_PUBLIC . '/js/admin/';
$adminModules = [
    'utils.js',
    'api.js',
    'media-browser.js',
    'rte.js',
    'gallery.js',
    'menu-editor.js',
    'template-editor.js',
    'dashboard-config.js',
    'nav-config.js',
    'shell.js',
    'index.js',
];
foreach ($adminModules as $module):
    $mtime = file_exists($adminJsBase . $module) ? filemtime($adminJsBase . $module) : 0;
?>
<script src="<?= url('/js/admin/' . $module) ?>?v=<?= $mtime ?>"></script>
<?php endforeach; ?>
<?php foreach (\Cruinn\Template::flushJs() as $_jsModule): ?>
<script src="<?= url('/js/admin/' . e($_jsModule)) ?>?v=<?= file_exists($adminJsBase . $_jsModule) ? filemtime($adminJsBase . $_jsModule) : 0 ?>"></script>
<?php endforeach; ?>
</body>
</html>
