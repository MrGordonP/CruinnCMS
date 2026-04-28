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
     * Build HTML + CSS for a named global zone ('header' or 'footer').
     * Looks up the reserved page by slug (_header / _footer), reads its published
     * pages, and returns ['page_id', 'html', 'css'].
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
            'SELECT id FROM pages_index WHERE slug = ? LIMIT 1',
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

        $css        = '';
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
            'SELECT * FROM pages
              WHERE page_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$canvasPageId]
        );

        // Page content blocks
        $pageFlat = $this->db->fetchAll(
            'SELECT * FROM pages
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
                    $pbAttrs  = '';
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
                    $pageById, $pageChildrenOf, $pageByZone
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
     * Looks up the page_templates record, finds its canvas_page_id, renders it.
     * Returns null if no such template or no canvas page.
     */
    public function buildContentTemplate(string $slug, array $context): ?string
    {
        $tpl = $this->db->fetch(
            "SELECT pt.canvas_page_id FROM page_templates pt WHERE pt.slug = ? AND pt.template_type = 'content' LIMIT 1",
            [$slug]
        );
        if (!$tpl || !$tpl['canvas_page_id']) {
            return null;
        }
        return $this->buildWithContext((int) $tpl['canvas_page_id'], $context);
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
