<?php
/**
 * module-widget block type.
 *
 * Renders a module-provided widget selected by key.
 * The widget key is stored in block_config['widget_key'].
 */

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;

BlockRegistry::register([
    'slug'      => 'module-widget',
    'label'     => 'Module Widget',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db): string {
        $widgetKey = trim((string) ($config['widget_key'] ?? ''));
        if ($widgetKey === '') {
            return '<p class="cruinn-module-widget-empty">Module widget: no widget selected.</p>';
        }

        // Check if userContext is present (injected by DashboardService for widget dashboards)
        $userContext = $config['_userContext'] ?? null;

        if ($userContext && is_array($userContext)) {
            // Provider-based widget with userContext — extract settings and call provider
            $settings = $config;
            unset($settings['widget_key'], $settings['_userContext']);

            $html = ModuleRegistry::renderProviderWidget($widgetKey, $settings, $userContext);
        } else {
            // Simple widget (sidebar) — pre-rendered HTML
            $html = ModuleRegistry::renderWidgetByKey($widgetKey);
        }

        if ($html === '') {
            return '<p class="cruinn-module-widget-empty">Module widget not found: '
                 . htmlspecialchars($widgetKey, ENT_QUOTES, 'UTF-8')
                 . '.</p>';
        }

        return $html;
    },
]);
