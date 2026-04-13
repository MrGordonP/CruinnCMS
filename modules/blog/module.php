<?php
/**
 * Blog Module
 *
 * Public blog content with admin CRUD and block editor support.
 */

use Cruinn\Router;
use Cruinn\Module\Blog\Controllers\ArticleController;

return [
    'slug'        => 'articles',
    'name'        => 'Blog',
    'version'     => '1.0.0',
    'description' => 'Public-facing blog posts with block editor support.',

    'routes' => function (Router $router): void {
        // Admin (primary blog URLs)
        $router->get('/admin/blog',                 [ArticleController::class, 'adminList']);
        $router->get('/admin/blog/new',             [ArticleController::class, 'adminNew']);
        $router->post('/admin/blog',                [ArticleController::class, 'adminCreate']);
        $router->get('/admin/blog/{id}/edit',       [ArticleController::class, 'adminEdit']);
        $router->post('/admin/blog/{id}',           [ArticleController::class, 'adminUpdate']);
        $router->post('/admin/blog/{id}/delete',    [ArticleController::class, 'adminDelete']);

        // Public (primary blog URLs)
        $router->get('/blog',        [ArticleController::class, 'index']);
        $router->get('/blog/{slug}', [ArticleController::class, 'show']);
    },

    'migrations' => [
        __DIR__ . '/migrations/001_articles_core.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/blog', 'icon' => '📰'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Blog', 'url' => '/admin/blog', 'icon' => '📰', 'roles' => ['admin']],
    ],

    'provides' => ['articles'],
];
