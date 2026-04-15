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

        $all = $db->fetchAll(
            'SELECT mi.*, p.slug AS page_slug
             FROM menu_items mi
             LEFT JOIN pages p ON mi.page_id = p.id
             WHERE mi.menu_id = ? AND mi.is_active = 1
             ORDER BY mi.sort_order ASC',
            [$menuId]
        );
        if (empty($all)) {
            return '';
        }

        // Index by id and group children
        $byId      = [];
        $childrenOf = [];
        foreach ($all as $mi) {
            $byId[$mi['id']] = $mi;
            $pid = $mi['parent_id'] ?? null;
            $childrenOf[$pid ?? 0][] = $mi['id'];
        }

        $resolve = function (array $mi) use (&$resolve, $byId, $childrenOf): string {
            $href = match ($mi['link_type'] ?? 'url') {
                'page'    => '/' . ($mi['page_slug'] ?? ''),
                'route'   => $mi['route'] ?? '/',
                'subject' => '/subject/' . ($mi['subject_id'] ?? ''),
                default   => $mi['url'] ?? '#',
            };
            $label    = htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8');
            $hrefAttr = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $target   = $mi['open_new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
            $children = $childrenOf[$mi['id']] ?? [];
            if ($children) {
                $sub = '<ul class="nav-dropdown">';
                foreach ($children as $cid) {
                    $sub .= $resolve($byId[$cid]);
                }
                $sub .= '</ul>';
                return '<li><a href="' . $hrefAttr . '"' . $target . '>' . $label . '</a>' . $sub . '</li>';
            }
            return '<li><a href="' . $hrefAttr . '"' . $target . '>' . $label . '</a></li>';
        };

        $html = '<ul class="nav-list">';
        foreach ($childrenOf[0] ?? [] as $id) {
            $html .= $resolve($byId[$id]);
        }
        $html .= '</ul>';
        return $html;
    },
]);
