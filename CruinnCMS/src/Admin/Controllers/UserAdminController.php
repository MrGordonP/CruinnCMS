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
            $where[] = 'EXISTS (
                SELECT 1
                FROM user_roles urf
                JOIN roles rf ON rf.id = urf.role_id
                WHERE urf.user_id = u.id AND rf.slug = ?
            )';
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
                    (SELECT COUNT(*) FROM activity_log al WHERE al.user_id = u.id) AS activity_count,
                    (
                        SELECT r.slug
                        FROM user_roles ur
                        JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id
                        ORDER BY r.level DESC, r.id ASC
                        LIMIT 1
                    ) AS primary_role_slug,
                    (
                        SELECT r.name
                        FROM user_roles ur
                        JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id
                        ORDER BY r.level DESC, r.id ASC
                        LIMIT 1
                    ) AS primary_role_name
             FROM users u
             {$whereSQL}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Role counts for summary
        $roleCounts = $this->db->fetchAll(
            'SELECT r.slug AS role_slug,
                    r.name AS role_name,
                    COUNT(*) AS cnt,
                    COALESCE(MAX(r.level), 0) AS max_level
             FROM users u
             LEFT JOIN roles r ON r.id = (
                 SELECT ur.role_id
                 FROM user_roles ur
                 JOIN roles r2 ON r2.id = ur.role_id
                 WHERE ur.user_id = u.id
                 ORDER BY r2.level DESC, r2.id ASC
                 LIMIT 1
             )
             GROUP BY r.slug, r.name
             ORDER BY max_level DESC, role_name ASC'
        );

        $roleOptions = $this->db->fetchAll(
            'SELECT slug, name FROM roles ORDER BY level DESC, name ASC'
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
            'roleOptions' => $roleOptions,
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

        $member = null;
        try {
            $member = $this->db->fetch('SELECT * FROM members WHERE user_id = ?', [$id]);
        } catch (\PDOException $e) {}

        $activity = $this->db->fetchAll(
            'SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 25',
            [$id]
        );

        $roleService = new RoleService();
        $this->renderAdmin('admin/users/edit', [
            'title'             => $user['display_name'],
            'user'              => $user,
            'member'            => $member,
            'activity'          => $activity,
            'userRoles'         => $roleService->getUserRoles($id),
            'rolesNotAssigned'  => $roleService->getRolesNotAssignedToUser($id),
            'userGroups'        => $roleService->getUserGroups($id),
            'groupsNotAssigned' => $roleService->getGroupsNotAssignedToUser($id),
            'errors'            => [],
            'breadcrumbs'       => [['Admin', '/admin'], ['Users', '/admin/users'], [$user['display_name']]],
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
            'active'        => $data['active'],
        ]);

        // Sync roles (sets primary role automatically)
        $roleService->syncUserRoles((int) $userId, $roleIds, Auth::userId());

        $this->logActivity('create', 'user', (int) $userId, "Created user: {$data['email']}");
        Auth::flash('success', "User account created for {$data['email']}.");
        $this->redirect("/admin/users/{$userId}");
    }

    /**
     * GET /admin/users/{id}/edit — Redirects to profile page.
     */
    public function userEdit(int $id): void
    {
        $this->redirect("/admin/users/{$id}");
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

        // Prevent deactivating self
        if ($id === Auth::userId() && !$data['active']) {
            $errors['active'] = 'You cannot deactivate your own account.';
        }

        if ($errors) {
            $data['id'] = $id;
            $member = null;
            try { $member = $this->db->fetch('SELECT * FROM members WHERE user_id = ?', [$id]); } catch (\PDOException $e) {}
            $activity = $this->db->fetchAll('SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 25', [$id]);
            $this->renderAdmin('admin/users/edit', [
                'title'             => 'Edit User — ' . $user['display_name'],
                'user'              => $data,
                'member'            => $member,
                'activity'          => $activity,
                'userRoles'         => $roleService->getUserRoles($id),
                'rolesNotAssigned'  => $roleService->getRolesNotAssignedToUser($id),
                'userGroups'        => $roleService->getUserGroups($id),
                'groupsNotAssigned' => $roleService->getGroupsNotAssignedToUser($id),
                'errors'            => $errors,
                'breadcrumbs'       => [['Admin', '/admin'], ['Users', '/admin/users'], [$user['display_name']]],
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

    /** POST /admin/users/{id}/roles/add — AJAX */
    public function userAddRole(int $id): void
    {
        header('Content-Type: application/json');
        $roleId = (int) $this->input('role_id', 0);
        if (!$roleId) { echo json_encode(['ok' => false, 'error' => 'Invalid role.']); return; }
        $roleService = new RoleService();
        $roleService->addUserToRole($roleId, $id, Auth::userId());
        echo json_encode(['ok' => true, 'roles' => $roleService->getUserRoles($id)]);
    }

    /** POST /admin/users/{id}/roles/remove — AJAX */
    public function userRemoveRole(int $id): void
    {
        header('Content-Type: application/json');
        $roleId = (int) $this->input('role_id', 0);
        if (!$roleId) { echo json_encode(['ok' => false, 'error' => 'Invalid role.']); return; }
        $roleService = new RoleService();
        if ($id === Auth::userId()) {
            $adminRole = $roleService->findBySlug('admin');
            if ($adminRole && (int) $adminRole['id'] === $roleId) {
                echo json_encode(['ok' => false, 'error' => 'Cannot remove admin from your own account.']);
                return;
            }
        }
        $roleService->removeUserFromRole($roleId, $id);
        echo json_encode(['ok' => true, 'roles' => $roleService->getUserRoles($id)]);
    }

    /** POST /admin/users/{id}/groups/add — AJAX */
    public function userAddGroup(int $id): void
    {
        header('Content-Type: application/json');
        $groupId = (int) $this->input('group_id', 0);
        if (!$groupId) { echo json_encode(['ok' => false, 'error' => 'Invalid group.']); return; }
        $roleService = new RoleService();
        $roleService->assignGroup($id, $groupId, Auth::userId());
        echo json_encode(['ok' => true, 'groups' => $roleService->getUserGroups($id)]);
    }

    /** POST /admin/users/{id}/groups/remove — AJAX */
    public function userRemoveGroup(int $id): void
    {
        header('Content-Type: application/json');
        $groupId = (int) $this->input('group_id', 0);
        if (!$groupId) { echo json_encode(['ok' => false, 'error' => 'Invalid group.']); return; }
        $roleService = new RoleService();
        $roleService->removeGroup($id, $groupId);
        echo json_encode(['ok' => true, 'groups' => $roleService->getUserGroups($id)]);
    }

    /**
     * GET /admin/users/search?q= — AJAX user typeahead.
     */
    public function userSearch(): void
    {
        Auth::requireRole('admin');
        $q = trim((string) $this->query('q', ''));
        if (strlen($q) < 2) {
            $this->json([]);
        }
        $like = '%' . $q . '%';
        $rows = $this->db->fetchAll(
            'SELECT id, display_name, email FROM users
             WHERE display_name LIKE ? OR email LIKE ?
             ORDER BY display_name ASC LIMIT 10',
            [$like, $like]
        );
        $this->json(array_values(array_map(fn($r) => [
            'id'           => (int) $r['id'],
            'display_name' => $r['display_name'],
            'email'        => $r['email'],
        ], $rows)));
    }

    /** POST /admin/users/{id}/link-member */
    public function linkMember(int $id): void
    {
        Auth::requireRole('admin');

        $q = trim((string) $this->input('member_search', ''));
        if ($q === '') {
            Auth::flash('error', 'Enter a membership number or email.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        try {
            $member = $this->db->fetch(
                'SELECT * FROM members WHERE membership_number = ? OR email = ? LIMIT 1',
                [$q, strtolower($q)]
            );
        } catch (\PDOException $e) {
            Auth::flash('error', 'Membership module is not installed.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        if (!$member) {
            Auth::flash('error', 'No member found with that membership number or email.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        if (!empty($member['user_id']) && (int)$member['user_id'] !== $id) {
            Auth::flash('error', 'That member is already linked to a different user account.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        $this->db->update('members', ['user_id' => $id], 'id = ?', [$member['id']]);
        $this->logActivity('update', 'member', $member['id'], "Linked to user #{$id}.");
        Auth::flash('success', 'Member record linked to this user.');
        $this->redirect("/admin/users/{$id}");
    }

    /** POST /admin/users/{id}/unlink-member */
    public function unlinkMember(int $id): void
    {
        Auth::requireRole('admin');

        try {
            $member = $this->db->fetch('SELECT id FROM members WHERE user_id = ?', [$id]);
        } catch (\PDOException $e) {
            Auth::flash('error', 'Membership module is not installed.');
            $this->redirect("/admin/users/{$id}");
            return;
        }

        if ($member) {
            $this->db->update('members', ['user_id' => null], 'id = ?', [$member['id']]);
            $this->logActivity('update', 'member', $member['id'], "Unlinked from user #{$id}.");
        }

        Auth::flash('success', 'Member link removed.');
        $this->redirect("/admin/users/{$id}");
    }
}
