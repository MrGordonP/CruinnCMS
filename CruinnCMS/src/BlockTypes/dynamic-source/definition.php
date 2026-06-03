<?php
/**
 * dynamic-include block type.
 *
 * One dynamic block that can render from multiple source providers:
 * - php_include    (template include)
 * - module_widget  (module widget catalog)
 * - module_content (module content provider catalog)
 * - core_fragment  (core-managed reusable fragments)
 */

use Cruinn\Auth;
use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\CSRF;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;

BlockRegistry::register([
    'slug'      => 'dynamic-include',
    'label'     => 'Dynamic Include',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db, array $context = []): string {
        $annotateEditable = static function (string $html): string {
            if (trim($html) === '') {
                return $html;
            }

            $dom = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $dom->loadHTML(
                '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);
            $idx = 0;
            foreach ($xpath->query('/html/body//*') as $el) {
                /** @var \DOMElement $el */
                $el->setAttribute('data-phpi-el', (string) $idx++);
                $el->setAttribute('data-phpi-classes', trim((string) $el->getAttribute('class')));
            }

            $body = $dom->getElementsByTagName('body')->item(0);
            $output = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $output .= $dom->saveHTML($child);
                }
            }
            return $output;
        };

        $sourceType = trim((string) ($config['source_type'] ?? ''));
        if ($sourceType === '') {
            if (!empty($config['template'])) {
                $sourceType = 'php_include';
            } elseif (!empty($config['widget_key'])) {
                $sourceType = 'module_widget';
            } elseif (!empty($config['provider_key'])) {
                $sourceType = 'module_content';
            }
        }

        if ($sourceType === 'php_include') {
            $rel = trim((string) ($config['template'] ?? ''));
            if ($rel === '') {
                return '<p class="php-include-empty" style="color:#9ca3af;font-size:0.8rem;padding:0.5rem">Dynamic include (PHP include) - no template selected</p>';
            }

            $base = realpath(dirname(__DIR__, 3) . '/templates');
            $exclude = ['/admin/', '/platform/'];

            if (!$base || str_contains($rel, '..') || str_contains($rel, "\0")) {
                return '';
            }

            $fullPath = realpath($base . '/' . $rel);
            if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
                return '';
            }
            foreach ($exclude as $ex) {
                if (str_contains('/' . $rel, $ex)) {
                    return '';
                }
            }

            $vars = $config;
            unset($vars['source_type'], $vars['core_fragment_key'], $vars['template'], $vars['widget_key'], $vars['provider_key'], $vars['settings_json'], $vars['display_mode'], $vars['per_page'], $vars['blog_profile_id'], $vars['event_profile_id'], $vars['childStyles']);
            $vars['db'] = $db;

            if (BlockRegistry::isEditMode()) {
                set_error_handler(static function (int $errno): bool {
                    return ($errno & (E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING)) !== 0;
                });

                ob_start();
                try {
                    $vars = array_merge(\Cruinn\Template::globals(), $vars);
                    extract($vars, EXTR_SKIP);
                    include $fullPath;
                    $output = ob_get_clean();
                } catch (\Throwable $e) {
                    $output = ob_get_clean() ?: '';
                    $output .= '<div style="color:#b91c1c;font-size:0.75rem;padding:0.25rem 0.5rem;background:#fef2f2;border:1px solid #fecaca;margin-top:0.25rem">'
                        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                        . '</div>';
                } finally {
                    restore_error_handler();
                }

                return $annotateEditable($output);
            }

            $vars = array_merge(\Cruinn\Template::globals(), $vars, $context);
            extract($vars, EXTR_SKIP);
            ob_start();
            try {
                include $fullPath;
            } catch (\Throwable $e) {
                ob_end_clean();
                return '<div class="php-include-error" style="color:#b91c1c;font-size:0.8rem;padding:0.5rem;background:#fef2f2;border:1px solid #fecaca">'
                    . htmlspecialchars('PHP Include error: ' . $e->getMessage())
                    . '</div>';
            }
            return ob_get_clean();
        }

        if ($sourceType === 'module_widget') {
            $widgetKey = trim((string) ($config['widget_key'] ?? ''));
            if ($widgetKey === '') {
                return '<p class="cruinn-module-widget-empty">Dynamic include (module widget): no widget selected.</p>';
            }

            $userContext = $config['_userContext'] ?? [];
            if (!is_array($userContext)) {
                $userContext = [];
            }

            $settings = $config;
            unset($settings['source_type'], $settings['core_fragment_key'], $settings['template'], $settings['provider_key'], $settings['settings_json'], $settings['display_mode'], $settings['per_page'], $settings['blog_profile_id'], $settings['event_profile_id'], $settings['_userContext']);

            $html = ModuleRegistry::renderProviderWidget($widgetKey, $settings, $userContext);
            if ($html === '') {
                $html = ModuleRegistry::renderWidgetByKey($widgetKey);
            }
            if ($html === '') {
                return '<p class="cruinn-module-widget-empty">Module widget not found: '
                    . htmlspecialchars($widgetKey, ENT_QUOTES, 'UTF-8')
                    . '.</p>';
            }
            if (BlockRegistry::isEditMode()) {
                return $annotateEditable($html);
            }
            return $html;
        }

        if ($sourceType === 'module_content') {
            $providerKey = trim((string) ($config['provider_key'] ?? ''));
            if ($providerKey === '') {
                return '<p class="cruinn-module-content-empty">Dynamic include (module content): no provider selected.</p>';
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

            if (BlockRegistry::isEditMode()) {
                return $annotateEditable($html);
            }
            return $html;
        }

        if ($sourceType === 'core_fragment') {
            $fragmentKey = trim((string) ($config['core_fragment_key'] ?? ''));

            if ($fragmentKey === 'subjects_quick_link') {
                $html = '<div class="dash-quick-grid">'
                    . '<a href="/admin/subjects" class="dash-quick-link">'
                    . '<span class="dash-quick-icon">🧭</span>'
                    . '<span>Subjects</span>'
                    . '</a>'
                    . '</div>';
                return BlockRegistry::isEditMode() ? $annotateEditable($html) : $html;
            }

            if ($fragmentKey === 'subjects_status_summary') {
                try {
                    $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM subjects');
                    $active = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'active'");
                    $draft = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'draft'");
                    $archived = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'archived'");
                } catch (\Throwable) {
                    $total = 0;
                    $active = 0;
                    $draft = 0;
                    $archived = 0;
                }

                $html = '<div class="activity-header"><h2>Subjects Status</h2></div>'
                    . '<div class="dash-quick-grid">'
                    . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Total</span></a>'
                    . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $active . '</strong><span>Active</span></a>'
                    . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $draft . '</strong><span>Draft</span></a>'
                    . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $archived . '</strong><span>Archived</span></a>'
                    . '</div>';
                return BlockRegistry::isEditMode() ? $annotateEditable($html) : $html;
            }


            return '<p class="account-block-empty">Core fragment not found: '
                . htmlspecialchars($fragmentKey, ENT_QUOTES, 'UTF-8')
                . '.</p>';
        }

        return '<p class="editor-dynamic-placeholder">Dynamic include - no source type selected.</p>';
    },
]);
