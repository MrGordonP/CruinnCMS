<?php
/**
 * CruinnCMS — Authentication
 *
 * Session-based authentication with role and group level support.
 * Roles control CMS admin access. Groups control content access.
 * Passwords hashed with bcrypt via password_hash().
 */

namespace Cruinn;

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
     * Get the current user's highest role slug (admin, council, editor, public).
     * Derived from the session role level — no DB query.
     * Returns null if not logged in.
     */
    public static function role(): ?string
    {
        if (!self::check()) {
            return null;
        }
        $level = self::roleLevel();
        if ($level >= 100) return 'admin';
        if ($level >= 50)  return 'council';
        if ($level >= 20)  return 'editor';
        return 'member';
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
     * Check if the current user has at least the given CMS role.
     * Accepts legacy slug names mapped to levels, or a numeric level.
     * admin=100, council=50, editor=20, public=0
     */
    public static function hasRole(string $minimumRole): bool
    {
        $levels = ['public' => 0, 'editor' => 20, 'council' => 50, 'admin' => 100];
        $required = $levels[$minimumRole] ?? 0;
        return self::roleLevel() >= $required;
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
        self::requireLogin();

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
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }
    }

    /**
     * Require the user to have at least the given role.
     * Returns 403 if insufficient permissions.
     */
    public static function requireRole(string $minimumRole): void
    {
        self::requireLogin();

        if (!self::hasRole($minimumRole)) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
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

    // ── Middleware Callbacks ───────────────────────────────────────

    /**
     * Middleware: require admin role for /admin routes.
     * Uses role level (>= 100) with legacy ENUM fallback.
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

        if (self::roleLevel() < 100) {
            http_response_code(403);
            $template = new Template();
            echo $template->render('errors/403');
            exit;
        }

        return null; // Allow request to proceed
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
