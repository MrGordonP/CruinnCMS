<?php
/**
 * CMS Portal — Application Bootstrap
 *
 * Core application class: loads config, registers autoloader,
 * wires up the request lifecycle.
 */

namespace Cruinn;

class App
{
    private static ?array $config = null;
    private static ?App $instance = null;

    /**
     * Config keys that must only come from config files — never from the DB.
     * Prevents payment secrets from being overridden by a settings table row.
     */
    private const FILE_ONLY_KEYS = [
        'paypal.client_secret',
        'stripe.secret_key',
    ];

    private Router $router;

    /**
     * Boot the application.
     */
    public static function boot(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self();
        self::$instance->init();
        return self::$instance;
    }

    /**
     * Initialise: load config, set up error handling, start session.
     */
    private function init(): void
    {
        // Load configuration
        self::loadConfig();

        // Merge DB settings over config-file defaults (runtime overrides)
        self::loadSettingsOverrides();

        // Timezone
        date_default_timezone_set(self::config('site.timezone'));

        // Error handling
        if (self::config('site.debug')) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }

        // Centralized handlers — convert errors to exceptions and catch
        // uncaught exceptions so nothing leaks to the browser in production.
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);

        // Start session
        Auth::startSession();

        // Make common data available to all templates
        Template::addGlobal('site_name', self::config('site.name'));
        Template::addGlobal('site_tagline', self::config('site.tagline', ''));
        Template::addGlobal('current_user', Auth::check() ? [
            'id'   => Auth::userId(),
            'name' => $_SESSION['user_name'] ?? '',
            'role' => Auth::role(),
        ] : null);
        Template::addGlobal('flashes', Auth::getFlashes());

        // Load dynamic navigation for the user's role
        $roleNavItems = [];
        if (Auth::check() && Auth::roleId()) {
            $navService = new Services\NavService();
            $roleNavItems = $navService->getNavForRole(Auth::roleId());
        }
        Template::addGlobal('role_nav_items', $roleNavItems);

        // Discover and load all drop-in modules (no-op until modules/ has content)
        Modules\ModuleRegistry::load();

        // Register each active module's template directory as a fallback path
        foreach (Modules\ModuleRegistry::templatePaths() as $path) {
            Template::addTemplatePath($path);
        }

        // Make discovered-module flag available to all admin templates
        Template::addGlobal('modules_has_new', Modules\ModuleRegistry::hasDiscovered());

        // Set up router
        $this->router = new Router();

        // Global middleware
        $this->router->addGlobalMiddleware([CSRF::class, 'middleware']);

        // Platform routes require platform login (file-based credential, /cms/login exempt)
        $this->router->addPrefixMiddleware('/cms', [\Cruinn\Platform\PlatformAuth::class, 'middleware']);

        // Admin routes require admin role
        $this->router->addPrefixMiddleware('/admin', [Auth::class, 'adminMiddleware']);

        // Council routes require council role
        $this->router->addPrefixMiddleware('/council', [Auth::class, 'councilMiddleware']);

        // Member self-service routes require member role
        $this->router->addPrefixMiddleware('/members', [Auth::class, 'memberMiddleware']);

        // File Manager routes require at least member login
        $this->router->addPrefixMiddleware('/files', [Auth::class, 'memberMiddleware']);

        // Load core route definitions
        $registerRoutes = require dirname(__DIR__) . '/config/routes.php';
        $registerRoutes($this->router);

        // Register routes from active modules BEFORE the page catch-alls,
        // so module paths like /news, /events, /forum are never shadowed.
        Modules\ModuleRegistry::registerRoutes($this->router);

        // Collect sidebar widgets from active modules and expose to all templates.
        Template::addGlobal('sidebar_widgets', Modules\ModuleRegistry::collectWidgets());

        // ── Public page catch-alls (must be last) ──────────────────────────
        $this->router->get('/',        [Controllers\PageController::class, 'home']);
        $this->router->get('/{slug}',  [Controllers\PageController::class, 'show']);
    }

    /**
     * Handle the incoming HTTP request.
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $_SERVER['REQUEST_URI'];

        // Strip base path prefix for subdirectory installs (e.g. XAMPP)
        $basePath = self::config('site.base_path', '');
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        // ── Uninitialized guard ────────────────────────────────────────────
        // If the platform has not been set up yet, redirect everything to the
        // install wizard. /cms/* routes are exempt — PlatformAuth handles them.
        $cleanUri = strtok($uri, '?');
        if (!Platform\PlatformAuth::isInitialized() && !str_starts_with($cleanUri, '/cms/') && $cleanUri !== '/cms') {
            header('Location: /cms/install');
            exit;
        }

        // ── No-instance guard ──────────────────────────────────────────────
        // Platform is installed but no hostname matched any instance. Any non-/cms/*
        // request would hit the instance DB (which doesn't exist). Redirect to
        // the platform dashboard where the operator can provision an instance.
        if (Platform\PlatformAuth::isInitialized() && self::instanceDir() === null
            && !str_starts_with($cleanUri, '/cms/') && $cleanUri !== '/cms'
        ) {
            header('Location: /cms/dashboard');
            exit;
        }

        // ── Maintenance guard ──────────────────────────────────────────────
        // Instance resolved but marked offline (instance/{slug}/.active absent).
        $instancePath = self::instanceDir();
        if ($instancePath !== null && !is_file($instancePath . '/.active')
            && !str_starts_with($cleanUri, '/cms/') && $cleanUri !== '/cms'
        ) {
            http_response_code(503);
            require dirname(__DIR__) . '/templates/errors/maintenance.php';
            exit;
        }

        $this->router->dispatch($method, $uri);
    }

    // ── Configuration ─────────────────────────────────────────────

    /**
     * Merge DB-managed settings over config-file values.
     * Only non-empty DB values override — empty strings are ignored.
     * Uses the instance `settings` table when an instance is active,
     * or `platform_settings` at the platform layer.
     */
    private static function loadSettingsOverrides(): void
    {
        try {
            $db    = Database::getInstance();
            $table = self::instanceDir() !== null ? 'settings' : 'platform_settings';
            $rows  = $db->fetchAll("SELECT `key`, `value` FROM `{$table}` WHERE `value` != ''");
            foreach ($rows as $row) {
                if (in_array($row['key'], self::FILE_ONLY_KEYS, true)) {
                    continue; // Secrets must come from config files only
                }
                self::setConfigKey($row['key'], $row['value']);
            }
        } catch (\Throwable) {
            // Settings table may not exist yet (fresh install, pre-migration)
        }
    }

    /**
     * Return the real client IP address.
     *
     * When running behind a trusted reverse proxy (e.g. Nginx on 127.0.0.1),
     * the real IP is taken from the leftmost entry in X-Forwarded-For.
     * The trusted_proxy config key is a comma-separated list of proxy IPs.
     */
    public static function clientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) self::config('trusted_proxy', ''))));

        if ($remoteAddr && $trustedProxies && in_array($remoteAddr, $trustedProxies, true)) {
            $xff = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                $ip = trim(explode(',', $xff)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Set a config value by dot-notation key.
     */
    private static function setConfigKey(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &self::$config;
        foreach ($keys as $i => $k) {
            if ($i === array_key_last($keys)) {
                $ref[$k] = $value;
            } else {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    $ref[$k] = [];
                }
                $ref = &$ref[$k];
            }
        }
    }

    /**
     * Resolve the active instance directory path for this request.
     *
     * Resolution order:
     *   1. CRUINN_INSTANCE environment variable — explicit slug (CLI tools, Nginx fastcgi_param)
     *   2. HTTP_HOST — scan instance configs, match against each instance's 'hostname' key
     *      (string or array of strings; port stripped before comparison)
     *   3. null — no match; no-instance guard in run() will intercept
     *
     * The returned path points to instance/{slug}/ and is only returned if that
     * directory actually exists.  Whether the instance is online or offline is
     * NOT checked here — see the maintenance guard in run().
     */
    public static function instanceDir(): ?string
    {
        $basePath = dirname(__DIR__) . '/instance/';

        // 1. Explicit CLI / server-variable override
        $name = getenv('CRUINN_INSTANCE');
        if ($name !== false && $name !== '') {
            $dir = $basePath . basename($name);
            return is_dir($dir) ? $dir : null;
        }

        // 2. Hostname match
        $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0]; // strip port
        }

        if ($host !== '') {
            foreach (glob($basePath . '*', GLOB_ONLYDIR) ?: [] as $dir) {
                $cfgFile = $dir . '/config.php';
                if (!is_file($cfgFile)) {
                    continue;
                }
                $cfg       = require $cfgFile;
                $hostnames = $cfg['hostname'] ?? [];
                if (is_string($hostnames)) {
                    $hostnames = [$hostnames];
                }
                foreach ($hostnames as $h) {
                    if (strtolower(trim((string) $h)) === $host) {
                        return $dir;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Load configuration from file.
     */
    private static function loadConfig(): void
    {
        $configDir  = dirname(__DIR__) . '/config';
        $rootDir    = dirname(__DIR__);

        // 1. CMS defaults
        self::$config = require $configDir . '/config.php';

        // 2. Local developer overrides (gitignored) — base layer for dev/platform
        $localConfig = $configDir . '/config.local.php';
        if (file_exists($localConfig)) {
            $local = require $localConfig;
            self::$config = array_replace_recursive(self::$config, $local);
        }

        // 3. Instance config (always wins when an instance is active — instance DB
        //    credentials must not be overridden by config.local.php)
        $instanceDir    = self::instanceDir();
        $instanceConfig = $instanceDir ? $instanceDir . '/config.php' : null;
        if ($instanceConfig && file_exists($instanceConfig)) {
            $instance = require $instanceConfig;
            self::$config = array_replace_recursive(self::$config, $instance);
        }
    }

    /**
     * Get a configuration value using dot notation.
     *
     * Usage: App::config('db.host')       => 'localhost'
     *        App::config('site.name')     => 'My Organisation'
     *        App::config('db')            => ['host' => ..., 'port' => ..., ...]
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::loadConfig();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Convert PHP errors into ErrorExceptions so they propagate uniformly.
     * Respects the current error_reporting level.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // honour @ suppression operator
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Last-resort handler for any uncaught Throwable.
     * Logs full detail; shows generic page in production.
     */
    public static function handleException(\Throwable $e): void
    {
        error_log(
            'Uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString()
        );

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::config('site.debug')) {
            echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;font-size:13px">'
                . htmlspecialchars((string) $e, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</pre>';
        } else {
            // Render a plain, safe 500 page without template engine
            // (the error may have occurred inside the template system).
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
                . '<title>Service Unavailable</title></head><body>'
                . '<h1>Something went wrong</h1>'
                . '<p>We\'ve logged the issue and will look into it. Please try again shortly.</p>'
                . '</body></html>';
        }
    }
}
