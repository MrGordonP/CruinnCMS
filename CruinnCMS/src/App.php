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
        // Register exception/error handlers first so any crash during init
        // can be caught and returned as JSON for AJAX callers.
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);

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

        // Centralized handlers already registered at the top of init().
        // (Kept here as a comment to mark where they originally lived.)

        // Start session
        Auth::startSession();

        // Make common data available to all templates
        Template::addGlobal('site_name', self::config('site.name'));
        Template::addGlobal('site_tagline', self::config('site.tagline', ''));
        Template::addGlobal('current_user', Auth::check() ? [
            'id'         => Auth::userId(),
            'name'       => $_SESSION['user_name'] ?? '',
            'role_level' => Auth::roleLevel(),
            'group_level'=> Auth::groupLevel(),
        ] : null);
        Template::addGlobal('flashes', Auth::getFlashes());

        // Load dynamic navigation for the user's role.
        // Skip when in platform editor mode — the platform DB has no user/role tables.
        $roleNavItems = [];
        $isPlatformSession = (($_SESSION['_platform_editor_instance'] ?? null) === '__platform__');
        if (!$isPlatformSession && Auth::check() && Auth::roleLevel() > 0) {
            $navService = new Services\NavService();
            $primaryRoleId = Database::getInstance()->fetchColumn(
                'SELECT role_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.level DESC LIMIT 1',
                [Auth::userId()]
            );
            if ($primaryRoleId) {
                $roleNavItems = $navService->getNavForRole((int) $primaryRoleId);
            }
        }
        Template::addGlobal('role_nav_items', $roleNavItems);

        // Discover and load all drop-in modules (no-op until modules/ has content)
        Modules\ModuleRegistry::load();

        // Register core platform modules — always active, not tracked in module_config.
        Modules\ModuleRegistry::registerCore([
            'slug'        => 'subjects',
            'name'        => 'Subjects',
            'description' => 'Core subject/category management.',
            'acp_sections' => [
                ['group' => 'Content', 'label' => 'Subjects', 'url' => '/admin/subjects', 'icon' => '🧭'],
            ],
            'widgets' => static function (): array {
                try {
                    $db       = \Cruinn\Database::getInstance();
                    $total    = (int) $db->fetchColumn('SELECT COUNT(*) FROM subjects');
                    $active   = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'active'");
                    $draft    = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'draft'");
                    $archived = (int) $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE status = 'archived'");
                } catch (\Throwable) {
                    $total = $active = $draft = $archived = 0;
                }

                return [
                    [
                        'key'   => 'subjects_quick_link',
                        'title' => 'Subjects Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/subjects" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">🧭</span>'
                            . '<span>Subjects</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'subjects_status_summary',
                        'title' => 'Subjects Status Summary',
                        'html'  => '<div class="activity-header"><h2>Subjects Status</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Total</span></a>'
                            . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $active . '</strong><span>Active</span></a>'
                            . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $draft . '</strong><span>Draft</span></a>'
                            . '<a href="/admin/subjects" class="dash-quick-link"><strong class="dash-stat-num">' . $archived . '</strong><span>Archived</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'accounts',
            'name'        => 'Accounts',
            'description' => 'Core user account management.',
            'acp_sections' => [
                ['group' => 'Admin', 'label' => 'Users', 'url' => '/admin/users', 'icon' => '👤'],
            ],
            'template_path' => dirname(__DIR__) . '/templates',
            'widget_providers' => [
                [
                    'slug'     => 'recent-activity',
                    'label'    => 'Recent Activity',
                    'provider' => Services\DashboardService::class . '::recentActivityData',
                    'template' => 'admin/widgets/recent-activity',
                ],
                [
                    'slug'     => 'notifications-summary',
                    'label'    => 'Notifications Summary',
                    'provider' => Services\DashboardService::class . '::notificationsSummaryData',
                    'template' => 'admin/widgets/notifications-summary',
                ],
                [
                    'slug'     => 'member-profile',
                    'label'    => 'Member Profile',
                    'provider' => Services\DashboardService::class . '::memberProfileData',
                    'template' => 'admin/widgets/member-profile',
                ],
            ],
            'content_providers' => [
                [
                    'slug'     => 'account-information',
                    'title'    => 'Account Information',
                    'provider' => Controllers\AuthController::class . '::contentProviderAccountInformation',
                    'template' => 'public/account/information',
                ],
                [
                    'slug'     => 'account-details',
                    'title'    => 'Account Details Form',
                    'provider' => Controllers\AuthController::class . '::contentProviderAccountDetails',
                    'template' => 'public/account/details-form',
                ],
                [
                    'slug'     => 'account-password',
                    'title'    => 'Account Password Form',
                    'provider' => Controllers\AuthController::class . '::contentProviderAccountPassword',
                    'template' => 'public/account/password-form',
                ],
            ],
            'widgets' => static function (): array {
                try {
                    $db      = \Cruinn\Database::getInstance();
                    $total   = (int) $db->fetchColumn('SELECT COUNT(*) FROM users');
                    $active  = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE active = 1");
                    $recent  = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                } catch (\Throwable) {
                    $total = $active = $recent = 0;
                }

                return [
                    [
                        'key'   => 'accounts_quick_link',
                        'title' => 'Users Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/users" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">👤</span>'
                            . '<span>Users</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'accounts_status_summary',
                        'title' => 'Users Status Summary',
                        'html'  => '<div class="activity-header"><h2>Users</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/users" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Total</span></a>'
                            . '<a href="/admin/users" class="dash-quick-link"><strong class="dash-stat-num">' . $active . '</strong><span>Active</span></a>'
                            . '<a href="/admin/users" class="dash-quick-link"><strong class="dash-stat-num">' . $recent . '</strong><span>Last 30d</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'pages',
            'name'        => 'Pages',
            'description' => 'Core page tree, block editor, and HTML editor.',
            'acp_sections' => [
                ['group' => 'Content', 'label' => 'Pages', 'url' => '/admin/pages', 'icon' => '📄'],
                ['group' => 'Content', 'label' => 'Templates', 'url' => '/admin/templates', 'icon' => '🗂️'],
            ],
            'template_path' => dirname(__DIR__) . '/templates',
            'widget_providers' => [
                [
                    'slug'     => 'stats-overview',
                    'label'    => 'Stats Overview',
                    'provider' => Services\DashboardService::class . '::statsOverviewData',
                    'template' => 'admin/widgets/stats-overview',
                ],
                [
                    'slug'     => 'communications',
                    'label'    => 'Communications',
                    'provider' => Services\DashboardService::class . '::communicationsData',
                    'template' => 'admin/widgets/communications',
                ],
                [
                    'slug'     => 'comms-social',
                    'label'    => 'Communications &amp; Social',
                    'provider' => Services\DashboardService::class . '::commsSocialData',
                    'template' => 'admin/widgets/comms-social',
                ],
                [
                    'slug'     => 'social-links',
                    'label'    => 'Social Links',
                    'provider' => Services\DashboardService::class . '::socialLinksData',
                    'template' => 'admin/widgets/social-links',
                ],
                [
                    'slug'     => 'upcoming-events',
                    'label'    => 'Upcoming Events',
                    'provider' => Services\DashboardService::class . '::upcomingEventsData',
                    'template' => 'admin/widgets/upcoming-events',
                ],
                [
                    'slug'     => 'forum-recent',
                    'label'    => 'Recent Forum Activity',
                    'provider' => Services\DashboardService::class . '::forumRecentData',
                    'template' => 'admin/widgets/forum-recent',
                ],
                [
                    'slug'     => 'active-discussions',
                    'label'    => 'Active Discussions',
                    'provider' => Services\DashboardService::class . '::activeDiscussionsData',
                    'template' => 'admin/widgets/active-discussions',
                ],
                [
                    'slug'     => 'council-stats',
                    'label'    => 'Council Stats',
                    'provider' => Services\DashboardService::class . '::councilStatsData',
                    'template' => 'admin/widgets/council-stats',
                ],
                [
                    'slug'     => 'recent-documents',
                    'label'    => 'Recent Documents',
                    'provider' => Services\DashboardService::class . '::recentDocumentsData',
                    'template' => 'admin/widgets/recent-documents',
                ],
            ],
            'widgets' => static function (): array {
                try {
                    $db        = \Cruinn\Database::getInstance();
                    $total     = (int) $db->fetchColumn('SELECT COUNT(*) FROM pages');
                    $published = (int) $db->fetchColumn("SELECT COUNT(*) FROM pages WHERE status = 'published'");
                    $draft     = (int) $db->fetchColumn("SELECT COUNT(*) FROM pages WHERE status = 'draft'");
                } catch (\Throwable) {
                    $total = $published = $draft = 0;
                }

                return [
                    [
                        'key'   => 'pages_quick_link',
                        'title' => 'Pages Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/pages" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">📄</span>'
                            . '<span>Pages</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'pages_status_summary',
                        'title' => 'Pages Status Summary',
                        'html'  => '<div class="activity-header"><h2>Pages</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/pages" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Total</span></a>'
                            . '<a href="/admin/pages" class="dash-quick-link"><strong class="dash-stat-num">' . $published . '</strong><span>Published</span></a>'
                            . '<a href="/admin/pages" class="dash-quick-link"><strong class="dash-stat-num">' . $draft . '</strong><span>Draft</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'menus',
            'name'        => 'Menus',
            'description' => 'Core navigation menu management.',
            'acp_sections' => [
                ['group' => 'Content', 'label' => 'Menus', 'url' => '/admin/menus', 'icon' => '🔗'],
            ],
            'widgets' => static function (): array {
                try {
                    $db    = \Cruinn\Database::getInstance();
                    $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM menus');
                    $items = (int) $db->fetchColumn('SELECT COUNT(*) FROM menu_items');
                } catch (\Throwable) {
                    $total = $items = 0;
                }

                return [
                    [
                        'key'   => 'menus_quick_link',
                        'title' => 'Menus Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/menus" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">🔗</span>'
                            . '<span>Menus</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'menus_status_summary',
                        'title' => 'Menus Summary',
                        'html'  => '<div class="activity-header"><h2>Menus</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/menus" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Menus</span></a>'
                            . '<a href="/admin/menus" class="dash-quick-link"><strong class="dash-stat-num">' . $items . '</strong><span>Items</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'media',
            'name'        => 'Media',
            'description' => 'Core media library and file management.',
            'acp_sections' => [
                ['group' => 'Content', 'label' => 'Media', 'url' => '/admin/media', 'icon' => '🖼️'],
            ],
            'widgets' => static function (): array {
                return [
                    [
                        'key'   => 'media_quick_link',
                        'title' => 'Media Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/media" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">🖼️</span>'
                            . '<span>Media</span>'
                            . '</a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'roles',
            'name'        => 'Roles',
            'description' => 'Core role and permission management.',
            'acp_sections' => [
                ['group' => 'Admin', 'label' => 'Roles', 'url' => '/admin/roles', 'icon' => '🛡️'],
            ],
            'widgets' => static function (): array {
                try {
                    $db    = \Cruinn\Database::getInstance();
                    $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM roles');
                } catch (\Throwable) {
                    $total = 0;
                }

                return [
                    [
                        'key'   => 'roles_quick_link',
                        'title' => 'Roles Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/roles" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">🛡️</span>'
                            . '<span>Roles</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'roles_status_summary',
                        'title' => 'Roles Summary',
                        'html'  => '<div class="activity-header"><h2>Roles</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/roles" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Roles</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        Modules\ModuleRegistry::registerCore([
            'slug'        => 'groups',
            'name'        => 'Groups',
            'description' => 'Core group and group membership management.',
            'acp_sections' => [
                ['group' => 'Admin', 'label' => 'Groups', 'url' => '/admin/groups', 'icon' => '👥'],
            ],
            'widgets' => static function (): array {
                try {
                    $db    = \Cruinn\Database::getInstance();
                    $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM groups');
                } catch (\Throwable) {
                    $total = 0;
                }

                return [
                    [
                        'key'   => 'groups_quick_link',
                        'title' => 'Groups Quick Link',
                        'html'  => '<div class="dash-quick-grid">'
                            . '<a href="/admin/groups" class="dash-quick-link">'
                            . '<span class="dash-quick-icon">👥</span>'
                            . '<span>Groups</span>'
                            . '</a>'
                            . '</div>',
                    ],
                    [
                        'key'   => 'groups_status_summary',
                        'title' => 'Groups Summary',
                        'html'  => '<div class="activity-header"><h2>Groups</h2></div>'
                            . '<div class="dash-quick-grid">'
                            . '<a href="/admin/groups" class="dash-quick-link"><strong class="dash-stat-num">' . $total . '</strong><span>Groups</span></a>'
                            . '</div>',
                    ],
                ];
            },
        ]);

        // Register each active module's template directory as a fallback path
        foreach (Modules\ModuleRegistry::templatePaths() as $path) {
            Template::addTemplatePath($path);
        }

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

        // ── Public page catch-alls (must be last) ──────────────────────────
        $this->router->get('/',         [Controllers\PageController::class, 'home']);
        $this->router->get('/{slug*}',  [Controllers\PageController::class, 'show']);
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

        // For AJAX requests, return HTTP 200 with JSON so server-level 5xx
        // interception (LiteSpeed, nginx custom_error_page, etc.) cannot blank
        // the body. The JS caller checks for success:false in the response.
        $isAjax = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            while (ob_get_level() > 0) { ob_get_clean(); }
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::config('site.debug')) {
            // Flush any buffered output so the error is always visible
            while (ob_get_level() > 0) { ob_end_clean(); }
            ini_set('display_errors', '1');
            header('Content-Type: text/html; charset=UTF-8', true, 500);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
                . '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;font-size:13px;white-space:pre-wrap">'
                . htmlspecialchars((string) $e, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</pre></body></html>';
            exit;
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
