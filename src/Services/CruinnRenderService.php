<?php
/**
 * Cruinn CMS — Render Service
 *
 * Used by PageController to render published Cruinn pages on the public site.
 * Reads from cruinn_blocks (published table only — never from draft).
 */

namespace Cruinn\Services;

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

class CruinnRenderService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check whether this page has any published Cruinn blocks.
     */
    public function hasPublished(int $pageId): bool
    {
        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM cruinn_blocks WHERE page_id = ?',
            [$pageId]
        );
        return $count > 0;
    }

    /**
     * Build the full page HTML from published cruinn_blocks.
     * Dynamic blocks have their content server-rendered here.
     */
    public function buildHtml(int $pageId): string
    {
        $flat = $this->db->fetchAll(
            'SELECT * FROM cruinn_blocks
              WHERE page_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$pageId]
        );

        $byId       = [];
        $childrenOf = [];
        foreach ($flat as $row) {
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        return $this->renderTree('__root', $byId, $childrenOf);
    }

    /**
     * Build HTML + CSS for a named global zone ('header' or 'footer').
     * Looks up the reserved page by slug (_header / _footer), reads its published
     * cruinn_blocks, and returns ['page_id', 'html', 'css'].
     * Returns null when the zone page does not exist or has no published content.
     * Results are statically cached for the lifetime of the request.
     */
    public function buildZone(string $zone): ?array
    {
        static $cache = [];
        if (array_key_exists($zone, $cache)) {
            return $cache[$zone];
        }

        $page = $this->db->fetch(
            'SELECT id FROM pages WHERE slug = ? LIMIT 1',
            ['_' . $zone]
        );
        if (!$page) {
            return $cache[$zone] = null;
        }

        $pageId = (int) $page['id'];
        if (!$this->hasPublished($pageId)) {
            return $cache[$zone] = null;
        }

        return $cache[$zone] = [
            'page_id' => $pageId,
            'html'    => $this->buildHtml($pageId),
            'css'     => $this->buildCss($pageId),
        ];
    }

    /**
     * Build the CSS stylesheet from published cruinn_blocks css_props.
     * Returns a string of #id { ... } rules to be injected as a <style> tag.
     */
    public function buildCss(int $pageId): string
    {
        $flat = $this->db->fetchAll(
            'SELECT block_id, block_type, block_config, css_props FROM cruinn_blocks WHERE page_id = ?',
            [$pageId]
        );

        $css = '';
        foreach ($flat as $row) {
            if (empty($row['css_props'])) {
                continue;
            }
            $props = json_decode($row['css_props'], true);
            if (!is_array($props) || empty($props)) {
                continue;
            }
            $id    = htmlspecialchars($row['block_id'], ENT_QUOTES, 'UTF-8');
            $rules = '';
            foreach ($props as $property => $value) {
                $property = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $property);
                $value    = str_replace(['{', '}', ';', '<', '>'], '', (string) $value);
                if ($property !== '' && $value !== '') {
                    $rules .= "  {$property}: {$value};\n";
                }
            }
            if ($rules !== '') {
                $css .= "#{$id} {\n{$rules}}\n";
            }
        }

        // Emit child-element styles for php-include blocks.
        // Stored as block_config.childStyles: { ".class-name": { "property": "value" } }
        // Rendered as: #blockId .class-name { property: value; }
        foreach ($flat as $row) {
            if ($row['block_type'] !== 'php-include') {
                continue;
            }
            if (empty($row['block_config'])) {
                continue;
            }
            $cfg         = json_decode($row['block_config'], true) ?? [];
            $childStyles = $cfg['childStyles'] ?? [];
            if (empty($childStyles) || !is_array($childStyles)) {
                continue;
            }
            $id = htmlspecialchars($row['block_id'], ENT_QUOTES, 'UTF-8');
            foreach ($childStyles as $selector => $props) {
                $selector = preg_replace('/[^a-zA-Z0-9\s\-_\.\:#\[\]="]/', '', (string) $selector);
                if ($selector === '' || !is_array($props)) {
                    continue;
                }
                $rules = '';
                foreach ($props as $property => $value) {
                    $property = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $property);
                    $value    = str_replace(['{', '}', ';', '<', '>'], '', (string) $value);
                    if ($property !== '' && $value !== '') {
                        $rules .= "  {$property}: {$value};\n";
                    }
                }
                if ($rules !== '') {
                    $css .= "#{$id} {$selector} {\n{$rules}}\n";
                }
            }
        }

        return $css;
    }

    // ── Private helpers ────────────────────────────────────────────

    private function renderTree(string $parentKey, array $byId, array $childrenOf): string
    {
        if (empty($childrenOf[$parentKey])) {
            return '';
        }

        $html = '';
        foreach ($childrenOf[$parentKey] as $blockId) {
            $row  = $byId[$blockId];
            $cfg  = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $tag  = $cfg['_tag'] ?? $this->tagForType($row['block_type']);
            $type = htmlspecialchars($row['block_type'], ENT_QUOTES, 'UTF-8');
            $id   = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');

            $extraAttrs = '';
            // Restore original HTML attributes from imported blocks
            foreach ($cfg['_attrs'] ?? [] as $k => $v) {
                if ($k === 'id') { continue; }
                $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                             . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }
            if ($row['block_type'] === 'nav-menu' && isset($cfg['menu_id'])) {
                $extraAttrs .= ' data-menu-id="' . (int) $cfg['menu_id'] . '"';
            }

            $isDynamic    = $this->isDynamicType($row['block_type']);
            $innerContent = $isDynamic
                ? $this->renderDynamicBlock($row)
                : ($row['inner_html'] ?? '');

            $innerContent .= $this->renderTree($blockId, $byId, $childrenOf);

            $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\"{$extraAttrs}>"
                   . $innerContent
                   . "</{$tag}>\n";
        }

        return $html;
    }

    /**
     * Merge a template canvas with a page's own Cruinn blocks.
     *
     * Zone blocks in the template are replaced by the page blocks whose
     * block_config.zone_name matches (defaulting to 'main').
     * The zone block element itself becomes the container — so its CSS
     * (padding, background, etc.) wraps the injected content.
     * Returns ['html' => ..., 'css' => ...].
     */
    public function buildWithTemplate(int $pageId, int $canvasPageId): array
    {
        // Template canvas blocks
        $tplFlat = $this->db->fetchAll(
            'SELECT * FROM cruinn_blocks
              WHERE page_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$canvasPageId]
        );

        // Page content blocks
        $pageFlat = $this->db->fetchAll(
            'SELECT * FROM cruinn_blocks
              WHERE page_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$pageId]
        );

        // Index template blocks
        $tplById       = [];
        $tplChildrenOf = [];
        foreach ($tplFlat as $row) {
            $tplById[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $tplChildrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        // Index page blocks
        $pageById       = [];
        $pageChildrenOf = [];
        $pageByZone     = [];   // top-level page blocks grouped by zone_name
        foreach ($pageFlat as $row) {
            $pageById[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $pageChildrenOf[$pid ?? '__root'][] = $row['block_id'];
            // Only index root-level page blocks by zone
            if ($pid === null) {
                $cfg      = json_decode($row['block_config'] ?? '{}', true) ?: [];
                $zoneName = $cfg['zone_name'] ?? 'main';
                $pageByZone[$zoneName][] = $row;
            }
        }

        $html = $this->renderTemplateTree(
            '__root',
            $tplById, $tplChildrenOf,
            $pageById, $pageChildrenOf, $pageByZone
        );

        // Emit CSS for both the template canvas blocks and the page blocks
        $css = $this->buildCss($canvasPageId) . $this->buildCss($pageId);

        return ['html' => $html, 'css' => $css];
    }

    /**
     * Render the template canvas tree.
     * Zone blocks are expanded: the zone element becomes the wrapper and the
     * matching page content fills the inside.
     */
    private function renderTemplateTree(
        string $parentKey,
        array $tplById, array $tplChildrenOf,
        array $pageById, array $pageChildrenOf, array $pageByZone
    ): string {
        if (empty($tplChildrenOf[$parentKey])) {
            return '';
        }

        $html = '';
        foreach ($tplChildrenOf[$parentKey] as $blockId) {
            $row  = $tplById[$blockId];
            $id   = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');
            $tCfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $tag  = $tCfg['_tag'] ?? $this->tagForType($row['block_type']);
            $type = htmlspecialchars($row['block_type'], ENT_QUOTES, 'UTF-8');

            if ($row['block_type'] === 'zone') {
                // Zone block: render as a container, filled with matching page content
                $zoneName = htmlspecialchars($tCfg['zone_name'] ?? 'main', ENT_QUOTES, 'UTF-8');
                $inner    = '';
                foreach ($pageByZone[$tCfg['zone_name'] ?? 'main'] ?? [] as $pb) {
                    $pbCfg    = json_decode($pb['block_config'] ?? '{}', true) ?: [];
                    $pbTag    = $pbCfg['_tag'] ?? $this->tagForType($pb['block_type']);
                    $pbType   = htmlspecialchars($pb['block_type'], ENT_QUOTES, 'UTF-8');
                    $pbId     = htmlspecialchars($pb['block_id'], ENT_QUOTES, 'UTF-8');
                    $pbInner  = $this->isDynamicType($pb['block_type'])
                        ? $this->renderDynamicBlock($pb)
                        : ($pb['inner_html'] ?? '');
                    $pbInner .= $this->renderTree($pb['block_id'], $pageById, $pageChildrenOf);
                    $inner   .= "<{$pbTag} id=\"{$pbId}\" data-block data-block-type=\"{$pbType}\">{$pbInner}</{$pbTag}>\n";
                }
                $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\" data-zone-name=\"{$zoneName}\">";
                $html .= $inner;
                $html .= "</{$tag}>\n";
            } else {
                // Regular template block — render with its template children
                $extraAttrs = '';
                foreach ($tCfg['_attrs'] ?? [] as $k => $v) {
                    if ($k === 'id') { continue; }
                    $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                                 . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($row['block_type'] === 'nav-menu' && isset($tCfg['menu_id'])) {
                    $extraAttrs .= ' data-menu-id="' . (int) $tCfg['menu_id'] . '"';
                }
                $isDynamic = $this->isDynamicType($row['block_type']);
                $inner     = $isDynamic
                    ? $this->renderDynamicBlock($row)
                    : ($row['inner_html'] ?? '');
                $inner .= $this->renderTemplateTree(
                    $blockId,
                    $tplById, $tplChildrenOf,
                    $pageById, $pageChildrenOf, $pageByZone
                );
                $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\"{$extraAttrs}>";
                $html .= $inner;
                $html .= "</{$tag}>\n";
            }
        }

        return $html;
    }

    private function isDynamicType(string $type): bool
    {
        return BlockRegistry::isDynamic($type);
    }

    private function renderDynamicBlock(array $block): string
    {
        return BlockRegistry::renderDynamic($block, $this->db);
    }

    private function tagForType(string $type): string
    {
        return BlockRegistry::getTag($type);
    }
}
