<?php
/**
 * Organisation Module — Restricted workspace for organisation members.
 * Document management, discussion threads, and inbox viewer.
 */

use Cruinn\Module\Organisation\Controllers\OrganisationController;

return [
    'slug'         => 'organisation',
    'name'         => 'Organisation Workspace',
    'description'  => 'Restricted workspace for organisation members: document management, discussion threads, and inbox.',
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

        // Documents
        $router->get('/organisation/documents',                                        [OrganisationController::class, 'documentList']);
        $router->get('/organisation/documents/new',                                    [OrganisationController::class, 'documentNew']);
        $router->post('/organisation/documents',                                       [OrganisationController::class, 'documentCreate']);
        $router->get('/organisation/documents/{id}',                                   [OrganisationController::class, 'documentShow']);
        $router->get('/organisation/documents/{id}/download',                          [OrganisationController::class, 'documentDownload']);
        $router->get('/organisation/documents/{id}/versions/{versionId}/download',     [OrganisationController::class, 'documentDownloadVersion']);
        $router->post('/organisation/documents/{id}/version',                          [OrganisationController::class, 'documentNewVersion']);
        $router->post('/organisation/documents/{id}/submit',                           [OrganisationController::class, 'documentSubmit']);
        $router->post('/organisation/documents/{id}/approve',                          [OrganisationController::class, 'documentApprove']);
        $router->post('/organisation/documents/{id}/archive',                          [OrganisationController::class, 'documentArchive']);
        $router->post('/organisation/documents/{id}/delete',                           [OrganisationController::class, 'documentDelete']);

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
