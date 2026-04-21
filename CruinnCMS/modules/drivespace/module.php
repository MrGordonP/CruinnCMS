<?php
/**
 * Drivespace Module — Google-Drive-style file management.
 * Folder tree, file upload, version history, and sharing.
 */

use Cruinn\Module\Drivespace\Controllers\FileManagerController;
use Cruinn\Module\Drivespace\Controllers\FileManagerAdminController;

return [
    'slug'         => 'drivespace',
    'name'         => 'Drivespace',
    'description'  => 'Google Drive-style storage with folder tree, upload, version history, and sharing.',
    'provides'     => ['drivespace'],
    'migrations'   => [
        __DIR__ . '/migrations/schema.sql',
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Drivespace',       'url' => '/drivespace',       'icon' => '📁'],
        ['group' => 'Community', 'label' => 'Drivespace Admin', 'url' => '/admin/drivespace',  'icon' => '⚙️'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Drivespace', 'url' => '/drivespace', 'icon' => '📁', 'roles' => ['admin']],
    ],

    'routes' => function (Cruinn\Router $router) {
        $router->get('/drivespace',                               [FileManagerController::class, 'index']);
        $router->get('/drivespace/search',                        [FileManagerController::class, 'search']);
        $router->get('/drivespace/folder/{id}/info',              [FileManagerController::class, 'folderInfo']);
        $router->get('/drivespace/file/{id}/info',                [FileManagerController::class, 'fileInfo']);
        $router->get('/drivespace/upload',                        [FileManagerController::class, 'uploadForm']);
        $router->post('/drivespace/upload',                       [FileManagerController::class, 'upload']);
        $router->post('/drivespace/folders',                      [FileManagerController::class, 'createFolder']);
        $router->post('/drivespace/folders/{id}/update',          [FileManagerController::class, 'updateFolder']);
        $router->post('/drivespace/folders/{id}/delete',          [FileManagerController::class, 'deleteFolder']);
        $router->get('/drivespace/{id}',                          [FileManagerController::class, 'show']);
        $router->get('/drivespace/{id}/download',                 [FileManagerController::class, 'download']);
        $router->post('/drivespace/{id}/version',                 [FileManagerController::class, 'newVersion']);
        $router->post('/drivespace/{id}/share',                   [FileManagerController::class, 'share']);
        $router->post('/drivespace/{id}/unshare',                 [FileManagerController::class, 'unshare']);
        $router->post('/drivespace/{id}/delete',                  [FileManagerController::class, 'delete']);

        // Admin quota management
        $router->get('/admin/drivespace',                         [FileManagerAdminController::class, 'index']);
        $router->post('/admin/drivespace/{id}/quota',             [FileManagerAdminController::class, 'setQuota']);
    },
];
