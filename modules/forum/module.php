<?php
/**
 * Cruinn CMS — Forum Module
 *
 * Provides a native threaded forum with categories, threads, posts,
 * role-based access control, and admin moderation tools.
 *
 * Swap this module out and declare your own 'forum' provider
 * (e.g. a phpBB adapter) to replace the native implementation.
 */

use Cruinn\Module\Forum\Controllers\ForumController;
use Cruinn\Module\Forum\Controllers\ForumAdminController;

return [
    'slug'        => 'forum',
    'name'        => 'Forum',
    'version'     => '1.0.0',
    'description' => 'Threaded forum with categories, role-based access, and admin moderation.',

    'dependencies' => [],

    'migrations' => [
        __DIR__ . '/migrations/009_forum_core.sql',
        __DIR__ . '/migrations/017_forum_hierarchy.sql',
    ],

    'routes' => static function (object $router): void {
        // Public forum routes
        $router->get('/forum',                          [ForumController::class, 'index']);
        $router->get('/forum/{slug}',                   [ForumController::class, 'category']);
        $router->get('/forum/{slug}/new',               [ForumController::class, 'newThreadForm']);
        $router->post('/forum/{slug}/new',              [ForumController::class, 'createThread']);
        $router->get('/forum/thread/{id}',              [ForumController::class, 'thread']);
        $router->post('/forum/thread/{id}/reply',       [ForumController::class, 'reply']);

        // Admin forum moderation routes
        $router->get('/admin/forum',                    [ForumAdminController::class, 'index']);
        $router->post('/admin/forum/{id}/pin',          [ForumAdminController::class, 'togglePin']);
        $router->post('/admin/forum/{id}/lock',         [ForumAdminController::class, 'toggleLock']);
        $router->post('/admin/forum/{id}/delete',       [ForumAdminController::class, 'deleteThread']);
    },

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Forum', 'url' => '/admin/forum', 'icon' => '💬'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Forum', 'url' => '/admin/forum', 'icon' => '💬', 'roles' => ['admin']],
    ],

    'template_path' => __DIR__ . '/templates',

    'settings_schema' => [
        [
            'key'     => 'provider',
            'type'    => 'select',
            'label'   => 'Forum Provider',
            'hint'    => 'Which forum backend to use. "native" uses the built-in implementation.',
            'default' => 'native',
            'options' => ['native' => 'Native (built-in)'],
        ],
    ],

    'provides' => ['forum'],
];
