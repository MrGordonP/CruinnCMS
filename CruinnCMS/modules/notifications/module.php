<?php
/**
 * Notifications Module
 *
 * In-app notification inbox, per-category delivery preferences,
 * and public-facing mailing list subscribe/unsubscribe.
 */

use Cruinn\Router;
use Cruinn\Module\Notifications\Controllers\NotificationsController;

// Last edit: 2026-06-11 13:39 UTC.

return [
    'slug'        => 'notifications',
    'name'        => 'Notifications',
    'version'     => '1.0.0',
    'description' => 'In-app notification inbox, delivery preferences, and mailing list subscription management.',

    'dependencies' => [],

    'routes' => static function (Router $router): void {
        // In-app notifications
        $router->get('/notifications',                      [NotificationsController::class, 'index']);
        $router->post('/notifications/read-all',            [NotificationsController::class, 'markAllRead']);
        $router->post('/notifications/{id}/read',           [NotificationsController::class, 'markRead']);

        // Preferences
        $router->get('/notifications/preferences',          [NotificationsController::class, 'preferences']);
        $router->post('/notifications/preferences',         [NotificationsController::class, 'savePreferences']);

        // Mailing list public UI
        $router->get('/mailing-lists',                      [NotificationsController::class, 'mailingLists']);
        $router->post('/mailing-lists/{id}/subscribe',      [NotificationsController::class, 'subscribe']);
        $router->post('/mailing-lists/{id}/unsubscribe',    [NotificationsController::class, 'unsubscribe']);

        // Token-based unsubscribe (from email link)
        $router->get('/unsubscribe',                        [NotificationsController::class, 'unsubscribeToken']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Modules', 'label' => 'Notifications', 'url' => '/notifications', 'icon' => '🔔'],
        ['group' => 'Modules', 'label' => 'Mailing Lists', 'url' => '/mailing-lists', 'icon' => '✉️'],
    ],

    'provides' => ['notifications', 'mailing_list_subscriptions'],
];
