<?php
/**
 * Organisation Module — Restricted workspace for organisation members.
 * Discussion threads and inbox. Document management lives in the documents module.
 */

use Cruinn\Module\Organisation\Controllers\OrganisationController;
use Cruinn\Module\Organisation\Controllers\OrganisationAdminController;
use Cruinn\Module\Organisation\Controllers\FinanceController;

return [
    'slug'         => 'organisation',
    'name'         => 'Organisation Workspace',
    'description'  => 'Restricted workspace for organisation members: discussion threads and inbox.',
    'provides'     => ['organisation'],
    'migrations'   => [
        __DIR__ . '/migrations/schema.sql',
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Organisation', 'label' => 'Workspace',            'url' => '/organisation',                   'icon' => '🏢'],
        ['group' => 'Organisation', 'label' => 'Discussions',          'url' => '/organisation/discussions',       'icon' => '💬'],
        ['group' => 'Organisation', 'label' => 'Organisation Profile', 'url' => '/admin/organisation/profile',     'icon' => '⚙️'],
        ['group' => 'Organisation', 'label' => 'Officers',             'url' => '/admin/organisation/officers',    'icon' => '👤'],
        ['group' => 'Organisation', 'label' => 'Meetings',             'url' => '/admin/organisation/meetings',    'icon' => '📅'],
        ['group' => 'Organisation', 'label' => 'Finance',              'url' => '/admin/organisation/finance',     'icon' => '💰'],
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

        // Admin — profile, officers, meetings
        $router->get('/admin/organisation/profile',                                    [OrganisationAdminController::class, 'profile']);
        $router->post('/admin/organisation/profile',                                   [OrganisationAdminController::class, 'saveProfile']);

        $router->get('/admin/organisation/officers',                                   [OrganisationAdminController::class, 'officers']);
        $router->post('/admin/organisation/officers',                                  [OrganisationAdminController::class, 'createOfficer']);
        $router->post('/admin/organisation/officers/{id}/update',                      [OrganisationAdminController::class, 'updateOfficer']);
        $router->post('/admin/organisation/officers/{id}/delete',                      [OrganisationAdminController::class, 'deleteOfficer']);
        $router->post('/admin/organisation/officers/{id}/mailbox/assign',              [OrganisationAdminController::class, 'assignMailbox']);
        $router->post('/admin/organisation/officers/{id}/mailbox/{grant_id}/revoke',   [OrganisationAdminController::class, 'revokeMailbox']);

        $router->get('/admin/organisation/meetings',                                   [OrganisationAdminController::class, 'meetings']);
        $router->post('/admin/organisation/meetings',                                  [OrganisationAdminController::class, 'createMeeting']);
        $router->post('/admin/organisation/meetings/{id}/update',                      [OrganisationAdminController::class, 'updateMeeting']);
        $router->post('/admin/organisation/meetings/{id}/delete',                      [OrganisationAdminController::class, 'deleteMeeting']);

        // Admin — finance
        $router->get('/admin/organisation/finance',                                    [FinanceController::class, 'index']);
        $router->get('/admin/organisation/finance/new',                                [FinanceController::class, 'newEntry']);
        $router->post('/admin/organisation/finance/create',                            [FinanceController::class, 'createEntry']);
        $router->get('/admin/organisation/finance/edit/{id}',                          [FinanceController::class, 'editEntry']);
        $router->post('/admin/organisation/finance/update/{id}',                       [FinanceController::class, 'updateEntry']);
        $router->post('/admin/organisation/finance/delete/{id}',                       [FinanceController::class, 'deleteEntry']);
        $router->post('/admin/organisation/finance/ingest',                            [FinanceController::class, 'ingest']);
        $router->get('/admin/organisation/finance/export/{periodId}',                  [FinanceController::class, 'exportCsv']);
        $router->get('/admin/organisation/finance/periods',                            [FinanceController::class, 'periods']);
        $router->post('/admin/organisation/finance/periods/create',                    [FinanceController::class, 'createPeriod']);
        $router->post('/admin/organisation/finance/periods/set-current/{id}',          [FinanceController::class, 'setCurrentPeriod']);
    },
];
