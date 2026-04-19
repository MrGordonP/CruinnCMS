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
    <div class="dashboard-view-toggle">
        <a href="?view=groups"  class="btn btn-small <?= ($dashboardView ?? 'groups') === 'groups'  ? 'btn-primary' : 'btn-outline' ?>">By Group</a>
        <a href="?view=modules" class="btn btn-small <?= ($dashboardView ?? 'groups') === 'modules' ? 'btn-primary' : 'btn-outline' ?>">By Module</a>
    </div>
    <?php endif; ?>

    <?php if (($current_user['role'] ?? '') === 'admin' && ($dashboardView ?? 'groups') === 'groups'): ?>
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
                <?php foreach (\Cruinn\Modules\ModuleRegistry::acpSections() as $_s):
                    if (($_s['group'] ?? '') !== 'Settings') { continue; } ?>
                <a href="<?= url($_s['url']) ?>" class="dash-quick-link">
                    <span class="dash-quick-icon"><?= $_s['icon'] ?? '🧩' ?></span>
                    <span><?= e($_s['label']) ?></span>
                </a>
                <?php endforeach; ?>
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

        <?php
        // ── Dynamic module group tiles ────────────────────────────────
        // Collect all acp_sections from active modules, skip 'Settings'
        // (already rendered above) and 'People' (rendered separately below).
        $acpGroups = [];
        foreach (\Cruinn\Modules\ModuleRegistry::acpSections() as $s) {
            $g = $s['group'] ?? 'Other';
            if ($g === 'Settings') { continue; }
            $acpGroups[$g][] = $s;
        }
        // Render each group as a tile
        foreach ($acpGroups as $groupName => $groupItems):
            if ($groupName === 'People') { continue; } // handled below
        ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2><?= e($groupName) ?></h2>
            </div>
            <div class="dash-quick-grid">
                <?php foreach ($groupItems as $s): ?>
                <a href="<?= url($s['url']) ?>" class="dash-quick-link">
                    <span class="dash-quick-icon"><?= $s['icon'] ?? '🧩' ?></span>
                    <span><?= e($s['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

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
                <?php foreach ($acpGroups['People'] ?? [] as $s): ?>
                <a href="<?= url($s['url']) ?>" class="dash-quick-link">
                    <span class="dash-quick-icon"><?= $s['icon'] ?? '🧩' ?></span>
                    <span><?= e($s['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- .dashboard-widget-stack groups view -->

    <?php elseif (($current_user['role'] ?? '') === 'admin' && ($dashboardView ?? 'groups') === 'modules'): ?>
    <div class="dashboard-widget-stack">
        <?php foreach (\Cruinn\Modules\ModuleRegistry::all() as $slug => $def):
            if (!\Cruinn\Modules\ModuleRegistry::isActive($slug)) { continue; }
            $sections = $def['dashboard_sections'] ?? [];
            if (empty($sections)) { continue; }
        ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2><?= e($def['name']) ?> <small class="text-muted" style="font-weight:normal;font-size:0.75em;">v<?= e($def['version'] ?? '') ?></small></h2>
            </div>
            <div class="dash-quick-grid">
                <?php foreach ($sections as $s): ?>
                <a href="<?= url($s['url']) ?>" class="dash-quick-link">
                    <span class="dash-quick-icon"><?= $s['icon'] ?? '🧩' ?></span>
                    <span><?= e($s['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div><!-- .dashboard-widget-stack modules view -->

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
