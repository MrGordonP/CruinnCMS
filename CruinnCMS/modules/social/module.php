<?php
/**
 * Social Module — Social Media Command Centre.
 * Unified feed, shared inbox, content distribution, and connected accounts.
 */

use Cruinn\Module\Social\Controllers\SocialController;

return [
    'slug'         => 'social',
    'name'         => 'Social Media',
    'description'  => 'Social Media Command Centre — unified feed, inbox, content distribution, and account management.',
    'provides'     => ['social'],
    'migrations'   => [
        __DIR__ . '/migrations/001_social_core.sql',
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Comms', 'label' => 'Social Hub', 'url' => '/admin/social', 'icon' => '📢'],
        ['group' => 'Comms', 'label' => 'Mailing Lists', 'url' => '/admin/social/mailing-lists', 'icon' => '📧'],
        ['group' => 'Comms', 'label' => 'Accounts', 'url' => '/admin/social/accounts', 'icon' => '🔗'],
        ['group' => 'Comms', 'label' => 'Distribute', 'url' => '/admin/social/distribute', 'icon' => '📤'],
    ],

    'dashboard_sections' => [
        ['group' => 'Social & Communications', 'label' => 'Social Hub', 'url' => '/admin/social', 'icon' => '📢', 'roles' => ['admin']],
        ['group' => 'Social & Communications', 'label' => 'Mailing Lists', 'url' => '/admin/social/mailing-lists', 'icon' => '📧', 'roles' => ['admin']],
        ['group' => 'Social & Communications', 'label' => 'Accounts', 'url' => '/admin/social/accounts', 'icon' => '🔗', 'roles' => ['admin']],
        ['group' => 'Social & Communications', 'label' => 'Distribute', 'url' => '/admin/social/distribute', 'icon' => '📤', 'roles' => ['admin']],
        ['group' => 'Settings', 'label' => 'Social Config', 'url' => '/admin/settings/social', 'icon' => '📡', 'roles' => ['admin']],
    ],

    'routes' => function (\Cruinn\Router $router) {
        $router->get('/admin/social',                             [SocialController::class, 'dashboard']);
        $router->get('/admin/social/feed/{platform}',             [SocialController::class, 'feed']);
        $router->get('/admin/social/inbox',                       [SocialController::class, 'inbox']);
        $router->post('/admin/social/inbox/sync',                 [SocialController::class, 'syncInbox']);
        $router->post('/admin/social/inbox/{id}/read',            [SocialController::class, 'markRead']);
        $router->post('/admin/social/inbox/{id}/star',            [SocialController::class, 'toggleStar']);
        $router->post('/admin/social/inbox/{id}/reply',           [SocialController::class, 'replyToMessage']);
        $router->get('/admin/social/distribute',                  [SocialController::class, 'distribute']);
        $router->post('/admin/social/distribute',                 [SocialController::class, 'distributePost']);
        $router->post('/admin/social/quick-post',                 [SocialController::class, 'quickPost']);
        $router->get('/admin/social/accounts',                    [SocialController::class, 'accounts']);
        $router->post('/admin/social/accounts',                   [SocialController::class, 'saveAccount']);
        $router->post('/admin/social/accounts/{id}/disconnect',   [SocialController::class, 'disconnectAccount']);
        $router->get('/admin/social/connect/{platform}',          [SocialController::class, 'oauthConnect']);
        $router->get('/admin/social/callback/{platform}',         [SocialController::class, 'oauthCallback']);
        $router->get('/admin/social/mailing-lists',               [SocialController::class, 'mailingLists']);
        $router->post('/admin/social/mailing-lists',              [SocialController::class, 'saveMailingList']);
        $router->post('/admin/social/mailing-lists/{id}/delete',  [SocialController::class, 'deleteMailingList']);
        $router->get('/admin/social/mailing-lists/{id}/members',                      [SocialController::class, 'listMembers']);
        $router->post('/admin/social/mailing-lists/{id}/members/add',                 [SocialController::class, 'addMember']);
        $router->post('/admin/social/mailing-lists/{id}/members/{subId}/remove',      [SocialController::class, 'removeMember']);
        $router->post('/admin/social/mailing-lists/{id}/members/{subId}/approve',     [SocialController::class, 'approveMember']);
        $router->post('/admin/social/mailing-lists/{id}/members/{subId}/reject',      [SocialController::class, 'rejectMember']);

        // Member self-service (profile)
        $router->post('/profile/mailing-lists/{id}/subscribe',   [SocialController::class, 'subscribeSelf']);
        $router->post('/profile/mailing-lists/{id}/unsubscribe', [SocialController::class, 'unsubscribeSelf']);
    },
];
