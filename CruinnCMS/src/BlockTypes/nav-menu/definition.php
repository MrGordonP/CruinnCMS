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
             LEFT JOIN pages_index p ON mi.page_id = p.id
             WHERE mi.menu_id = ? AND mi.is_active = 1
             ORDER BY mi.sort_order ASC',
            [$menuId]
        );
        if (empty($all)) {
            return '';
        }

        // Filter by visibility and min_role (mirrors get_menu() logic)
        if (!BlockRegistry::isEditMode()) {
            $loggedIn   = \Cruinn\Auth::check();
            $userRole   = $loggedIn ? (\Cruinn\Auth::user()['role'] ?? 'member') : null;
            $roleLevels = ['public' => 0, 'member' => 20, 'council' => 50, 'admin' => 100];
            $userLevel  = $roleLevels[$userRole] ?? 0;

            $all = array_filter($all, function ($row) use ($loggedIn, $userLevel, $roleLevels) {
                $vis = $row['visibility'] ?? 'always';
                if ($vis === 'logged_in' && !$loggedIn) return false;
                if ($vis === 'logged_out' && $loggedIn) return false;
                if (!empty($row['min_role'])) {
                    $reqLevel = $roleLevels[$row['min_role']] ?? 0;
                    if ($userLevel < $reqLevel) return false;
                }
                return true;
            });
            $all = array_values($all);
            if (empty($all)) {
                return '';
            }
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
