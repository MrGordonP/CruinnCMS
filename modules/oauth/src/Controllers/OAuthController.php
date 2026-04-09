<?php
/**
 * IGA Portal â€” OAuth Controller
 *
 * Handles the OAuth social login flow:
 *  GET  /auth/{provider}          â†’ redirect to provider's auth page
 *  GET  /auth/{provider}/callback â†’ process the callback, log in or register
 */

namespace IGA\Module\OAuth\Controllers;

use IGA\Auth;
use IGA\Controllers\BaseController;
use IGA\Database;
use IGA\Services\OAuthService;

class OAuthController extends BaseController
{
    /**
     * GET /auth/{provider} â€” Redirect to the OAuth provider.
     */
    public function startAuth(string $provider): void
    {
        try {
            $url = OAuthService::getAuthUrl($provider);
            $this->redirect($url);
        } catch (\RuntimeException $e) {
            Auth::flash('error', 'Social login is not available for this provider.');
            $this->redirect('/login');
        }
    }

    /**
     * GET /auth/{provider}/callback â€” Handle the OAuth callback.
     */
    public function callback(string $provider): void
    {
        // Check for errors from provider (user denied, etc.)
        $error = $_GET['error'] ?? $_GET['error_description'] ?? null;
        if ($error) {
            Auth::flash('error', 'Login was cancelled or denied.');
            $this->redirect('/login');
        }

        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';

        if (empty($code)) {
            Auth::flash('error', 'Invalid response from login provider.');
            $this->redirect('/login');
        }

        try {
            $identity = OAuthService::handleCallback($provider, $code, $state);
        } catch (\RuntimeException $e) {
            Auth::flash('error', 'Login failed: ' . $e->getMessage());
            $this->redirect('/login');
        }

        // Look up existing OAuth link
        $oauthAccount = $this->db->fetch(
            'SELECT * FROM user_oauth_accounts WHERE provider = ? AND provider_uid = ?',
            [$identity['provider'], $identity['uid']]
        );

        if ($oauthAccount) {
            // Existing linked account â€” log them in
            $this->loginOAuthUser($oauthAccount['user_id'], $identity);
            return;
        }

        // No existing link â€” check if user is already logged in (linking flow)
        if (Auth::check()) {
            $this->linkOAuthAccount(Auth::userId(), $identity);
            Auth::flash('success', ucfirst($identity['provider']) . ' account linked successfully.');
            $this->redirect('/members/profile');
            return;
        }

        // Not logged in â€” try to match by email
        if ($identity['email']) {
            $existingUser = $this->db->fetch(
                'SELECT * FROM users WHERE email = ? AND active = 1',
                [strtolower($identity['email'])]
            );

            if ($existingUser) {
                // Auto-link and log in (email is verified by the OAuth provider)
                $this->linkOAuthAccount($existingUser['id'], $identity);
                $this->loginOAuthUser($existingUser['id'], $identity);
                return;
            }
        }

        // Brand new user â€” create account from OAuth profile
        $this->createOAuthUser($identity);
    }

    // â”€â”€ Private Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Log in a user by their ID (no password needed â€” OAuth-authenticated).
     */
    private function loginOAuthUser(int $userId, array $identity): void
    {
        $user = $this->db->fetch(
            'SELECT * FROM users WHERE id = ? AND active = 1',
            [$userId]
        );

        if (!$user) {
            Auth::flash('error', 'This account has been deactivated.');
            $this->redirect('/login');
        }

        // Update OAuth token data
        $this->updateOAuthTokens($userId, $identity);

        // Set session (same as Auth::attempt but without password)
        Auth::loginById($userId);

        $this->logActivity('login_oauth', 'user', $userId, 'Provider: ' . $identity['provider']);

        $stored = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);
        $isPrivilegedRoute = $stored && (
            str_starts_with($stored, '/admin') || str_starts_with($stored, '/organisation') || str_starts_with($stored, '/council')
        );
        $redirect = (!$stored || $isPrivilegedRoute) ? $this->defaultRedirectForRole() : $stored;
        $this->redirect($redirect);
    }

    /**
     * Create a new user from OAuth profile data.
     */
    private function createOAuthUser(array $identity): void
    {
        // Check if registration is open
        $regOpen = $this->db->fetchColumn("SELECT value FROM settings WHERE `key` = 'registration_open'");
        if ($regOpen !== '1' && $regOpen !== null) {
            Auth::flash('error', 'Registration is currently closed. Please log in with an existing account.');
            $this->redirect('/login');
        }

        $email       = $identity['email'] ? strtolower($identity['email']) : null;
        $displayName = $identity['name'] ?? 'User';

        // Split display name into first/last
        $nameParts  = explode(' ', $displayName, 2);
        $forenames  = $nameParts[0];
        $surnames   = $nameParts[1] ?? '';

        $this->db->transaction(function () use ($email, $displayName, $forenames, $surnames, $identity) {
            // Create user â€” no password (OAuth-only for now, can set one later)
            $userId = $this->db->insert('users', [
                'email'         => $email ?? ($identity['provider'] . '_' . $identity['uid'] . '@oauth.local'),
                'password_hash' => null,
                'display_name'  => $displayName,
                'role'          => 'member',
                'active'        => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            // Create member record
            $this->db->insert('members', [
                'user_id'          => $userId,
                'forenames'        => $forenames,
                'surnames'         => $surnames,
                'email'            => $email,
                'status'           => 'active',
                'public_directory' => 0,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            // Link OAuth account
            $this->linkOAuthAccount((int) $userId, $identity);

            $this->logActivity('register_oauth', 'user', (int) $userId,
                'Provider: ' . $identity['provider'], (int) $userId);

            // Log them in
            Auth::loginById((int) $userId);
        });

        Auth::flash('success', 'Welcome! Your account has been created. You can update your profile below.');
        $this->redirect('/members/profile');
    }

    /**
     * Insert or update an OAuth account link.
     */
    private function linkOAuthAccount(int $userId, array $identity): void
    {
        $existing = $this->db->fetch(
            'SELECT id FROM user_oauth_accounts WHERE provider = ? AND provider_uid = ?',
            [$identity['provider'], $identity['uid']]
        );

        $tokenExpiry = $identity['expires']
            ? date('Y-m-d H:i:s', time() + (int) $identity['expires'])
            : null;

        if ($existing) {
            $this->db->update('user_oauth_accounts', [
                'user_id'       => $userId,
                'email'         => $identity['email'],
                'display_name'  => $identity['name'],
                'avatar_url'    => $identity['avatar'],
                'access_token'  => $identity['access_token'],
                'refresh_token' => $identity['refresh_token'],
                'token_expires' => $tokenExpiry,
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('user_oauth_accounts', [
                'user_id'       => $userId,
                'provider'      => $identity['provider'],
                'provider_uid'  => $identity['uid'],
                'email'         => $identity['email'],
                'display_name'  => $identity['name'],
                'avatar_url'    => $identity['avatar'],
                'access_token'  => $identity['access_token'],
                'refresh_token' => $identity['refresh_token'],
                'token_expires' => $tokenExpiry,
            ]);
        }
    }

    /**
     * Update tokens for an existing link.
     */
    private function updateOAuthTokens(int $userId, array $identity): void
    {
        $tokenExpiry = $identity['expires']
            ? date('Y-m-d H:i:s', time() + (int) $identity['expires'])
            : null;

        $this->db->execute(
            'UPDATE user_oauth_accounts SET access_token = ?, refresh_token = ?, token_expires = ?, updated_at = NOW()
             WHERE user_id = ? AND provider = ?',
            [$identity['access_token'], $identity['refresh_token'], $tokenExpiry, $userId, $identity['provider']]
        );
    }

    /**
     * Determine landing page for current role (same logic as AuthController).
     */
    private function defaultRedirectForRole(): string
    {
        $roleId = Auth::roleId();
        if ($roleId) {
            $redirect = $this->db->fetchColumn('SELECT default_redirect FROM roles WHERE id = ?', [$roleId]);
            if ($redirect) {
                return $redirect;
            }
        }

        return match (Auth::role()) {
            'admin'   => '/admin',
            'organisation' => '/organisation',
            'council' => '/organisation',
            'member'  => '/members/profile',
            default   => '/',
        };
    }
}
