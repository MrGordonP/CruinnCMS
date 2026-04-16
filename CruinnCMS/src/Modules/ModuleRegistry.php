<?php
/**
 * CruinnCMS — Module Registry
 *
 * Central registry for all drop-in feature modules.
 * Module manifests live at modules/{slug}/module.php.
 * Each manifest calls ModuleRegistry::register() with a definition array.
 *
 * Definition keys:
 *   slug             (string)    Module identifier, e.g. 'forum'
 *   name             (string)    Human-readable name, e.g. 'Forum'
 *   version          (string)    Semver string, e.g. '1.0.0'
 *   description      (string)    Short description shown in the ACP Modules panel
 *   dependencies     (string[])  Slugs of modules this module requires
 *   routes           (callable)  function(Router $router): void — registers public + admin routes
 *   public_routes    (array[])   Menu-linkable public entry points: [['route' => '/path', 'label' => 'Label'], ...]
 *   migrations       (string[])  Absolute paths to SQL migration files, in apply order
 *   acp_sections     (array[])   Sidebar entries: [group, label, url, icon]
 *   template_path    (string)    Absolute path to this module's templates directory
 *   settings_schema  (array[])   Settings fields rendered in the ACP Modules panel
 *   provides         (string[])  Capabilities: 'block_types', 'nav_items', 'content_feed'
 */

namespace Cruinn\Modules;

use Cruinn\Database;

class ModuleRegistry
{
    private static array $modules    = [];
    private static bool  $loaded     = false;
    private static bool  $dbChecked  = false;

    // Keyed by slug: 'discovered' | 'active' | 'offline'
    private static array $statuses   = [];
    // Keyed by slug: decoded settings array
    private static array $settings   = [];
    // True if at least one module is in 'discovered' state (new, never configured)
    private static bool  $hasNew     = false;

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Register a module definition. Called from each module.php manifest.
     */
    public static function register(array $def): void
    {
        $slug = $def['slug'] ?? '';
        if ($slug !== '') {
            self::$modules[$slug] = array_merge([
                'name'            => $slug,
                'version'         => '1.0.0',
                'description'     => '',
                'dependencies'    => [],
                'routes'          => null,
                'public_routes'   => [],
                'migrations'      => [],
                'acp_sections'    => [],
                'template_path'   => null,
                'settings_schema' => [],
                'provides'        => [],
                'widgets'         => null,
            ], $def);
        }
    }

    // ── Querying ─────────────────────────────────────────────────────────────

    /**
     * Return the full definition array for a module, or null if not found.
     */
    public static function get(string $slug): ?array
    {
        self::load();
        return self::$modules[$slug] ?? null;
    }

    /**
     * Return all registered module definitions, indexed by slug.
     * Only includes modules that were actually loadable (dependencies met, etc.)
     */
    public static function all(): array
    {
        self::load();
        return self::$modules;
    }

    /**
     * Return true if the named module is present and active.
     */
    public static function isActive(string $slug): bool
    {
        self::load();
        return (self::$statuses[$slug] ?? 'discovered') === 'active';
    }

    /**
     * Return true if any discovered-but-unconfigured modules exist.
     * Used by the admin layout to show the "new module detected" banner.
     */
    public static function hasDiscovered(): bool
    {
        self::load();
        return self::$hasNew;
    }

    /**
     * Return the list of discovered (never configured) module slugs.
     */
    public static function discoveredSlugs(): array
    {
        self::load();
        $out = [];
        foreach (self::$statuses as $slug => $status) {
            if ($status === 'discovered') {
                $out[] = $slug;
            }
        }
        return $out;
    }

    /**
     * Read a setting value for a module. Returns $default if not set.
     */
    public static function setting(string $slug, string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$settings[$slug][$key] ?? $default;
    }

    // ── Route & ACP Registration ──────────────────────────────────────────────

    /**
     * Invoke the routes callable for every active module.
     * Called from App::init() after core routes are registered.
     */
    public static function registerRoutes(object $router): void
    {
        self::load();
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] !== 'active') {
                continue;
            }
            if (is_callable($def['routes'])) {
                ($def['routes'])($router);
            }
        }
    }

    /**
     * Collect sidebar widgets from all active modules.
     * Each module's 'widgets' callable returns an array of
     * ['title' => string, 'html' => string] widget definitions.
     */
    public static function collectWidgets(): array
    {
        self::load();
        $widgets = [];
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] !== 'active') {
                continue;
            }
            if (is_callable($def['widgets'] ?? null)) {
                $result = ($def['widgets'])();
                if (is_array($result)) {
                    foreach ($result as $widget) {
                        $widgets[] = $widget;
                    }
                }
            }
        }
        return $widgets;
    }

    /**
     * Return all menu-linkable public routes from active modules.
     * Each entry: ['route' => '/path', 'label' => 'Label']
     */
    public static function menuRoutes(): array
    {
        self::load();
        $routes = [];
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] !== 'active') {
                continue;
            }
            foreach ($def['public_routes'] as $entry) {
                $routes[] = $entry;
            }
        }
        return $routes;
    }

    /**
     * Return all ACP sidebar section entries from active modules.
     * Each entry: ['group' => ..., 'label' => ..., 'url' => ..., 'icon' => ...]
     */
    public static function acpSections(): array
    {
        self::load();
        $sections = [];
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] !== 'active') {
                continue;
            }
            foreach ($def['acp_sections'] as $section) {
                $sections[] = $section;
            }
        }
        return $sections;
    }

    /**
     * Return all active modules that declare a given capability.
     * e.g. ModuleRegistry::providing('content_feed')
     */
    public static function providing(string $capability): array
    {
        self::load();
        $out = [];
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] === 'active' && in_array($capability, $def['provides'], true)) {
                $out[$slug] = $def;
            }
        }
        return $out;
    }

    /**
     * Return all template paths from active modules (for Template engine lookup).
     */
    public static function templatePaths(): array
    {
        self::load();
        $paths = [];
        foreach (self::$modules as $slug => $def) {
            if (self::$statuses[$slug] === 'active' && $def['template_path'] !== null) {
                $paths[$slug] = $def['template_path'];
            }
        }
        return $paths;
    }

    // ── Migration Status ──────────────────────────────────────────────────────

    /**
     * Return migration status for all modules (and core).
     * Each entry: ['module' => slug, 'file' => basename, 'path' => abs, 'applied' => bool]
     *
     * Returns an empty array if the module_migrations table doesn't exist yet.
     */
    public static function migrationStatus(Database $db): array
    {
        try {
            $applied = $db->fetchAll("SELECT module, filename FROM module_migrations");
            $appliedSet = [];
            foreach ($applied as $row) {
                $appliedSet[$row['module'] . '::' . $row['filename']] = true;
            }
        } catch (\Throwable) {
            $appliedSet = [];
        }

        self::load();
        $rows = [];
        foreach (self::$modules as $slug => $def) {
            foreach ($def['migrations'] as $path) {
                $filename = basename($path);
                $rows[] = [
                    'module'  => $slug,
                    'file'    => $filename,
                    'path'    => $path,
                    'applied' => isset($appliedSet[$slug . '::' . $filename]),
                ];
            }
        }
        return $rows;
    }

    // ── Discovery & Loading ───────────────────────────────────────────────────

    /**
     * Discover all module manifests, sort by dependency order, include them,
     * register the Cruinn\Module\* autoloader, and load lifecycle state from DB.
     *
     * Called once from App::init(). Subsequent calls are no-ops.
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        // Register the umbrella autoloader for Cruinn\Module\* before including
        // manifests, so any class references inside manifests can resolve.
        self::registerAutoloader();

        // Include every module.php manifest.
        // Manifests may either call ModuleRegistry::register() directly,
        // or return a definition array — both patterns are supported.
        $manifestRoot = dirname(__DIR__, 2) . '/modules';
        foreach (glob($manifestRoot . '/*/module.php') ?: [] as $file) {
            $def = require $file;
            if (is_array($def) && isset($def['slug'])) {
                self::register($def);
            }
        }

        // Topological sort: load dependencies before dependents.
        self::$modules = self::sortByDependencies(self::$modules);

        // Load lifecycle state from DB (status + settings JSON).
        // Failures here must never crash the site — fresh installs won't have
        // the module_config table yet.
        self::loadDbState();
    }

    /**
     * Register the single autoloader that maps Cruinn\Module\{Slug}\... to
     * modules/{slug}/src/....php.
     */
    private static function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            // Cruinn\Module\Forum\Controllers\ForumController
            //   → modules/forum/src/Controllers/ForumController.php
            // Cruinn\Module\FileManager\Controllers\FileManagerController
            //   → modules/file-manager/src/Controllers/FileManagerController.php
            if (!str_starts_with($class, 'Cruinn\\Module\\')) {
                return;
            }
            $parts     = explode('\\', substr($class, strlen('Cruinn\\Module\\')));
            $rest      = implode('/', array_slice($parts, 1));
            $base      = dirname(__DIR__, 2) . '/modules/';

            // First try direct lowercase (covers most modules: forum, oauth, gdpr, etc.)
            $slug = strtolower($parts[0]);
            $path = $base . "{$slug}/src/{$rest}.php";
            if (!file_exists($path)) {
                // Fallback: PascalCase → kebab-case (covers FileManager → file-manager)
                $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $parts[0]));
                $path = $base . "{$slug}/src/{$rest}.php";
            }
            if (file_exists($path)) {
                require_once $path;
            }
        });
    }

    /**
     * Read module status and settings from the module_config DB table.
     * On any DB failure (table missing on fresh install), all modules default
     * to 'discovered' state.
     */
    private static function loadDbState(): void
    {
        if (self::$dbChecked) {
            return;
        }
        self::$dbChecked = true;

        try {
            $db   = Database::getInstance();
            $rows = $db->fetchAll("SELECT slug, status, settings FROM module_config");
            foreach ($rows as $row) {
                self::$statuses[$row['slug']] = $row['status'];
                self::$settings[$row['slug']] = json_decode($row['settings'] ?? '{}', true) ?? [];
            }
        } catch (\Throwable) {
            // module_config table doesn't exist yet — treat everything as discovered
        }

        // Any module on disk not in DB is 'discovered'.
        // Any active module whose dependency is not active gets forced offline.
        foreach (self::$modules as $slug => $def) {
            if (!isset(self::$statuses[$slug])) {
                self::$statuses[$slug] = 'discovered';
            }

            if (self::$statuses[$slug] === 'active') {
                foreach ($def['dependencies'] as $dep) {
                    if ((self::$statuses[$dep] ?? 'discovered') !== 'active') {
                        self::$statuses[$slug] = 'offline';
                        error_log("ModuleRegistry: '{$slug}' forced offline — dependency '{$dep}' is not active.");
                        break;
                    }
                }
            }

            if (self::$statuses[$slug] === 'discovered') {
                self::$hasNew = true;
            }
        }
    }

    /**
     * Topological sort. Modules are ordered so dependencies always appear before
     * the modules that depend on them. Circular/unresolvable dependencies result
     * in the dependent module being placed last (safe fallback).
     */
    private static function sortByDependencies(array $modules): array
    {
        $sorted  = [];
        $visited = [];

        $visit = function (string $slug) use (&$visit, &$modules, &$sorted, &$visited): void {
            if (isset($visited[$slug])) {
                return;
            }
            $visited[$slug] = true;
            $def = $modules[$slug] ?? null;
            if ($def === null) {
                return;
            }
            foreach ($def['dependencies'] as $dep) {
                $visit($dep);
            }
            $sorted[$slug] = $def;
        };

        foreach (array_keys($modules) as $slug) {
            $visit($slug);
        }

        return $sorted;
    }
}
