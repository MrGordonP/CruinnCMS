<?php
/**
 * CruinnCMS — OAuth Service
 *
 * Handles OAuth 2.0 / OAuth 1.0a flows for social login.
 * Supported providers: Google, Facebook, X (Twitter).
 *
 * Each provider flow:
 *  1. Build authorisation URL → redirect user
 *  2. Receive callback with auth code → exchange for access token
 *  3. Fetch user profile → return normalised identity
 */

namespace Cruinn\Services;

use Cruinn\App;

class OAuthService
{
    // ── Provider Configuration ─────────────────────────────────

    private const PROVIDERS = [
        'google' => [
            'auth_url'     => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'    => 'https://oauth2.googleapis.com/token',
            'profile_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope'        => 'openid email profile',
            'label'        => 'Google',
        ],
        'facebook' => [
            'auth_url'     => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url'    => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'profile_url'  => 'https://graph.facebook.com/v19.0/me?fields=id,name,email,picture.type(large)',
            'scope'        => 'email,public_profile',
            'label'        => 'Facebook',
        ],
        'twitter' => [
            'auth_url'     => 'https://twitter.com/i/oauth2/authorize',
            'token_url'    => 'https://api.x.com/2/oauth2/token',
            'profile_url'  => 'https://api.x.com/2/users/me?user.fields=id,name,username,profile_image_url',
            'scope'        => 'users.read tweet.read offline.access',
            'label'        => 'X',
        ],
    ];

    /**
     * Get the list of enabled OAuth providers with their labels.
     * A provider is enabled if its client_id is configured.
     *
     * @return array<string, string>  ['google' => 'Google', ...]
     */
    public static function enabledProviders(): array
    {
        $enabled = [];
        foreach (self::PROVIDERS as $provider => $meta) {
            $cfg = App::config("oauth.{$provider}", []);
            if (!empty($cfg['client_id'])) {
                $enabled[$provider] = $meta['label'];
            }
        }
        return $enabled;
    }

    /**
     * Build the authorisation redirect URL for a provider.
     */
    public static function getAuthUrl(string $provider): string
    {
        self::validateProvider($provider);

        $cfg  = self::providerConfig($provider);
        $meta = self::PROVIDERS[$provider];

        // Generate CSRF state token
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_provider'] = $provider;

        $callbackUrl = self::callbackUrl($provider);

        $params = [
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $callbackUrl,
            'response_type' => 'code',
            'scope'         => $meta['scope'],
            'state'         => $state,
        ];

        // Twitter (X) uses PKCE instead of client_secret in the auth step
        if ($provider === 'twitter') {
            $codeVerifier  = self::generateCodeVerifier();
            $codeChallenge = self::s256Challenge($codeVerifier);
            $_SESSION['oauth_code_verifier'] = $codeVerifier;

            $params['code_challenge']        = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        // Google: request offline access for refresh tokens
        if ($provider === 'google') {
            $params['access_type'] = 'offline';
            $params['prompt']      = 'select_account';
        }

        return $meta['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * Handle the OAuth callback — exchange code for tokens, fetch profile.
     *
     * @return array{provider: string, uid: string, email: ?string, name: ?string, avatar: ?string, access_token: string, refresh_token: ?string, expires: ?int}
     * @throws \RuntimeException on any error
     */
    public static function handleCallback(string $provider, string $code, string $state): array
    {
        self::validateProvider($provider);

        // Verify CSRF state
        if (!hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
            throw new \RuntimeException('Invalid OAuth state — possible CSRF attack.');
        }

        // Clear state to prevent replay
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

        // Exchange code for token
        $tokens = self::exchangeCode($provider, $code);

        // Fetch user profile
        $profile = self::fetchProfile($provider, $tokens['access_token']);

        return [
            'provider'      => $provider,
            'uid'           => $profile['uid'],
            'email'         => $profile['email'] ?? null,
            'name'          => $profile['name'] ?? null,
            'avatar'        => $profile['avatar'] ?? null,
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires'       => $tokens['expires_in'] ?? null,
        ];
    }

    // ── Token Exchange ─────────────────────────────────────────

    private static function exchangeCode(string $provider, string $code): array
    {
        $cfg  = self::providerConfig($provider);
        $meta = self::PROVIDERS[$provider];

        $body = [
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code'          => $code,
            'redirect_uri'  => self::callbackUrl($provider),
            'grant_type'    => 'authorization_code',
        ];

        // Twitter uses PKCE — attach code_verifier
        if ($provider === 'twitter') {
            $body['code_verifier'] = $_SESSION['oauth_code_verifier'] ?? '';
            unset($_SESSION['oauth_code_verifier']);
        }

        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        // Twitter requires Basic auth header for token exchange
        if ($provider === 'twitter') {
            $credentials = base64_encode($cfg['client_id'] . ':' . $cfg['client_secret']);
            $headers[]   = 'Authorization: Basic ' . $credentials;
            unset($body['client_id'], $body['client_secret']);
        }

        $response = self::httpPost($meta['token_url'], http_build_query($body), $headers);
        $data     = json_decode($response, true);

        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            throw new \RuntimeException("OAuth token exchange failed: {$error}");
        }

        return $data;
    }

    // ── Profile Fetching ───────────────────────────────────────

    private static function fetchProfile(string $provider, string $accessToken): array
    {
        $meta     = self::PROVIDERS[$provider];
        $headers  = ['Authorization: Bearer ' . $accessToken];
        $response = self::httpGet($meta['profile_url'], $headers);
        $data     = json_decode($response, true);

        if (!$data) {
            throw new \RuntimeException("Failed to fetch {$provider} user profile.");
        }

        return match ($provider) {
            'google'   => self::normaliseGoogle($data),
            'facebook' => self::normaliseFacebook($data),
            'twitter'  => self::normaliseTwitter($data),
        };
    }

    private static function normaliseGoogle(array $data): array
    {
        return [
            'uid'    => $data['sub'],
            'email'  => $data['email'] ?? null,
            'name'   => $data['name'] ?? null,
            'avatar' => $data['picture'] ?? null,
        ];
    }

    private static function normaliseFacebook(array $data): array
    {
        return [
            'uid'    => $data['id'],
            'email'  => $data['email'] ?? null,
            'name'   => $data['name'] ?? null,
            'avatar' => $data['picture']['data']['url'] ?? null,
        ];
    }

    private static function normaliseTwitter(array $data): array
    {
        $user = $data['data'] ?? $data;
        return [
            'uid'    => $user['id'],
            'email'  => null, // Twitter v2 doesn't return email by default
            'name'   => $user['name'] ?? $user['username'] ?? null,
            'avatar' => $user['profile_image_url'] ?? null,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────

    private static function providerConfig(string $provider): array
    {
        $cfg = App::config("oauth.{$provider}", []);
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            throw new \RuntimeException("OAuth provider '{$provider}' is not configured.");
        }
        return $cfg;
    }

    private static function validateProvider(string $provider): void
    {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new \RuntimeException("Unknown OAuth provider: {$provider}");
        }
    }

    private static function callbackUrl(string $provider): string
    {
        $baseUrl = rtrim(App::config('site.url', 'http://localhost:8080'), '/');
        return $baseUrl . '/auth/' . $provider . '/callback';
    }

    /**
     * PKCE code verifier (used by Twitter/X).
     */
    private static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function s256Challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    // ── HTTP Client (cURL) ─────────────────────────────────────

    private static function httpPost(string $url, string $body, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("OAuth HTTP error: {$error}");
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("OAuth token request returned HTTP {$httpCode}: {$response}");
        }

        return $response;
    }

    private static function httpGet(string $url, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("OAuth HTTP error: {$error}");
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("OAuth profile request returned HTTP {$httpCode}: {$response}");
        }

        return $response;
    }
}
