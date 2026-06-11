<?php
/**
 * CruinnCMS — Authentication
 *
 * Session-based authentication with role and group level support.
 * Roles control CMS admin access. Groups control content access.
 * Passwords hashed with bcrypt via password_hash().
 */

namespace Cruinn;

// Last edit: 2026-06-11 15:35 UTC.

class Auth
{
    /** @var array|null Cached permissions for current session */
    private static ?array $permissionCache = null;
    /**
     * Start the session if not already started.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = App::config('session');
            session_name($config['name']);
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => $config['lifetime'],
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Attempt to log in a user with email and password.
     * Returns the user row on success, false on failure.
     *
     * Enforces the configurable account-lockout policy from ACP settings:
     *   auth.max_login_attempts — number of failures before lockout
     *   auth.lockout_duration   — seconds the account stays locked
     *
     * Returns false AND sets a specific error message in the session on
     * lockout so the AuthController can surface a helpful message:
     *   $_SESSION['_login_error'] = 'locked'  → account locked
     *   $_SESSION['_login_error'] = 'invalid' → wrong credentials
     */
    public static function attempt(string $email, string $password): array|false
    {
        $db = Database::getInstance();

        // Check for unverified account first — surface a helpful message rather than
        // generic "invalid credentials" which would leave the user confused.
        $unverified = $db->fetch(
            'SELECT id FROM users WHERE email = ? AND active = 0 LIMIT 1',
            [strtolower(trim($email))]
        );
        if ($unverified) {
            $_SESSION['_login_error'] = 'unverified';
            return false;
        }

        $user = $db->fetch(
            'SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1',
            [strtolower(trim($email))]
        );

        // ── Lockout check ────────────────────────────────────────────
        if ($user && !empty($user['locked_until'])) {
            if (strtotime($user['locked_until']) > time()) {
                $_SESSION['_login_error'] = 'locked';
                return false;
            }
            // Lock has expired — reset counters
            $db->update('users', ['failed_logins' => 0, 'locked_until' => null], 'id = ?', [$user['id']]);
            $user['failed_logins'] = 0;
            $user['locked_until']  = null;
        }

        if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']         = $user['id'];
            $_SESSION['user_name']       = $user['display_name'];
            $_SESSION['user_role_level'] = self::loadRoleLevel($user['id']);
            $_SESSION['user_group_level']= self::loadGroupLevel($user['id']);
            $_SESSION['user_position_ids'] = self::loadPositionIds($user['id']);
            $_SESSION['login_time']      = time();
            unset($_SESSION['_login_error']);

            // Clear permission cache so it reloads for new session
            self::$permissionCache = null;
            unset($_SESSION['user_permissions']);

            // Reset failed-login counter and update last_login
            $db->update('users', [
                'last_login'    => date('Y-m-d H:i:s'),
                'failed_logins' => 0,
                'locked_until'  => null,
            ], 'id = ?', [$user['id']]);

            // Rehash password if algorithm has changed
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $db->update(
                    'users',
                    ['password_hash' => password_hash($password, PASSWORD_DEFAULT)],
                    'id = ?',
                    [$user['id']]
                );
            }

            return $user;
        }

        // ── Failed attempt — increment counter and possibly lock ──────
        if ($user) {
            $maxAttempts     = (int) App::config('auth.max_login_attempts', 5);
            $lockoutDuration = (int) App::config('auth.lockout_duration', 900);
            $newCount = ($user['failed_logins'] ?? 0) + 1;

            $updates = ['failed_logins' => $newCount];
            if ($newCount >= $maxAttempts) {
                $updates['locked_until'] = date('Y-m-d H:i:s', time() + $lockoutDuration);
                $_SESSION['_login_error'] = 'locked';
            } else {
                $_SESSION['_login_error'] = 'invalid';
            }

            $db->update('users', $updates, 'id = ?', [$user['id']]);
        } else {
            $_SESSION['_login_error'] = 'invalid';
        }

        return false;
    }

    /**
     * Log in a user by their ID (no password check).
     * Used by OAuth flows where the provider has already authenticated the user.
     */
    public static function loginById(int $userId): array|false
    {
        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM users WHERE id = ? AND active = 1 LIMIT 1',
            [$userId]
        );

        if (!$user) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user_id']         = $user['id'];
        $_SESSION['user_name']       = $user['display_name'];
        $_SESSION['user_role_level'] = self::loadRoleLevel($user['id']);
        $_SESSION['user_group_level']= self::loadGroupLevel($user['id']);
        $_SESSION['user_position_ids'] = self::loadPositionIds($user['id']);
        $_SESSION['login_time']      = time();

        self::$permissionCache = null;
        unset($_SESSION['user_permissions']);

        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        return $user;
    }

    /**
     * Log out the current user.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 3600,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Check if a user is currently logged in.
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get the current user's ID, or null if not logged in.
     */
    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get the current user's maximum role level (CMS admin access).
     * Returns 0 if not logged in or no roles assigned.
     */
    public static function roleLevel(): int
    {
        return (int) ($_SESSION['user_role_level'] ?? 0);
    }

    /**
     * Get the current user's primary role ID (highest level role).
     * Returns null if not logged in or no roles assigned.
     */
    public static function roleId(): ?int
    {
        $userId = self::userId();
        if (!$userId) {
            return null;
        }
        $db = Database::getInstance();
        $id = $db->fetchColumn(
            'SELECT r.id FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?
             ORDER BY r.level DESC
             LIMIT 1',
            [$userId]
        );
        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Get the current user's maximum group level (content access).
     * Returns 0 if not logged in or no groups assigned.
     */
    public static function groupLevel(): int
    {
        return (int) ($_SESSION['user_group_level'] ?? 0);
    }

    /**
     * Load the maximum role level for a user from the DB.
     * Called at login — result stored in session.
     */
    private static function loadRoleLevel(int $userId): int
    {
        $db = Database::getInstance();
        $level = $db->fetchColumn(
            'SELECT MAX(r.level) FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            [$userId]
        );
        return $level !== false && $level !== null ? (int) $level : 0;
    }

    /**
     * Load the maximum group level for a user from the DB.
     * Called at login — result stored in session.
     */
    private static function loadGroupLevel(int $userId): int
    {
        $db = Database::getInstance();
        $level = $db->fetchColumn(
            'SELECT MAX(g.level) FROM `groups` g JOIN user_groups ug ON ug.group_id = g.id WHERE ug.user_id = ?',
            [$userId]
        );
        return $level !== false && $level !== null ? (int) $level : 0;
    }

    /**
     * Check if the current user is an admin (role level >= 100).
     */
    public static function isAdmin(): bool
    {
        return self::roleLevel() >= 100;
    }

    /**
     * Check if a user is currently logged in.
     * Alias for check() — kept for semantic clarity.
     */
    public static function isLoggedIn(): bool
    {
        return self::check();
    }

    /**
     * Check if the current user has at least the given group (content access) level.
     */
    public static function hasGroupLevel(int $minimumLevel): bool
    {
        return self::groupLevel() >= $minimumLevel;
    }

    // ── Permission Checks ─────────────────────────────────────────

    /**
     * Get all permission slugs for the current user.
     * Cached in session to avoid repeated DB queries.
     *
     * @return string[]
     */
    public static function permissions(): array
    {
        // Memory cache
        if (self::$permissionCache !== null) {
            return self::$permissionCache;
        }

        // Session cache
        if (isset($_SESSION['user_permissions'])) {
            self::$permissionCache = $_SESSION['user_permissions'];
            return self::$permissionCache;
        }

        if (!self::userId()) {
            self::$permissionCache = [];
            $_SESSION['user_permissions'] = [];
            return [];
        }

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT DISTINCT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?',
            [self::userId()]
        );

        $perms = array_column($rows, 'slug');
        self::$permissionCache = $perms;
        $_SESSION['user_permissions'] = $perms;
        return $perms;
    }

    /**
     * Check if the current user has a specific permission.
     */
    public static function can(string $permission): bool
    {
        // Admin role level (>=100) always has all permissions
        if (self::roleLevel() >= 100) {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    /**
     * Require the user to have a specific permission. 403 if not.
     */
    public static function requirePermission(string $permission): void
    {
        self::requireLoggedIn();

        if (!self::can($permission)) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }
    }

    /**
     * Clear the cached permissions (e.g. after role change).
     */
    public static function clearPermissionCache(): void
    {
        self::$permissionCache = null;
        unset($_SESSION['user_permissions']);
    }

    /**
     * Require the user to be logged in. Redirects to login page if not.
     */
    public static function requireLoggedIn(): void
    {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }
    }

    /**
     * Backward-compatible alias for older module code paths.
     */
    public static function requireLogin(): void
    {
        self::requireLoggedIn();
    }

    /**
     * Require the user to be an admin (role level >= 100).
     * Returns 403 if not admin.
     */
    public static function requireAdmin(): void
    {
        self::requireLoggedIn();

        if (!self::isAdmin()) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }
    }

    /**
     * Require the user to have at least the given role level.
     * Returns 403 if insufficient permissions.
     */
    public static function requireLevel(int $minimumLevel): void
    {
        self::requireLoggedIn();

        if (self::roleLevel() < $minimumLevel) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }
    }

    /**
     * Require access to a specific admin area.
     * Passes if admin OR user/position has a grant for this area.
     *
     * @param string $slug Admin area slug (e.g. 'blog', 'forum', 'events').
     */
    public static function requireAdminArea(string $slug): void
    {
        self::requireLoggedIn();

        // Admin role level always has access to all areas
        if (self::isAdmin()) {
            return;
        }

        // Check role-based grants
        $roleId = self::roleId();
        if ($roleId && self::hasAreaGrant($slug, 'role', $roleId)) {
            return;
        }

        // Check position-based grants (Stage 4 — organisation module)
        $positionIds = self::positionIds();
        foreach ($positionIds as $positionId) {
            if (self::hasAreaGrant($slug, 'position', $positionId)) {
                return;
            }
        }

        // No access — return 403
        http_response_code(403);
        $template = new Template();
        echo $template->render('errors/403');
        exit;
    }

    /**
     * Check if a grant exists for the given area, context type, and context ID.
     * Result is cached in session for the request lifetime.
     *
     * @param string $areaSlug    Admin area slug
     * @param string $contextType 'role' or 'position'
     * @param int    $contextId   Role ID or position ID
     * @return bool
     */
    private static function hasAreaGrant(string $areaSlug, string $contextType, int $contextId): bool
    {
        // Session cache key
        $cacheKey = "area_grant_{$areaSlug}_{$contextType}_{$contextId}";
        if (isset($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }

        $db = Database::getInstance();
        $exists = $db->fetchColumn(
            'SELECT COUNT(*) FROM admin_area_grants
             WHERE area_slug = ? AND context_type = ? AND context_id = ?',
            [$areaSlug, $contextType, $contextId]
        );

        $result = (int) $exists > 0;
        $_SESSION[$cacheKey] = $result;
        return $result;
    }

    /**
     * Map a URI to an admin area slug based on the admin_areas.php config.
     * Returns the area slug if the URI matches a grantable area, null otherwise.
     *
     * @param string $uri Request URI (e.g. '/admin/media/list')
     * @return string|null Area slug or null if not grantable
     */
    private static function getAreaSlugForUri(string $uri): ?string
    {
        static $areas = null;
        if ($areas === null) {
            $areasPath = App::path('config/admin_areas.php');
            $areas = file_exists($areasPath) ? require $areasPath : [];
        }

        // Check each area's route patterns
        foreach ($areas as $slug => $config) {
            $patterns = $config['routes'] ?? [];
            foreach ($patterns as $pattern) {
                // Convert glob pattern to regex
                $regex = '#^' . str_replace(['\*', '/'], ['.*', '\/'], preg_quote($pattern, '#')) . '#';
                if (preg_match($regex, $uri)) {
                    return $slug;
                }
            }
        }

        // No match — this is a non-grantable admin route (users, roles, settings, etc.)
        return null;
    }

    /**
     * Get the current user's organisation position IDs.
     * Returns array of organisation_officers.id where user_id matches and active = 1.
     * Returns empty array if not logged in or organisation module inactive.
     *
     * Cached in session for performance. Position changes require re-login to reflect.
     *
     * @return int[]
     */
    public static function positionIds(): array
    {
        if (!self::check()) {
            return [];
        }

        // Check if organisation module is active
        if (!class_exists('Cruinn\Modules\ModuleRegistry') || !\Cruinn\Modules\ModuleRegistry::isActive('organisation')) {
            return [];
        }

        // Return cached value if present
        if (isset($_SESSION['user_position_ids']) && is_array($_SESSION['user_position_ids'])) {
            return $_SESSION['user_position_ids'];
        }

        // Load from database and cache
        $_SESSION['user_position_ids'] = self::loadPositionIds(self::userId());
        return $_SESSION['user_position_ids'];
    }

    /**
     * Load organisation position IDs for a user from the DB.
     * Called at login and when positions are modified.
     *
     * @return int[]
     */
    private static function loadPositionIds(int $userId): array
    {
        $db = Database::getInstance();

        // Check if organisation_officers table exists
        try {
            $ids = $db->fetchColumn(
                'SELECT id FROM organisation_officers WHERE user_id = ? AND active = 1 ORDER BY sort_order',
                [$userId],
                \PDO::FETCH_COLUMN | \PDO::FETCH_NUM
            );
            return is_array($ids) ? array_map('intval', $ids) : [];
        } catch (\Throwable $e) {
            // Table doesn't exist or query failed — module not installed or misconfigured
            error_log("Auth::loadPositionIds() failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Refresh cached position IDs for the current user.
     * Call this after modifying organisation_officers assignments.
     */
    public static function refreshPositionIds(): void
    {
        if (self::check()) {
            $_SESSION['user_position_ids'] = self::loadPositionIds(self::userId());
        }
    }

    /**
     * Get the current logged-in user's full data from the database.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        $db = Database::getInstance();
        return $db->fetch('SELECT * FROM users WHERE id = ?', [self::userId()]);
    }

    /**
     * Hash a password for storage.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // ── Flash Messages ────────────────────────────────────────────

    /**
     * Set a flash message to display on the next page load.
     * Types: success, error, warning, info
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash_messages'][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Get and clear all flash messages.
     */
    public static function getFlashes(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    // ══════════════════════════════════════════════════════════════
    //  DEPRECATED COMPATIBILITY SHIMS (Stage 1 refactor)
    // ══════════════════════════════════════════════════════════════
    // ══════════════════════════════════════════════════════════════

    // ── Middleware Callbacks ───────────────────────────────────────

    /**
     * Middleware: require admin role OR area grant for /admin routes.
     * Admin role (level >= 100) always passes.
     * Non-admins must have an admin_area_grant for the specific section.
     */
    public static function adminMiddleware(string $uri, string $method): ?string
    {
        // Platform passthrough handles its own auth — let it through
        if ($uri === '/admin/platform-passthrough') {
            return null;
        }

        // Platform editor mode: platform auth is higher trust than instance auth.
        // Allow all /admin/* AJAX calls through when editing the platform itself.
        if (!empty($_SESSION['_platform_editor_instance'])
            && \Cruinn\Platform\PlatformAuth::check()
        ) {
            return null;
        }

        if (!self::check()) {
            // Return JSON for AJAX requests
            if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired. Please reload the page and log in again.']);
                exit;
            }
            $_SESSION['redirect_after_login'] = $uri;
            header('Location: /login');
            exit;
        }

        // Admin role always has access
        if (self::isAdmin()) {
            return null;
        }

        // Non-admin: check if this URI maps to a grantable area
        $areaSlug = self::getAreaSlugForUri($uri);
        if ($areaSlug) {
            // Check role grant
            $roleId = self::roleId();
            if ($roleId && self::hasAreaGrant($areaSlug, 'role', $roleId)) {
                return null;
            }

            // Check position grants
            $positionIds = self::positionIds();
            foreach ($positionIds as $positionId) {
                if (self::hasAreaGrant($areaSlug, 'position', $positionId)) {
                    return null;
                }
            }
        }

        // No admin role and no area grant — deny access
        http_response_code(403);
        $template = new Template();
        echo $template->render('errors/403');
        exit;
    }

    /**
     * Middleware: require council-level group access for /council routes.
     */
    public static function councilMiddleware(string $uri, string $method): ?string
    {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $uri;
            header('Location: /login');
            exit;
        }

        if (self::groupLevel() < 50) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }

        return null;
    }

    /**
     * Middleware: require member-level group access for /members routes.
     */
    public static function memberMiddleware(string $uri, string $method): ?string
    {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $uri;
            header('Location: /login');
            exit;
        }

        if (self::groupLevel() < 20) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }

        return null;
    }
}
