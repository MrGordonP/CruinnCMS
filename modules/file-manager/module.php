<?php
/**
 * File Manager Module — Google-Drive-style file management.
 * Folder tree, file upload/compose, document parsing, version history, sharing.
 */

use IGA\Module\FileManager\Controllers\FileManagerController;

return [
    'slug'         => 'file-manager',
    'name'         => 'File Manager',
    'description'  => 'Google Drive-style file management with folder tree, document compose, version history, and sharing.',
    'provides'     => ['file-manager'],
    'migrations'   => ['016_file_manager.sql'],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Files', 'url' => '/files', 'icon' => '📁'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Files', 'url' => '/files', 'icon' => '📁', 'roles' => ['admin']],
    ],

    'routes' => function (IGA\Router $router) {
        $router->get('/files',                               [FileManagerController::class, 'index']);
        $router->get('/files/search',                        [FileManagerController::class, 'search']);
        $router->get('/files/upload',                        [FileManagerController::class, 'uploadForm']);
        $router->post('/files/upload',                       [FileManagerController::class, 'upload']);
        $router->get('/files/compose',                       [FileManagerController::class, 'composeForm']);
        $router->post('/files/compose',                      [FileManagerController::class, 'compose']);
        $router->post('/files/folders',                      [FileManagerController::class, 'createFolder']);
        $router->post('/files/folders/{id}/update',          [FileManagerController::class, 'updateFolder']);
        $router->post('/files/folders/{id}/delete',          [FileManagerController::class, 'deleteFolder']);
        $router->get('/files/{id}',                          [FileManagerController::class, 'show']);
        $router->get('/files/{id}/edit',                     [FileManagerController::class, 'edit']);
        $router->post('/files/{id}/edit',                    [FileManagerController::class, 'update']);
        $router->post('/files/{id}/autosave',                [FileManagerController::class, 'autosave']);
        $router->get('/files/{id}/download',                 [FileManagerController::class, 'download']);
        $router->post('/files/{id}/export',                  [FileManagerController::class, 'export']);
        $router->post('/files/{id}/version',                 [FileManagerController::class, 'newVersion']);
        $router->post('/files/{id}/status',                  [FileManagerController::class, 'updateStatus']);
        $router->post('/files/{id}/share',                   [FileManagerController::class, 'share']);
        $router->post('/files/{id}/unshare',                 [FileManagerController::class, 'unshare']);
        $router->post('/files/{id}/publish',                 [FileManagerController::class, 'publish']);
        $router->post('/files/{id}/delete',                  [FileManagerController::class, 'delete']);
    },
];
