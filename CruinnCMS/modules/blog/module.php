<?php
/**
 * Blog Module
 *
 * Public blog content with admin CRUD and block editor support.
 */

use Cruinn\Router;
use Cruinn\Module\Blog\Controllers\ArticleController;
use Cruinn\Module\Blog\Controllers\ArticleEditorController;

return [
    'slug'        => 'blog',
    'name'        => 'Blog',
    'version'     => '1.0.0',
    'description' => 'Public-facing blog posts with block editor support.',

    'routes' => function (Router $router): void {
        // Admin
        $router->get('/admin/articles',                 [ArticleController::class, 'adminList']);
        $router->get('/admin/articles/new',             [ArticleController::class, 'adminNew']);
        $router->post('/admin/articles',                [ArticleController::class, 'adminCreate']);
        $router->get('/admin/articles/{id}/edit',       [ArticleController::class, 'adminEdit']);
        $router->post('/admin/articles/{id}',           [ArticleController::class, 'adminUpdate']);
        $router->post('/admin/articles/{id}/delete',    [ArticleController::class, 'adminDelete']);

        // Admin article editor (full Cruinn editor for article content)
        $router->get('/admin/article-editor/{id}/edit',     [ArticleEditorController::class, 'edit']);
        $router->post('/admin/article-editor/{id}/action',  [ArticleEditorController::class, 'recordAction']);
        $router->post('/admin/article-editor/{id}/undo',    [ArticleEditorController::class, 'undo']);
        $router->post('/admin/article-editor/{id}/redo',    [ArticleEditorController::class, 'redo']);
        $router->post('/admin/article-editor/{id}/publish', [ArticleEditorController::class, 'publish']);
        $router->post('/admin/article-editor/{id}/discard', [ArticleEditorController::class, 'discardDraft']);

        // Public (primary blog URLs)
        $router->get('/blog',        [ArticleController::class, 'index']);
        $router->get('/blog/{slug}', [ArticleController::class, 'show']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/articles', 'icon' => '📰'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/articles', 'icon' => '📰', 'roles' => ['admin']],
    ],

    'provides' => ['articles'],

    'public_routes' => [
        ['route' => '/blog', 'label' => 'Blog'],
    ],
];
