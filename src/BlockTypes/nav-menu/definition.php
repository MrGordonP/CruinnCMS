<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

BlockRegistry::register([
    'slug'       => 'nav-menu',
    'label'      => 'Nav Menu',
    'tag'        => 'nav',
    'dynamic'    => true,
    'container'  => false,
    'isLayout'   => false,
    'hasContent' => true,
    'renderer'   => function (array $config, Database $db): string {
        $menuId = (int) ($config['menu_id'] ?? 0);
        if (!$menuId) {
            return '';
        }
        $items = $db->fetchAll(
            'SELECT mi.*, p.slug AS page_slug
             FROM menu_items mi
             LEFT JOIN pages p ON mi.page_id = p.id
             WHERE mi.menu_id = ? AND mi.is_active = 1
               AND (mi.parent_id IS NULL OR mi.parent_id = 0)
             ORDER BY mi.sort_order ASC',
            [$menuId]
        );
        if (empty($items)) {
            return '';
        }
        $html = '<ul class="nav-list">';
        foreach ($items as $mi) {
            $href = match ($mi['link_type'] ?? 'url') {
                'page'  => '/' . ($mi['page_slug'] ?? ''),
                'route' => $mi['route'] ?? '/',
                default => $mi['url'] ?? '#',
            };
            $html .= '<li><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                   . htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8')
                   . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    },
]);
