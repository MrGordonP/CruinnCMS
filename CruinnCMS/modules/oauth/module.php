<?php
/**
 * OAuth Module — Social login via OAuth 2.0 providers (Google, GitHub, etc.).
 */

use Cruinn\Module\OAuth\Controllers\OAuthController;

return [
    'slug'         => 'oauth',
    'name'         => 'OAuth / Social Login',
    'description'  => 'Social login via OAuth 2.0 providers. OAuthService remains in core to support login page provider listing.',
    'provides'     => ['oauth'],
    'migrations'   => [
        __DIR__ . '/migrations/001_oauth_core.sql',
    ],
    'template_path' => null,

    'dashboard_sections' => [
        ['group' => 'Settings', 'label' => 'OAuth', 'url' => '/admin/settings/oauth', 'icon' => '🔐', 'roles' => ['admin']],
    ],

    'routes' => function (\Cruinn\Router $router) {
        $router->get('/auth/{provider}',          [OAuthController::class, 'startAuth']);
        $router->get('/auth/{provider}/callback', [OAuthController::class, 'callback']);
    },
];
