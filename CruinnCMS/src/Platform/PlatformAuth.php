<?php
/**
 * CMS Platform — Authentication
 *
 * Manages session-based authentication for the top-level /cms/ platform area.
 * Credentials and state are read from config/CruinnCMS.php.
 * This is entirely separate from any instance's user/session system so that
 * platform access is not blocked by a broken instance database.
 */

namespace Cruinn\Platform;

class PlatformAuth
{
    private const SESSION_KEY = 'cms_platform_auth';

    // ── Credential loading ────────────────────────────────────────

    private static function credential(): array
    {
        static $cred = null;
        if ($cred === null) {
            $path = dirname(__DIR__, 2) . '/config/CruinnCMS.php';
            $cred = file_exists($path) ? require $path : [];
        }
        return $cred;
    }

    // ── Session ───────────────────────────────────────────────────

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function login(string $username, string $password): bool
    {
        $cred = self::credential();

        if (empty($cred['username']) || empty($cred['password_hash'])) {
            return false;
        }

        if ($username !== $cred['username']) {
            return false;
        }

        if (!password_verify($password, $cred['password_hash'])) {
            return false;
        }

        $_SESSION[self::SESSION_KEY] = [
            'username'  => $username,
            'logged_in' => time(),
        ];

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function username(): string
    {
        return $_SESSION[self::SESSION_KEY]['username'] ?? '';
    }

    // ── Config helpers ────────────────────────────────────────────

    public static function isMultiInstance(): bool
    {
        return !empty(self::credential()['multi_instance']);
    }

    public static function credentialFileExists(): bool
    {
        return file_exists(dirname(__DIR__, 2) . '/config/CruinnCMS.php');
    }

    public static function isInitialized(): bool
    {
        return !empty(self::credential()['initialized']);
    }

    public static function dbConfig(): array
    {
        return self::credential()['db'] ?? [];
    }

    // ── Middleware ────────────────────────────────────────────────

    /**
     * Router middleware: redirect to /cms/login if not authenticated.
     * Passes through /cms/login itself to avoid redirect loops.
     */
    public static function middleware(string $uri, string $method): ?string
    {
        $trimmed = rtrim($uri, '/');

        // Always allow the login page and the install wizard
        if ($trimmed === '/cms/login' || $trimmed === '/cms/install') {
            return null;
        }

        // Not yet installed — send everything to the install wizard
        if (!self::isInitialized()) {
            header('Location: /cms/install');
            exit;
        }

        if (!self::check()) {
            header('Location: /cms/login');
            exit;
        }

        return null;
    }
}
