<?php
/**
 * Documents Module — Organisation document library with versioning and approval workflow.
 */

use Cruinn\Module\Documents\Controllers\DocumentController;
use Cruinn\Module\Documents\Controllers\DocumentAdminController;

return [
    'slug'         => 'documents',
    'name'         => 'Documents',
    'description'  => 'Organisation document library: upload, version, approve, and archive documents.',
    'provides'     => ['documents'],
    'migrations'   => [
        __DIR__ . '/migrations/schema.sql',
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Organisation', 'label' => 'Documents',       'url' => '/documents',            'icon' => '📄'],
        ['group' => 'Organisation', 'label' => 'Documents Admin', 'url' => '/admin/documents',      'icon' => '⚙️'],
    ],

    'dashboard_sections' => [
        ['group' => 'Organisation', 'label' => 'Documents', 'url' => '/documents', 'icon' => '📄', 'roles' => ['admin', 'organisation']],
    ],

    'dashboard_widgets' => [
        [
            'slug'     => 'documents-summary',
            'label'    => 'Documents',
            'template' => 'admin/documents/widgets/summary',
            'provider' => 'Cruinn\\Module\\Documents\\Services\\DocumentDashboardService::dashboardSummary',
            'roles'    => ['admin'],
            'width'    => 'half',
            'order'    => 135,
            'settings' => ['limit' => 5],
        ],
    ],

    'routes' => static function (object $router): void {
        // Member-facing
        $router->get('/documents',                                              [DocumentController::class, 'index']);
        $router->get('/documents/new',                                         [DocumentController::class, 'uploadForm']);
        $router->post('/documents',                                            [DocumentController::class, 'upload']);
        $router->get('/documents/{id}',                                        [DocumentController::class, 'show']);
        $router->get('/documents/{id}/download',                               [DocumentController::class, 'download']);
        $router->get('/documents/{id}/versions/{versionId}/download',          [DocumentController::class, 'downloadVersion']);
        $router->post('/documents/{id}/version',                               [DocumentController::class, 'newVersion']);
        $router->post('/documents/{id}/submit',                                [DocumentController::class, 'submit']);
        $router->post('/documents/{id}/approve',                               [DocumentController::class, 'approve']);
        $router->post('/documents/{id}/archive',                               [DocumentController::class, 'archive']);
        $router->post('/documents/{id}/delete',                                [DocumentController::class, 'delete']);

        // Admin
        $router->get('/admin/documents',                                       [DocumentAdminController::class, 'index']);
        $router->post('/admin/documents/{id}/approve',                         [DocumentAdminController::class, 'approve']);
        $router->post('/admin/documents/{id}/reject',                          [DocumentAdminController::class, 'reject']);
        $router->post('/admin/documents/{id}/archive',                         [DocumentAdminController::class, 'archive']);
        $router->get('/admin/documents/categories',                            [DocumentAdminController::class, 'categories']);
        $router->post('/admin/documents/categories',                           [DocumentAdminController::class, 'createCategory']);
        $router->post('/admin/documents/categories/{id}/update',               [DocumentAdminController::class, 'updateCategory']);
        $router->post('/admin/documents/categories/{id}/delete',               [DocumentAdminController::class, 'deleteCategory']);
    },
];
