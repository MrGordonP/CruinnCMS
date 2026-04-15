<?php
/**
 * CruinnCMS — Navigation Service
 *
 * Provides per-role navigation from the database.
 * Returns a nested tree of nav items that layout templates render.
 */

namespace Cruinn\Services;

use Cruinn\Auth;
use Cruinn\Database;

class NavService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get the navigation tree for a role, filtered by user permissions.
     *
     * @return array[] Nested array: each item may have a 'children' key.
     */
    public function getNavForRole(int $roleId): array
    {
        $items = $this->db->fetchAll(
            'SELECT * FROM role_nav_items
             WHERE role_id = ? AND is_visible = 1
             ORDER BY sort_order ASC',
            [$roleId]
        );

        // Filter by permission if set
        $items = array_filter($items, function ($item) {
            if (empty($item['permission_required'])) {
                return true;
            }
            return Auth::can($item['permission_required']);
        });

        return $this->buildTree($items);
    }

    /**
     * Get ALL nav items for a role (flat, including hidden), for admin config.
     */
    public function getAllForConfig(int $roleId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM role_nav_items
             WHERE role_id = ?
             ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC',
            [$roleId]
        );
    }

    /**
     * Save navigation config for a role.
     * Expects a flat array of items with parent relationships expressed by parent_id.
     *
     * @param int   $roleId
     * @param array $navItems Array of ['label', 'url', 'parent_id', 'sort_order', 'is_visible', 'css_class', 'permission_required', 'opens_new_tab']
     */
    public function saveNavConfig(int $roleId, array $navItems): void
    {
        $this->db->transaction(function () use ($roleId, $navItems) {
            // Remove existing nav items for this role
            $this->db->delete('role_nav_items', 'role_id = ?', [$roleId]);

            // Track old_id → new_id mapping for parent references
            $idMap = [];

            // First pass: insert top-level items (parent_id = null)
            foreach ($navItems as $item) {
                if (!empty($item['parent_temp_id'])) {
                    continue; // skip children for now
                }
                $newId = $this->db->insert('role_nav_items', [
                    'role_id'              => $roleId,
                    'parent_id'            => null,
                    'label'                => $item['label'],
                    'url'                  => $item['url'] ?? '#',
                    'permission_required'  => $item['permission_required'] ?: null,
                    'sort_order'           => (int) ($item['sort_order'] ?? 0),
                    'is_visible'           => !empty($item['is_visible']) ? 1 : 0,
                    'css_class'            => $item['css_class'] ?: null,
                    'opens_new_tab'        => !empty($item['opens_new_tab']) ? 1 : 0,
                ]);
                if (!empty($item['temp_id'])) {
                    $idMap[$item['temp_id']] = $newId;
                }
            }

            // Second pass: insert children
            foreach ($navItems as $item) {
                if (empty($item['parent_temp_id'])) {
                    continue;
                }
                $parentId = $idMap[$item['parent_temp_id']] ?? null;
                if (!$parentId) {
                    continue;
                }
                $this->db->insert('role_nav_items', [
                    'role_id'              => $roleId,
                    'parent_id'            => $parentId,
                    'label'                => $item['label'],
                    'url'                  => $item['url'] ?? '#',
                    'permission_required'  => $item['permission_required'] ?: null,
                    'sort_order'           => (int) ($item['sort_order'] ?? 0),
                    'is_visible'           => !empty($item['is_visible']) ? 1 : 0,
                    'css_class'            => $item['css_class'] ?: null,
                    'opens_new_tab'        => !empty($item['opens_new_tab']) ? 1 : 0,
                ]);
            }
        });
    }

    /**
     * Build a nested tree from a flat array of items.
     */
    private function buildTree(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $item['children'] = [];
            $indexed[$item['id']] = $item;
        }

        $tree = [];
        foreach ($indexed as &$item) {
            if ($item['parent_id'] && isset($indexed[$item['parent_id']])) {
                $indexed[$item['parent_id']]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
