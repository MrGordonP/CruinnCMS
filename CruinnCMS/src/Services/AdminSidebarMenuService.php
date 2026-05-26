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
                    ['label' => 'Dynamic Content', 'url' => '/admin/content'],
                    ['label' => 'Import', 'url' => '/admin/import'],
                ],
            ],
        ];

        $moduleGroups = [];
        $reservedGroups = [
            'Content' => true,
            'People' => true,
            'Settings' => true,
            'Site Builder' => true,
        ];

        foreach (ModuleRegistry::acpSections() as $section) {
            $group = trim((string) ($section['group'] ?? ''));
            $label = trim((string) ($section['label'] ?? ''));
            $url = trim((string) ($section['url'] ?? ''));

            if ($group === '' || $label === '' || $url === '') {
                continue;
            }
            if (isset($reservedGroups[$group])) {
                continue;
            }

            if (!isset($moduleGroups[$group])) {
                $moduleGroups[$group] = [
                    'label' => $group,
                    'url' => $url,
                    'children' => [],
                ];
            }

            $exists = false;
            foreach ($moduleGroups[$group]['children'] as $child) {
                if ($child['url'] === $url) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $moduleGroups[$group]['children'][] = [
                    'label' => $label,
                    'url' => $url,
                ];
            }
        }

        ksort($moduleGroups);
        foreach ($moduleGroups as $group) {
            $menu[] = $group;
        }

        $peopleChildren = [
            ['label' => 'Users', 'url' => '/admin/users'],
            ['label' => 'Roles', 'url' => '/admin/roles'],
            ['label' => 'Groups', 'url' => '/admin/groups'],
        ];
        foreach (ModuleRegistry::acpSections() as $section) {
            if (($section['group'] ?? '') !== 'People') {
                continue;
            }
            $label = trim((string) ($section['label'] ?? ''));
            $url = trim((string) ($section['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $exists = false;
            foreach ($peopleChildren as $child) {
                if ($child['url'] === $url) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $peopleChildren[] = ['label' => $label, 'url' => $url];
            }
        }

        $menu[] = [
            'label' => 'People',
            'url' => '/admin/users',
            'children' => $peopleChildren,
        ];

        return $menu;
    }
}
