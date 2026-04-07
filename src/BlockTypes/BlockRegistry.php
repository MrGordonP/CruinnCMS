<?php
/**
 * Cruinn CMS — Block Type Registry
 *
 * Central registry for all block types.
 * Definition files live at src/BlockTypes/{slug}/definition.php.
 * Each file calls BlockRegistry::register() with a block definition array.
 *
 * Definition keys:
 *   slug      (string)   Block type identifier, e.g. 'text'
 *   label     (string)   Human-readable label, e.g. 'Text'
 *   tag       (string)   HTML element used when rendering on the live page, e.g. 'div'
 *   dynamic   (bool)     True if block content is server-rendered at request time
 *   container (bool)     True if block can hold child blocks (section / columns)
 *   isLayout  (bool)     True if block shows the Layout group in the properties panel
 *   renderer  (callable) Optional. For dynamic blocks: function(array $config, Database $db): string
 */

namespace Cruinn\BlockTypes;

use Cruinn\Database;

class BlockRegistry
{
    private static array $types    = [];
    private static bool  $loaded   = false;
    private static bool  $editMode = false;

    /**
     * Set/unset edit mode.
     * When true, dynamic block renderers may produce editor-annotated output
     * (e.g. data-phpi-el attributes on php-include children) instead of live HTML.
     */
    public static function setEditMode(bool $on): void
    {
        self::$editMode = $on;
    }

    public static function isEditMode(): bool
    {
        return self::$editMode;
    }

    /**
     * Register a block type definition.
     * Called from individual definition.php files.
     */
    public static function register(array $def): void
    {
        $slug = $def['slug'] ?? '';
        if ($slug !== '') {
            self::$types[$slug] = $def;
        }
    }

    /**
     * Get the full definition for a block type, or null if unknown.
     */
    public static function get(string $slug): ?array
    {
        self::load();
        return self::$types[$slug] ?? null;
    }

    /**
     * Return all registered block type definitions, indexed by slug.
     */
    public static function all(): array
    {
        self::load();
        return self::$types;
    }

    /**
     * Return the HTML tag to render for a given block type on the live page.
     * Defaults to 'div' for unknown types.
     */
    public static function getTag(string $slug): string
    {
        self::load();
        return self::$types[$slug]['tag'] ?? 'div';
    }

    /**
     * Return whether a block type requires server-side dynamic rendering.
     */
    public static function isDynamic(string $slug): bool
    {
        self::load();
        return !empty(self::$types[$slug]['dynamic']);
    }

    /**
     * Invoke the renderer callback for a dynamic block.
     * Returns an empty string if no renderer is registered for this type.
     *
     * @param array    $block  Full DB row for the block.
     * @param Database $db     Active database connection.
     */
    public static function renderDynamic(array $block, Database $db): string
    {
        self::load();
        $def = self::$types[$block['block_type']] ?? null;
        if (!$def || empty($def['renderer'])) {
            return '';
        }
        $config = !empty($block['block_config'])
            ? (json_decode($block['block_config'], true) ?? [])
            : [];
        return ($def['renderer'])($config, $db);
    }

    /**
     * Discover and load all definition.php files on first use.
     */
    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        // __DIR__ = src/BlockTypes  →  sub-dirs hold the individual type definitions
        foreach (glob(__DIR__ . '/*/definition.php') as $file) {
            require $file;
        }
    }
}
