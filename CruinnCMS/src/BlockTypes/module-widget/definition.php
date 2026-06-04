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
    'group'          => 'function-cards',
    'group_label'    => 'Function Cards',
    'palette_entries'=> [['label' => 'Function Card']],
    'renderer'  => function (array $config, Database $db): string {
        $widgetKey = trim((string) ($config['widget_key'] ?? ''));
        if ($widgetKey === '') {
            return '<p class="cruinn-module-widget-empty">Module widget: no widget selected.</p>';
        }

        // Provider widgets can render with userContext (dashboard runtime) or
        // empty context (editor/preview); fall back to legacy pre-rendered widgets.
        $userContext = $config['_userContext'] ?? [];
        if (!is_array($userContext)) {
            $userContext = [];
        }

        $settings = $config;
        unset($settings['widget_key'], $settings['_userContext']);

        $html = ModuleRegistry::renderProviderWidget($widgetKey, $settings, $userContext);
        if ($html === '') {
            // Simple widget (legacy sidebar callable) — pre-rendered HTML
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
