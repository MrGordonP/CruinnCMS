<?php
/**
 * CMS — Default Configuration
 *
 * Generic defaults for all CMS instances.
 * Instance-specific values live in instance/config.php.
 * Local developer overrides go in config/config.local.php (gitignored).
 *
 * Load order:  config.php  →  instance/config.php  →  config.local.php
 */

return [
    // Database
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'cms_portal',
        'user'     => 'cms_user',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Site
    'site' => [
        'name'     => 'My Organisation',
        'url'      => 'http://localhost:8000',
        'timezone' => 'UTC',
        'tagline'  => '',
        'debug'    => false,
    ],

    // Trusted reverse proxy IP(s) — comma-separated.
    // When REMOTE_ADDR matches one of these, the real client IP is read from
    // X-Forwarded-For. Set to '127.0.0.1,::1' for a standard Nginx + PHP-FPM
    // setup, or add your CDN/load-balancer IP if applicable.
    'trusted_proxy' => '127.0.0.1,::1',

    // Email (PHPMailer)
    'mail' => [
        'host'       => 'localhost',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',
        'from_email' => 'noreply@example.com',
        'from_name'  => 'My Organisation',
    ],

    // IMAP (Council inbox viewer)
    'imap' => [
        'host'     => 'localhost',
        'port'     => 993,
        'username' => '',
        'password' => '',
        'mailbox'  => 'INBOX',
    ],

    // Roundcube URL (for "Open in Roundcube" links)
    'roundcube_url' => '',

    // Payments
    // Public keys / IDs may be stored in the DB via ACP.
    // Secrets (client_secret, secret_key) must be set in config.local.php —
    // they are never read from or written to the database.
    'paypal' => [
        'client_id'     => '',
        'client_secret' => '',
        'sandbox'       => true,
    ],
    'stripe' => [
        'public_key' => '',
        'secret_key' => '',
        'sandbox'    => true,
    ],

    // Forum
    'forum' => [
        // Provider abstraction entry point: native (current), phpbb (future), etc.
        'provider' => 'native',
    ],

    // OAuth Login (Social Sign-In)
    // Set client_id + client_secret for each provider to enable it.
    // Get credentials from:
    //   Google:   https://console.cloud.google.com/apis/credentials
    //   Facebook: https://developers.facebook.com/apps
    //   X:        https://developer.x.com/en/portal/dashboard
    'oauth' => [
        'google' => [
            'client_id'     => '',
            'client_secret' => '',
        ],
        'facebook' => [
            'client_id'     => '',
            'client_secret' => '',
        ],
        'twitter' => [
            'client_id'     => '',
            'client_secret' => '',
        ],
    ],

    // GDPR / Privacy
    // Set 'enabled' to true for EU/GDPR jurisdictions.
    // When disabled, no cookie banner, consent tracking, or data request UI is shown.
    'gdpr' => [
        'enabled'       => false,
        'org_name'      => '',
        'contact_email' => '',
        'dpo_email'     => '',   // Data Protection Officer email (optional)
    ],

    // Security
    'session' => [
        'lifetime' => 3600,       // 1 hour
        'name'     => 'cms_sess',
    ],

    // Uploads
    'uploads' => [
        'max_size'    => 10 * 1024 * 1024, // 10MB
        'allowed'     => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'],
        'image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    ],

    // Social Media
    'social' => [
        'facebook'  => '',
        'twitter'   => '',
        'instagram' => '',
        'default_image' => '/uploads/images/social-card.jpg',

        // ── OAuth Authentication Proxy ──────────────────────────────
        // Central auth proxy URL. When set, OAuth flows are routed
        // through this proxy so individual installs don't need their
        // own developer app credentials or callback registrations.
        // Leave empty to use direct OAuth with credentials below.
        'auth_proxy_url'    => '',
        'auth_proxy_secret' => '',   // Shared secret for encrypting token payloads

        // ── Per-Site OAuth Apps (optional override) ─────────────────
        // If a site admin provides their own developer app credentials,
        // direct OAuth is used instead of the proxy. Set in config.local.php.
        'custom_facebook_app_id'     => '',
        'custom_facebook_app_secret' => '',
        'custom_twitter_api_key'     => '',
        'custom_twitter_api_secret'  => '',
        'custom_instagram_app_id'     => '',
        'custom_instagram_app_secret' => '',
    ],
];
