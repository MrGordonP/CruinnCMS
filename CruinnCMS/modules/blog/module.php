<?php
/**
 * Blog Module
 *
 * Public blog content with admin CRUD and block editor support.
 */

use Cruinn\Router;
use Cruinn\Controllers\CruinnController;
use Cruinn\Module\Blog\Controllers\ArticleController;
use Cruinn\Module\Blog\Controllers\ArticleEditorController;

return [
    'slug'        => 'blog',
    'name'        => 'Blog',
    'version'     => '1.0.0',
    'description' => 'Public-facing blog posts with block editor support.',

    'routes' => function (Router $router): void {
        // Admin
        $router->get('/admin/blog',                     [ArticleController::class, 'dashboard']);
        $router->get('/admin/blog/settings',            [ArticleController::class, 'settings']);
        $router->post('/admin/blog/settings',           [ArticleController::class, 'saveSettings']);
        $router->get('/admin/blog/profiles',            [ArticleController::class, 'profiles']);
        $router->get('/admin/blog/profiles/new',        [ArticleController::class, 'profileNew']);
        $router->post('/admin/blog/profiles',           [ArticleController::class, 'profileCreate']);
        $router->get('/admin/blog/profiles/{id}/edit',  [ArticleController::class, 'profileEdit']);
        $router->post('/admin/blog/profiles/{id}',      [ArticleController::class, 'profileUpdate']);
        $router->post('/admin/blog/profiles/{id}/delete',[ArticleController::class, 'profileDelete']);
        $router->get('/admin/blog/posts',               [ArticleController::class, 'adminList']);
        $router->get('/admin/blog/posts/new',           [ArticleController::class, 'adminNew']);
        $router->post('/admin/blog/posts',              [ArticleController::class, 'adminCreate']);
        $router->get('/admin/blog/posts/{id}/edit',     [ArticleController::class, 'adminEdit']);
        $router->post('/admin/blog/posts/{id}',         [ArticleController::class, 'adminUpdate']);
        $router->post('/admin/blog/posts/{id}/delete',  [ArticleController::class, 'adminDelete']);

        // Admin article editor — UI opened by CruinnController; AJAX handled here
        $router->get('/admin/blog/editor/{id}/edit',        [CruinnController::class, 'editArticle']);
        $router->get('/admin/article-editor/{id}/edit',     [CruinnController::class, 'editArticle']);
        $router->post('/admin/article-editor/{id}/action',  [ArticleEditorController::class, 'recordAction']);
        $router->post('/admin/article-editor/{id}/undo',    [ArticleEditorController::class, 'undo']);
        $router->post('/admin/article-editor/{id}/redo',    [ArticleEditorController::class, 'redo']);
        $router->post('/admin/article-editor/{id}/publish', [ArticleEditorController::class, 'publish']);
        $router->post('/admin/article-editor/{id}/discard', [ArticleEditorController::class, 'discardDraft']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
        __DIR__ . '/migrations/001_articles_core.sql',
        __DIR__ . '/migrations/002_article_blocks.sql',
        __DIR__ . '/migrations/003_article_editor_tables.sql',
        __DIR__ . '/migrations/004_fix_editor_state_schema.sql',
        __DIR__ . '/migrations/005_blog_profiles.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/blog', 'icon' => '📰'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/blog', 'icon' => '📰', 'roles' => ['admin']],
    ],

    'provides' => ['articles'],

    'public_routes' => [],

    'public_path_resolver' => ArticleController::class . '::resolvePublicPath',

    'content_providers' => [
        [
            'slug'     => 'list',
            'title'    => 'Blog List',
            'provider' => ArticleController::class . '::contentProviderBlogList',
            'template' => 'public/articles/module-content/list',
        ],
        [
            'slug'     => 'content',
            'title'    => 'Blog Content',
            'provider' => ArticleController::class . '::contentProviderBlogContent',
            'template' => 'public/articles/module-content/content',
        ],
        [
            'slug'     => 'post',
            'title'    => 'Blog Post',
            'provider' => ArticleController::class . '::contentProviderBlogPost',
            'template' => 'public/articles/module-content/post',
        ],
    ],
];
