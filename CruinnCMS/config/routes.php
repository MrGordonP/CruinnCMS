<?php
/**
 * CruinnCMS � Route Definitions (Core)
 *
 * Only core routes are registered here. Feature module routes are registered
 * automatically by ModuleRegistry::registerRoutes() from each module's module.php.
 *
 * Routes are matched in order � first match wins.
 */

use Cruinn\Controllers\PageController;
use Cruinn\Controllers\AdminController;
use Cruinn\Controllers\AuthController;
use Cruinn\Controllers\SubjectController;
use Cruinn\Controllers\MenuController;
use Cruinn\Admin\Controllers\RoleAdminController;
use Cruinn\Admin\Controllers\GroupController;
use Cruinn\Controllers\AcpController;
use Cruinn\Controllers\CruinnController;
use Cruinn\Admin\Controllers\AcpSystemController;
use Cruinn\Admin\Controllers\AcpInstanceController;
use Cruinn\Admin\Controllers\SiteBuilderController;
use Cruinn\Admin\Controllers\AdminPageController;
use Cruinn\Admin\Controllers\BlockController;
use Cruinn\Admin\Controllers\MediaController;
use Cruinn\Admin\Controllers\UserAdminController;
use Cruinn\Platform\Controllers\PlatformController;

return function (Cruinn\Router $router) {

    // -- Platform (/cms/) ------------------------------------------
    // Login/logout are exempt from PlatformAuth middleware (handled in middleware itself).
    $router->get('/cms/install',                        [PlatformController::class, 'showInstall']);
    $router->post('/cms/install',                       [PlatformController::class, 'install']);
    $router->get('/cms/login',                          [PlatformController::class, 'showLogin']);
    $router->post('/cms/login',                         [PlatformController::class, 'login']);
    $router->get('/cms/logout',                         [PlatformController::class, 'logout']);
    $router->get('/cms',                                [PlatformController::class, 'dashboard']);
    $router->get('/cms/dashboard',                      [PlatformController::class, 'dashboard']);
    $router->get('/cms/settings',                       [PlatformController::class, 'showSettings']);
    $router->post('/cms/settings',                      [PlatformController::class, 'saveSettings']);
    $router->post('/cms/instances/{name}/toggle',     [PlatformController::class, 'toggleInstance']);
    $router->get('/cms/instances/new',                  [PlatformController::class, 'showProvisionInstance']);
    $router->post('/cms/instances/new',                 [PlatformController::class, 'provisionInstance']);
    $router->get('/cms/source',                         [PlatformController::class, 'platformSource']);
    $router->post('/cms/source/save',                   [PlatformController::class, 'platformSourceSave']);
    $router->get('/cms/source/preview',                 [PlatformController::class, 'platformSourcePreview']);
    $router->get('/cms/editor',                         [PlatformController::class, 'editorPicker']);
    $router->get('/cms/editor/files',                   [PlatformController::class, 'editorFiles']);
    // Platform editor AJAX — same CruinnController methods, platform auth via requireEditorAuth()
    $router->post('/cms/editor/{pageId}/action',        [CruinnController::class, 'recordAction']);
    $router->post('/cms/editor/{pageId}/undo',          [CruinnController::class, 'undo']);
    $router->post('/cms/editor/{pageId}/redo',           [CruinnController::class, 'redo']);
    $router->post('/cms/editor/{pageId}/publish',        [CruinnController::class, 'publish']);
    $router->post('/cms/editor/{pageId}/discard',        [CruinnController::class, 'discardDraft']);
    $router->post('/cms/editor/{pageId}/doc-attrs',      [CruinnController::class, 'saveDocAttrs']);
    $router->get('/cms/database',                       [PlatformController::class, 'dbBrowse']);
    $router->get('/cms/database/browse/{table}',             [PlatformController::class, 'dbBrowseTable']);
    $router->get('/cms/database/browse/{table}/edit',        [PlatformController::class, 'dbEditRow']);
    $router->post('/cms/database/browse/{table}/edit',       [PlatformController::class, 'dbSaveRow']);
    $router->post('/cms/database/browse/{table}/delete',     [PlatformController::class, 'dbDeleteRow']);
    $router->get('/cms/database/query',                 [PlatformController::class, 'dbQueryPage']);
    $router->post('/cms/database/query',                [PlatformController::class, 'dbRunQuery']);
    $router->get('/admin/maintenance/links',            [\Cruinn\Admin\Controllers\MaintenanceController::class, 'linkCheck']);
    $router->post('/admin/maintenance/links',           [\Cruinn\Admin\Controllers\MaintenanceController::class, 'runLinkCheck']);

    // -- Authentication ---------------------------------------------
    $router->get('/login',            [AuthController::class, 'showLogin']);
    $router->post('/login',           [AuthController::class, 'login']);
    $router->get('/logout',           [AuthController::class, 'logout']);

    // -- Password Reset ---------------------------------------------
    $router->get('/forgot-password',           [AuthController::class, 'showForgotPassword']);
    $router->post('/forgot-password',          [AuthController::class, 'forgotPassword']);
    $router->get('/reset-password/{token}',    [AuthController::class, 'showResetPassword']);
    $router->post('/reset-password/{token}',   [AuthController::class, 'resetPassword']);

    // -- User Profile -----------------------------------------------
    $router->get('/profile',                   [AuthController::class, 'showProfile']);
    $router->post('/profile',                  [AuthController::class, 'updateProfile']);

    // -- Admin Panel ------------------------------------------------
    $router->get('/admin',                       [AcpController::class, 'index']);
    $router->get('/admin/dashboard',             [AdminController::class, 'dashboard']);
    $router->get('/admin/platform-passthrough',  [PlatformController::class, 'passthrough']);

    // Admin � Users
    $router->get('/admin/users',              [UserAdminController::class, 'userList']);
    $router->get('/admin/users/new',          [UserAdminController::class, 'userNew']);
    $router->post('/admin/users',             [UserAdminController::class, 'userCreate']);
    $router->get('/admin/users/{id}',         [UserAdminController::class, 'userShow']);
    $router->get('/admin/users/{id}/edit',    [UserAdminController::class, 'userEdit']);
    $router->post('/admin/users/{id}',        [UserAdminController::class, 'userUpdate']);
    $router->post('/admin/users/{id}/toggle', [UserAdminController::class, 'userToggleActive']);
    $router->post('/admin/users/{id}/delete', [UserAdminController::class, 'userDelete']);

    // Admin � Roles & Permissions
    $router->get('/admin/roles',                [RoleAdminController::class, 'index']);
    $router->get('/admin/roles/new',            [RoleAdminController::class, 'create']);
    $router->post('/admin/roles',               [RoleAdminController::class, 'store']);
    $router->get('/admin/roles/{id}/edit',      [RoleAdminController::class, 'edit']);
    $router->post('/admin/roles/{id}',          [RoleAdminController::class, 'update']);
    $router->post('/admin/roles/{id}/delete',   [RoleAdminController::class, 'delete']);
    $router->post('/admin/roles/{id}/clone',    [RoleAdminController::class, 'cloneRole']);
    $router->get('/admin/roles/{id}/dashboard',  [RoleAdminController::class, 'dashboardConfig']);
    $router->post('/admin/roles/{id}/dashboard', [RoleAdminController::class, 'saveDashboardConfig']);
    $router->get('/admin/roles/{id}/navigation',  [RoleAdminController::class, 'navConfig']);
    $router->post('/admin/roles/{id}/navigation', [RoleAdminController::class, 'saveNavConfig']);

    // Admin � Groups
    $router->get('/admin/groups',               [GroupController::class, 'groupIndex']);
    $router->get('/admin/groups/new',           [GroupController::class, 'groupCreate']);
    $router->post('/admin/groups',              [GroupController::class, 'groupStore']);
    $router->get('/admin/groups/{id}/edit',     [GroupController::class, 'groupEdit']);
    $router->post('/admin/groups/{id}',         [GroupController::class, 'groupUpdate']);
    $router->post('/admin/groups/{id}/delete',          [GroupController::class, 'groupDelete']);
    $router->post('/admin/groups/{id}/members/add',    [GroupController::class, 'groupMemberAdd']);
    $router->post('/admin/groups/{id}/members/{uid}/remove', [GroupController::class, 'groupMemberRemove']);

    // Admin � Pages
    $router->get('/admin/pages',                    [AdminPageController::class, 'listPages']);
    $router->get('/admin/pages/new',                [AdminPageController::class, 'newPage']);
    $router->post('/admin/pages',                   [AdminPageController::class, 'createPage']);
    $router->get('/admin/pages/{id}/edit',          [AdminPageController::class, 'editPage']);
    $router->post('/admin/pages/{id}',              [AdminPageController::class, 'updatePage']);
    $router->post('/admin/pages/{id}/delete',       [AdminPageController::class, 'deletePage']);
    $router->get('/admin/pages/{id}/html',          [AdminPageController::class, 'htmlEditor']);
    $router->post('/admin/pages/{id}/html',         [AdminPageController::class, 'saveHtml']);
    $router->post('/admin/pages/{id}/export-html',  [AdminPageController::class, 'exportHtml']);
    $router->post('/admin/pages/{id}/convert-to-blocks', [AdminPageController::class, 'convertToBlocks']);

    // Admin � Import
    $router->get('/admin/import',                   [\Cruinn\Admin\Controllers\AdminImportController::class, 'index']);
    $router->post('/admin/import/upload',           [\Cruinn\Admin\Controllers\AdminImportController::class, 'upload']);
    $router->post('/admin/import/confirm',          [\Cruinn\Admin\Controllers\AdminImportController::class, 'confirm']);

    // Admin � Subjects
    $router->get('/admin/subjects',              [SubjectController::class, 'adminList']);
    $router->get('/admin/subjects/new',          [SubjectController::class, 'adminNew']);
    $router->post('/admin/subjects',             [SubjectController::class, 'adminCreate']);
    $router->get('/admin/subjects/{id}/edit',    [SubjectController::class, 'adminEdit']);
    $router->post('/admin/subjects/{id}',        [SubjectController::class, 'adminUpdate']);
    $router->post('/admin/subjects/{id}/delete', [SubjectController::class, 'adminDelete']);

    // Admin � Menus
    $router->get('/admin/menus',                              [MenuController::class, 'adminList']);
    $router->get('/admin/menus/new',                          [MenuController::class, 'adminNew']);
    $router->post('/admin/menus',                             [MenuController::class, 'adminCreate']);
    $router->get('/admin/menus/{id}/edit',                    [MenuController::class, 'adminEdit']);
    $router->post('/admin/menus/{id}',                        [MenuController::class, 'adminUpdate']);
    $router->post('/admin/menus/{id}/delete',                 [MenuController::class, 'adminDelete']);
    $router->post('/admin/menus/{menuId}/items',              [MenuController::class, 'addItem']);
    $router->post('/admin/menus/{menuId}/items/{itemId}',              [MenuController::class, 'updateItem']);
    $router->post('/admin/menus/{menuId}/items/{itemId}/delete',       [MenuController::class, 'deleteItem']);
    $router->post('/admin/menus/{menuId}/reorder',            [MenuController::class, 'reorderItems']);
    $router->get('/admin/menus/{id}/block-editor',            [MenuController::class, 'blockEditor']);

    // Admin � Blocks (AJAX endpoints for block editor)
    $router->post('/admin/blocks',             [BlockController::class, 'addBlock']);
    $router->post('/admin/blocks/reorder',     [BlockController::class, 'reorderBlocks']);
    $router->post('/admin/blocks/{id}/move',   [BlockController::class, 'moveBlock']);
    $router->post('/admin/blocks/{id}',        [BlockController::class, 'updateBlock']);
    $router->post('/admin/blocks/{id}/delete', [BlockController::class, 'deleteBlock']);

    // Admin CruinnOpenEditor – redirect to first page
    $router->get('/admin/editor',                          [CruinnController::class, 'openEditor']);

    // Admin � Cruinn Page Editor
    $router->get('/admin/editor/zone/{zone}',              [CruinnController::class, 'editZone']);
    $router->get('/admin/editor/nav-menu-preview',         [CruinnController::class, 'navMenuPreview']);
    $router->get('/admin/editor/php-include-preview',      [CruinnController::class, 'phpIncludePreview']);
    $router->get('/admin/editor/{pageId}/edit',            [CruinnController::class, 'edit']);
    $router->post('/admin/editor/{pageId}/action',         [CruinnController::class, 'recordAction']);
    $router->post('/admin/editor/{pageId}/undo',           [CruinnController::class, 'undo']);
    $router->post('/admin/editor/{pageId}/redo',           [CruinnController::class, 'redo']);
    $router->post('/admin/editor/{pageId}/publish',        [CruinnController::class, 'publish']);
    $router->post('/admin/editor/{pageId}/discard',        [CruinnController::class, 'discardDraft']);
    $router->post('/admin/editor/{pageId}/doc-attrs',      [CruinnController::class, 'saveDocAttrs']);

    // Named Block Library
    $router->get('/admin/blocks/named',              [SiteBuilderController::class, 'namedBlockList']);
    $router->post('/admin/blocks/named',             [SiteBuilderController::class, 'namedBlockSave']);
    $router->post('/admin/blocks/named/{id}/delete', [SiteBuilderController::class, 'namedBlockDelete']);
    $router->post('/admin/editor-mode',      [BlockController::class, 'updateEditorMode']);

    // Admin � Media uploads
    $router->post('/admin/upload',          [MediaController::class, 'uploadFile']);
    $router->get('/admin/media',            [MediaController::class, 'index']);
    $router->get('/admin/media/list',       [MediaController::class, 'listMedia']);
    $router->post('/admin/media/folder',    [MediaController::class, 'createFolder']);
    $router->post('/admin/media/delete',    [MediaController::class, 'deleteFolder']);
    $router->post('/admin/media/delete-file', [MediaController::class, 'deleteFile']);

    // Admin � ACP Settings
    $router->get('/admin/settings',                    [AcpController::class, 'index']);
    // Admin � ACP Settings: Core System
    $router->get('/admin/settings/site',               [AcpSystemController::class, 'site']);
    $router->post('/admin/settings/site',              [AcpSystemController::class, 'saveSite']);
    $router->get('/admin/settings/email',              [AcpSystemController::class, 'email']);
    $router->post('/admin/settings/email',             [AcpSystemController::class, 'saveEmail']);
    $router->post('/admin/settings/email/test',        [AcpSystemController::class, 'testEmail']);
    $router->get('/admin/settings/auth',               [AcpSystemController::class, 'auth']);
    $router->post('/admin/settings/auth',              [AcpSystemController::class, 'saveAuth']);
    $router->get('/admin/settings/security',           [AcpSystemController::class, 'security']);
    $router->post('/admin/settings/security',          [AcpSystemController::class, 'saveSecurity']);
    $router->get('/admin/settings/system',             [AcpSystemController::class, 'system']);
    $router->get('/admin/settings/database',                    [AcpSystemController::class, 'database']);
    $router->post('/admin/settings/database/optimize',          [AcpSystemController::class, 'optimizeDatabase']);
    $router->get('/admin/settings/database/export',             [AcpSystemController::class, 'exportDatabase']);
    $router->post('/admin/settings/database/export-instance',   [AcpSystemController::class, 'exportInstance']);
    $router->post('/admin/settings/database/run-queue',         [AcpSystemController::class, 'runQueue']);
    $router->get('/admin/settings/database/browse/{table}',          [AcpSystemController::class, 'browseTable']);
    $router->get('/admin/settings/database/browse/{table}/edit',      [AcpSystemController::class, 'editRow']);
    $router->post('/admin/settings/database/browse/{table}/edit',     [AcpSystemController::class, 'saveRow']);
    $router->post('/admin/settings/database/browse/{table}/delete',   [AcpSystemController::class, 'deleteRow']);
    $router->get('/admin/settings/database/query',              [AcpSystemController::class, 'queryPage']);
    $router->post('/admin/settings/database/query',             [AcpSystemController::class, 'runQuery']);
    $router->post('/admin/settings/layout',            [AcpController::class, 'saveLayout']);

    // Admin � ACP Settings: Instance Integrations
    $router->get('/admin/settings/gdpr',               [AcpInstanceController::class, 'gdpr']);
    $router->post('/admin/settings/gdpr',              [AcpInstanceController::class, 'saveGdpr']);
    $router->get('/admin/settings/social',             [AcpInstanceController::class, 'social']);
    $router->post('/admin/settings/social',            [AcpInstanceController::class, 'saveSocial']);
    $router->get('/admin/settings/payments',           [AcpInstanceController::class, 'payments']);
    $router->post('/admin/settings/payments',          [AcpInstanceController::class, 'savePayments']);
    $router->get('/admin/settings/oauth',              [AcpInstanceController::class, 'oauth']);
    $router->post('/admin/settings/oauth',             [AcpInstanceController::class, 'saveOauth']);

    // Admin � Modules panel
    $router->get('/admin/settings/modules',                         [AcpSystemController::class, 'modules']);
    $router->post('/admin/settings/modules/{slug}/toggle',          [AcpSystemController::class, 'toggleModule']);
    $router->post('/admin/settings/modules/{slug}/settings',        [AcpSystemController::class, 'saveModuleSettings']);
    $router->post('/admin/settings/modules/{slug}/migrate',         [AcpSystemController::class, 'applyModuleMigrations']);

    // Admin � Site Builder
    $router->get('/admin/site-builder',                       [SiteBuilderController::class, 'builderPages']);
    $router->get('/admin/site-builder/pages',                 [SiteBuilderController::class, 'builderPages']);
    $router->get('/admin/templates',                          [SiteBuilderController::class, 'builderTemplates']);
    $router->post('/admin/templates',                         [SiteBuilderController::class, 'builderCreateTemplate']);
    $router->get('/admin/site-builder/global-header',         [SiteBuilderController::class, 'builderGlobalHeader']);
    $router->get('/admin/site-builder/global-footer',         [SiteBuilderController::class, 'builderGlobalFooter']);
    $router->get('/admin/templates/{id}/edit',                [SiteBuilderController::class, 'builderEditTemplate']);
    $router->get('/admin/templates/{id}/preview',             [SiteBuilderController::class, 'builderPreviewTemplate']);
    $router->post('/admin/templates/{id}',                    [SiteBuilderController::class, 'builderUpdateTemplate']);
    $router->post('/admin/templates/{id}/zone-settings',      [SiteBuilderController::class, 'builderUpdateZoneSettings']);
    $router->post('/admin/templates/{id}/canvas',             [SiteBuilderController::class, 'builderEnsureCanvas']);
    $router->post('/admin/templates/{id}/delete',             [SiteBuilderController::class, 'builderDeleteTemplate']);
    $router->get('/admin/site-builder/menus',                 [SiteBuilderController::class, 'builderMenus']);
    $router->get('/admin/site-builder/structure',             [SiteBuilderController::class, 'builderStructure']);
    $router->get('/admin/template-editor',                    [SiteBuilderController::class, 'templateEditorList']);
    $router->get('/admin/template-editor/edit',               [SiteBuilderController::class, 'templateEditorEdit']);
    $router->post('/admin/template-editor/edit',              [SiteBuilderController::class, 'templateEditorEdit']);
    $router->get('/admin/template-editor/vars',               [SiteBuilderController::class, 'templateEditorVars']);
    $router->get('/admin/template-editor/preview',            [SiteBuilderController::class, 'templateEditorPreview']);

    // '/' and '/{slug}' catch-alls are intentionally registered in App::init()
    // AFTER module routes, so module paths like /news /events /forum are never
    // shadowed by the page catch-all. Do not add them here.
};
