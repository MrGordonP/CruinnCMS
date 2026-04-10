<?php

use Cruinn\Module\Membership\Controllers\MembershipAdminController;

return [
    'slug'        => 'membership',
    'name'        => 'Membership',
    'version'     => '1.0.0',
    'description' => 'Organisation membership accounts, plans, subscriptions, and payments.',

    'dependencies' => [],

    'routes' => static function (object $router): void {
        // Members
        $router->get('/admin/membership',                             [MembershipAdminController::class, 'indexMembers']);
        $router->get('/admin/membership/members/new',                 [MembershipAdminController::class, 'newMember']);
        $router->post('/admin/membership/members',                    [MembershipAdminController::class, 'createMember']);
        $router->get('/admin/membership/members/{id}',                [MembershipAdminController::class, 'showMember']);
        $router->get('/admin/membership/members/{id}/edit',           [MembershipAdminController::class, 'editMember']);
        $router->post('/admin/membership/members/{id}',               [MembershipAdminController::class, 'updateMember']);
        $router->post('/admin/membership/members/{id}/subscriptions', [MembershipAdminController::class, 'createSubscription']);

        // Subscriptions and payments
        $router->post('/admin/membership/subscriptions/{id}/status',  [MembershipAdminController::class, 'updateSubscriptionStatus']);
        $router->post('/admin/membership/subscriptions/{id}/payments', [MembershipAdminController::class, 'recordPayment']);

        // Plans
        $router->get('/admin/membership/plans',                       [MembershipAdminController::class, 'listPlans']);
        $router->get('/admin/membership/plans/new',                   [MembershipAdminController::class, 'newPlan']);
        $router->post('/admin/membership/plans',                      [MembershipAdminController::class, 'createPlan']);
        $router->get('/admin/membership/plans/{id}/edit',             [MembershipAdminController::class, 'editPlan']);
        $router->post('/admin/membership/plans/{id}',                 [MembershipAdminController::class, 'updatePlan']);
    },

    'migrations' => [
        __DIR__ . '/migrations/001_membership_core.sql',
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

    'provides' => ['membership_accounts', 'membership_subscriptions'],
];
