<?php
/**
 * CruinnCMS — Dashboard Service
 *
 * Renders dynamic, per-role dashboards using configurable widgets.
 * Each widget has a data provider (static method) and a template partial.
 */

namespace Cruinn\Services;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;
use Cruinn\Template;

class DashboardService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * During council→organisation rename, accept either module slug.
     */
    private static function organisationModuleActive(): bool
    {
        return self::capabilityAvailable('organisation', 'council');
    }

    private static function capabilityAvailable(string $capability, ?string $legacySlug = null): bool
    {
        if (!empty(ModuleRegistry::providing($capability))) {
            return true;
        }

        return $legacySlug !== null && ModuleRegistry::isActive($legacySlug);
    }

    // ── Widget Configuration ──────────────────────────────────────

    /**
     * Get ordered, visible widgets for a role.
     *
     * @return array[] Each element has widget fields + config fields.
     */
    public function getWidgetsForRole(int $roleId): array
    {
        return $this->db->fetchAll(
            'SELECT dw.*, rdw.sort_order, rdw.grid_width, rdw.settings_override, rdw.is_visible
             FROM role_dashboard_widgets rdw
             JOIN dashboard_widgets dw ON dw.id = rdw.widget_id
             WHERE rdw.role_id = ? AND rdw.is_visible = 1 AND dw.is_active = 1
             ORDER BY rdw.sort_order ASC',
            [$roleId]
        );
    }

    /**
     * Get ALL widgets with their config for a role (including hidden),
     * for the dashboard configuration UI.
     */
    public function getAllWidgetsForConfig(int $roleId): array
    {
        // All available widgets
        $allWidgets = $this->db->fetchAll(
            'SELECT * FROM dashboard_widgets WHERE is_active = 1 ORDER BY category, name'
        );

        // Current config for this role
        $configured = $this->db->fetchAll(
            'SELECT * FROM role_dashboard_widgets WHERE role_id = ?',
            [$roleId]
        );
        $configMap = [];
        foreach ($configured as $c) {
            $configMap[$c['widget_id']] = $c;
        }

        // Merge
        $result = [];
        foreach ($allWidgets as $w) {
            $conf = $configMap[$w['id']] ?? null;
            $result[] = array_merge($w, [
                'sort_order'        => $conf['sort_order'] ?? 999,
                'grid_width'        => $conf['grid_width'] ?? 'full',
                'settings_override' => $conf['settings_override'] ?? null,
                'is_visible'        => $conf ? (bool) $conf['is_visible'] : false,
                'is_configured'     => $conf !== null,
            ]);
        }

        // Sort configured ones first by sort_order, then unconfigured
        usort($result, function ($a, $b) {
            if ($a['is_configured'] && !$b['is_configured']) return -1;
            if (!$a['is_configured'] && $b['is_configured']) return 1;
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $result;
    }

    /**
     * Save widget configuration for a role.
     *
     * @param int   $roleId
     * @param array $widgetConfigs Array of ['widget_id', 'sort_order', 'grid_width', 'is_visible']
     */
    public function saveWidgetConfig(int $roleId, array $widgetConfigs): void
    {
        $this->db->transaction(function () use ($roleId, $widgetConfigs) {
            // Remove existing config for this role
            $this->db->delete('role_dashboard_widgets', 'role_id = ?', [$roleId]);

            // Insert new config
            foreach ($widgetConfigs as $config) {
                $this->db->insert('role_dashboard_widgets', [
                    'role_id'           => $roleId,
                    'widget_id'         => (int) $config['widget_id'],
                    'sort_order'        => (int) $config['sort_order'],
                    'grid_width'        => in_array($config['grid_width'], ['full', 'half']) ? $config['grid_width'] : 'full',
                    'settings_override' => !empty($config['settings_override']) ? json_encode($config['settings_override']) : null,
                    'is_visible'        => !empty($config['is_visible']) ? 1 : 0,
                ]);
            }
        });
    }

    // ── Dashboard Rendering ───────────────────────────────────────

    /**
     * Build the full dashboard data for a role.
     * Returns an array of widgets, each with their rendered data.
     *
     * @return array[] Each element: widget config + 'data' key with provider output.
     */
    public function buildDashboard(int $roleId): array
    {
        $widgets = $this->getWidgetsForRole($roleId);
        $result = [];

        foreach ($widgets as $widget) {
            // Merge default settings with per-role overrides
            $defaults = json_decode($widget['default_settings'] ?? '{}', true) ?? [];
            $overrides = json_decode($widget['settings_override'] ?? '{}', true) ?? [];
            $settings = array_merge($defaults, $overrides);

            // Call data provider
            $data = $this->callProvider($widget['data_provider'], $settings);

            $widget['settings'] = $settings;
            $widget['data'] = $data;
            $result[] = $widget;
        }

        return $result;
    }

    /**
     * Build dashboard widgets declared directly by active modules for a role.
     *
     * @return array[] Each element contains render-ready widget data.
     */
    public function buildModuleWidgetsForRole(string $role): array
    {
        $result = [];

        foreach (ModuleRegistry::dashboardWidgets($role) as $widget) {
            $settings = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];
            $templateFile = $this->resolveModuleWidgetTemplate($widget);
            $data = [];

            if (!empty($widget['provider'])) {
                $data = $this->callProvider((string) $widget['provider'], $settings);
            }

            $result[] = array_merge($widget, [
                'grid_width'    => (($widget['width'] ?? 'full') === 'half') ? 'half' : 'full',
                'template_file' => $templateFile,
                'settings'      => $settings,
                'data'          => $data,
            ]);
        }

        return $result;
    }

    /**
     * Call a widget data provider.
     * Providers are static methods in the format "Class::method".
     */
    private function callProvider(string $provider, array $settings): array
    {
        if (!str_contains($provider, '::')) {
            return [];
        }

        [$class, $method] = explode('::', $provider, 2);

        if (!class_exists($class) || !method_exists($class, $method)) {
            return ['_error' => "Provider not found: {$provider}"];
        }

        return $class::$method($settings);
    }

    private function resolveModuleWidgetTemplate(array $widget): ?string
    {
        $template = trim((string) ($widget['template'] ?? ''));
        $templateRoot = $widget['template_root'] ?? null;

        if ($template === '' || !is_string($templateRoot) || $templateRoot === '') {
            return null;
        }

        if (str_starts_with($template, '/')) {
            return is_file($template) ? $template : null;
        }

        $path = rtrim($templateRoot, '/') . '/' . ltrim($template, '/');
        if (!str_ends_with($path, '.php')) {
            $path .= '.php';
        }

        return is_file($path) ? $path : null;
    }

    // ══════════════════════════════════════════════════════════════
    //  WIDGET DATA PROVIDERS (static methods)
    // ══════════════════════════════════════════════════════════════

    /**
     * Stats overview: page, article, event, member, user, subject counts.
     */
    public static function statsOverviewData(array $settings): array
    {
        $db = Database::getInstance();
        $stats = [
            'pages'    => $db->fetchColumn('SELECT COUNT(*) FROM pages'),
            'users'    => $db->fetchColumn('SELECT COUNT(*) FROM users'),
            'subjects' => $db->fetchColumn('SELECT COUNT(*) FROM subjects'),
        ];
        if (self::capabilityAvailable('articles')) {
            $stats['articles'] = $db->fetchColumn('SELECT COUNT(*) FROM articles');
        }
        if (self::capabilityAvailable('events')) {
            $stats['events'] = $db->fetchColumn('SELECT COUNT(*) FROM events');
        }
        if (!empty($settings['show_forum']) && self::capabilityAvailable('forum')) {
            $stats['forum_threads'] = $db->fetchColumn('SELECT COUNT(*) FROM forum_threads');
        }

        return $stats;
    }

    /**
     * Recent activity log entries.
     */
    public static function recentActivityData(array $settings): array
    {
        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 20);

        $activities = $db->fetchAll(
            'SELECT al.*, u.display_name
             FROM activity_log al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ' . $limit
        );

        return ['activities' => $activities];
    }

    /**
     * Communications: recent articles and draft count.
     */
    public static function communicationsData(array $settings): array
    {
        if (!self::capabilityAvailable('articles')) {
            return ['articles' => [], 'draftCount' => 0, 'totalArticles' => 0];
        }

        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 5);

        $articles = $db->fetchAll(
            'SELECT id, title, slug, status, published_at
             FROM articles
             ORDER BY updated_at DESC
             LIMIT ' . $limit
        );

        $draftCount = $db->fetchColumn(
            "SELECT COUNT(*) FROM articles WHERE status = 'draft'"
        );

        $totalArticles = $db->fetchColumn('SELECT COUNT(*) FROM articles');

        return [
            'articles'      => $articles,
            'draftCount'    => (int) $draftCount,
            'totalArticles' => (int) $totalArticles,
        ];
    }

    /**
     * Social media quick links from config.
     */
    public static function socialLinksData(array $settings): array
    {
        return [
            'facebook'  => App::config('social.facebook', ''),
            'twitter'   => App::config('social.twitter', ''),
            'instagram' => App::config('social.instagram', ''),
        ];
    }

    /**
     * Combined communications + social data for the merged dashboard widget.
     */
    public static function commsSocialData(array $settings): array
    {
        return array_merge(
            static::communicationsData($settings),
            static::socialLinksData($settings)
        );
    }

    /**
    * Organisation stats: document and discussion counts.
     */
    public static function councilStatsData(array $settings): array
    {
        if (!self::organisationModuleActive()) {
            return ['documents' => 0, 'pending' => 0, 'discussions' => 0, 'posts' => 0];
        }

        $db = Database::getInstance();
        return [
            'documents'   => $db->fetchColumn('SELECT COUNT(*) FROM documents'),
            'pending'     => $db->fetchColumn("SELECT COUNT(*) FROM documents WHERE status = 'submitted'"),
            'discussions' => $db->fetchColumn('SELECT COUNT(*) FROM discussions'),
            'posts'       => $db->fetchColumn('SELECT COUNT(*) FROM discussion_posts'),
        ];
    }

    /**
    * Recent organisation documents.
     */
    public static function recentDocumentsData(array $settings): array
    {
        if (!self::organisationModuleActive()) {
            return ['documents' => []];
        }

        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 10);

        $docs = $db->fetchAll(
            'SELECT d.*, u.display_name AS uploader_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             ORDER BY d.updated_at DESC
             LIMIT ' . $limit
        );

        return ['documents' => $docs];
    }

    /**
    * Active organisation discussions.
     */
    public static function activeDiscussionsData(array $settings): array
    {
        if (!self::organisationModuleActive()) {
            return ['discussions' => []];
        }

        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 10);

        $discussions = $db->fetchAll(
            'SELECT d.*, u.display_name AS author_name
             FROM discussions d
             LEFT JOIN users u ON d.created_by = u.id
             ORDER BY d.pinned DESC, d.last_post_at DESC, d.created_at DESC
             LIMIT ' . $limit
        );

        return ['discussions' => $discussions];
    }

    /**
     * Upcoming events.
     */
    public static function upcomingEventsData(array $settings): array
    {
        if (!self::capabilityAvailable('events')) {
            return ['events' => []];
        }

        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 5);

        $events = $db->fetchAll(
            "SELECT id, title, slug, date_start, date_end, location, event_type
             FROM events
             WHERE date_start >= CURDATE() AND status = 'published'
             ORDER BY date_start ASC
             LIMIT " . $limit
        );

        return ['events' => $events];
    }

    /**
     * Recent forum threads.
     */
    public static function forumRecentData(array $settings): array
    {
        if (!self::capabilityAvailable('forum')) {
            return ['threads' => []];
        }

        $db = Database::getInstance();
        $limit = (int) ($settings['limit'] ?? 10);

        $threads = $db->fetchAll(
            'SELECT ft.*, fc.title AS category_title, u.display_name AS author_name
             FROM forum_threads ft
             JOIN forum_categories fc ON fc.id = ft.category_id
             LEFT JOIN users u ON ft.user_id = u.id
             ORDER BY ft.last_post_at DESC
             LIMIT ' . $limit
        );

        return ['threads' => $threads];
    }

    /**
     * Notifications summary for current user.
     */
    public static function notificationsSummaryData(array $settings): array
    {
        $db = Database::getInstance();
        $userId = Auth::userId();
        $limit = (int) ($settings['limit'] ?? 5);

        if (!$userId) {
            return ['unread' => 0, 'notifications' => []];
        }

        $unread = $db->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );

        $notifications = $db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit,
            [$userId]
        );

        return [
            'unread'        => (int) $unread,
            'notifications' => $notifications,
        ];
    }

    /**
     * Member profile summary for current user.
     */
    public static function memberProfileData(array $settings): array
    {
        $db = Database::getInstance();
        $userId = Auth::userId();

        if (!$userId) {
            return ['member' => null];
        }

        $member = $db->fetch(
            'SELECT * FROM members WHERE user_id = ?',
            [$userId]
        );

        return ['member' => $member ?: null];
    }
}
