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
    private static array $types     = [];
    private static bool  $loaded    = false;
    private static bool  $dbChecked = false;
    private static bool  $editMode  = false;

    /** Keyed by slug: 'discovered' | 'active' | 'offline' */
    private static array $statuses  = [];

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
     * @param array    $block    Full DB row for the block.
     * @param Database $db       Active database connection.
     * @param array    $context  Optional render context (e.g. ['article' => $row]).
     */
    public static function renderDynamic(array $block, Database $db, array $context = []): string
    {
        self::load();
        $def = self::$types[$block['block_type']] ?? null;
        if (!$def || empty($def['renderer'])) {
            return '';
        }
        $config = !empty($block['block_config'])
            ? (json_decode($block['block_config'], true) ?? [])
            : [];
        return ($def['renderer'])($config, $db, $context);
    }

    /**
     * Return the activation status of a block type for this instance.
     * Returns 'discovered' for types on disk but not yet in the DB.
     */
    public static function statusOf(string $slug): string
    {
        self::load();
        return self::$statuses[$slug] ?? 'discovered';
    }

    /**
     * Return true if the named block type is active for this instance.
     */
    public static function isActive(string $slug): bool
    {
        return self::statusOf($slug) === 'active';
    }

    /**
     * Return all slugs discovered on disk (active + discovered + offline).
     * Used by the ACP browse screen.
     */
    public static function allDiscovered(): array
    {
        self::load();
        return array_keys(self::$statuses);
    }

    /**
     * Discover block types on disk and load active ones.
     *
     * On first call:
     *  1. Scan all definition.php files to discover available slugs.
     *  2. Load DB state from block_type_config.
     *  3. Require definition.php only for active slugs.
     *     (Falls back to loading all if the table doesn't exist yet.)
     */
    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        // Step 1: discover all available slugs from the filesystem.
        $available = [];
        foreach (glob(__DIR__ . '/*/definition.php') as $file) {
            $slug = basename(dirname($file));
            $available[$slug] = $file;
        }

        // Step 2: load activation state from the DB.
        self::loadDbState();

        // Any slug on disk not in DB is 'discovered' (not yet activated).
        foreach (array_keys($available) as $slug) {
            if (!isset(self::$statuses[$slug])) {
                self::$statuses[$slug] = 'discovered';
            }
        }

        // Step 3: require definition files.
        // If the DB table doesn't exist yet ($dbChecked is false after the
        // catch), load everything so the platform still functions.
        $loadAll = !self::$dbChecked;
        foreach ($available as $slug => $file) {
            if ($loadAll || (self::$statuses[$slug] ?? 'discovered') === 'active') {
                require $file;
            }
        }
    }

    /**
     * Load activation statuses from block_type_config.
     * Sets $dbChecked = true on success; leaves it false on failure (table missing).
     */
    private static function loadDbState(): void
    {
        if (self::$dbChecked) {
            return;
        }

        try {
            $db   = Database::getInstance();
            $rows = $db->fetchAll("SELECT slug, status FROM block_type_config");
            foreach ($rows as $row) {
                self::$statuses[$row['slug']] = $row['status'];
            }
            self::$dbChecked = true;
        } catch (\Throwable) {
            // block_type_config table doesn't exist yet — load everything.
        }
    }
}
