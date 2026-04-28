<?php
/**
 * CruinnCMS — Block Controller
 *
 * Handles block editor AJAX endpoints (add, update, delete, reorder, move)
 * and the editor-mode switch used by the Cruinn CMS editor.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Admin\Controllers;

class BlockController extends \Cruinn\Controllers\BaseController
{
    private const BLOCK_TYPES = [
        'section', 'columns',
        'text', 'heading', 'image', 'gallery', 'html',
        'site-logo', 'site-title', 'nav-menu',
        'event-list', 'map',
    ];

    /**
     * POST /admin/blocks — Add a new block to a page.
     */
    public function addBlock(): void
    {
        $parentType    = $this->input('parent_type', 'page');
        $parentId      = $this->input('parent_id');
        $blockType     = $this->input('block_type', 'text');
        $parentBlockId = $this->input('parent_block_id');
        $column        = (int) $this->input('column', 0);
        $zone          = $this->input('zone', 'body');

        // Validate block type against allowlist
        if (!in_array($blockType, self::BLOCK_TYPES, true)) {
            $this->json(['error' => 'Invalid block type'], 400);
            return;
        }

        // Validate zone
        if (!in_array($zone, ['header', 'body', 'footer'])) {
            $zone = 'body';
        }

        // Validate parent type
        if (!in_array($parentType, ['page', 'article', 'template'])) {
            $this->json(['error' => 'Invalid parent type'], 400);
            return;
        }

        // Get the next sort order (scoped to parent block if nested)
        if ($parentBlockId) {
            $maxOrder = $this->db->fetchColumn(
                'SELECT COALESCE(MAX(sort_order), 0) FROM content_blocks WHERE parent_block_id = ?',
                [$parentBlockId]
            );
        } else {
            $maxOrder = $this->db->fetchColumn(
                'SELECT COALESCE(MAX(sort_order), 0) FROM content_blocks WHERE parent_type = ? AND parent_id = ? AND parent_block_id IS NULL',
                [$parentType, $parentId]
            );
        }

        $defaultContent  = $this->getDefaultBlockContent($blockType);
        $defaultSettings = $this->getDefaultBlockSettings($blockType);
        if ($parentBlockId) {
            $defaultSettings['column'] = $column;
        }

        $blockId = $this->db->insert('content_blocks', [
            'parent_type'     => $parentType,
            'parent_id'       => $parentId,
            'parent_block_id' => $parentBlockId ?: null,
            'zone'            => $zone,
            'block_type'      => $blockType,
            'sort_order'      => $maxOrder + 1,
            'content'         => json_encode($defaultContent, JSON_UNESCAPED_UNICODE),
            'settings'        => json_encode($defaultSettings),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Update parent timestamp
        $this->touchBlockParent($parentType, $parentId);

        $this->json(['success' => true, 'block_id' => $blockId]);
    }

    /**
     * POST /admin/blocks/{id} — Update a block's content.
     */
    public function updateBlock(string $id): void
    {
        $block = $this->db->fetch('SELECT * FROM content_blocks WHERE id = ?', [$id]);
        if (!$block) {
            $this->json(['error' => 'Block not found'], 404);
        }

        $content = $this->input('content');
        if (is_string($content)) {
            // Validate that it's valid JSON
            $decoded = json_decode($content, true);
            if ($decoded === null && $content !== 'null') {
                $this->json(['error' => 'Invalid JSON content'], 400);
            }
        }

        $this->db->update('content_blocks', [
            'content'    => is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE),
            'settings'   => $this->input('settings', '{}'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Update parent timestamp
        $this->touchBlockParent($block['parent_type'], $block['parent_id']);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/blocks/{id}/delete — Remove a block.
     */
    public function deleteBlock(string $id): void
    {
        $block = $this->db->fetch('SELECT * FROM content_blocks WHERE id = ?', [$id]);
        if (!$block) {
            $this->json(['error' => 'Block not found'], 404);
        }

        // Cascade delete children
        $this->deleteBlockAndChildren((int)$id);
        $this->touchBlockParent($block['parent_type'], $block['parent_id']);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/blocks/reorder — Reorder blocks on a page.
     * Expects JSON body: { "order": [3, 1, 5, 2, 4] } — array of block IDs in desired order.
     */
    public function reorderBlocks(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $order = $input['order'] ?? [];

        if (empty($order)) {
            $this->json(['error' => 'No order provided'], 400);
        }

        foreach ($order as $position => $blockId) {
            $this->db->update('content_blocks', [
                'sort_order' => $position + 1,
            ], 'id = ?', [$blockId]);
        }

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/blocks/{id}/move — Move a block to a new parent container.
     * Expects JSON body: { "parent_block_id": 123 } or { "parent_block_id": null } for top-level.
     * Optionally include "column": 0 for row columns.
     */
    public function moveBlock(string $id): void
    {
        $blockId = (int) $id;
        $block = $this->db->fetch('SELECT * FROM content_blocks WHERE id = ?', [$blockId]);
        if (!$block) {
            $this->json(['error' => 'Block not found'], 404);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newParentId = isset($input['parent_block_id']) ? ($input['parent_block_id'] ? (int)$input['parent_block_id'] : null) : null;
        $column = isset($input['column']) ? (int)$input['column'] : null;

        // Prevent moving a block into itself or its descendants
        if ($newParentId) {
            $check = $newParentId;
            while ($check) {
                if ($check === $blockId) {
                    $this->json(['error' => 'Cannot move block into itself'], 400);
                    return;
                }
                $parent = $this->db->fetch('SELECT parent_block_id FROM content_blocks WHERE id = ?', [$check]);
                $check = $parent ? $parent['parent_block_id'] : null;
            }
        }

        // Get next sort order in the target
        if ($newParentId) {
            $maxSort = $this->db->fetchColumn(
                'SELECT COALESCE(MAX(sort_order), 0) FROM content_blocks WHERE parent_block_id = ?',
                [$newParentId]
            );
        } else {
            $maxSort = $this->db->fetchColumn(
                'SELECT COALESCE(MAX(sort_order), 0) FROM content_blocks WHERE parent_type = ? AND parent_id = ? AND parent_block_id IS NULL AND zone = ?',
                [$block['parent_type'], $block['parent_id'], $block['zone']]
            );
        }

        // Update block
        $updateData = [
            'parent_block_id' => $newParentId,
            'sort_order'      => $maxSort + 1,
        ];

        // Update column in settings if provided
        if ($column !== null) {
            $settings = json_decode($block['settings'] ?? '{}', true) ?: [];
            $settings['column'] = $column;
            $updateData['settings'] = json_encode($settings);
        }

        $this->db->update('content_blocks', $updateData, 'id = ?', [$blockId]);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/editor-mode — Switch editor mode for a page or article.
     */
    public function updateEditorMode(): void
    {
        $parentType = $this->input('parent_type', 'page');
        $parentId   = $this->input('parent_id');
        $mode       = $this->input('editor_mode', 'structured');

        if (!in_array($parentType, ['page', 'article', 'template'])) {
            $this->json(['error' => 'Invalid parent type'], 400);
            return;
        }
        if (!in_array($mode, ['structured', 'freeform'])) {
            $this->json(['error' => 'Invalid editor mode'], 400);
            return;
        }

        $table = $this->parentTypeTable($parentType);
        $this->db->update($table, ['editor_mode' => $mode], 'id = ?', [$parentId]);
        $this->json(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function getDefaultBlockSettings(string $type): array
    {
        return match ($type) {
            'columns' => ['display' => 'grid', 'gridCols' => '1fr 1fr', 'gridGap' => '16px'],
            default   => [],
        };
    }

    private function getDefaultBlockContent(string $type): array
    {
        return match ($type) {
            'section', 'columns' => [],
            'text'        => ['html' => '<p>Enter your content here.</p>'],
            'heading'     => ['text' => 'Section Heading', 'level' => 2],
            'image'       => ['src' => '', 'alt' => ''],
            'gallery'     => ['images' => []],
            'html'        => ['raw' => ''],
            'event-list'  => ['count' => 5],
            'map'         => ['lat' => 53.3498, 'lng' => -6.2603, 'caption' => ''],
            'nav-menu'    => ['menu_id' => 0],
            'site-logo'   => [],
            'site-title'  => [],
            'php-include' => ['template' => ''],
            default       => ['html' => ''],
        };
    }

    private function deleteBlockAndChildren(int $blockId): void
    {
        $children = $this->db->fetchAll(
            'SELECT id FROM content_blocks WHERE parent_block_id = ?',
            [$blockId]
        );
        foreach ($children as $child) {
            $this->deleteBlockAndChildren((int)$child['id']);
        }
        $this->db->delete('content_blocks', 'id = ?', [$blockId]);
    }

    private function parentTypeTable(string $type): string
    {
        return match ($type) {
            'article'  => 'articles',
            'template' => 'page_templates',
            default    => 'pages',
        };
    }

    private function touchBlockParent(string $parentType, int|string $parentId): void
    {
        $table = $this->parentTypeTable($parentType);
        $this->db->update($table, ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$parentId]);
    }
}
