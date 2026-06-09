<?php

use Cruinn\Module\Membership\Controllers\MembershipAdminController;
use Cruinn\Module\Membership\Controllers\MembershipContentController;

return [
    'slug'        => 'membership',
    'name'        => 'Membership',
    'version'     => '1.0.0',
    'description' => 'Organisation membership accounts, plans, subscriptions, and payments.',

    'dependencies' => [],

    'routes' => static function (object $router): void {
        // Hub
        $router->get('/admin/membership',                             [MembershipAdminController::class, 'hub']);

        // Members
        $router->get('/admin/membership/members',                     [MembershipAdminController::class, 'indexMembers']);
        $router->get('/admin/membership/members/new',                 [MembershipAdminController::class, 'newMember']);
        $router->post('/admin/membership/members',                    [MembershipAdminController::class, 'createMember']);
        $router->post('/admin/membership/members/bulk',               [MembershipAdminController::class, 'bulkMembers']);
        $router->get('/admin/membership/members/{id}',                [MembershipAdminController::class, 'showMember']);
        $router->get('/admin/membership/members/{id}/edit',           [MembershipAdminController::class, 'editMember']);
        $router->post('/admin/membership/members/{id}',               [MembershipAdminController::class, 'updateMember']);
        $router->post('/admin/membership/members/{id}/subscriptions', [MembershipAdminController::class, 'createSubscription']);

        // Subscriptions and payments
        $router->get('/admin/membership/subscriptions',                       [MembershipAdminController::class, 'indexSubscriptions']);
        $router->post('/admin/membership/subscriptions/{id}/status',  [MembershipAdminController::class, 'updateSubscriptionStatus']);
        $router->post('/admin/membership/subscriptions/{id}/payments', [MembershipAdminController::class, 'recordPayment']);
        $router->post('/admin/membership/subscriptions/{id}/link-payment', [MembershipAdminController::class, 'linkPayment']);
        $router->post('/admin/membership/subscriptions/{id}/verify',  [MembershipAdminController::class, 'verifySubscription']);

        // Plans
        $router->get('/admin/membership/plans',                       [MembershipAdminController::class, 'listPlans']);
        $router->get('/admin/membership/plans/new-group',             [MembershipAdminController::class, 'newGroup']);
        $router->get('/admin/membership/plans/new-tier',              [MembershipAdminController::class, 'newTier']);
        $router->get('/admin/membership/plans/new',                   [MembershipAdminController::class, 'newPlan']);
        $router->post('/admin/membership/plans',                      [MembershipAdminController::class, 'createPlan']);
        $router->get('/admin/membership/plans/{id}/edit',             [MembershipAdminController::class, 'editPlan']);
        $router->post('/admin/membership/plans/{id}',                 [MembershipAdminController::class, 'updatePlan']);
        $router->post('/admin/membership/plans/bulk',                 [MembershipAdminController::class, 'bulkPlans']);

        // Membership forms/responses workspace
        $router->get('/admin/membership/forms',                       [MembershipAdminController::class, 'formsWorkspace']);

        // User linking
        $router->post('/admin/membership/members/{id}/link-user',   [MembershipAdminController::class, 'linkUser']);
        $router->post('/admin/membership/members/{id}/unlink-user', [MembershipAdminController::class, 'unlinkUser']);
        $router->get('/admin/membership/members/search',            [MembershipAdminController::class, 'searchMembers']);

        // Import
        $router->get('/admin/membership/import',                      [MembershipAdminController::class, 'importForm']);
        $router->post('/admin/membership/import',                     [MembershipAdminController::class, 'processImport']);
        $router->post('/admin/membership/import/confirm',             [MembershipAdminController::class, 'confirmImport']);
        $router->get('/admin/membership/import/map-plans',            [MembershipAdminController::class, 'mapPlansForm']);
        $router->post('/admin/membership/import/map-plans',           [MembershipAdminController::class, 'runImport']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'People', 'label' => 'Membership', 'url' => '/admin/membership', 'icon' => '👥'],
    ],

    'dashboard_sections' => [
        ['group' => 'People', 'label' => 'Membership', 'url' => '/admin/membership', 'icon' => '👥', 'roles' => ['admin']],
    ],

    'dashboard_widgets' => [
        [
            'slug'     => 'membership-summary',
            'label'    => 'Membership Summary',
            'template' => 'admin/membership/widgets/summary',
            'provider' => 'Cruinn\\Module\\Membership\\Services\\MembershipService::dashboardSummary',
            'roles'    => ['admin'],
            'width'    => 'full',
            'order'    => 120,
            'settings' => ['limit' => 5],
        ],
    ],

    'content_providers' => [
        [
            'slug'     => 'member-dashboard-header',
            'title'    => 'Member Dashboard Header',
            'provider' => MembershipContentController::class . '::contentProviderMemberDashboardHeader',
            'template' => 'public/membership/module-content/member-dashboard-header',
        ],
        [
            'slug'     => 'member-details-form',
            'title'    => 'Member Details Form',
            'provider' => MembershipContentController::class . '::contentProviderMemberDetailsForm',
            'template' => 'public/membership/module-content/member-details-form',
        ],
        [
            'slug'     => 'member-address-form',
            'title'    => 'Member Address Form',
            'provider' => MembershipContentController::class . '::contentProviderMemberAddressForm',
            'template' => 'public/membership/module-content/member-address-form',
        ],
        [
            'slug'     => 'member-notifications',
            'title'    => 'Member Notifications',
            'provider' => MembershipContentController::class . '::contentProviderMemberNotifications',
            'template' => 'public/membership/module-content/member-notifications',
        ],
        [
            'slug'     => 'member-upcoming-events',
            'title'    => 'Member Upcoming Events',
            'provider' => MembershipContentController::class . '::contentProviderMemberUpcomingEvents',
            'template' => 'public/membership/module-content/member-upcoming-events',
        ],
        [
            'slug'     => 'member-membership-summary',
            'title'    => 'Member Membership Summary',
            'provider' => MembershipContentController::class . '::contentProviderMemberMembershipSummary',
            'template' => 'public/membership/module-content/member-membership-summary',
        ],
        [
            'slug'     => 'member-admin-stats',
            'title'    => 'Member Admin Stats',
            'provider' => MembershipContentController::class . '::contentProviderMemberAdminStats',
            'template' => 'public/membership/module-content/member-admin-stats',
        ],
    ],

    'provides' => ['membership_accounts', 'membership_subscriptions'],
];
