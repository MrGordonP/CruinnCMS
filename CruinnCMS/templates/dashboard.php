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
    $_dashboardCatalog = \Cruinn\Modules\ModuleRegistry::widgetCatalog();

    $_cardsByKind = [
        'function-card' => [],
        'summary-card'  => [],
        'quick-link'    => [],
    ];
    $_cardsByModule = [];

    foreach ($_dashboardCatalog as $_card) {
        $_kind = $_card['kind'] ?? 'function-card';
        if (!isset($_cardsByKind[$_kind])) {
            $_kind = 'function-card';
        }
        $_cardsByKind[$_kind][] = $_card;

        $_module = $_card['module'] ?? 'core';
        if (!isset($_cardsByModule[$_module])) {
            $_cardsByModule[$_module] = [];
        }
        $_cardsByModule[$_module][] = $_card;
    }

    $_kindLabels = [
        'function-card' => 'Function Cards',
        'summary-card'  => 'Summary Cards',
        'quick-link'    => 'Quick Link Cards',
    ];

    $_renderCardHtml = static function (array $_card) use ($_dashUserContext): string {
        $_key = (string) ($_card['key'] ?? '');
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
    ?>

    <?php if (($current_user['role_level'] ?? 0) >= 100 && ($dashboardView ?? 'groups') === 'groups'): ?>
    <div class="dashboard-widget-stack">
        <?php foreach (['function-card', 'summary-card', 'quick-link'] as $_kind): ?>
        <?php if (empty($_cardsByKind[$_kind])) { continue; } ?>
        <section>
            <div class="activity-header">
                <h2><?= e($_kindLabels[$_kind] ?? 'Cards') ?></h2>
            </div>
            <div class="dashboard-widget-grid">
            <?php foreach ($_cardsByKind[$_kind] as $_card): ?>
            <div class="dashboard-widget">
                <?= $_renderCardHtml($_card) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div><!-- .dashboard-widget-stack groups view -->

    <?php elseif (($current_user['role_level'] ?? 0) >= 100 && ($dashboardView ?? 'groups') === 'modules'): ?>
    <div class="dashboard-widget-stack">
        <?php foreach ($_cardsByModule as $_module => $_cards): ?>
        <section>
            <div class="activity-header">
                <h2><?= e($_module) ?></h2>
            </div>
            <div class="dashboard-widget-grid">
            <?php foreach ($_cards as $_card): ?>
            <div class="dashboard-widget">
                <?= $_renderCardHtml($_card) ?>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div><!-- .dashboard-widget-stack modules view -->

    <?php elseif (($current_user['role_level'] ?? 0) >= 50): ?>
    <div class="dashboard-widget-grid">
        <?php foreach ($_cardsByKind['quick-link'] as $_card): ?>
        <div class="dashboard-widget">
            <?= $_renderCardHtml($_card) ?>
        </div>
        <?php endforeach; ?>
    </div><!-- .dashboard-widget-grid council view -->

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
