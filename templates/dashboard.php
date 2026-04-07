<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
/**
 * Dynamic Dashboard Template
 *
 * Admin: single-column cPanel tile widgets (stats are in the layout sidebar).
 * Other roles: configured widgets rendered from DashboardService.
 *
 * Variables: $dashboardTitle, $widgets, $current_user
 */
?>
<div class="dynamic-dashboard">
    <h1><?= e($dashboardTitle ?? 'Dashboard') ?></h1>

    <?php if (($current_user['role'] ?? '') === 'admin'): ?>
    <div class="dashboard-widget-stack">

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Settings</h2>
                <a href="<?= url('/admin/settings/site') ?>" class="btn btn-primary btn-small">⚙ Open ACP</a>
            </div>
            <div class="dash-quick-grid">
                <a href="<?= url('/admin/settings/site') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🏠</span><span>Site</span>
                </a>
                <a href="<?= url('/admin/settings/email') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">✉️</span><span>Email</span>
                </a>
                <a href="<?= url('/admin/settings/auth') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🔑</span><span>Auth</span>
                </a>
                <a href="<?= url('/admin/settings/security') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🛡️</span><span>Security</span>
                </a>
                <a href="<?= url('/admin/settings/system') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">ℹ️</span><span>System</span>
                </a>
                <a href="<?= url('/admin/settings/database') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🗄️</span><span>Database</span>
                </a>
                <a href="<?= url('/admin/settings/payments') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">💳</span><span>Payments</span>
                </a>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('oauth')): ?>
                <a href="<?= url('/admin/settings/oauth') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🔐</span><span>OAuth</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('gdpr')): ?>
                <a href="<?= url('/admin/settings/gdpr') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🔒</span><span>GDPR</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('social')): ?>
                <a href="<?= url('/admin/settings/social') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📡</span><span>Social&nbsp;Config</span>
                </a>
                <?php endif; ?>
                <a href="<?= url('/admin/settings/modules') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🧩</span><span>Modules</span>
                </a>
                <a href="<?= url('/admin/maintenance/links') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🔍</span><span>Maintenance</span>
                </a>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Site Builder</h2>
                <a href="<?= url('/admin/editor') ?>" class="btn btn-primary btn-small">✏️ Open Editor</a>
            </div>
            <div class="dash-quick-grid">
                <a href="<?= url('/admin/editor') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">✏️</span><span>Open Editor</span>
                </a>
                <a href="<?= url('/admin/pages') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📄</span><span>Pages</span>
                </a>
                <a href="<?= url('/admin/templates') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📐</span><span>Templates</span>
                </a>
                <a href="<?= url('/admin/menus') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">☰</span><span>Menus</span>
                </a>
                <a href="<?= url('/admin/site-builder/structure') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🗺️</span><span>Structure</span>
                </a>
                <a href="<?= url('/admin/site-builder/global-header') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">⬆️</span><span>Global Header</span>
                </a>
                <a href="<?= url('/admin/site-builder/global-footer') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">⬇️</span><span>Global Footer</span>
                </a>
                <a href="<?= url('/admin/blocks/named') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🧱</span><span>Named Blocks</span>
                </a>
                <a href="<?= url('/admin/import') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📥</span><span>Import</span>
                </a>
            </div>
        </div>

        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('articles') || \Cruinn\Modules\ModuleRegistry::isActive('broadcasts') || \Cruinn\Modules\ModuleRegistry::isActive('forms')): ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Content</h2>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('articles')): ?>
                <a href="<?= url('/admin/articles/new') ?>" class="btn btn-primary btn-small">+ New Article</a>
                <?php endif; ?>
            </div>
            <div class="dash-quick-grid">
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('articles')): ?>
                <a href="<?= url('/admin/articles') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📰</span><span>Articles</span>
                </a>
                <a href="<?= url('/admin/subjects') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🏷️</span><span>Subjects</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('broadcasts')): ?>
                <a href="<?= url('/admin/broadcasts') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📣</span><span>Broadcasts</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('forms')): ?>
                <a href="<?= url('/admin/forms') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📋</span><span>Forms</span>
                </a>
                <?php endif; ?>
                <a href="<?= url('/admin/media') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🖼️</span><span>Media</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('events') || \Cruinn\Modules\ModuleRegistry::isActive('forum') || \Cruinn\Modules\ModuleRegistry::isActive('file-manager')): ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Community</h2>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('events')): ?>
                <a href="<?= url('/admin/events/new') ?>" class="btn btn-primary btn-small">+ New Event</a>
                <?php endif; ?>
            </div>
            <div class="dash-quick-grid">
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('events')): ?>
                <a href="<?= url('/admin/events') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📅</span><span>Events</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('forum')): ?>
                <a href="<?= url('/admin/forum') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">💬</span><span>Forum</span>
                </a>
                <?php endif; ?>
                <?php if (\Cruinn\Modules\ModuleRegistry::isActive('file-manager')): ?>
                <a href="<?= url('/files') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📁</span><span>Files</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('social')): ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Social &amp; Communications</h2>
                <a href="<?= url('/admin/social') ?>" class="btn btn-primary btn-small">Command Centre</a>
            </div>
            <div class="dash-quick-grid">
                <a href="<?= url('/admin/social') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📢</span><span>Social Hub</span>
                </a>
                <a href="<?= url('/admin/social/mailing-lists') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📧</span><span>Mailing Lists</span>
                </a>
                <a href="<?= url('/admin/social/accounts') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🔗</span><span>Accounts</span>
                </a>
                <a href="<?= url('/admin/social/distribute') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📤</span><span>Distribute</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>People</h2>
                <a href="<?= url('/admin/users/new') ?>" class="btn btn-primary btn-small">+ New User</a>
            </div>
            <div class="dash-quick-grid">
                <a href="<?= url('/admin/users') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">👤</span><span>Users</span>
                </a>
                <a href="<?= url('/admin/roles') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🎭</span><span>Roles</span>
                </a>
                <a href="<?= url('/admin/groups') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">👥</span><span>Groups</span>
                </a>
            </div>
        </div>

    </div><!-- .dashboard-widget-stack -->

    <?php else: ?>
    <div class="dashboard-widget-grid">
        <?php if (empty($widgets)): ?>
        <div class="dashboard-widget widget-full">
            <p class="text-muted">No dashboard widgets configured for your role. Contact an administrator to set up your dashboard.</p>
        </div>
        <?php else: ?>
        <?php foreach ($widgets as $widget):
            $widthClass = ($widget['grid_width'] === 'half') ? 'widget-half' : 'widget-full';
            $templateFile = __DIR__ . '/' . $widget['template_path'] . '.php';
            $data = $widget['data'] ?? [];
            $settings = $widget['settings'] ?? [];
        ?>
        <div class="dashboard-widget <?= $widthClass ?>">
            <?php if (file_exists($templateFile)): ?>
                <?php include $templateFile; ?>
            <?php else: ?>
                <p class="text-muted">Widget template not found: <?= e($widget['template_path']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
