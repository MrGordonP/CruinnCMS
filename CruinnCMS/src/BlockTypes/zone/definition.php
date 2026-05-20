<?php
/**
 * Zone Block Type
 *
 * Defines a zone slot in a template layout. Zone blocks are containers that
 * reference external zone canvas pages, allowing template layouts to compose
 * reusable structural elements (headers, footers, sidebars, etc.).
 *
 * Config schema:
 *   {
 *     "zone_name": "header",           // Name of this zone slot
 *     "canvas_page_id": 123,           // ID of zone canvas page to render here (optional)
 *     "fallback_content": "..."        // HTML to show if no canvas assigned (optional)
 *   }
 */

return [
    'label'       => 'Zone',
    'description' => 'Template zone slot (header, footer, sidebar, main, etc.)',
    'icon'        => '📦',
    'category'    => 'layout',

    /**
     * Render function - called during public page render and editor preview.
     *
     * @param array    $config  Block configuration (zone_name, canvas_page_id, etc.)
     * @param Database $db      Database instance
     * @param array    $context Render context (page data, etc.)
     * @return string  Rendered HTML
     */
    'render' => function (array $config, \Cruinn\Database $db, array $context = []): string {
        $zoneName = $config['zone_name'] ?? 'main';
        $canvasPageId = !empty($config['canvas_page_id']) ? (int) $config['canvas_page_id'] : null;

        // Zone rendering happens at the template level - individual zone blocks
        // are just slot markers. The actual rendering is handled by CruinnRenderService
        // when building a page with a template.
        //
        // During standalone render (if this block is somehow rendered directly),
        // show a placeholder.

        if ($canvasPageId && $canvasPageId > 0) {
            $cruinn = new \Cruinn\Services\CruinnRenderService();
            if ($cruinn->hasPublished($canvasPageId)) {
                return $cruinn->buildHtml($canvasPageId);
            }
        }

        // Fallback content if configured
        if (!empty($config['fallback_content'])) {
            return $config['fallback_content'];
        }

        // Empty placeholder for unpopulated zones
        return '';
    },

    /**
     * Editor-specific rendering - how the zone appears on the template canvas.
     * Shows zone name and canvas assignment status.
     */
    'editorRender' => function (array $config, \Cruinn\Database $db): string {
        $zoneName = htmlspecialchars($config['zone_name'] ?? 'main', ENT_QUOTES, 'UTF-8');
        $canvasPageId = !empty($config['canvas_page_id']) ? (int) $config['canvas_page_id'] : null;

        $canvasTitle = 'Not assigned';
        if ($canvasPageId) {
            $canvas = $db->fetch('SELECT title FROM pages_index WHERE id = ? LIMIT 1', [$canvasPageId]);
            $canvasTitle = $canvas ? htmlspecialchars($canvas['title'], ENT_QUOTES, 'UTF-8') : 'Canvas #' . $canvasPageId;
        }

        return sprintf(
            '<div class="zone-block-editor-placeholder">
                <div class="zone-block-name">Zone: %s</div>
                <div class="zone-block-canvas">%s</div>
            </div>',
            $zoneName,
            $canvasTitle
        );
    },

    /**
     * Default configuration for new zone blocks.
     */
    'defaultConfig' => [
        'zone_name'       => 'main',
        'canvas_page_id'  => null,
        'fallback_content' => '',
    ],

    /**
     * Validation rules for zone configuration.
     */
    'validate' => function (array $config): array {
        $errors = [];

        if (empty($config['zone_name'])) {
            $errors[] = 'Zone name is required';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $config['zone_name'])) {
            $errors[] = 'Zone name must contain only lowercase letters, numbers, hyphens, and underscores';
        }

        if (isset($config['canvas_page_id']) && $config['canvas_page_id'] !== null) {
            if (!is_int($config['canvas_page_id']) && !ctype_digit((string) $config['canvas_page_id'])) {
                $errors[] = 'Canvas page ID must be an integer';
            }
        }

        return $errors;
    },
];
