<?php
/**
 * CruinnCMS — OAuth Controller
 *
 * Handles the OAuth social login flow:
 *  GET  /auth/{provider}          → redirect to provider's auth page
 *  GET  /auth/{provider}/callback → process the callback, log in or register
 */

namespace Cruinn\Module\OAuth\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\OAuthService;

class OAuthController extends BaseController
{
    /**
     * GET /auth/{provider} — Redirect to the OAuth provider.
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
     * GET /auth/{provider}/callback — Handle the OAuth callback.
     */
    public function callback(string $provider): void
    {
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
            Auth::flash('error', 'Login failed. Please try again.');
            $this->redirect('/login');
        }

        // 1. Existing OAuth link — log straight in
        $oauthAccount = $this->db->fetch(
            'SELECT * FROM user_oauth_accounts WHERE provider = ? AND provider_uid = ?',
            [$identity['provider'], $identity['uid']]
        );

        if ($oauthAccount) {
            $this->updateOAuthTokens($oauthAccount['user_id'], $identity);
            $this->loginOAuthUser($oauthAccount['user_id'], $identity);
            return;
        }

        // 2. Logged-in user linking a new provider
        if (Auth::check()) {
            $this->linkOAuthAccount(Auth::userId(), $identity);
            Auth::flash('success', ucfirst($identity['provider']) . ' account linked successfully.');
            $this->redirect('/profile');
            return;
        }

        // 3. Match by email — provider has verified ownership
        if (!empty($identity['email'])) {
            $existingUser = $this->db->fetch(
                'SELECT * FROM users WHERE email = ? AND active = 1',
                [strtolower($identity['email'])]
            );

            if ($existingUser) {
                $this->linkOAuthAccount($existingUser['id'], $identity);
                $this->loginOAuthUser($existingUser['id'], $identity);
                return;
            }
        }

        // 4. Brand new user — create user account only (not a member)
        $this->createOAuthUser($identity);
    }

    // ── Private Helpers ───────────────────────────────────────────

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

        Auth::loginById($userId);
        $this->logActivity('login_oauth', 'user', $userId, 'Provider: ' . $identity['provider']);

        $stored = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);
        $isPrivilegedRoute = $stored && (
            str_starts_with($stored, '/admin') || str_starts_with($stored, '/council')
        );
        $redirect = (!$stored || $isPrivilegedRoute) ? '/profile' : $stored;
        $this->redirect($redirect);
    }

    /**
     * Create a new user account from OAuth profile data.
     * A user account is not a membership — the user can join separately.
     */
    private function createOAuthUser(array $identity): void
    {
        if (empty($identity['email'])) {
            Auth::flash('error', 'Your ' . ucfirst($identity['provider']) . ' account did not provide an email address. Please log in with email and password or use a different provider.');
            $this->redirect('/login');
        }

        $email       = strtolower($identity['email']);
        $displayName = $identity['name'] ?? 'User';

        $this->db->transaction(function () use ($email, $displayName, $identity) {
            $userId = $this->db->insert('users', [
                'email'         => $email,
                'password_hash' => null,
                'display_name'  => $displayName,
                'active'        => 1,
            ]);

            $this->linkOAuthAccount((int) $userId, $identity);

            $this->logActivity('register_oauth', 'user', (int) $userId,
                'Provider: ' . $identity['provider'], (int) $userId);

            Auth::loginById((int) $userId);
        });

        Auth::flash('success', 'Welcome! Your account has been created.');
        $this->redirect('/profile');
    }

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

    private function updateOAuthTokens(int $userId, array $identity): void
    {
        $tokenExpiry = $identity['expires']
            ? date('Y-m-d H:i:s', time() + (int) $identity['expires'])
            : null;

        $this->db->execute(
            'UPDATE user_oauth_accounts
             SET access_token = ?, refresh_token = ?, token_expires = ?, updated_at = NOW()
             WHERE user_id = ? AND provider = ?',
            [$identity['access_token'], $identity['refresh_token'], $tokenExpiry, $userId, $identity['provider']]
        );
    }

}
