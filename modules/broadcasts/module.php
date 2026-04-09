<?php
/**
 * Broadcasts Module
 *
 * Email broadcast composer and queue management for mailing lists and member segments.
 */

use IGA\Router;
use IGA\Module\Broadcasts\Controllers\BroadcastController;

return [
    'slug'        => 'broadcasts',
    'name'        => 'Email Broadcasts',
    'version'     => '1.0.0',
    'description' => 'Compose and queue email broadcasts to mailing lists, member segments, or portal users.',

    'routes' => function (Router $router): void {
        $router->get('/admin/broadcasts',                         [BroadcastController::class, 'index']);
        $router->get('/admin/broadcasts/new',                     [BroadcastController::class, 'newForm']);
        $router->post('/admin/broadcasts',                        [BroadcastController::class, 'create']);
        $router->get('/admin/broadcasts/{id}',                    [BroadcastController::class, 'show']);
        $router->get('/admin/broadcasts/{id}/edit',               [BroadcastController::class, 'editForm']);
        $router->post('/admin/broadcasts/{id}',                   [BroadcastController::class, 'update']);
        $router->post('/admin/broadcasts/{id}/queue',             [BroadcastController::class, 'queue']);
        $router->post('/admin/broadcasts/{id}/cancel',            [BroadcastController::class, 'cancel']);
        $router->post('/admin/broadcasts/{id}/delete',            [BroadcastController::class, 'delete']);
    },

    'migrations' => [
        __DIR__ . '/migrations/032_email_broadcasts.sql',
        __DIR__ . '/migrations/034_broadcast_target_type.sql',
        __DIR__ . '/migrations/035_member_year_broadcast_targets.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Broadcasts', 'url' => '/admin/broadcasts', 'icon' => '📣'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Broadcasts', 'url' => '/admin/broadcasts', 'icon' => '📣', 'roles' => ['admin']],
    ],

    'provides' => ['broadcasts'],
];
