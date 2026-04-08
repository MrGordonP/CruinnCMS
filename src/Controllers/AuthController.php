<?php
/**
 * CruinnCMS — Authentication Controller
 *
 * Handles login/logout.
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Mailer;
use Cruinn\App;
use Cruinn\Services\OAuthService;

class AuthController extends BaseController
{
    /**
     * GET /login — Show the login form.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect($this->defaultRedirectForRole());
        }

        $this->render('public/login', [
            'title' => 'Login',
            'oauth_providers' => OAuthService::enabledProviders(),
        ]);
    }

    /**
     * POST /login — Process login attempt.
     */
    public function login(): void
    {
        $email    = $this->input('email');
        $password = $this->input('password');

        if (empty($email) || empty($password)) {
            Auth::flash('error', 'Please enter your email and password.');
            $this->redirect('/login');
        }

        $user = Auth::attempt($email, $password);

        if ($user) {
            $this->logActivity('login', 'user', $user['id']);

            // Redirect to where they were trying to go, but never back into admin/council
            // on first load — everyone lands on the portal, then navigates from there.
            $stored = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            $isPrivilegedRoute = $stored && (
                str_starts_with($stored, '/admin') || str_starts_with($stored, '/council')
            );
            $redirect = (!$stored || $isPrivilegedRoute) ? $this->defaultRedirectForRole() : $stored;
            $this->redirect($redirect);
        }

        // Log failed login attempt for security auditing
        $this->logActivity('login_failed', 'user', null, 'Failed login attempt for: ' . $email);

        $loginError = $_SESSION['_login_error'] ?? 'invalid';
        unset($_SESSION['_login_error']);

        if ($loginError === 'locked') {
            Auth::flash('error', 'Your account has been temporarily locked due to too many failed login attempts. Please try again later or reset your password.');
        } else {
            Auth::flash('error', 'Invalid email or password.');
        }
        $this->redirect('/login');
    }

    /**
     * GET /logout — Log out and redirect to homepage.
     */
    public function logout(): void
    {
        $userId = Auth::userId();
        if ($userId) {
            $this->logActivity('logout', 'user', $userId);
        }
        Auth::logout();
        $this->redirect('/');
    }

    // ── Password Reset ────────────────────────────────────────────

    /**
     * GET /forgot-password — Show the forgot password form.
     */
    public function showForgotPassword(): void
    {
        $this->render('public/forgot-password', [
            'title' => 'Forgot Password',
        ]);
    }

    /**
     * POST /forgot-password — Send a password reset link.
     */
    public function forgotPassword(): void
    {
        $email = strtolower(trim($this->input('email', '')));

        if (empty($email)) {
            Auth::flash('error', 'Please enter your email address.');
            $this->redirect('/forgot-password');
        }

        $user = $this->db->fetch('SELECT id, email, display_name FROM users WHERE email = ? AND active = 1', [$email]);

        if ($user) {
            // Generate token — store hash in DB, send raw to user
            $rawToken   = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);
            $expiry     = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $this->db->update('users', [
                'password_reset_token'  => $hashedToken,
                'password_reset_expiry' => $expiry,
            ], 'id = ?', [$user['id']]);

            // Build reset link
            $baseUrl = rtrim(App::config('site.url', 'http://localhost:8080'), '/');
            $resetUrl = $baseUrl . '/reset-password/' . $rawToken;

            $html = '<p>Hello ' . htmlspecialchars($user['display_name']) . ',</p>'
                  . '<p>You requested a password reset. Click the link below to set a new password:</p>'
                  . '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>'
                  . '<p>This link will expire in 1 hour.</p>'
                  . '<p>If you did not request this, you can safely ignore this email.</p>';

            Mailer::send($user['email'], 'Password Reset — CruinnCMS', $html);

            $this->logActivity('password_reset_request', 'user', $user['id'], 'Reset link sent to: ' . $email, $user['id']);
        }

        // Log all reset requests (even for non-existent emails) for security
        if (!$user) {
            $this->logActivity('password_reset_request', 'user', null, 'Reset requested for unknown email: ' . $email);
        }

        // Always show same message to prevent email enumeration
        Auth::flash('success', 'If an account with that email exists, a password reset link has been sent.');
        $this->redirect('/forgot-password');
    }

    /**
     * GET /reset-password/{token} — Show the reset password form.
     */
    public function showResetPassword(string $token): void
    {
        // Validate the token
        $hashedToken = hash('sha256', $token);
        $user = $this->db->fetch(
            'SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expiry > NOW()',
            [$hashedToken]
        );

        if (!$user) {
            Auth::flash('error', 'This password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }

        $this->render('public/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
        ]);
    }

    /**
     * POST /reset-password/{token} — Process the password reset.
     */
    public function resetPassword(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->db->fetch(
            'SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expiry > NOW()',
            [$hashedToken]
        );

        if (!$user) {
            Auth::flash('error', 'This password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }

        $password = $this->input('password');
        $confirm  = $this->input('password_confirm');

        if (empty($password) || strlen($password) < 8) {
            Auth::flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/reset-password/' . $token);
        }

        if ($password !== $confirm) {
            Auth::flash('error', 'Passwords do not match.');
            $this->redirect('/reset-password/' . $token);
        }

        // Update password and clear token
        $this->db->update('users', [
            'password_hash'         => Auth::hashPassword($password),
            'password_reset_token'  => null,
            'password_reset_expiry' => null,
        ], 'id = ?', [$user['id']]);

        $this->logActivity('password_reset', 'user', $user['id'], null, $user['id']);
        Auth::flash('success', 'Your password has been reset. You can now log in.');
        $this->redirect('/login');
    }

    /**
     * Determine the appropriate landing page for the current user's role.
     */
    private function defaultRedirectForRole(): string
    {
        // Try DB-driven redirect first
        $roleId = Auth::roleId();
        if ($roleId) {
            $db = \Cruinn\Database::getInstance();
            $redirect = $db->fetchColumn('SELECT default_redirect FROM roles WHERE id = ?', [$roleId]);
            if ($redirect) {
                return $redirect;
            }
        }

        // Legacy fallback — all roles land on the user portal; admin/council
        // can navigate to their control panel from there.
        return match (Auth::role()) {
            'admin'   => '/users/profile',
            'council' => '/users/profile',
            'member'  => '/users/profile',
            default   => '/',
        };
    }
}
