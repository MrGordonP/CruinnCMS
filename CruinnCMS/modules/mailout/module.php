<?php
/**
 * Mailout Module
 *
 * Email campaign composer and queue management for mailing lists and member segments.
 */

use Cruinn\Router;
use Cruinn\Module\Mailout\Controllers\BroadcastController;
use Cruinn\Module\Mailout\Controllers\MailingListController;

return [
    'slug'        => 'mailout',
    'name'        => 'Mailout',
    'version'     => '1.0.0',
    'description' => 'Compose and queue email campaigns to mailing lists, member segments, or portal users.',

    'routes' => function (Router $router): void {
        $router->get('/admin/mailout/lists',                                               [MailingListController::class, 'index']);
        $router->post('/admin/mailout/lists',                                              [MailingListController::class, 'create']);
        $router->get('/admin/mailout/lists/{id}/subscribers',                              [MailingListController::class, 'subscribers']);
        $router->post('/admin/mailout/lists/{id}/subscribers/add',                         [MailingListController::class, 'addSubscriber']);
        $router->post('/admin/mailout/lists/{id}',                                         [MailingListController::class, 'update']);
        $router->post('/admin/mailout/lists/{id}/delete',                                  [MailingListController::class, 'delete']);
        $router->post('/admin/mailout/lists/{id}/subscribers/{subId}/remove',              [MailingListController::class, 'removeSubscriber']);
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
        __DIR__ . '/migrations/schema.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Mailout',       'url' => '/admin/mailout',       'icon' => '📣'],
        ['group' => 'Content', 'label' => 'Mailing Lists', 'url' => '/admin/mailout/lists', 'icon' => '📋'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Mailout',       'url' => '/admin/mailout',       'icon' => '📣', 'roles' => ['admin']],
        ['group' => 'Content', 'label' => 'Mailing Lists', 'url' => '/admin/mailout/lists', 'icon' => '📋', 'roles' => ['admin']],
    ],

    'provides' => ['mailout'],
];
