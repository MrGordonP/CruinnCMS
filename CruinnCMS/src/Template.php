<?php
/**
 * CruinnCMS — Template Engine
 *
 * Simple PHP-based template renderer with layout inheritance.
 * No external template engine — just PHP includes with output buffering.
 *
 * Templates live in /templates/ and use .php extension.
 * Layout wraps content with header/footer/nav.
 *
 * Usage:
 *   $tpl = new Template();
 *   echo $tpl->render('public/events/index', [
 *       'title'  => 'Upcoming Events',
 *       'events' => $events,
 *   ]);
 */

namespace Cruinn {

class Template
{
    /** @var string Base path to template directory */
    private string $basePath;

    /** @var string|null Default layout template */
    private ?string $layout = 'layout';

    /** @var array Global data available to all templates */
    private static array $globals = [];

    /** @var array CSS files queued by content templates for this request */
    private static array $cssQueue = [];

    /** @var array JS module paths queued by content templates for this request */
    private static array $jsQueue = [];

    /** @var array<string> Extra template base paths registered by modules */
    private static array $extraPaths = [];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__) . '/templates';
    }

    /**
     * Register an additional template base path (e.g. from a module).
     * Paths are checked in registration order after the primary templates/ dir.
     */
    public static function addTemplatePath(string $path): void
    {
        if (!in_array($path, self::$extraPaths, true)) {
            self::$extraPaths[] = $path;
        }
    }

    /**
     * Set a global variable available in all templates.
     * Useful for site name, current user, flash messages, etc.
     */
    public static function addGlobal(string $key, mixed $value): void
    {
        self::$globals[$key] = $value;
    }

    /**
     * Set the layout template for this render cycle.
     * Pass null to render without a layout (for AJAX responses, etc.).
     */
    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Render a template with optional data.
     *
     * @param string $template  Template path relative to /templates/ (no .php extension)
     * @param array  $data      Variables to extract into template scope
     * @return string           Rendered HTML
     */
    public function render(string $template, array $data = []): string
    {
        // Render the content template
        $content = $this->renderPartial($template, $data);

        // Wrap in layout if one is set
        if ($this->layout !== null) {
            $layoutData = array_merge($data, ['content' => $content]);
            $content = $this->renderPartial($this->layout, $layoutData);
        }

        return $content;
    }

    /**
     * Render a partial template (no layout wrapping).
     * Used for includes, components, and AJAX fragments.
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . $template . '.php';

        if (!file_exists($file)) {
            foreach (self::$extraPaths as $extraPath) {
                $candidate = $extraPath . '/' . $template . '.php';
                if (file_exists($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
        }

        if (!file_exists($file)) {
            throw new \RuntimeException("Template not found: {$template} ({$file})");
        }

        // Merge global data with local data (local takes precedence)
        $data = array_merge(self::$globals, $data);

        // Extract variables into scope and capture output
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Escape HTML output to prevent XSS.
     * Use in templates: <?= $this->e($variable) ?>
     * Or use the global helper: <?= e($variable) ?>
     */
    public function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Queue additional CSS files to be emitted by the layout's <head>.
     * Call from content templates before the layout outputs its <head>.
     * Since content is rendered first (then wrapped by layout), this works naturally.
     *
     * Usage in a template:  \Cruinn\Template::requireCss('admin-events.css');
     */
    public static function requireCss(string ...$files): void
    {
        foreach ($files as $file) {
            if (!in_array($file, self::$cssQueue, true)) {
                self::$cssQueue[] = $file;
            }
        }
    }

    /**
     * Return and clear the queued CSS files.
     * Called once by the layout's <head> to emit <link> tags.
     */
    public static function flushCss(): array
    {
        $queue = self::$cssQueue;
        self::$cssQueue = [];
        return $queue;
    }

    /**
     * Queue additional admin JS modules to load at the bottom of admin/layout.php.
     * Path is relative to public/js/admin/ (e.g. 'gallery.js').
     */
    public static function requireJs(string ...$files): void
    {
        foreach ($files as $file) {
            if (!in_array($file, self::$jsQueue, true)) {
                self::$jsQueue[] = $file;
            }
        }
    }

    /**
     * Return and clear the queued JS module paths.
     */
    public static function flushJs(): array
    {
        $queue = self::$jsQueue;
        self::$jsQueue = [];
        return $queue;
    }
}

} // end namespace Cruinn

// ── Global Helper Functions ───────────────────────────────────────

namespace {

    /**
     * Escape HTML — shorthand for use in templates.
     * Usage: <?= e($title) ?>
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Generate a URL path.
     * Usage: <?= url('/events/' . $event['slug']) ?>
     */
    function url(string $path = '/'): string
    {
        $basePath = \Cruinn\App::config('site.base_path', '');
        return rtrim($basePath . $path, '/') ?: '/';
    }

    /**
     * Get a CSRF token field for forms.
     * Usage: <?= csrf_field() ?>
     */
    function csrf_field(): string
    {
        $token = \Cruinn\CSRF::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
    }

    /**
     * Format a date for display.
     * Usage: <?= format_date($event['date_start'], 'j F Y') ?>
     */
    function format_date(?string $datetime, string $format = 'j F Y'): string
    {
        if ($datetime === null) {
            return '';
        }
        $dt = new \DateTime($datetime, new \DateTimeZone('Europe/Dublin'));
        return $dt->format($format);
    }

    /**
     * Truncate text to a maximum length with ellipsis.
     */
    function truncate(?string $text, int $length = 150, string $suffix = '…'): string
    {
        if ($text === null || mb_strlen($text) <= $length) {
            return $text ?? '';
        }
        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * Render a navigation menu by its location slug.
     * Returns an array of menu item arrays with resolved 'href' and nested 'children'.
     * If the menu doesn't exist, returns an empty array.
     *
     * Usage in templates:
     *   <?php foreach (get_menu('main') as $item): ?>
     *       <li><a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a></li>
     *   <?php endforeach; ?>
     */
    function get_menu(string $location): array
    {
        static $cache = [];

        if (isset($cache[$location])) {
            return $cache[$location];
        }

        $db = \Cruinn\Database::getInstance();

        $menu = $db->fetch('SELECT id FROM menus WHERE location = ? LIMIT 1', [$location]);
        if (!$menu) {
            $cache[$location] = [];
            return [];
        }

        $rows = $db->fetchAll(
            'SELECT mi.*, p.slug AS page_slug
             FROM menu_items mi
             LEFT JOIN pages p ON mi.page_id = p.id
             WHERE mi.menu_id = ? AND mi.is_active = 1
             ORDER BY mi.sort_order ASC',
            [$menu['id']]
        );

        $loggedIn = \Cruinn\Auth::check();
        $userRole = $loggedIn ? (\Cruinn\Auth::user()['role'] ?? 'member') : null;
        $roleLevels = ['public' => 0, 'member' => 20, 'council' => 50, 'admin' => 100];
        $userLevel = $roleLevels[$userRole] ?? 0;

        // Filter by visibility and role
        $rows = array_filter($rows, function ($row) use ($loggedIn, $userLevel, $roleLevels) {
            $vis = $row['visibility'] ?? 'always';
            if ($vis === 'logged_in' && !$loggedIn) return false;
            if ($vis === 'logged_out' && $loggedIn) return false;
            if (!empty($row['min_role'])) {
                $reqLevel = $roleLevels[$row['min_role']] ?? 0;
                if ($userLevel < $reqLevel) return false;
            }
            return true;
        });
        $rows = array_values($rows);

        // Resolve hrefs
        foreach ($rows as &$row) {
            $row['href'] = match ($row['link_type']) {
                'page'    => '/' . ($row['page_slug'] ?? ''),
                'route'   => $row['route'] ?? '/',
                'url'     => $row['url'] ?? '#',
                'subject' => '#', // Subjects don't have a public page yet
                default   => '#',
            };
            $row['target'] = $row['open_new_tab'] ? '_blank' : '';
            $row['children'] = [];
        }
        unset($row);

        // Build tree
        $byId = [];
        foreach ($rows as &$row) {
            $byId[$row['id']] = &$row;
        }
        unset($row);

        $tree = [];
        foreach ($byId as &$row) {
            if ($row['parent_id'] && isset($byId[$row['parent_id']])) {
                $byId[$row['parent_id']]['children'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }
        unset($row);

        $cache[$location] = $tree;
        return $tree;
    }

    /**
     * Return unread notification count for the logged-in user.
     */
    function unread_notifications_count(): int
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        if (!\Cruinn\Auth::check()) {
            $cache = 0;
            return $cache;
        }

        try {
            $db = \Cruinn\Database::getInstance();
            $cache = (int)$db->fetchColumn(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
                [(int)\Cruinn\Auth::userId()]
            );
        } catch (\Throwable $e) {
            $cache = 0;
        }

        return $cache;
    }

    /**
     * Sanitise HTML content for safe output.
     * Allows a safe subset of tags and attributes used in rich text editing.
     * Strips scripts, event handlers, and dangerous content.
     */
    function sanitise_html(?string $html): string
    {
        if ($html === null || $html === '') return '';

        $allowedTags = '<p><br><strong><b><em><i><u><s><strike><del>'
            . '<h1><h2><h3><h4><h5><h6><blockquote><pre><code>'
            . '<ul><ol><li><a><img><figure><figcaption><hr><sub><sup><span><div>';

        $html = strip_tags($html, $allowedTags);

        // Remove event handler attributes (on*) and javascript: URLs
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]*/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $html);
        $html = preg_replace('/src\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'src=""', $html);

        return $html;
    }

    /**
     * Return an inline SVG icon for an OAuth provider.
     * Used on login/register pages for social sign-in buttons.
     */
    function oauth_icon(string $provider): string
    {
        return match ($provider) {
            'google'    => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A10.96 10.96 0 0 0 1 12c0 1.78.42 3.46 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
            'facebook'  => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'twitter'   => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'github'    => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
            'microsoft' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#F25022" d="M1 1h10v10H1z"/><path fill="#7FBA00" d="M13 1h10v10H13z"/><path fill="#00A4EF" d="M1 13h10v10H1z"/><path fill="#FFB900" d="M13 13h10v10H13z"/></svg>',
            'linkedin'  => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#0A66C2" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            default => '',
        };
    }
}
