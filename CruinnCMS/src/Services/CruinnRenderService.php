<?php
/**
 * Cruinn CMS — Render Service
 *
 * Used by PageController to render published Cruinn pages on the public site.
 * Reads from pages (published table only — never from draft).
 */

namespace Cruinn\Services;

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

class CruinnRenderService
{
    private Database $db;
    private array $context = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Set a render context (e.g. current article, articles list).
     * Context is passed to every dynamic block renderer.
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Check whether this page has any published Cruinn blocks.
     */
    public function hasPublished(int $pageId): bool
    {
        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM pages WHERE page_id = ?',
            [$pageId]
        );
        return $count > 0;
    }

    /**
     * Check whether a template has published layout blocks (via template_id).
     */
    public function hasPublishedTemplate(int $templateId): bool
    {
        try {
            $count = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM pages WHERE template_id = ?',
                [$templateId]
            );
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Build the full page HTML from published pages.
     * Dynamic blocks have their content server-rendered here.
     */
    public function buildHtml(int $pageId): string
    {
        $flat = $this->db->fetchAll(
            'SELECT * FROM pages
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
     * Build HTML for blocks within a specific zone.
     * Finds zone blocks matching the zone name, then renders their children.
     * Returns empty string if no matching zone found.
     */
    public function buildZoneHtml(int $pageId, string $zoneName): string
    {
        $flat = $this->db->fetchAll(
            'SELECT * FROM pages
              WHERE page_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$pageId]
        );

        $byId       = [];
        $childrenOf = [];
        $zoneBlockIds = [];

        foreach ($flat as $row) {
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];

            // Track root-level zone blocks matching the target zone name
            if ($row['block_type'] === 'zone' && $pid === null) {
                $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
                if (($cfg['zone_name'] ?? 'main') === $zoneName) {
                    $zoneBlockIds[] = $row['block_id'];
                }
            }
        }

        if (empty($zoneBlockIds)) {
            return '';
        }

        // Render all matching zone blocks with their wrappers and children
        // Zone wrappers carry important layout properties (width, height, etc.)
        $html = '';
        foreach ($zoneBlockIds as $zoneId) {
            $html .= $this->renderTree($zoneId, $byId, $childrenOf);
        }

        return $html;
    }

    /**
     * Build the CSS stylesheet for template layout blocks (queries by template_id).
     */
    public function buildCssForTemplate(int $templateId): string
    {
        try {
            $flat = $this->db->fetchAll(
                'SELECT block_id, block_type, css_props, css_props_tablet, css_props_mobile, block_config
                   FROM pages WHERE template_id = ?',
                [$templateId]
            );
        } catch (\Throwable $e) {
            $flat = [];
        }
        if (empty($flat)) {
            // Migration safety: fall back to canvas_page_id
            $tpl = $this->db->fetch('SELECT canvas_page_id FROM page_templates WHERE id = ? LIMIT 1', [$templateId]);
            $cpid = $tpl ? (int)($tpl['canvas_page_id'] ?? 0) : 0;
            if ($cpid > 0) {
                return $this->buildCss($cpid);
            }
            return '';
        }
        // Reuse buildCss logic by swapping the fetched flat data.
        // We build a temporary anonymous page object using the same rendering path.
        return $this->buildCssFromFlat($flat);
    }

    /**
     * Build the CSS stylesheet from published pages css_props.
     * Returns a string of #id { ... } rules (desktop) plus @media blocks for
     * tablet (600px – 1023px) and mobile (≤ 599px) overrides.
     */
    public function buildCss(int $pageId): string
    {
        $flat = $this->db->fetchAll(
            'SELECT block_id, block_type, css_props, css_props_tablet, css_props_mobile, block_config
               FROM pages WHERE page_id = ?',
            [$pageId]
        );
        return $this->buildCssFromFlat($flat);
    }

    private function buildCssFromFlat(array $flat): string
    {
        $css         = '';
        $tabletRules = '';
        $mobileRules = '';

        foreach ($flat as $row) {
            $id = htmlspecialchars($row['block_id'], ENT_QUOTES, 'UTF-8');

            // Desktop (base)
            if (!empty($row['css_props'])) {
                $props = json_decode($row['css_props'], true);
                if (is_array($props) && !empty($props)) {
                    $rules = $this->buildRules($props);
                    if ($rules !== '') { $css .= "#{$id} {\n{$rules}}\n"; }
                }
            }

            // Tablet overrides (600px – 1023px)
            if (!empty($row['css_props_tablet'])) {
                $props = json_decode($row['css_props_tablet'], true);
                if (is_array($props) && !empty($props)) {
                    $rules = $this->buildRules($props);
                    if ($rules !== '') { $tabletRules .= "  #{$id} {\n"; foreach (explode("\n", rtrim($rules)) as $r) { $tabletRules .= "  {$r}\n"; } $tabletRules .= "  }\n"; }
                }
            }

            // Mobile overrides (≤ 599px)
            if (!empty($row['css_props_mobile'])) {
                $props = json_decode($row['css_props_mobile'], true);
                if (is_array($props) && !empty($props)) {
                    $rules = $this->buildRules($props);
                    if ($rules !== '') { $mobileRules .= "  #{$id} {\n"; foreach (explode("\n", rtrim($rules)) as $r) { $mobileRules .= "  {$r}\n"; } $mobileRules .= "  }\n"; }
                }
            }
        }

        if ($tabletRules !== '') {
            $css .= "@media (max-width: 1023px) {\n{$tabletRules}}\n";
        }
        if ($mobileRules !== '') {
            $css .= "@media (max-width: 599px) {\n{$mobileRules}}\n";
        }

        // Emit child-element styles for php-include/dynamic-include blocks.
        // Stored as block_config.childStyles: { ".class-name": { "property": "value" } }
        // Rendered as: #blockId .class-name { property: value; }
        foreach ($flat as $row) {
            if ($row['block_type'] !== 'php-include' && $row['block_type'] !== 'dynamic-include') {
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

    /**
     * Convert a css_props array to sanitised CSS declaration lines.
     * Skips internal keys (prefixed with '_').
     */
    private function buildRules(array $props): string
    {
        $rules = '';
        foreach ($props as $property => $value) {
            if (isset($property[0]) && $property[0] === '_') { continue; }
            $property = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $property);
            $value    = str_replace(['{', '}', ';', '<', '>'], '', (string) $value);
            if ($property !== '' && $value !== '') {
                $rules .= "  {$property}: {$value};\n";
            }
        }
        return $rules;
    }

    private function renderTree(string $parentKey, array $byId, array $childrenOf): string
    {
        if (empty($childrenOf[$parentKey])) {
            return '';
        }

        $html = '';
        foreach ($childrenOf[$parentKey] as $blockId) {
            $row  = $byId[$blockId];
            $cfg  = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $cssProps = json_decode($row['css_props'] ?? '{}', true) ?: [];
            $tag  = $cfg['_tag'] ?? $this->tagForType($row['block_type']);
            $type = htmlspecialchars($row['block_type'], ENT_QUOTES, 'UTF-8');
            $id   = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');

            $extraAttrs = '';
            // CSS class from css_props._class
            $cssClass = $cssProps['_class'] ?? '';
            if ($cssClass !== '') {
                $extraAttrs .= ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"';
            }
            // Restore original HTML attributes from imported blocks
            foreach ($cfg['_attrs'] ?? [] as $k => $v) {
                if ($k === 'id' || $k === 'class') { continue; }
                $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                             . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }
            if ($row['block_type'] === 'nav-menu' && isset($cfg['menu_id'])) {
                $extraAttrs .= ' data-menu-id="' . (int) $cfg['menu_id'] . '"';
            }
            $uiCollapse = (string) ($cfg['ui_collapse'] ?? '');
            if ($uiCollapse === 'tablet' || $uiCollapse === 'mobile') {
                $extraAttrs .= ' data-ui-collapse="' . $uiCollapse . '"';
                $uiLabel = trim((string) ($cfg['ui_collapse_label'] ?? ''));
                if ($uiLabel !== '') {
                    $extraAttrs .= ' data-ui-collapse-label="' . htmlspecialchars($uiLabel, ENT_QUOTES, 'UTF-8') . '"';
                }
                $uiAlign = (string) ($cfg['ui_collapse_align'] ?? '');
                if (in_array($uiAlign, ['left', 'right', 'center'], true)) {
                    $extraAttrs .= ' data-ui-collapse-align="' . $uiAlign . '"';
                }
            }

            $isDynamic    = $this->isDynamicType($row['block_type']);
            $innerContent = $isDynamic
                ? $this->renderDynamicBlock($row)
                : $this->resolveBinding($row, $cfg);

            $innerContent .= $this->renderTree($blockId, $byId, $childrenOf);

            $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\"{$extraAttrs}>"
                   . $innerContent
                   . "</{$tag}>\n";
        }

        return $html;
    }

    /**
     * Merge a template's layout with a page's content and all zone canvases.
     *
     * Template layout blocks are fetched via template_id (direct ownership).
     * Falls back to canvas_page_id if no template_id blocks exist (pre-012 instances).
     *
     * Zone canvas resolution (per zone block):
     *   1. Zone block's block_config.canvas_page_id (template-level zone assignment)
     *   2. Page-level zone_overrides JSON (page-specific override)
     *   3. Global zone canvas (canvas_type='zone' AND zone_name=?)
     *
     * @param int         $templateId page_templates.id
     * @param string      $pageZone   Zone slot to inject the page content into
     * @param int|null    $pageId     pages_index.id for block-mode pages
     * @param string|null $injectHtml Raw HTML for html-mode pages (used when $pageId is null)
     * @return array ['html' => string, 'css' => string]
     */
    public function buildWithTemplate(
        int     $templateId,
        string  $pageZone   = 'main',
        ?int    $pageId     = null,
        ?string $injectHtml = null
    ): array {
        $templateRow = $this->db->fetch(
            'SELECT canvas_page_id, layout_page_id FROM page_templates WHERE id = ? LIMIT 1',
            [$templateId]
        ) ?: [];

        // ── Fetch template layout blocks ─────────────────────────────
        $tplFlat = [];
        $layoutPageId = (int) ($templateRow['layout_page_id'] ?? 0);
        if ($layoutPageId > 0) {
            $tplFlat = $this->db->fetchAll(
                'SELECT * FROM pages
                  WHERE page_id = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$layoutPageId]
            );
        } else {
            try {
                $tplFlat = $this->db->fetchAll(
                    'SELECT * FROM pages
                      WHERE template_id = ?
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                    [$templateId]
                );
            } catch (\Throwable $e) {
                // Column doesn't exist yet (migration 012 not applied) — fall through to canvas_page_id
            }
        }

        // Migration safety: if no template_id blocks, fall back to canvas_page_id
        $canvasCssPageId = null;
        if (empty($tplFlat)) {
            $cpid = (int) ($templateRow['canvas_page_id'] ?? 0);
            if ($cpid > 0) {
                $tplFlat = $this->db->fetchAll(
                    'SELECT * FROM pages
                      WHERE page_id = ?
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                    [$cpid]
                );
                $canvasCssPageId = $cpid;
            }
        }

        $tplById       = [];
        $tplChildrenOf = [];
        foreach ($tplFlat as $row) {
            $tplById[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $tplChildrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        // ── Extract zone canvas map from template zone blocks ────────
        // Zone blocks declare which canvas to render via block_config.canvas_page_id
        $zoneCanvasMap = [];  // [zoneName => canvasPageId]
        $assignmentRows = $layoutPageId > 0
            ? $this->db->fetchAll(
                'SELECT block_type, block_config, parent_block_id FROM pages
                  WHERE template_id = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$templateId]
            )
            : $tplFlat;
        foreach ($assignmentRows as $row) {
            if (($row['block_type'] ?? null) === 'zone' && ($row['parent_block_id'] ?? null) === null) {
                $cfg      = json_decode($row['block_config'] ?? '{}', true) ?: [];
                $zoneName = $cfg['zone_name'] ?? null;
                if ($zoneName !== null && $zoneName !== $pageZone) {
                    // Priority 1: zone block's own canvas_page_id
                    if (!empty($cfg['canvas_page_id'])) {
                        $zoneCanvasMap[$zoneName] = (int) $cfg['canvas_page_id'];
                    }
                }
            }
        }

        // ── Apply page-level zone_overrides (higher priority) ────────
        if ($pageId !== null) {
            try {
                $row = $this->db->fetch(
                    'SELECT zone_overrides FROM pages_index WHERE id = ? LIMIT 1',
                    [$pageId]
                );
                if ($row && !empty($row['zone_overrides'])) {
                    $overrides = json_decode($row['zone_overrides'], true) ?: [];
                    foreach ($overrides as $zone => $canvasId) {
                        if ($canvasId !== null && (int) $canvasId > 0) {
                            $zoneCanvasMap[$zone] = (int) $canvasId;
                        }
                    }
                }
            } catch (\Throwable $e) { /* column may not exist yet */ }
        }

        // ── Fallback to global zone canvases ──────────────────────────
        // For any zone in the template that doesn't have a canvas assigned yet,
        // check if there's a global zone canvas (canvas_type='zone')
        foreach ($tplById as $row) {
            if ($row['block_type'] === 'zone' && ($row['parent_block_id'] ?? null) === null) {
                $cfg      = json_decode($row['block_config'] ?? '{}', true) ?: [];
                $zoneName = $cfg['zone_name'] ?? null;
                if ($zoneName !== null && $zoneName !== $pageZone && !isset($zoneCanvasMap[$zoneName])) {
                    try {
                        $global = $this->db->fetch(
                            "SELECT id FROM pages_index WHERE canvas_type = 'zone' AND zone_name = ? LIMIT 1",
                            [$zoneName]
                        );
                        if ($global) {
                            $zoneCanvasMap[$zoneName] = (int) $global['id'];
                        }
                    } catch (\Throwable $e) { /* column may not exist yet */ }
                }
            }
        }

        // ── Fetch and index page content blocks (block mode) ─────────
        $pageById       = [];
        $pageChildrenOf = [];
        $pageByZone     = [];

        if ($pageId !== null) {
            $pageFlat = $this->db->fetchAll(
                'SELECT * FROM pages
                  WHERE page_id = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$pageId]
            );
            foreach ($pageFlat as $row) {
                $pageById[$row['block_id']] = $row;
                $pid = $row['parent_block_id'] ?? null;
                $pageChildrenOf[$pid ?? '__root'][] = $row['block_id'];
                if ($pid === null) {
                    $cfg  = json_decode($row['block_config'] ?? '{}', true) ?: [];
                    $zone = $cfg['zone_name'] ?? $pageZone;
                    $pageByZone[$zone][] = $row;
                }
            }
        }

        // ── Fetch and index zone canvas blocks ───────────────────────
        $zoneCanvasBlocks = [];  // [zoneName => ['byId' => [...], 'childrenOf' => [...]]]
        foreach ($zoneCanvasMap as $zoneName => $canvasId) {
            $canvasId = (int) $canvasId;
            if ($canvasId <= 0) {
                continue;
            }
            $flat    = $this->db->fetchAll(
                'SELECT * FROM pages
                  WHERE page_id = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$canvasId]
            );
            $byId        = [];
            $childrenOf  = [];
            foreach ($flat as $row) {
                $byId[$row['block_id']] = $row;
                $pid = $row['parent_block_id'] ?? null;
                $childrenOf[$pid ?? '__root'][] = $row['block_id'];
            }
            $zoneCanvasBlocks[(string) $zoneName] = ['byId' => $byId, 'childrenOf' => $childrenOf];
        }

        // ── Render merged tree ───────────────────────────────────────
        $html = $this->renderTemplateTree(
            '__root',
            $tplById, $tplChildrenOf,
            $pageById, $pageChildrenOf, $pageByZone,
            $zoneCanvasBlocks,
            $pageZone,
            $injectHtml
        );

        // ── Merge CSS from all sources ───────────────────────────────
        if ($layoutPageId > 0) {
            // Layout-driven templates must use the layout page CSS as the base
            // so zone width constraints carry through to live rendering.
            $css = $this->buildCss($layoutPageId);
        } else {
            $css = $canvasCssPageId !== null
                ? $this->buildCss($canvasCssPageId)      // fallback path
                : $this->buildCssForTemplate($templateId); // normal path
        }

        if ($pageId !== null) {
            $css .= $this->buildCss($pageId);
        }
        foreach ($zoneCanvasMap as $canvasId) {
            $canvasId = (int) $canvasId;
            if ($canvasId > 0) {
                $css .= $this->buildCss($canvasId);
            }
        }

        return ['html' => $html, 'css' => $css];
    }

    /**
     * Render the merged template + page + zone canvas tree.
     *
     * When a zone block is encountered:
     *   - zone_name === $pageZone → inject page content blocks (or $injectHtml for html-mode)
     *   - any other zone_name     → inject from $zoneCanvasBlocks[zoneName] if present
     */
    private function renderTemplateTree(
        string  $parentKey,
        array   $tplById,
        array   $tplChildrenOf,
        array   $pageById,
        array   $pageChildrenOf,
        array   $pageByZone,
        array   $zoneCanvasBlocks = [],   // [zoneName => ['byId' => [...], 'childrenOf' => [...]]]
        string  $pageZone         = 'main',
        ?string $injectHtml       = null
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
                $rawZone  = $tCfg['zone_name'] ?? 'main';
                $zoneName = htmlspecialchars($rawZone, ENT_QUOTES, 'UTF-8');
                $inner    = '';

                if ($rawZone === $pageZone) {
                    // ── Page zone: inject the page's own content ──
                    if ($injectHtml !== null) {
                        // html-mode: inject raw HTML directly
                        $inner = $injectHtml;
                    } else {
                        foreach ($pageByZone[$rawZone] ?? [] as $pb) {
                            $pbCfg      = json_decode($pb['block_config'] ?? '{}', true) ?: [];
                            $pbTag      = $pbCfg['_tag'] ?? $this->tagForType($pb['block_type']);
                            $pbType     = htmlspecialchars($pb['block_type'], ENT_QUOTES, 'UTF-8');
                            $pbId       = htmlspecialchars($pb['block_id'], ENT_QUOTES, 'UTF-8');
                            $pbAttrs    = '';
                            $pbCollapse = (string) ($pbCfg['ui_collapse'] ?? '');
                            if ($pbCollapse === 'tablet' || $pbCollapse === 'mobile') {
                                $pbAttrs .= ' data-ui-collapse="' . $pbCollapse . '"';
                            }
                            $pbInner  = $this->isDynamicType($pb['block_type'])
                                ? $this->renderDynamicBlock($pb)
                                : ($pb['inner_html'] ?? '');
                            $pbInner .= $this->renderTree($pb['block_id'], $pageById, $pageChildrenOf);
                            $inner   .= "<{$pbTag} id=\"{$pbId}\" data-block data-block-type=\"{$pbType}\"{$pbAttrs}>{$pbInner}</{$pbTag}>\n";
                        }
                    }
                } elseif (isset($zoneCanvasBlocks[$rawZone])) {
                    // ── Non-page zone: inject zone canvas blocks ──
                    $zc = $zoneCanvasBlocks[$rawZone];
                    $inner = $this->renderTree('__root', $zc['byId'], $zc['childrenOf']);
                }

                $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\" data-zone-name=\"{$zoneName}\">";
                $html .= $inner;
                $html .= "</{$tag}>\n";
            } else {
                // Regular template block
                $extraAttrs = '';
                foreach ($tCfg['_attrs'] ?? [] as $k => $v) {
                    if ($k === 'id') { continue; }
                    $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                                 . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($row['block_type'] === 'nav-menu' && isset($tCfg['menu_id'])) {
                    $extraAttrs .= ' data-menu-id="' . (int) $tCfg['menu_id'] . '"';
                }
                $uiCollapse = (string) ($tCfg['ui_collapse'] ?? '');
                if ($uiCollapse === 'tablet' || $uiCollapse === 'mobile') {
                    $extraAttrs .= ' data-ui-collapse="' . $uiCollapse . '"';
                }
                $isDynamic = $this->isDynamicType($row['block_type']);
                $inner     = $isDynamic
                    ? $this->renderDynamicBlock($row)
                    : ($row['inner_html'] ?? '');
                $inner .= $this->renderTemplateTree(
                    $blockId,
                    $tplById, $tplChildrenOf,
                    $pageById, $pageChildrenOf, $pageByZone,
                    $zoneCanvasBlocks, $pageZone, $injectHtml
                );
                $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\"{$extraAttrs}>";
                $html .= $inner;
                $html .= "</{$tag}>\n";
            }
        }

        return $html;
    }

    /**
     * Render a content template page with a given context.
     * Used by modules (blog, events, etc.) to render their content templates.
     * Falls back to empty string if no blocks published for the page.
     */
    public function buildWithContext(int $pageId, array $context): string
    {
        $this->context = $context;
        return $this->buildHtml($pageId);
    }

    /**
     * Same as buildWithContext but using a named template slug.
     * Looks up the page_templates record, fetches blocks via template_id,
     * falls back to canvas_page_id for pre-012 instances.
     * Returns null if no such template or no blocks found.
     */
    public function buildContentTemplate(string $slug, array $context): ?string
    {
        $tpl = $this->db->fetch(
            "SELECT id, canvas_page_id FROM page_templates pt WHERE pt.slug = ? AND pt.template_type = 'content' LIMIT 1",
            [$slug]
        );
        if (!$tpl) {
            return null;
        }
        $templateId = (int) $tpl['id'];
        if ($this->hasPublishedTemplate($templateId)) {
            $this->context = $context;
            $flat = $this->db->fetchAll(
                'SELECT * FROM pages WHERE template_id = ? ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$templateId]
            );
            $byId = [];
            $childrenOf = [];
            foreach ($flat as $row) {
                $byId[$row['block_id']] = $row;
                $pid = $row['parent_block_id'] ?? null;
                $childrenOf[$pid ?? '__root'][] = $row['block_id'];
            }
            return $this->renderTree('__root', $byId, $childrenOf);
        }
        // Fallback: canvas_page_id (pre-012 instances)
        $cpid = (int)($tpl['canvas_page_id'] ?? 0);
        if ($cpid > 0) {
            return $this->buildWithContext($cpid, $context);
        }
        return null;
    }

    /**
     * Resolve a block's content, substituting context field values when a bind is configured.
     *
     * block_config.bind is a map of slot → context key, e.g.:
     *   {"inner_html": "title", "src": "featured_image", "href": "url"}
     *
     * For inner_html: replaces the block's stored inner_html with the context value.
     * For image src:  the inner_html should contain an <img> — we patch the src attribute.
     * For href:       patches the href of the first <a> inside the block.
     */
    private function resolveBinding(array $row, array $cfg): string
    {
        $bind    = $cfg['bind'] ?? [];
        $inner   = $row['inner_html'] ?? '';

        if (empty($bind) || empty($this->context)) {
            return $inner;
        }

        // inner_html binding: direct content replacement
        if (!empty($bind['inner_html'])) {
            $value = $this->context[$bind['inner_html']] ?? null;
            if ($value !== null) {
                $inner = (string) $value;
            }
        }

        // src binding: patch src on first <img> inside the block
        if (!empty($bind['src'])) {
            $value = $this->context[$bind['src']] ?? null;
            if ($value !== null) {
                $src = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                if (str_contains($inner, '<img')) {
                    $inner = preg_replace('/(<img\b[^>]*)\bsrc="[^"]*"/', '$1 src="' . $src . '"', $inner);
                    if (!str_contains($inner, 'src=')) {
                        $inner = preg_replace('/(<img\b)/', '$1 src="' . $src . '"', $inner);
                    }
                } else {
                    // No img tag — synthesise one
                    $inner = '<img src="' . $src . '" alt="">';
                }
            }
        }

        // href binding: patch href on first <a> inside the block
        if (!empty($bind['href'])) {
            $value = $this->context[$bind['href']] ?? null;
            if ($value !== null) {
                $href = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $inner = preg_replace('/(<a\b[^>]*)\bhref="[^"]*"/', '$1 href="' . $href . '"', $inner, 1);
            }
        }

        return $inner;
    }

    private function isDynamicType(string $type): bool
    {
        return BlockRegistry::isDynamic($type);
    }

    private function renderDynamicBlock(array $block): string
    {
        return BlockRegistry::renderDynamic($block, $this->db, $this->context);
    }

    private function tagForType(string $type): string
    {
        return BlockRegistry::getTag($type);
    }
}
