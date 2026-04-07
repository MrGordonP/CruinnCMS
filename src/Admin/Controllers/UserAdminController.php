<?php
/**
 * CruinnCMS — User Admin Controller
 *
 * Handles user account management in the admin panel.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Services\RoleService;

class UserAdminController extends \Cruinn\Controllers\BaseController
{
    /**
     * GET /admin/users — List all user accounts with filters.
     */
    public function userList(): void
    {
        $role   = $this->query('role', '');
        $status = $this->query('status', '');
        $search = $this->query('q', '');
        $page   = max(1, (int) $this->query('page', 1));
        $perPage = 25;

        $where  = [];
        $params = [];

        if ($role !== '') {
            $where[]  = 'u.role = ?';
            $params[] = $role;
        }
        if ($status === 'active') {
            $where[] = 'u.active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.active = 0';
        }
        if ($search !== '') {
            $where[]  = '(u.email LIKE ? OR u.display_name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM users u {$whereSQL}", $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $users = $this->db->fetchAll(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM activity_log al WHERE al.user_id = u.id) AS activity_count
             FROM users u
             {$whereSQL}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Role counts for summary
        $roleCounts = $this->db->fetchAll(
            'SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY FIELD(role, "admin", "council", "member", "public")'
        );

        $this->renderAdmin('admin/users/index', [
            'title'       => 'Users',
            'users'       => $users,
            'role'        => $role,
            'status'      => $status,
            'search'      => $search,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'roleCounts'  => $roleCounts,
            'breadcrumbs' => [['Admin', '/admin'], ['Users']],
        ]);
    }

    /**
     * GET /admin/users/{id} — View user profile and activity.
     */
    public function userShow(int $id): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        // Check if user has a linked member record
        $member = $this->db->fetch(
            'SELECT * FROM members WHERE user_id = ?',
            [$id]
        );

        // Recent activity
        $activity = $this->db->fetchAll(
            'SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 25',
            [$id]
        );

        $roleService = new RoleService();
        $this->renderAdmin('admin/users/show', [
            'title'       => $user['display_name'],
            'user'        => $user,
            'member'      => $member,
            'activity'    => $activity,
            'userRoles'   => $roleService->getUserRoles($id),
            'userGroups'  => $roleService->getUserGroups($id),
            'breadcrumbs' => [['Admin', '/admin'], ['Users', '/admin/users'], [$user['display_name']]],
        ]);
    }

    /**
     * GET /admin/users/new — New user form.
     */
    public function userNew(): void
    {
        $roleService = new RoleService();
        $this->renderAdmin('admin/users/edit', [
            'title'       => 'New User',
            'user'        => null,
            'allRoles'    => $roleService->all(),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Users', '/admin/users'], ['New User']],
        ]);
    }

    /**
     * POST /admin/users — Create a new user account.
     */
    public function userCreate(): void
    {
        $data = [
            'email'        => $this->input('email'),
            'display_name' => $this->input('display_name'),
            'role'         => $this->input('role', 'member'),
            'active'       => $this->input('active') ? 1 : 0,
        ];
        $password = $this->input('password', '');

        $errors = $this->validateRequired([
            'email'        => 'Email',
            'display_name' => 'Display name',
        ]);

        // Validate email format
        if (empty($errors['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        // Check email uniqueness
        if (empty($errors['email'])) {
            $existing = $this->db->fetchColumn('SELECT COUNT(*) FROM users WHERE email = ?', [$data['email']]);
            if ($existing) {
                $errors['email'] = 'A user with this email already exists.';
            }
        }

        // Password required for new users
        if (empty($password)) {
            $errors['password'] = 'Password is required for new users.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        $roleService = new RoleService();
        $roleIds = array_map('intval', $this->input('role_ids') ?? []);
        if (empty($roleIds)) {
            $errors['role'] = 'At least one role must be assigned.';
        }

        if ($errors) {
            $this->renderAdmin('admin/users/edit', [
                'title'       => 'New User',
                'user'        => $data,
                'allRoles'    => $roleService->all(),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Users', '/admin/users'], ['New User']],
            ]);
            return;
        }

        $userId = $this->db->insert('users', [
            'email'         => $data['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name'  => $data['display_name'],
            'role'          => 'member',
            'role_id'       => null,
            'active'        => $data['active'],
        ]);

        // Sync roles (sets primary role automatically)
        $roleService->syncUserRoles((int) $userId, $roleIds, Auth::userId());

        $this->logActivity('create', 'user', (int) $userId, "Created user: {$data['email']}");
        Auth::flash('success', "User account created for {$data['email']}.");
        $this->redirect("/admin/users/{$userId}");
    }

    /**
     * GET /admin/users/{id}/edit — Edit user form.
     */
    public function userEdit(int $id): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $roleService = new RoleService();
        $this->renderAdmin('admin/users/edit', [
            'title'       => 'Edit User — ' . $user['display_name'],
            'user'        => $user,
            'allRoles'    => $roleService->all(),
            'allGroups'   => $roleService->allGroups(),
            'userGroups'  => $roleService->getUserGroups($id),
            'userRoleIds' => $roleService->getUserRoleIds($id),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Users', '/admin/users'], [$user['display_name']]],
        ]);
    }

    /**
     * POST /admin/users/{id} — Update user account.
     */
    public function userUpdate(int $id): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = [
            'email'        => $this->input('email'),
            'display_name' => $this->input('display_name'),
            'active'       => $this->input('active') ? 1 : 0,
        ];
        $password = $this->input('password', '');
        $roleIds = array_map('intval', $this->input('role_ids') ?? []);

        $errors = $this->validateRequired([
            'email'        => 'Email',
            'display_name' => 'Display name',
        ]);

        // Validate email format
        if (empty($errors['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        // Check uniqueness (excluding self)
        if (empty($errors['email'])) {
            $existing = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM users WHERE email = ? AND id != ?',
                [$data['email'], $id]
            );
            if ($existing) {
                $errors['email'] = 'A user with this email already exists.';
            }
        }

        // Optional password update
        if (!empty($password) && strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        $roleService = new RoleService();

        // Validate at least one role selected
        if (empty($roleIds)) {
            $errors['role'] = 'At least one role must be assigned.';
        }

        // Prevent self-demotion from admin
        if ($id === Auth::userId()) {
            $adminRole = $roleService->findBySlug('admin');
            if ($adminRole && !in_array((int)$adminRole['id'], $roleIds, true)) {
                $errors['role'] = 'You cannot remove admin from your own account.';
            }
        }

        // Prevent deactivating self
        if ($id === Auth::userId() && !$data['active']) {
            $errors['active'] = 'You cannot deactivate your own account.';
        }

        if ($errors) {
            $data['id'] = $id;
            $this->renderAdmin('admin/users/edit', [
                'title'       => 'Edit User — ' . $user['display_name'],
                'user'        => $data,
                'allRoles'    => $roleService->all(),
                'allGroups'   => $roleService->allGroups(),
                'userGroups'  => $roleService->getUserGroups($id),
                'userRoleIds' => $roleIds,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Users', '/admin/users'], [$user['display_name']]],
            ]);
            return;
        }

        $updateData = [
            'email'        => $data['email'],
            'display_name' => $data['display_name'],
            'active'       => $data['active'],
        ];

        // Only update password if provided
        if (!empty($password)) {
            $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->db->update('users', $updateData, 'id = ?', [$id]);

        // Sync roles (also sets primary role on users table)
        $roleService->syncUserRoles($id, $roleIds, Auth::userId());

        // Sync group memberships
        $groupIds = array_map('intval', $_POST['group_ids'] ?? []);
        $roleService->syncUserGroups($id, $groupIds, Auth::userId());

        $changes = [];
        if ($user['active'] != $data['active']) {
            $changes[] = $data['active'] ? 'activated' : 'deactivated';
        }
        if (!empty($password)) {
            $changes[] = 'password reset';
        }

        $detail = $changes ? implode(', ', $changes) : 'profile updated';
        $this->logActivity('update', 'user', $id, "Updated {$user['email']}: {$detail}");
        Auth::flash('success', "User {$user['email']} updated.");
        $this->redirect("/admin/users/{$id}");
    }

    /**
     * POST /admin/users/{id}/toggle — Toggle active/inactive status.
     */
    public function userToggleActive(int $id): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        // Prevent self-deactivation
        if ($id === Auth::userId()) {
            Auth::flash('error', 'You cannot deactivate your own account.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        $newActive = $user['active'] ? 0 : 1;
        $this->db->update('users', ['active' => $newActive], 'id = ?', [$id]);

        $action = $newActive ? 'activated' : 'deactivated';
        $this->logActivity('update', 'user', $id, "Account {$action}: {$user['email']}");
        Auth::flash('success', "User {$user['email']} {$action}.");
        $this->redirect("/admin/users/{$id}");
    }

    /**
     * POST /admin/users/{id}/delete — Delete a user account.
     */
    public function userDelete(int $id): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        // Prevent self-deletion
        if ($id === Auth::userId()) {
            Auth::flash('error', 'You cannot delete your own account.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        $email = $user['email'];
        $this->db->delete('users', 'id = ?', [$id]);
        $this->logActivity('delete', 'user', $id, "Deleted user: {$email}");
        Auth::flash('success', "User {$email} deleted.");
        $this->redirect('/admin/users');
    }
}
