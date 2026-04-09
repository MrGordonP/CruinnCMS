<?php
/**
 * GDPR Module — Privacy policy, cookie consent, data export, and account deletion.
 */

use Cruinn\Module\Gdpr\Controllers\GdprController;

return [
    'slug'         => 'gdpr',
    'name'         => 'GDPR / Privacy',
    'description'  => 'Privacy policy, cookie consent, Subject Access Request data export, and Right-to-Erasure account deletion.',
    'provides'     => ['gdpr'],
    'migrations'   => ['014_gdpr.sql', '015_deleted_accounts.sql'],
    'template_path' => __DIR__ . '/templates',

    'dashboard_sections' => [
        ['group' => 'Settings', 'label' => 'GDPR', 'url' => '/admin/settings/gdpr', 'icon' => '🔒', 'roles' => ['admin']],
    ],

    'routes' => function (IGA\Router $router) {
        $router->get('/privacy',                  [GdprController::class, 'privacyPolicy']);
        $router->get('/cookies',                  [GdprController::class, 'cookiePolicy']);
        $router->post('/gdpr/consent',            [GdprController::class, 'recordConsent']);
        $router->post('/members/data-export',     [GdprController::class, 'requestExport']);
        $router->get('/members/delete-account',   [GdprController::class, 'showDeleteAccount']);
        $router->post('/members/delete-account',  [GdprController::class, 'deleteAccount']);
    },
];
