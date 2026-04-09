<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
/**
 * Dynamic Dashboard Template
 *
 * Admin: single-column cPanel tile widgets (stats are in the layout sidebar).
 * Other roles: configured widgets rendered from DashboardService.
 *
 * Variables: $dashboardTitle, $widgets, $moduleWidgets, $current_user
 */
?>
<div class="dynamic-dashboard">
    <h1><?= e($dashboardTitle ?? 'Dashboard') ?></h1>
    <?php
    $moduleDashboardSections = \Cruinn\Modules\ModuleRegistry::dashboardSections($current_user['role'] ?? 'public');
    $moduleSectionsByGroup = [];
    foreach ($moduleDashboardSections as $section) {
        $group = (string) ($section['group'] ?? 'Modules');
        if (!isset($moduleSectionsByGroup[$group])) {
            $moduleSectionsByGroup[$group] = [];
        }
        $moduleSectionsByGroup[$group][] = $section;
    }
    $moduleDashboardWidgets = is_array($moduleWidgets ?? null) ? $moduleWidgets : [];
    ?>

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
                <a href="<?= url('/admin/subjects') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🏷️</span><span>Subjects</span>
                </a>
                <a href="<?= url('/admin/media') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🖼️</span><span>Media</span>
                </a>
                <a href="<?= url('/admin/import') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">📥</span><span>Import</span>
                </a>
            </div>
        </div>

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

        <?php foreach ($moduleSectionsByGroup as $group => $items): ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2><?= e($group) ?></h2>
            </div>
            <ul class="dash-quick-list" style="list-style:none; margin:0; padding:0; display:grid; gap:.5rem;">
                <?php foreach ($items as $item): ?>
                <li>
                    <a href="<?= url($item['url'] ?? '#') ?>" class="dash-quick-link">
                        <span class="dash-quick-icon"><?= e($item['icon'] ?? '🧩') ?></span><span><?= e($item['label'] ?? 'Module') ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <?php foreach ($moduleDashboardWidgets as $widget): ?>
        <?php
            $widthClass = ($widget['grid_width'] ?? 'full') === 'half' ? 'widget-half' : 'widget-full';
            $templateFile = $widget['template_file'] ?? null;
            $data = $widget['data'] ?? [];
            $settings = $widget['settings'] ?? [];
        ?>
        <div class="dashboard-widget <?= $widthClass ?>">
            <?php if (is_string($templateFile) && file_exists($templateFile)): ?>
                <?php include $templateFile; ?>
            <?php else: ?>
                <div class="activity-header">
                    <h2><?= e($widget['label'] ?? 'Module Widget') ?></h2>
                </div>
                <p class="text-muted">Module widget template not found: <?= e((string) ($widget['template'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

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
        <?php foreach ($moduleSectionsByGroup as $group => $items): ?>
        <div class="dashboard-widget widget-full">
            <div class="activity-header">
                <h2><?= e($group) ?></h2>
            </div>
            <ul class="dash-quick-list" style="list-style:none; margin:0; padding:0; display:grid; gap:.5rem;">
                <?php foreach ($items as $item): ?>
                <li>
                    <a href="<?= url($item['url'] ?? '#') ?>" class="dash-quick-link">
                        <span class="dash-quick-icon"><?= e($item['icon'] ?? '🧩') ?></span><span><?= e($item['label'] ?? 'Module') ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <?php foreach ($moduleDashboardWidgets as $widget): ?>
        <?php
            $widthClass = ($widget['grid_width'] ?? 'full') === 'half' ? 'widget-half' : 'widget-full';
            $templateFile = $widget['template_file'] ?? null;
            $data = $widget['data'] ?? [];
            $settings = $widget['settings'] ?? [];
        ?>
        <div class="dashboard-widget <?= $widthClass ?>">
            <?php if (is_string($templateFile) && file_exists($templateFile)): ?>
                <?php include $templateFile; ?>
            <?php else: ?>
                <div class="activity-header">
                    <h2><?= e($widget['label'] ?? 'Module Widget') ?></h2>
                </div>
                <p class="text-muted">Module widget template not found: <?= e((string) ($widget['template'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
