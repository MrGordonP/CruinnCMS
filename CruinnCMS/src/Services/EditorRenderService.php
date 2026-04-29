<?php
/**
 * CruinnCMS — Editor Render Service
 *
 * Single source of truth for rendering a flat block list into the editor
 * canvas HTML and CSS. Called from any controller that opens the Cruinn
 * block editor — platform, instance admin, or any future entry point.
 *
 * All block types are rendered through a single unified path. The HTML tag
 * is determined by _tag in block_config (for imported blocks) or by the
 * BlockRegistry default tag for the block type.
 *
 * Public API:
 *   buildCanvasHtml(array $flat, Database $db): string
 *   buildCanvasCss(array $flat): string
 */

namespace Cruinn\Services;

use Cruinn\Database;
use Cruinn\BlockTypes\BlockRegistry;

class EditorRenderService
{
    // Block types that carry structural metadata only — never rendered to canvas.
    private const DOC_TYPES = ['doc-html', 'doc-head', 'doc-body'];

    // Block types rendered as non-editable chips in the editor canvas.
    private const CHIP_TYPES = ['php-code'];

    /**
     * Build the full editor canvas HTML from a flat block list.
     *
     * Sets BlockRegistry edit mode on for the duration of the render so that
     * dynamic block types (e.g. php-include) annotate child elements with
     * data-phpi-el / data-phpi-classes attributes for the editor.
     */
    public function buildCanvasHtml(array $flat, Database $db): string
    {
        $byId       = [];
        $childrenOf = [];
        foreach ($flat as $row) {
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        BlockRegistry::setEditMode(true);
        try {
            $result = $this->renderTree('__root', $byId, $childrenOf, $db);
        } finally {
            BlockRegistry::setEditMode(false);
        }

        return $result;
    }

    /**
     * Build a CSS stylesheet from css_props values stored in the block list.
     * Produces: #block_id { property: value; ... }
     * Plus @media blocks for tablet (≤ 1023px) and mobile (≤ 599px) overrides.
     */
    public function buildCanvasCss(array $flat): string
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

            // Tablet overrides
            if (!empty($row['css_props_tablet'])) {
                $props = json_decode($row['css_props_tablet'], true);
                if (is_array($props) && !empty($props)) {
                    $rules = $this->buildRules($props);
                    if ($rules !== '') { $tabletRules .= "  #{$id} {\n"; foreach (explode("\n", rtrim($rules)) as $r) { $tabletRules .= "  {$r}\n"; } $tabletRules .= "  }\n"; }
                }
            }

            // Mobile overrides
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

        return $css;
    }

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

    // ── Private rendering helpers ─────────────────────────────────────

    private function renderTree(
        string   $parentKey,
        array    $byId,
        array    $childrenOf,
        Database $db,
        array    &$visited = []
    ): string {
        if (empty($childrenOf[$parentKey])) {
            return '';
        }
        if (isset($visited[$parentKey])) {
            return '<!-- cycle detected at ' . htmlspecialchars($parentKey, ENT_QUOTES, 'UTF-8') . ' -->';
        }
        $visited[$parentKey] = true;

        $html = '';
        foreach ($childrenOf[$parentKey] as $blockId) {
            $row = $byId[$blockId];

            if (in_array($row['block_type'], self::DOC_TYPES, true)) {
                continue;
            }

            // ── php-code blocks ─────────────────────────────────────────
            if (in_array($row['block_type'], self::CHIP_TYPES, true)) {
                $id   = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');
                $type = htmlspecialchars($row['block_type'], ENT_QUOTES, 'UTF-8');
                $cfg  = json_decode($row['block_config'] ?? '{}', true) ?: [];
                $phpSnippet = $cfg['_php'] ?? '';

                $configAttr = '';
                if (!empty($row['block_config'])) {
                    $configAttr = ' data-block-config=\'' . htmlspecialchars($row['block_config'], ENT_QUOTES, 'UTF-8') . '\'';
                }

                // Echo expression (php echo tag) → render as readable label
                $echoOpen  = '<' . '?=';
                $phpClose  = '?' . '>';
                $trimmed = trim($phpSnippet);
                if (str_starts_with($trimmed, $echoOpen) && str_ends_with($trimmed, $phpClose)) {
                    $expr = trim(substr($trimmed, 3, -2));
                    $html .= '<span id="' . $id . '" data-block data-block-type="' . $type . '"'
                           . $configAttr
                           . ' contenteditable="false" class="cruinn-php-expr">'
                           . htmlspecialchars($expr, ENT_QUOTES, 'UTF-8')
                           . "</span>\n";
                } else {
                    // Control-flow or multi-line PHP → opaque chip
                    $firstLine = strtok(trim($phpSnippet), "\n");
                    $label = mb_strlen($firstLine) > 60
                        ? mb_substr($firstLine, 0, 57) . '...'
                        : $firstLine;
                    $html .= '<div id="' . $id . '" data-block data-block-type="' . $type . '"'
                           . $configAttr
                           . ' contenteditable="false" class="cruinn-php-chip">'
                           . '<span class="cruinn-php-chip-label">&lt;?php&gt;</span> '
                           . '<code class="cruinn-php-chip-code">'
                           . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                           . '</code>'
                           . "</div>\n";
                }
                continue;
            }

            // ── Unified rendering for all block types ──────────────────
            $cfg  = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $type = htmlspecialchars($row['block_type'], ENT_QUOTES, 'UTF-8');
            $id   = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');

            // Use _tag from config (imported blocks) or registry default
            $tag = $cfg['_tag'] ?? BlockRegistry::getTag($row['block_type']);

            // Tags whose content model forbids block-level children — if rendered as-is
            // the browser auto-closes them before any nested block element, leaving the
            // [data-block] element empty in the canvas.  Coerce to div; the original tag
            // is already stored in block_config._tag and applied correctly on publish.
            $restrictedTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                               'dt', 'figcaption', 'caption', 'pre'];
            // Tags that must not execute or render raw in the canvas.
            $inertTags = ['script', 'style', 'noscript', 'html', 'head', 'body'];

            if (in_array($tag, $inertTags, true)) {
                $tag = 'div';
                $extraAttrs = ' data-editor-tag="' . htmlspecialchars($cfg['_tag'], ENT_QUOTES, 'UTF-8') . '"';
            } elseif (in_array($tag, $restrictedTags, true)) {
                $tag = 'div';
                $extraAttrs = '';
            } else {
                $extraAttrs = '';
            }

            // CSS class from css_props._class
            $cssProps = json_decode($row['css_props'] ?? '{}', true) ?: [];
            $cssClass = $cssProps['_class'] ?? '';
            if ($cssClass !== '') {
                $extraAttrs .= ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"';
            }

            // Emit original HTML attributes from imported blocks
            foreach ($cfg['_attrs'] ?? [] as $k => $v) {
                if ($k === 'id' || $k === 'class') { continue; }
                $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                             . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }

            if (!empty($row['block_config'])) {
                $extraAttrs .= ' data-block-config=\'' . htmlspecialchars($row['block_config'], ENT_QUOTES, 'UTF-8') . '\'';
            }
            if ($row['block_type'] === 'nav-menu' && isset($cfg['menu_id'])) {
                $extraAttrs .= ' data-menu-id="' . (int) $cfg['menu_id'] . '"';
            }
            if (!empty($row['css_props'])) {
                $extraAttrs .= ' data-css-props=\'' . htmlspecialchars($row['css_props'], ENT_QUOTES, 'UTF-8') . '\'';
            }
            if (!empty($row['css_props_tablet'])) {
                $extraAttrs .= ' data-css-props-tablet=\'' . htmlspecialchars($row['css_props_tablet'], ENT_QUOTES, 'UTF-8') . '\'';
            }
            if (!empty($row['css_props_mobile'])) {
                $extraAttrs .= ' data-css-props-mobile=\'' . htmlspecialchars($row['css_props_mobile'], ENT_QUOTES, 'UTF-8') . '\'';
            }

            $innerContent  = BlockRegistry::isDynamic($row['block_type'])
                ? BlockRegistry::renderDynamic($row, $db)
                : ($row['inner_html'] ?? '');
            // Strip any DOCTYPE declarations from inner content — safe for canvas rendering.
            $innerContent  = preg_replace('/<!DOCTYPE[^>]*>/i', '', $innerContent);
            $innerContent .= $this->renderTree($blockId, $byId, $childrenOf, $db, $visited);

            $html .= "<{$tag} id=\"{$id}\" data-block data-block-type=\"{$type}\"{$extraAttrs}>"
                   . $innerContent
                   . "</{$tag}>\n";
        }

        return $html;
    }
}
