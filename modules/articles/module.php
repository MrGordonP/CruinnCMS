<?php
/**
 * Articles Module
 *
 * Public news/blog articles with admin CRUD and block editor support.
 */

use IGA\Router;
use IGA\Module\Articles\Controllers\ArticleController;

return [
    'slug'        => 'articles',
    'name'        => 'Articles / News',
    'version'     => '1.0.0',
    'description' => 'Public-facing news and blog articles with block editor support.',

    'routes' => function (Router $router): void {
        // Admin
        $router->get('/admin/articles',              [ArticleController::class, 'adminList']);
        $router->get('/admin/articles/new',          [ArticleController::class, 'adminNew']);
        $router->post('/admin/articles',             [ArticleController::class, 'adminCreate']);
        $router->get('/admin/articles/{id}/edit',    [ArticleController::class, 'adminEdit']);
        $router->post('/admin/articles/{id}',        [ArticleController::class, 'adminUpdate']);
        $router->post('/admin/articles/{id}/delete', [ArticleController::class, 'adminDelete']);

        // Public
        $router->get('/news',        [ArticleController::class, 'index']);
        $router->get('/news/{slug}', [ArticleController::class, 'show']);
    },

    'migrations' => [
        __DIR__ . '/migrations/005_content_articles.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Articles', 'url' => '/admin/articles', 'icon' => '📰'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Articles', 'url' => '/admin/articles', 'icon' => '📰', 'roles' => ['admin']],
    ],

    'provides' => ['articles'],
];
