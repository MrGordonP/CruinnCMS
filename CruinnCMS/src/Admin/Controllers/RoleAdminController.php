<?php
/**
 * CruinnCMS — Role Admin Controller
 *
 * Admin CRUD for roles, permissions, dashboard config, and nav config.
 * All routes require 'admin' role + 'roles.manage' permission.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Services\RoleService;
use Cruinn\Services\DashboardService;
use Cruinn\Services\NavService;

class RoleAdminController extends \Cruinn\Controllers\BaseController
{
    private RoleService $roles;

    public function __construct()
    {
        parent::__construct();
        $this->roles = new RoleService();
    }

    /**
     * GET /admin/roles — List all roles.
     */
    public function index(): void
    {
        Auth::requirePermission('roles.manage');

        $allRoles = $this->roles->all();
        $selectedId = isset($_GET['role']) ? (int)$_GET['role'] : null;

        $role            = null;
        $permissions     = [];
        $rolePermissions = [];
        $roleUsers       = [];
        $usersNotInRole  = [];

        if ($selectedId) {
            $role = $this->roles->find($selectedId);
            if ($role) {
                $permissions    = $this->roles->allPermissions();
                $rolePermissions = $this->roles->rolePermissionIds($selectedId);
                $roleUsers      = $this->roles->getRoleUsers($selectedId);
                $usersNotInRole = $this->roles->getUsersNotInRole($selectedId);
            } else {
                $selectedId = null;
            }
        }

        $this->renderAdmin('admin/roles/index', [
            'title'           => 'Roles & Permissions',
            'allRoles'        => $allRoles,
            'role'            => $role,
            'permissions'     => $permissions,
            'rolePermissions' => $rolePermissions,
            'roleUsers'       => $roleUsers,
            'usersNotInRole'  => $usersNotInRole,
            'errors'          => [],
            'breadcrumbs'     => [['Admin', '/admin'], ['Roles']],
        ]);
    }

    /**
     * GET /admin/roles/new — New role form.
     */
    public function create(): void
    {
        Auth::requirePermission('roles.manage');

        $permissions = $this->roles->allPermissions();

        $this->renderAdmin('admin/roles/edit', [
            'title'            => 'New Role',
            'role'             => null,
            'permissions'      => $permissions,
            'rolePermissions'  => [],
            'errors'           => [],
            'breadcrumbs'      => [['Admin', '/admin'], ['Roles', '/admin/roles'], ['New Role']],
        ]);
    }

    /**
     * POST /admin/roles — Store a new role.
     */
    public function store(): void
    {
        Auth::requirePermission('roles.manage');

        $data = $this->gatherInput();
        $errors = $this->validate($data);

        if ($errors) {
            $permissions = $this->roles->allPermissions();
            $this->renderAdmin('admin/roles/edit', [
                'title'            => 'New Role',
                'role'             => $data,
                'permissions'      => $permissions,
                'rolePermissions'  => $data['permission_ids'] ?? [],
                'errors'           => $errors,
                'breadcrumbs'      => [['Admin', '/admin'], ['Roles', '/admin/roles'], ['New Role']],
            ]);
            return;
        }

        $id = $this->roles->create($data);

        $permIds = array_map('intval', $data['permission_ids'] ?? []);
        $this->roles->syncPermissions((int) $id, $permIds);

        $this->logActivity('create', 'role', (int) $id, "Created role: {$data['name']}");
        Auth::flash('success', "Role \"{$data['name']}\" created.");
        $this->redirect("/admin/roles?role={$id}");
    }

    /**
     * GET /admin/roles/{id}/edit — Edit role form.
     */
    public function edit(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $this->redirect('/admin/roles?role=' . $id);
    }

    /**
     * POST /admin/roles/{id} — Update a role.
     */
    public function update(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $data = $this->gatherInput();
        $errors = $this->validate($data, $id);

        // Prevent removing roles.manage from the admin role
        if ($role['slug'] === 'admin') {
            $permSlugs = $this->resolvePermissionSlugs($data['permission_ids'] ?? []);
            if (!in_array('roles.manage', $permSlugs, true)) {
                $errors['permissions'] = 'The admin role must retain the "Manage Roles" permission.';
            }
        }

        if ($errors) {
            $allRoles        = $this->roles->all();
            $roleUsers       = $this->roles->getRoleUsers($id);
            $usersNotInRole  = $this->roles->getUsersNotInRole($id);
            $permissions     = $this->roles->allPermissions();
            $this->renderAdmin('admin/roles/index', [
                'title'           => 'Edit Role — ' . $role['name'],
                'allRoles'        => $allRoles,
                'role'            => array_merge($role, $data),
                'permissions'     => $permissions,
                'rolePermissions' => array_map('intval', $data['permission_ids'] ?? []),
                'roleUsers'       => $roleUsers,
                'usersNotInRole'  => $usersNotInRole,
                'errors'          => $errors,
                'breadcrumbs'     => [['Admin', '/admin'], ['Roles', '/admin/roles'], [$role['name']]],
            ]);
            return;
        }

        $this->roles->update($id, $data);

        $permIds = array_map('intval', $data['permission_ids'] ?? []);
        $this->roles->syncPermissions($id, $permIds);

        $this->logActivity('update', 'role', $id, "Updated role: {$data['name']}");
        Auth::flash('success', "Role \"{$data['name']}\" updated.");
        $this->redirect("/admin/roles?role={$id}");
    }

    /**
     * POST /admin/roles/{id}/delete — Delete a role.
     */
    public function delete(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        if ($role['is_system']) {
            Auth::flash('error', 'System roles cannot be deleted.');
            $this->redirect('/admin/roles');
        }

        $name = $role['name'];
        $deleted = $this->roles->delete($id);
        if ($deleted) {
            $this->logActivity('delete', 'role', $id, "Deleted role: {$name}");
            Auth::flash('success', "Role \"{$name}\" deleted. Affected users moved to Public role.");
        } else {
            Auth::flash('error', 'Could not delete role.');
        }
        $this->redirect('/admin/roles');
    }

    /**
     * POST /admin/roles/{id}/clone — Clone a role.
     */
    public function cloneRole(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $baseSlug = $role['slug'] . '-copy';
        $slug = $baseSlug;
        $n = 1;
        while (!$this->roles->isSlugUnique($slug)) {
            $slug = $baseSlug . '-' . (++$n);
        }

        $newId = $this->roles->clone($id, $slug, $role['name'] . ' (Copy)');

        $this->logActivity('create', 'role', (int) $newId, "Cloned role from: {$role['name']}");
        Auth::flash('success', "Role cloned. Edit the new role below.");
        $this->redirect("/admin/roles/{$newId}/edit");
    }

    // ══════════════════════════════════════════════════════════════
    //  ROLE MEMBERSHIP (AJAX)
    // ══════════════════════════════════════════════════════════════

    /**
     * POST /admin/roles/{id}/users/add — Add a user to a role (AJAX).
     */
    public function addRoleUser(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            $this->json(['ok' => false, 'error' => 'Role not found'], 404);
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId) {
            $this->json(['ok' => false, 'error' => 'Invalid user_id']);
        }

        $this->roles->addUserToRole($id, $userId, Auth::userId());
        $this->logActivity('update', 'role', $id, "Added user #{$userId} to role: {$role['name']}");

        $users = $this->roles->getRoleUsers($id);
        $this->json(['ok' => true, 'users' => $users]);
    }

    /**
     * POST /admin/roles/{id}/users/remove — Remove a user from a role (AJAX).
     */
    public function removeRoleUser(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            $this->json(['ok' => false, 'error' => 'Role not found'], 404);
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId) {
            $this->json(['ok' => false, 'error' => 'Invalid user_id']);
        }

        // Prevent removing admin from the admin role
        if ($role['slug'] === 'admin' && $userId === Auth::userId()) {
            $this->json(['ok' => false, 'error' => 'You cannot remove yourself from the Admin role.']);
        }

        $this->roles->removeUserFromRole($id, $userId);
        $this->logActivity('update', 'role', $id, "Removed user #{$userId} from role: {$role['name']}");

        $users = $this->roles->getRoleUsers($id);
        $this->json(['ok' => true, 'users' => $users]);
    }

    // ══════════════════════════════════════════════════════════════
    //  DASHBOARD CONFIGURATION
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/roles/{id}/dashboard — Configure dashboard widgets for a role.
     */
    public function dashboardConfig(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $dashService = new DashboardService();
        $widgets = $dashService->getAllWidgetsForConfig($id);

        $this->renderAdmin('admin/roles/dashboard-config', [
            'title'       => 'Dashboard Config — ' . $role['name'],
            'role'        => $role,
            'widgets'     => $widgets,
            'breadcrumbs' => [['Admin', '/admin'], ['Roles', '/admin/roles'], [$role['name'], "/admin/roles/{$id}/edit"], ['Dashboard']],
        ]);
    }

    /**
     * POST /admin/roles/{id}/dashboard — Save dashboard widget config.
     */
    public function saveDashboardConfig(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $widgetConfigs = [];
        $widgetIds    = $_POST['widget_id'] ?? [];
        $sortOrders   = $_POST['sort_order'] ?? [];
        $gridWidths   = $_POST['grid_width'] ?? [];
        $visibilities = $_POST['is_visible'] ?? [];

        foreach ($widgetIds as $i => $widgetId) {
            $widgetConfigs[] = [
                'widget_id'  => (int) $widgetId,
                'sort_order' => (int) ($sortOrders[$i] ?? $i),
                'grid_width' => in_array($gridWidths[$i] ?? 'full', ['full', 'half']) ? $gridWidths[$i] : 'full',
                'is_visible' => !empty($visibilities[$widgetId]),
            ];
        }

        $dashService = new DashboardService();
        $dashService->saveWidgetConfig($id, $widgetConfigs);

        $this->logActivity('update', 'role', $id, "Updated dashboard config for role: {$role['name']}");
        Auth::flash('success', "Dashboard configuration updated for \"{$role['name']}\".");
        $this->redirect("/admin/roles/{$id}/dashboard");
    }

    // ══════════════════════════════════════════════════════════════
    //  NAVIGATION CONFIGURATION
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/roles/{id}/navigation — Configure nav items for a role.
     */
    public function navConfig(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $navService = new NavService();
        $navItems = $navService->getAllForConfig($id);

        $this->renderAdmin('admin/roles/navigation-config', [
            'title'       => 'Navigation Config — ' . $role['name'],
            'role'        => $role,
            'navItems'    => $navItems,
            'breadcrumbs' => [['Admin', '/admin'], ['Roles', '/admin/roles'], [$role['name'], "/admin/roles/{$id}/edit"], ['Navigation']],
        ]);
    }

    /**
     * POST /admin/roles/{id}/navigation — Save nav configuration.
     */
    public function saveNavConfig(int $id): void
    {
        Auth::requirePermission('roles.manage');

        $role = $this->roles->find($id);
        if (!$role) {
            Auth::flash('error', 'Role not found.');
            $this->redirect('/admin/roles');
        }

        $navItems   = [];
        $labels     = $_POST['label'] ?? [];
        $urls       = $_POST['url'] ?? [];
        $parentIds  = $_POST['parent_temp_id'] ?? [];
        $tempIds    = $_POST['temp_id'] ?? [];
        $sortOrders = $_POST['sort_order'] ?? [];
        $visibilities = $_POST['is_visible'] ?? [];
        $cssClasses = $_POST['css_class'] ?? [];
        $permissions = $_POST['permission_required'] ?? [];
        $newTabs    = $_POST['opens_new_tab'] ?? [];

        foreach ($labels as $i => $label) {
            $label = trim($label);
            if ($label === '') continue;

            $navItems[] = [
                'label'                => $label,
                'url'                  => trim($urls[$i] ?? '#'),
                'parent_temp_id'       => $parentIds[$i] ?: null,
                'temp_id'              => $tempIds[$i] ?: null,
                'sort_order'           => (int) ($sortOrders[$i] ?? $i),
                'is_visible'           => !empty($visibilities[$i]),
                'css_class'            => trim($cssClasses[$i] ?? ''),
                'permission_required'  => trim($permissions[$i] ?? ''),
                'opens_new_tab'        => !empty($newTabs[$i]),
            ];
        }

        $navService = new NavService();
        $navService->saveNavConfig($id, $navItems);

        $this->logActivity('update', 'role', $id, "Updated navigation config for role: {$role['name']}");
        Auth::flash('success', "Navigation configuration updated for \"{$role['name']}\".");
        $this->redirect("/admin/roles/{$id}/navigation");
    }

    // ── Private helpers ───────────────────────────────────────────

    private function gatherInput(): array
    {
        return [
            'name'             => $this->input('name', ''),
            'slug'             => $this->sanitiseSlug($this->input('slug', '')),
            'description'      => $this->input('description', ''),
            'level'            => (int) $this->input('level', 0),
            'colour'           => $this->input('colour', '#6c757d'),
            'default_redirect' => $this->input('default_redirect', '/'),
            'permission_ids'   => $_POST['permissions'] ?? [],
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Role name is required.';
        }
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required.';
        } elseif (!$this->roles->isSlugUnique($data['slug'], $excludeId)) {
            $errors['slug'] = 'A role with this slug already exists.';
        }
        if (!$this->roles->validateLevel($data['level'])) {
            $errors['level'] = 'Level must be between 0 and 100.';
        }

        return $errors;
    }

    private function resolvePermissionSlugs(array $permissionIds): array
    {
        if (empty($permissionIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT slug FROM permissions WHERE id IN ({$placeholders})",
            array_map('intval', $permissionIds)
        );
        return array_column($rows, 'slug');
    }
}
