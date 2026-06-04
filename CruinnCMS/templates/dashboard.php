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

    <?php if (($current_user['role_level'] ?? 0) >= 100): ?>
    <div class="dashboard-view-toggle">
        <a href="?view=groups"  class="btn btn-small <?= ($dashboardView ?? 'groups') === 'groups'  ? 'btn-primary' : 'btn-outline' ?>">By Group</a>
        <a href="?view=modules" class="btn btn-small <?= ($dashboardView ?? 'groups') === 'modules' ? 'btn-primary' : 'btn-outline' ?>">By Module</a>
    </div>
    <?php endif; ?>

    <?php
    $_dashUserContext = [
        'user_id'      => (int) (\Cruinn\Auth::userId() ?: 0),
        'role_id'      => (int) (\Cruinn\Auth::roleId() ?: 0),
        'role_level'   => (int) (\Cruinn\Auth::roleLevel() ?: 0),
        'position_ids' => \Cruinn\Auth::positionIds(),
    ];
    $_dashboardCards = \Cruinn\Modules\ModuleRegistry::dashboardCardCatalog();

    $_cardsByGroup = [];
    $_cardsByModule = [];
    $_groupUrlSeen = [];
    foreach ($_dashboardCards as $_card) {
        $_group = trim((string) ($_card['dashboard_group'] ?? 'Other'));
        if ($_group === '') {
            $_group = 'Other';
        }

        $_module = trim((string) ($_card['module'] ?? 'core'));
        if ($_module === '') {
            $_module = 'core';
        }

        $_url = '';
        if (!empty($_card['key'])) {
            $_htmlProbe = \Cruinn\Modules\ModuleRegistry::renderProviderWidget((string) $_card['key'], [], $_dashUserContext);
            if (preg_match('/href="([^"]+)"/', $_htmlProbe, $_m)) {
                $_url = trim((string) ($_m[1] ?? ''));
            }
        }

        $_dedupeKey = $_group . '|' . $_url;
        if ($_url !== '' && isset($_groupUrlSeen[$_dedupeKey])) {
            continue;
        }
        if ($_url !== '') {
            $_groupUrlSeen[$_dedupeKey] = true;
        }

        $_cardsByGroup[$_group][] = $_card;
        $_cardsByModule[$_module][] = $_card;
    }

    $_renderCardByKey = static function (string $_key) use ($_dashUserContext): string {
        if ($_key === '') {
            return '<p class="text-muted">Missing widget key.</p>';
        }
        $_html = \Cruinn\Modules\ModuleRegistry::renderProviderWidget($_key, [], $_dashUserContext);
        if ($_html === '') {
            $_html = \Cruinn\Modules\ModuleRegistry::renderWidgetByKey($_key);
        }
        if ($_html === '') {
            return '<p class="text-muted">Widget not found: ' . e($_key) . '</p>';
        }
        return $_html;
    };

    $_renderQuickLinkByKey = static function (string $_key) use ($_renderCardByKey): string {
        $_html = $_renderCardByKey($_key);
        if (preg_match('/<a\b[^>]*>.*?<\/a>/is', $_html, $_m)) {
            return (string) ($_m[0] ?? '');
        }
        return $_html;
    };

    $_renderGroupCards = static function (string $_groupName) use ($_cardsByGroup, $_renderQuickLinkByKey): void {
        foreach (($_cardsByGroup[$_groupName] ?? []) as $_card) {
            echo $_renderQuickLinkByKey((string) ($_card['key'] ?? ''));
        }
    };
    ?>

    <?php if (($current_user['role_level'] ?? 0) >= 100 && ($dashboardView ?? 'groups') === 'groups'): ?>
    <div class="dashboard-widget-stack">

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Settings</h2>
                <a href="<?= url('/admin/settings/site') ?>" class="btn btn-primary btn-small">⚙ Open ACP</a>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Settings'); ?>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Site Builder</h2>
                <a href="<?= url('/admin/editor') ?>" class="btn btn-primary btn-small">✏️ Open Editor</a>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Site Builder'); ?>
            </div>
        </div>

        <?php foreach ($_cardsByGroup as $groupName => $_unused):
            if (in_array($groupName, ['Settings', 'Site Builder', 'People'], true)) { continue; }
        ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2><?= e($groupName) ?></h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards((string) $groupName); ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>People</h2>
                <a href="<?= url('/admin/users/new') ?>" class="btn btn-primary btn-small">+ New User</a>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('People'); ?>
            </div>
        </div>
    </div><!-- .dashboard-widget-stack groups view -->

    <?php elseif (($current_user['role_level'] ?? 0) >= 100 && ($dashboardView ?? 'groups') === 'modules'): ?>
    <div class="dashboard-widget-stack">
        <?php foreach ($_cardsByModule as $_moduleSlug => $_moduleCards): ?>
        <div class="dashboard-widget">
            <div class="activity-header">
                <h2><?= e($_moduleSlug) ?></h2>
            </div>
            <div class="dash-quick-grid">
                <?php foreach ($_moduleCards as $_card): ?>
                <?= $_renderQuickLinkByKey((string) ($_card['key'] ?? '')) ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div><!-- .dashboard-widget-stack modules view -->

    <?php elseif (($current_user['role_level'] ?? 0) >= 50): ?>
    <div class="dashboard-widget-stack">

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Content</h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Content'); ?>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Organisation</h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Organisation'); ?>
                <?php if (empty($_cardsByGroup['Organisation'])): ?>
                <a href="<?= url('/organisation') ?>" class="dash-quick-link">
                    <span class="dash-quick-icon">🏛️</span><span>Organisation</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Community</h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Community'); ?>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>Comms</h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('Comms'); ?>
            </div>
        </div>

        <div class="dashboard-widget">
            <div class="activity-header">
                <h2>People</h2>
            </div>
            <div class="dash-quick-grid">
                <?php $_renderGroupCards('People'); ?>
            </div>
        </div>

    </div><!-- .dashboard-widget-stack council view -->

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
