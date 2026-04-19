<?php
/**
 * Mailout Module
 *
 * Email campaign composer and queue management for mailing lists and member segments.
 */

use Cruinn\Router;
use Cruinn\Module\Mailout\Controllers\BroadcastController;

return [
    'slug'        => 'mailout',
    'name'        => 'Mailout',
    'version'     => '1.0.0',
    'description' => 'Compose and queue email campaigns to mailing lists, member segments, or portal users.',

    'routes' => function (Router $router): void {
        $router->get('/admin/mailout',                         [BroadcastController::class, 'index']);
        $router->get('/admin/mailout/new',                     [BroadcastController::class, 'newForm']);
        $router->post('/admin/mailout',                        [BroadcastController::class, 'create']);
        $router->get('/admin/mailout/{id}',                    [BroadcastController::class, 'show']);
        $router->get('/admin/mailout/{id}/edit',               [BroadcastController::class, 'editForm']);
        $router->post('/admin/mailout/{id}',                   [BroadcastController::class, 'update']);
        $router->post('/admin/mailout/{id}/queue',             [BroadcastController::class, 'queue']);
        $router->post('/admin/mailout/{id}/cancel',            [BroadcastController::class, 'cancel']);
        $router->post('/admin/mailout/{id}/delete',            [BroadcastController::class, 'delete']);
    },

    'migrations' => [
        __DIR__ . '/migrations/001_mailout_core.sql',
        __DIR__ . '/migrations/002_subscription_modes.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Mailout', 'url' => '/admin/mailout', 'icon' => '📣'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Mailout', 'url' => '/admin/mailout', 'icon' => '📣', 'roles' => ['admin']],
    ],

    'provides' => ['mailout'],
];
