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
        __DIR__ . '/migrations/schema.sql',
        __DIR__ . '/migrations/019_subject_threads.sql',
    ],

    'routes' => static function (object $router): void {
        // Public forum action routes
        $router->post('/forum/{slug}/new',              [ForumController::class, 'createThread']);
        $router->post('/forum/thread/{id}/reply',       [ForumController::class, 'reply']);
        $router->post('/forum/thread/{id}/edit-title',  [ForumController::class, 'updateThreadTitle']);
        $router->post('/forum/post/{id}/edit',          [ForumController::class, 'updatePost']);
        $router->post('/forum/post/{id}/delete',        [ForumController::class, 'deletePost']);
        $router->post('/forum/post/{id}/report',        [ForumController::class, 'reportPost']);

        // Admin forum moderation routes
        $router->get('/admin/forum',                    [ForumAdminController::class, 'index']);
        $router->post('/admin/forum/category/new',      [ForumAdminController::class, 'createCategory']);
        $router->post('/admin/forum/category/{id}/update', [ForumAdminController::class, 'updateCategory']);
        $router->post('/admin/forum/category/{id}/delete', [ForumAdminController::class, 'deleteCategory']);
        $router->post('/admin/forum/bulk',              [ForumAdminController::class, 'bulkModerate']);
        $router->post('/admin/forum/{id}/pin',          [ForumAdminController::class, 'togglePin']);
        $router->post('/admin/forum/{id}/lock',         [ForumAdminController::class, 'toggleLock']);
        $router->post('/admin/forum/{id}/delete',       [ForumAdminController::class, 'deleteThread']);
        $router->post('/admin/forum/{id}/edit-title',   [ForumAdminController::class, 'editThreadTitle']);
        $router->get('/admin/forum/post/{id}/edit',     [ForumAdminController::class, 'editPost']);
        $router->post('/admin/forum/post/{id}/edit',    [ForumAdminController::class, 'updatePost']);
        $router->post('/admin/forum/post/{id}/delete',  [ForumAdminController::class, 'deletePost']);
        $router->get('/admin/forum/{id}/move',          [ForumAdminController::class, 'moveThreadForm']);
        $router->post('/admin/forum/{id}/move',         [ForumAdminController::class, 'moveThread']);
        $router->get('/admin/forum/reports',            [ForumAdminController::class, 'listReports']);
        $router->post('/admin/forum/report/{id}/review',[ForumAdminController::class, 'reviewReport']);
    },

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Forum', 'url' => '/admin/forum', 'icon' => '💬'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Forum', 'url' => '/admin/forum', 'icon' => '💬', 'roles' => ['admin']],
    ],

    'widget_providers' => [
        [
            'slug'     => 'quick-links',
            'label'    => 'Forum Quick Links',
            'provider' => 'Cruinn\\Module\\Forum\\Widgets\\DashboardWidgets::quickLinksData',
            'template' => 'widgets/dashboard-quick-links',
        ],
        [
            'slug'     => 'status-summary',
            'label'    => 'Forum Activity Summary',
            'provider' => 'Cruinn\\Module\\Forum\\Widgets\\DashboardWidgets::statusSummaryData',
            'template' => 'widgets/dashboard-status-summary',
        ],
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
        [
            'key'     => 'forum_list_page_id',
            'type'    => 'select',
            'label'   => 'Forum Shell Page',
            'hint'    => 'Published content page that owns the public forum path.',
            'default' => '',
            'options' => ['' => '— Select page —'],
        ],
        [
            'key'     => 'subject_thread_category_id',
            'type'    => 'select',
            'label'   => 'Subject Thread Category',
            'hint'    => 'Default forum category for auto-provisioned subject discussion threads.',
            'default' => '',
            'options' => ['' => '— Auto (first active category) —'],
        ],
    ],

    'provides' => ['forum'],

    'subject_sections' => [
        [
            'key'   => 'forum_thread',
            'label' => 'Forum Thread',
        ],
    ],

    'public_routes' => [],

    'public_path_resolver' => ForumController::class . '::resolvePublicPath',

    'content_providers' => [
        [
            'slug'     => 'content',
            'title'    => 'Forum Content',
            'provider' => ForumController::class . '::contentProviderForumContent',
            'template' => 'public/forum/module-content/content',
            'editor'   => [
                'display_mode' => [
                    'label'   => 'Forum View',
                    'default' => 'subject-thread',
                    'options' => [
                        [
                            'value' => 'subject-thread',
                            'label' => 'Linked subject thread',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
