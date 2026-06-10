<?php

declare(strict_types=1);

namespace Cruinn\Services;

use Cruinn\Modules\ModuleRegistry;

/**
 * Builds the default admin sidebar menu for full admins.
 *
 * Role-scoped custom navigation (role_nav_items) remains a separate path.
 */
class AdminSidebarMenuService
{
    /**
     * Return top-level sidebar groups and links.
     *
     * Each item: ['label' => string, 'url' => string, 'children' => array<array{label:string,url:string}>]
     */
    public static function build(): array
    {
        $menu = [
            [
                'label' => 'Dashboard',
                'url' => '/admin/dashboard',
                'children' => [],
            ],
            [
                'label' => 'Site Builder',
                'url' => '/admin/site-builder',
                'children' => [
                    ['label' => 'Open Editor', 'url' => '/admin/editor'],
                    ['label' => 'Widget Editor', 'url' => '/admin/site-builder/dashboards'],
                    ['label' => 'Structure', 'url' => '/admin/site-builder/structure'],
                    ['label' => 'Menus', 'url' => '/admin/menus'],
                    ['label' => 'Layout Templates', 'url' => '/admin/templates?panel=template-layouts'],
                    ['label' => 'Page Templates', 'url' => '/admin/templates?panel=page-templates'],
                    ['label' => 'Template Includes', 'url' => '/admin/template-editor'],
                    ['label' => 'Named Blocks', 'url' => '/admin/blocks/named'],
                ],
            ],
            [
                'label' => 'Content',
                'url' => '/admin/pages',
                'children' => [
                    ['label' => 'Pages', 'url' => '/admin/pages'],
                    ['label' => 'Media', 'url' => '/admin/media'],
                    ['label' => 'Subjects', 'url' => '/admin/subjects'],
                    ['label' => 'Dynamic Content', 'url' => '/admin/content'],
                    ['label' => 'Import', 'url' => '/admin/import'],
                ],
            ],
            [
                'label' => 'Accounts',
                'url' => '/admin/users',
                'children' => [
                    ['label' => 'Users', 'url' => '/admin/users'],
                    ['label' => 'Roles', 'url' => '/admin/roles'],
                    ['label' => 'Groups', 'url' => '/admin/groups'],
                ],
            ],
        ];

        // Collect module sidebar entries — one group per active module
        $moduleMenus = [];
        foreach (ModuleRegistry::all() as $slug => $def) {
            if (!ModuleRegistry::isActive((string) $slug)) {
                continue;
            }

            $children = [];
            foreach ((array) ($def['acp_sections'] ?? []) as $section) {
                $label = trim((string) ($section['label'] ?? ''));
                $url   = trim((string) ($section['url']   ?? ''));
                if ($label === '' || $url === '') {
                    continue;
                }
                $seenUrls = array_column($children, 'url');
                if (!in_array($url, $seenUrls, true)) {
                    $children[] = ['label' => $label, 'url' => $url];
                }
            }

            if (empty($children)) {
                continue;
            }

            $moduleLabel = trim((string) ($def['name'] ?? '')) ?: ucfirst((string) $slug);

            if (count($children) === 1) {
                // Single-item module — flat link, no flyout
                $moduleMenus[] = [
                    'label'    => $moduleLabel,
                    'url'      => $children[0]['url'],
                    'children' => [],
                ];
            } else {
                $moduleMenus[] = [
                    'label'    => $moduleLabel,
                    'url'      => $children[0]['url'],
                    'children' => $children,
                ];
            }
        }

        usort($moduleMenus, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        // Section heading + all module entries
        $menu[] = ['type' => 'heading', 'label' => 'Modules'];
        foreach ($moduleMenus as $moduleMenu) {
            $menu[] = $moduleMenu;
        }

        return $menu;
    }
}
