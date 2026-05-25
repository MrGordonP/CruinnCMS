<?php
/**
 * module-content block type.
 *
 * Renders module-provided content selected by provider key.
 */

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;

BlockRegistry::register([
    'slug'      => 'module-content',
    'label'     => 'Module Content',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db, array $context = []): string {
        $providerKey = trim((string) ($config['provider_key'] ?? ''));
        if ($providerKey === '') {
            return '<p class="cruinn-module-content-empty">Module content: no provider selected.</p>';
        }

        $settings = [];
        $rawSettings = (string) ($config['settings_json'] ?? '');
        if ($rawSettings !== '') {
            $decoded = json_decode($rawSettings, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $displayMode = trim((string) ($config['display_mode'] ?? ''));
        if ($displayMode !== '') {
            $settings['mode'] = $displayMode;
        }

        $perPage = (int) ($config['per_page'] ?? 0);
        if ($perPage > 0) {
            $settings['per_page'] = $perPage;
        }

        $blogProfileId = (int) ($config['blog_profile_id'] ?? 0);
        if ($blogProfileId > 0) {
            $settings['profile_id'] = $blogProfileId;
        }

        $eventProfileId = (int) ($config['event_profile_id'] ?? 0);
        if ($eventProfileId > 0) {
            $settings['event_profile_id'] = $eventProfileId;
        }

        $html = ModuleRegistry::renderContentByKey($providerKey, $settings, $context);
        if ($html === '') {
            return '<p class="cruinn-module-content-empty">Module content not found: '
                 . htmlspecialchars($providerKey, ENT_QUOTES, 'UTF-8')
                 . '.</p>';
        }

        return $html;
    },
]);
