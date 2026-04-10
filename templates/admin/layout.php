<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> — <?= e(\Cruinn\App::config('site.name', 'Admin')) ?></title>
    <link rel="stylesheet" href="<?= url('/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/css/admin-base.css') ?>?v=<?= filemtime(CRUINN_PUBLIC . '/css/admin-base.css') ?>">
    <?php foreach (\Cruinn\Template::flushCss() as $_cssFile): ?>
    <link rel="stylesheet" href="<?= url('/css/' . e($_cssFile)) ?>?v=<?= filemtime(CRUINN_PUBLIC . '/css/' . $_cssFile) ?>">
    <?php endforeach; ?>
    <script>if(localStorage.getItem('admin-layout-wide')==='1')document.documentElement.classList.add('admin-layout-wide');</script>
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
            <?php elseif (!empty($role_nav_items) && ($current_user['role'] ?? '') !== 'admin'): ?>
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
                        <a href="<?= url('/admin/site-builder/structure') ?>">Structure</a>
                        <a href="<?= url('/admin/site-builder/global-header') ?>">Global Header</a>
                        <a href="<?= url('/admin/site-builder/global-footer') ?>">Global Footer</a>
                        <a href="<?= url('/admin/blocks/named') ?>">Named Blocks</a>
                        <a href="<?= url('/admin/template-editor') ?>">PHP Templates</a>
                        <a href="<?= url('/admin/media') ?>">Media</a>
                        <a href="<?= url('/admin/import') ?>">Import</a>
                    </div>
                </div>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('articles') || \Cruinn\Modules\ModuleRegistry::isActive('broadcasts') || \Cruinn\Modules\ModuleRegistry::isActive('forms')): ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/articles') ?>" class="admin-sidebar-parent">Content <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('articles')): ?>
                        <a href="<?= url('/admin/articles') ?>">Articles</a>
                        <a href="<?= url('/admin/subjects') ?>">Subjects</a>
                        <?php endif; ?>
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('broadcasts')): ?>
                        <a href="<?= url('/admin/broadcasts') ?>">Broadcasts</a>
                        <?php endif; ?>
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('forms')): ?>
                        <a href="<?= url('/admin/forms') ?>">Forms</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('events') || \Cruinn\Modules\ModuleRegistry::isActive('forum') || \Cruinn\Modules\ModuleRegistry::isActive('file-manager')): ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/events') ?>" class="admin-sidebar-parent">Community <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('events')): ?>
                        <a href="<?= url('/admin/events') ?>">Events</a>
                        <?php endif; ?>
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('forum')): ?>
                        <a href="<?= url('/admin/forum') ?>">Forum</a>
                        <?php endif; ?>
                        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('file-manager')): ?>
                        <a href="<?= url('/files') ?>">Files</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/users') ?>" class="admin-sidebar-parent">People <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <a href="<?= url('/admin/users') ?>">Users</a>
                        <a href="<?= url('/admin/roles') ?>">Roles</a>
                        <a href="<?= url('/admin/groups') ?>">Groups</a>
                    </div>
                </div>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('social')): ?>
                <div class="admin-sidebar-group">
                    <a href="<?= url('/admin/social') ?>" class="admin-sidebar-parent">Comms <span class="sidebar-caret">▸</span></a>
                    <div class="admin-sidebar-flyout">
                        <a href="<?= url('/admin/social') ?>">Social Hub</a>
                        <a href="<?= url('/admin/social/mailing-lists') ?>">Mailing Lists</a>
                        <a href="<?= url('/admin/social/accounts') ?>">Accounts</a>
                        <a href="<?= url('/admin/social/distribute') ?>">Distribute</a>
                    </div>
                </div>
                <?php endif; ?>
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
                    <?php if (\Cruinn\Platform\PlatformAuth::check()): ?>
                    <a href="/cms/dashboard" class="admin-topbar-cms-link" title="Back to Cruinn CMS platform">← CMS</a>
                    <?php endif; ?>
                    <button class="admin-width-toggle" id="admin-width-btn" title="Toggle layout width" onclick="var w=document.documentElement.classList.toggle('admin-layout-wide');localStorage.setItem('admin-layout-wide',w?'1':'0');this.textContent=w?'\u22A1':'\u229E';">&#x229E;</button><script>document.getElementById('admin-width-btn').textContent=document.documentElement.classList.contains('admin-layout-wide')?'\u22A1':'\u229E';</script>
                    <span class="admin-topbar-user">&#x1F464; <?= e($current_user['display_name'] ?? $current_user['email'] ?? 'Admin') ?></span>
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

        <?php if (!empty($modules_has_new)): ?>
        <div class="modules-banner" role="alert">
            🧩 New module(s) detected.
            <a href="<?= url('/admin/settings/modules') ?>">Go to Modules panel</a> to activate them.
            <button type="button" class="modules-banner-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&times;</button>
        </div>
        <?php endif; ?>

        <div class="admin-main">
            <div class="admin-content">
                <?= $content ?>
            </div>
        </div>
    </div>
</div>

<!-- Media Browser Modal -->
<div class="media-modal-overlay" id="media-modal" style="display:none">
    <div class="media-modal">
        <div class="media-modal-header">
            <h2>Media Library</h2>
            <button type="button" class="media-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="media-modal-toolbar">
            <label class="btn btn-small btn-primary media-upload-btn">
                Upload New <input type="file" id="media-modal-upload" accept="image/*" hidden>
            </label>
            <input type="search" id="media-search" class="form-input" placeholder="Search files…" style="max-width:220px">
        </div>
        <div class="media-modal-grid" id="media-grid">
            <p class="media-loading">Loading…</p>
        </div>
        <div class="media-modal-footer">
            <button type="button" class="btn btn-primary media-select-btn" disabled>Insert Selected</button>
            <button type="button" class="btn btn-outline media-cancel-btn">Cancel</button>
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
    'social-hub.js',
    'dashboard-config.js',
    'nav-config.js',
    'index.js',
];
// Load block type registry first, then all registered type files
$blockTypesDir = $adminJsBase . 'block-types/';
$blockTypeFiles = [];
if (is_dir($blockTypesDir)) {
    // _registry.js first (underscore sorts before letters), then alphabetical type files
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
<?php foreach (\Cruinn\Template::flushJs() as $_jsModule): ?>
<script src="<?= url('/js/admin/' . e($_jsModule)) ?>?v=<?= file_exists($adminJsBase . $_jsModule) ? filemtime($adminJsBase . $_jsModule) : 0 ?>"></script>
<?php endforeach; ?>
</body>
</html>
