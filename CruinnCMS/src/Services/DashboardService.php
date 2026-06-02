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
            'pages'    => $db->fetchColumn('SELECT COUNT(*) FROM pages_index'),
            'users'    => $db->fetchColumn('SELECT COUNT(*) FROM users'),
            'subjects' => $db->fetchColumn('SELECT COUNT(*) FROM subjects'),
        ];
        if (ModuleRegistry::isActive('articles')) {
            $stats['articles'] = $db->fetchColumn('SELECT COUNT(*) FROM articles');
        }
        if (ModuleRegistry::isActive('events')) {
            $stats['events'] = $db->fetchColumn('SELECT COUNT(*) FROM events');
        }
        if (!empty($settings['show_forum']) && ModuleRegistry::isActive('forum')) {
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
        if (!ModuleRegistry::isActive('articles')) {
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
     * Council stats: document and discussion counts.
     */
    public static function councilStatsData(array $settings): array
    {
        if (!ModuleRegistry::isActive('council')) {
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
     * Recent council documents.
     */
    public static function recentDocumentsData(array $settings): array
    {
        if (!ModuleRegistry::isActive('council')) {
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
     * Active council discussions.
     */
    public static function activeDiscussionsData(array $settings): array
    {
        if (!ModuleRegistry::isActive('council')) {
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
        if (!ModuleRegistry::isActive('events')) {
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
        if (!ModuleRegistry::isActive('forum')) {
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

    // ══════════════════════════════════════════════════════════════
    //  WIDGET CANVAS RENDERING (Stage 3)
    // ══════════════════════════════════════════════════════════════

    /**
     * Render a widget dashboard canvas from blocks.
     * Fetches published blocks for the page and renders them.
     * For module-widget blocks, injects userContext into settings.
     *
     * @param int   $pageId      Dashboard canvas page ID
     * @param array $userContext User context: ['user_id', 'role_id', 'role_level', 'position_ids']
     * @return string Rendered HTML
     */
    public function renderWidgetCanvas(int $pageId, array $userContext): string
    {
        // Fetch published blocks for this dashboard page
        $blocks = $this->db->fetchAll(
            'SELECT * FROM blocks_published
             WHERE page_id = ?
             ORDER BY sort_order ASC',
            [$pageId]
        );

        if (empty($blocks)) {
            return '<div class="dashboard-empty"><p>No widgets configured for this dashboard.</p></div>';
        }

        $html = '';
        $renderService = new CruinnRenderService();

        foreach ($blocks as $block) {
            $blockType = $block['block_type'];

            // For module-widget blocks, inject userContext
            if ($blockType === 'module-widget') {
                $props = json_decode($block['properties'] ?? '{}', true) ?? [];
                $props['_userContext'] = $userContext;
                $block['properties'] = json_encode($props);
            }

            // Render block via CruinnRenderService
            $html .= $renderService->renderBlock($block);
        }

        return $html;
    }

    /**
     * Resolve which dashboard page to show for a user.
     * Resolution order: user → position → role → default admin dashboard.
     *
     * @param int $userId User ID
     * @return int|null Dashboard page ID, or null if none configured
     */
    public function resolveDashboardForUser(int $userId): ?int
    {
        // 1. Check user-specific dashboard
        $pageId = $this->db->fetchColumn(
            'SELECT page_id FROM context_dashboards
             WHERE context_type = ? AND context_id = ?',
            ['user', $userId]
        );
        if ($pageId) {
            return (int) $pageId;
        }

        // 2. Check position dashboards (highest priority position)
        $positionIds = Auth::positionIds();
        if (!empty($positionIds)) {
            // @TODO: when positions have priority/sort_order, use that
            // For now, just use the first position
            $pageId = $this->db->fetchColumn(
                'SELECT page_id FROM context_dashboards
                 WHERE context_type = ? AND context_id = ?',
                ['position', $positionIds[0]]
            );
            if ($pageId) {
                return (int) $pageId;
            }
        }

        // 3. Check role dashboard
        $roleId = Auth::roleId();
        if ($roleId) {
            $pageId = $this->db->fetchColumn(
                'SELECT page_id FROM context_dashboards
                 WHERE context_type = ? AND context_id = ?',
                ['role', $roleId]
            );
            if ($pageId) {
                return (int) $pageId;
            }
        }

        // 4. Fallback: admin role dashboard (default)
        $adminRoleId = $this->db->fetchColumn(
            'SELECT id FROM roles WHERE level >= 100 ORDER BY level DESC LIMIT 1'
        );
        if ($adminRoleId) {
            $pageId = $this->db->fetchColumn(
                'SELECT page_id FROM context_dashboards
                 WHERE context_type = ? AND context_id = ?',
                ['role', $adminRoleId]
            );
            if ($pageId) {
                return (int) $pageId;
            }
        }

        // No dashboard configured
        return null;
    }

    /**
     * Get all widget dashboard canvases (pages with canvas_type='widget-dashboard').
     *
     * @return array[] Dashboard page records
     */
    public function listDashboardCanvases(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM pages_index
             WHERE canvas_type = 'widget-dashboard'
             ORDER BY title ASC"
        );
    }

    /**
     * Get dashboard assignment for a context (role/position/user).
     *
     * @param string $contextType 'role', 'position', or 'user'
     * @param int    $contextId   Context ID
     * @return int|null Assigned dashboard page ID, or null
     */
    public function getDashboardForContext(string $contextType, int $contextId): ?int
    {
        $pageId = $this->db->fetchColumn(
            'SELECT page_id FROM context_dashboards
             WHERE context_type = ? AND context_id = ?',
            [$contextType, $contextId]
        );
        return $pageId ? (int) $pageId : null;
    }

    /**
     * Assign a dashboard to a context (role/position/user).
     *
     * @param string $contextType 'role', 'position', or 'user'
     * @param int    $contextId   Context ID
     * @param int    $pageId      Dashboard page ID
     */
    public function assignDashboard(string $contextType, int $contextId, int $pageId): void
    {
        // Check if assignment already exists
        $existing = $this->db->fetch(
            'SELECT id FROM context_dashboards
             WHERE context_type = ? AND context_id = ?',
            [$contextType, $contextId]
        );

        if ($existing) {
            // Update existing
            $this->db->update(
                'context_dashboards',
                ['page_id' => $pageId, 'created_by' => Auth::userId()],
                'id = ?',
                [$existing['id']]
            );
        } else {
            // Insert new
            $this->db->insert('context_dashboards', [
                'context_type' => $contextType,
                'context_id'   => $contextId,
                'page_id'      => $pageId,
                'created_by'   => Auth::userId(),
            ]);
        }
    }

    /**
     * Remove dashboard assignment for a context.
     *
     * @param string $contextType 'role', 'position', or 'user'
     * @param int    $contextId   Context ID
     */
    public function removeDashboard(string $contextType, int $contextId): void
    {
        $this->db->delete(
            'context_dashboards',
            'context_type = ? AND context_id = ?',
            [$contextType, $contextId]
        );
    }
}
