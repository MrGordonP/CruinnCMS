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
        } elseif ($loginError === 'unverified') {
            Auth::flash('error', 'Please verify your email address before logging in. Check your inbox for the verification link.');
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

    // ── User Profile ───────────────────────────────────────────────

    /**
     * GET /profile — Show the logged-in user's profile.
     */
    public function showProfile(): void
    {
        if (!Auth::check()) {
            $_SESSION['redirect_after_login'] = '/profile';
            $this->redirect('/login');
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [Auth::userId()]);
        if (!$user) {
            Auth::logout();
            $this->redirect('/login');
        }

        $this->render('public/profile', [
            'title' => 'My Profile',
            'user'  => $user,
        ]);
    }

    /**
     * POST /profile — Update the logged-in user's profile.
     */
    public function updateProfile(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $userId = Auth::userId();
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            Auth::logout();
            $this->redirect('/login');
        }

        $displayName = trim($this->input('display_name', ''));
        $email = strtolower(trim($this->input('email', '')));
        $currentPassword = $this->input('current_password', '');
        $newPassword = $this->input('new_password', '');
        $confirmPassword = $this->input('confirm_password', '');

        $errors = [];

        // Validate display name
        if (empty($displayName)) {
            $errors['display_name'] = 'Display name is required.';
        }

        // Validate email
        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        } else {
            // Check for uniqueness (excluding self)
            $existing = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM users WHERE email = ? AND id != ?',
                [$email, $userId]
            );
            if ($existing) {
                $errors['email'] = 'This email is already in use.';
            }
        }

        // Password change (optional)
        if (!empty($newPassword) || !empty($confirmPassword)) {
            if (empty($currentPassword)) {
                $errors['current_password'] = 'Current password is required to set a new password.';
            } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            }

            if (strlen($newPassword) < 8) {
                $errors['new_password'] = 'New password must be at least 8 characters.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }

        if (!empty($errors)) {
            $this->render('public/profile', [
                'title'  => 'My Profile',
                'user'   => array_merge($user, ['display_name' => $displayName, 'email' => $email]),
                'errors' => $errors,
            ]);
            return;
        }

        // Update user
        $updateData = [
            'display_name' => $displayName,
            'email'        => $email,
        ];

        if (!empty($newPassword)) {
            $updateData['password_hash'] = Auth::hashPassword($newPassword);
        }

        $this->db->update('users', $updateData, 'id = ?', [$userId]);

        $this->logActivity('profile_update', 'user', $userId, 'Profile updated');
        Auth::flash('success', 'Your profile has been updated.');
        $this->redirect('/profile');
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
            'admin'   => '/profile',
            'council' => '/profile',
            'member'  => '/profile',
            default   => '/',
        };
    }

    // ── Registration ───────────────────────────────────────────────

    /**
     * GET /register — Show the registration form.
     */
    public function showRegister(): void
    {
        if (Auth::check()) {
            $this->redirect($this->defaultRedirectForRole());
        }

        $this->render('public/register', [
            'title'          => 'Create Account',
            'oauth_providers' => OAuthService::enabledProviders(),
        ]);
    }

    /**
     * POST /register — Create a new account and send verification email.
     */
    public function register(): void
    {
        if (Auth::check()) {
            $this->redirect($this->defaultRedirectForRole());
        }

        $displayName = trim($this->input('display_name', ''));
        $email       = strtolower(trim($this->input('email', '')));
        $password    = $this->input('password', '');
        $confirm     = $this->input('password_confirm', '');

        $errors = [];

        if (empty($displayName)) {
            $errors['display_name'] = 'Please enter your name.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $existing = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM users WHERE email = ?', [$email]
            );
            if ($existing) {
                $errors['email'] = 'An account with this email address already exists.';
            }
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->render('public/register', [
                'title'           => 'Create Account',
                'oauth_providers' => OAuthService::enabledProviders(),
                'errors'          => $errors,
                'old'             => ['display_name' => $displayName, 'email' => $email],
            ]);
            return;
        }

        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiry      = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->db->insert('users', [
            'email'                => $email,
            'password_hash'        => Auth::hashPassword($password),
            'display_name'         => $displayName,
            'role'                 => 'public',
            'active'               => 0,
            'email_verify_token'   => $hashedToken,
            'email_verify_expiry'  => $expiry,
        ]);

        $this->sendVerificationEmail($email, $displayName, $rawToken);
        $this->logActivity('register', 'user', null, 'New registration: ' . $email);

        $this->render('public/verify-email-sent', [
            'title' => 'Verify Your Email',
            'email' => $email,
        ]);
    }

    /**
     * GET /verify-email/{token} — Verify email address and activate account.
     */
    public function verifyEmail(string $token): void
    {
        $hashedToken = hash('sha256', $token);

        $user = $this->db->fetch(
            'SELECT * FROM users WHERE email_verify_token = ? AND email_verify_expiry > NOW() LIMIT 1',
            [$hashedToken]
        );

        if (!$user) {
            Auth::flash('error', 'This verification link is invalid or has expired. Please register again or contact support.');
            $this->redirect('/register');
        }

        $this->db->update('users', [
            'active'               => 1,
            'email_verify_token'   => null,
            'email_verify_expiry'  => null,
        ], 'id = ?', [$user['id']]);

        // Auto-match to a membership record if email matches
        $this->matchMembershipRecord($user['id'], $user['email']);

        $this->logActivity('email_verified', 'user', $user['id'], null, $user['id']);

        // Log them straight in
        Auth::loginById($user['id']);
        Auth::flash('success', 'Your email has been verified. Welcome!');
        $this->redirect('/profile');
    }

    // ── Private helpers ────────────────────────────────────────────

    private function sendVerificationEmail(string $email, string $displayName, string $rawToken): void
    {
        $baseUrl = rtrim(App::config('site.url', 'http://localhost:8080'), '/');
        $verifyUrl = $baseUrl . '/verify-email/' . $rawToken;

        $html = '<p>Hello ' . htmlspecialchars($displayName) . ',</p>'
              . '<p>Thanks for creating an account. Please verify your email address by clicking the link below:</p>'
              . '<p><a href="' . $verifyUrl . '">' . $verifyUrl . '</a></p>'
              . '<p>This link will expire in 24 hours.</p>'
              . '<p>If you did not create an account, you can safely ignore this email.</p>';

        Mailer::send($email, 'Verify your email address', $html);
    }

    private function linkOAuthAccount(int $userId, array $identity): void
    {
        $this->db->insert('user_oauth_accounts', [
            'user_id'      => $userId,
            'provider'     => $identity['provider'],
            'provider_uid' => $identity['uid'],
            'email'        => $identity['email'],
            'display_name' => $identity['name'],
            'avatar_url'   => $identity['avatar'],
            'access_token' => $identity['access_token'],
            'refresh_token' => $identity['refresh_token'],
            'token_expires' => $identity['expires']
                ? date('Y-m-d H:i:s', time() + (int) $identity['expires'])
                : null,
        ]);
    }

    private function updateOAuthAccount(int $userId, array $identity): void
    {
        $this->db->execute(
            'UPDATE user_oauth_accounts
             SET display_name = ?, avatar_url = ?, access_token = ?, refresh_token = ?, token_expires = ?, updated_at = NOW()
             WHERE user_id = ? AND provider = ? AND provider_uid = ?',
            [
                $identity['name'],
                $identity['avatar'],
                $identity['access_token'],
                $identity['refresh_token'],
                $identity['expires'] ? date('Y-m-d H:i:s', time() + (int) $identity['expires']) : null,
                $userId,
                $identity['provider'],
                $identity['uid'],
            ]
        );
    }

    /**
     * Attempt to link a newly created user to an existing membership record
     * by email. Only used when email ownership has been confirmed (OAuth or
     * email verification). Does nothing if the membership module is not active
     * or no matching record exists.
     */
    private function matchMembershipRecord(int $userId, string $email): void
    {
        try {
            $member = $this->db->fetch(
                'SELECT id FROM members WHERE email = ? LIMIT 1',
                [strtolower($email)]
            );
            if ($member) {
                $this->db->execute(
                    'UPDATE members SET user_id = ? WHERE id = ? AND (user_id IS NULL OR user_id = 0)',
                    [$userId, $member['id']]
                );
            }
        } catch (\Throwable) {
            // membership table may not exist on this instance — non-fatal
        }
    }
}
