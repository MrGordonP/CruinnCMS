<?php
/**
 * Notifications Module
 *
 * In-app notification inbox, per-category delivery preferences,
 * and public-facing mailing list subscribe/unsubscribe.
 */

use Cruinn\Router;
use Cruinn\Module\Notifications\Controllers\NotificationsController;
use Cruinn\Module\Notifications\Controllers\NotificationsContentController;
use Cruinn\Module\Notifications\Widgets\NotificationsWidgets;

// Last edit: 2026-06-11 16:00 UTC.

return [
    'slug'        => 'notifications',
    'name'        => 'Notifications',
    'version'     => '1.0.0',
    'description' => 'In-app notification inbox, delivery preferences, and mailing list subscription management.',

    'dependencies' => [],

    'routes' => static function (Router $router): void {
        // In-app notifications
        $router->get('/notifications',                      [NotificationsController::class, 'index']);
        $router->get('/admin/notifications',                [NotificationsController::class, 'index']);
        $router->post('/notifications/read-all',            [NotificationsController::class, 'markAllRead']);
        $router->post('/admin/notifications/read-all',      [NotificationsController::class, 'markAllRead']);
        $router->post('/notifications/{id}/read',           [NotificationsController::class, 'markRead']);
        $router->post('/admin/notifications/{id}/read',     [NotificationsController::class, 'markRead']);

        // Preferences
        $router->get('/notifications/preferences',          [NotificationsController::class, 'preferences']);
        $router->get('/admin/notifications/preferences',    [NotificationsController::class, 'preferences']);
        $router->post('/notifications/preferences',         [NotificationsController::class, 'savePreferences']);
        $router->post('/admin/notifications/preferences',   [NotificationsController::class, 'savePreferences']);

        // Mailing list public UI
        $router->get('/mailing-lists',                      [NotificationsController::class, 'mailingLists']);
        $router->get('/admin/mailing-lists',                [NotificationsController::class, 'mailingLists']);
        $router->post('/mailing-lists/{id}/subscribe',      [NotificationsController::class, 'subscribe']);
        $router->post('/admin/mailing-lists/{id}/subscribe',[NotificationsController::class, 'subscribe']);
        $router->post('/mailing-lists/{id}/unsubscribe',    [NotificationsController::class, 'unsubscribe']);
        $router->post('/admin/mailing-lists/{id}/unsubscribe', [NotificationsController::class, 'unsubscribe']);

        // Admin diagnostics
        $router->get('/admin/notifications/hub',            [NotificationsController::class, 'hub']);

        // Token-based unsubscribe (from email link)
        $router->get('/unsubscribe',                        [NotificationsController::class, 'unsubscribeToken']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
        __DIR__ . '/migrations/002_notifications_hub.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Modules', 'label' => 'Notifications', 'url' => '/admin/notifications', 'icon' => '🔔'],
        ['group' => 'Modules', 'label' => 'Mailing Lists', 'url' => '/admin/mailing-lists', 'icon' => '✉️'],
        ['group' => 'Modules', 'label' => 'Notifications Hub', 'url' => '/admin/notifications/hub', 'icon' => '🛰️'],
    ],

    'widget_providers' => [
        [
            'slug'     => 'status-summary',
            'label'    => 'Notifications Status Summary',
            'provider' => NotificationsWidgets::class . '::statusSummaryData',
            'template' => 'widgets/status-summary',
        ],
        [
            'slug'     => 'user-inbox',
            'label'    => 'User Inbox Card',
            'provider' => NotificationsWidgets::class . '::userInboxData',
            'template' => 'widgets/user-inbox',
        ],
    ],

    'content_providers' => [
        [
            'slug'     => 'user-inbox',
            'title'    => 'Notifications: User Inbox',
            'provider' => NotificationsContentController::class . '::contentProviderUserInbox',
            'template' => 'public/notifications/module-content/user-inbox',
        ],
        [
            'slug'     => 'recent-list',
            'title'    => 'Notifications: Recent List',
            'provider' => NotificationsContentController::class . '::contentProviderRecentList',
            'template' => 'public/notifications/module-content/recent-list',
        ],
        [
            'slug'     => 'unread-badge',
            'title'    => 'Notifications: Unread Badge',
            'provider' => NotificationsContentController::class . '::contentProviderUnreadBadge',
            'template' => 'public/notifications/module-content/unread-badge',
        ],
    ],

    'provides' => ['notifications', 'mailing_list_subscriptions'],
];
