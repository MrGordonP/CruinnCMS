<?php
/**
 * CruinnCMS — CSRF Protection
 *
 * Token-based CSRF protection for all POST forms.
 * Tokens are stored in the session and validated per-request.
 *
 * In forms:   <?= csrf_field() ?>
 * In POST handlers: CSRF::validate() is called automatically by middleware.
 */

namespace Cruinn;

class CSRF
{
    private const TOKEN_LENGTH = 32;

    /**
     * Generate or retrieve the current CSRF token.
     * Token persists for the duration of the session.
     */
    public static function getToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate the submitted CSRF token against the session token.
     * Returns true if valid, false if not.
     */
    public static function validate(?string $submittedToken = null): bool
    {
        $submittedToken ??= $_POST['csrf_token'] ?? $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Regenerate the CSRF token (call after successful validation if desired).
     */
    public static function regenerate(): string
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token on a POST request, aborting with 403 on failure.
     * Convenience wrapper around validate() for use in controllers.
     */
    public static function verify(): void
    {
        if (!self::validate()) {
            http_response_code(403);
            exit('CSRF token invalid.');
        }
    }

    /**
     * Middleware: validate CSRF on all POST requests.
     * Returns null to allow the request, or sends a 403 response.
     */
    public static function middleware(string $uri, string $method): ?string
    {
        if ($method !== 'POST') {
            return null; // Only check POST requests
        }

        if (!self::validate()) {
            http_response_code(403);
            // Return JSON for AJAX requests
            if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'CSRF token expired. Please reload the page and try again.']);
                exit;
            }
            $template = new Template();
            // If this is a /cms/ request, use the standalone platform error — no instance layout
            if (strncmp($uri, '/cms', 4) === 0) {
                $template->setLayout(null);
            }
            echo $template->render('errors/csrf');
            exit;
        }

        return null; // Token valid — proceed
    }
}
