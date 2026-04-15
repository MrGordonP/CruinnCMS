<?php
/**
 * Organisation Module — Restricted workspace for organisation members.
 * Discussion threads and inbox. Document management lives in the documents module.
 */

use Cruinn\Module\Organisation\Controllers\OrganisationController;

return [
    'slug'         => 'organisation',
    'name'         => 'Organisation Workspace',
    'description'  => 'Restricted workspace for organisation members: discussion threads and inbox.',
    'provides'     => ['organisation'],
    'migrations'   => [__DIR__ . '/migrations/001_organisation_tables.sql'],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Organisation', 'label' => 'Workspace', 'url' => '/organisation', 'icon' => '🏢'],
    ],

    'dashboard_sections' => [
        ['group' => 'Organisation', 'label' => 'Workspace', 'url' => '/organisation', 'icon' => '🏢', 'roles' => ['admin', 'organisation']],
    ],

    'dashboard_widgets' => [
        [
            'slug'     => 'organisation-summary',
            'label'    => 'Organisation Workspace',
            'template' => 'admin/organisation/widgets/summary',
            'provider' => 'Cruinn\\Module\\Organisation\\Services\\OrganisationDashboardService::dashboardSummary',
            'roles'    => ['admin'],
            'width'    => 'full',
            'order'    => 130,
            'settings' => ['limit' => 4],
        ],
    ],

    'routes' => static function (object $router): void {
        // Organisation workspace
        $router->get('/organisation', [OrganisationController::class, 'dashboard']);

        // Discussions
        $router->get('/organisation/discussions',                                      [OrganisationController::class, 'discussionList']);
        $router->get('/organisation/discussions/new',                                  [OrganisationController::class, 'discussionNew']);
        $router->post('/organisation/discussions',                                     [OrganisationController::class, 'discussionCreate']);
        $router->get('/organisation/discussions/{id}',                                 [OrganisationController::class, 'discussionShow']);
        $router->post('/organisation/discussions/{id}/reply',                          [OrganisationController::class, 'discussionReply']);
        $router->post('/organisation/discussions/{id}/pin',                            [OrganisationController::class, 'discussionTogglePin']);
        $router->post('/organisation/discussions/{id}/lock',                           [OrganisationController::class, 'discussionToggleLock']);
        $router->post('/organisation/discussions/{id}/delete',                         [OrganisationController::class, 'discussionDelete']);

        // Inbox
        $router->get('/organisation/inbox', [OrganisationController::class, 'inbox']);
    },
];
