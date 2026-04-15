<?php
/**
 * CruinnCMS — Role Service
 *
 * CRUD operations for the database-driven role & permission system.
 * System roles (admin, council, member, public) cannot be deleted
 * but can have their permissions and dashboard reconfigured.
 */

namespace Cruinn\Services;

use Cruinn\Auth;
use Cruinn\Database;

class RoleService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Role CRUD ─────────────────────────────────────────────────

    /**
     * Get all roles ordered by level descending.
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT r.*,
                    (SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
             FROM roles r
             ORDER BY r.level DESC, r.name ASC'
        );
    }

    /**
     * Get a single role by ID.
     */
    public function find(int $id): array|false
    {
        return $this->db->fetch('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    /**
     * Get a single role by slug.
     */
    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch('SELECT * FROM roles WHERE slug = ?', [$slug]);
    }

    /**
     * Create a new role. Returns the new role ID.
     */
    public function create(array $data): string
    {
        return $this->db->insert('roles', [
            'slug'             => $data['slug'],
            'name'             => $data['name'],
            'description'      => $data['description'] ?? '',
            'level'            => (int) ($data['level'] ?? 0),
            'is_system'        => 0,
            'colour'           => $data['colour'] ?? '#6c757d',
            'default_redirect' => $data['default_redirect'] ?? '/',
        ]);
    }

    /**
     * Update an existing role.
     */
    public function update(int $id, array $data): void
    {
        $fields = [
            'name'             => $data['name'],
            'description'      => $data['description'] ?? '',
            'level'            => (int) ($data['level'] ?? 0),
            'colour'           => $data['colour'] ?? '#6c757d',
            'default_redirect' => $data['default_redirect'] ?? '/',
        ];

        // Only allow slug updates on non-system roles
        $role = $this->find($id);
        if ($role && !$role['is_system']) {
            $fields['slug'] = $data['slug'];
        }

        $this->db->update('roles', $fields, 'id = ?', [$id]);
    }

    /**
     * Delete a role. System roles cannot be deleted.
     * Users with this role will have role_id set to NULL (FK ON DELETE SET NULL).
     *
     * @return bool True if deleted, false if system role.
     */
    public function delete(int $id): bool
    {
        $role = $this->find($id);
        if (!$role || $role['is_system']) {
            return false;
        }

        // Reassign users on this role to the 'public' role before deleting
        $publicRole = $this->findBySlug('public');
        if ($publicRole) {
            $this->db->update(
                'users',
                ['role_id' => $publicRole['id'], 'role' => 'public'],
                'role_id = ?',
                [$id]
            );
        }

        $this->db->delete('roles', 'id = ?', [$id]);
        return true;
    }

    /**
     * Clone an existing role (with permissions). Returns the new role ID.
     */
    public function clone(int $sourceId, string $newSlug, string $newName): string
    {
        $source = $this->find($sourceId);
        if (!$source) {
            throw new \RuntimeException("Source role not found: {$sourceId}");
        }

        $newId = $this->create([
            'slug'             => $newSlug,
            'name'             => $newName,
            'description'      => "Cloned from {$source['name']}",
            'level'            => $source['level'],
            'colour'           => $source['colour'],
            'default_redirect' => $source['default_redirect'],
        ]);

        // Copy permissions
        $this->db->execute(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT ?, permission_id FROM role_permissions WHERE role_id = ?',
            [$newId, $sourceId]
        );

        return $newId;
    }

    // ── Permission Management ─────────────────────────────────────

    /**
     * Get all permissions, grouped by category.
     *
     * @return array<string, array> ['Content' => [perm1, perm2, ...], ...]
     */
    public function allPermissions(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM permissions ORDER BY category, name'
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    /**
     * Get all permissions as a flat list.
     */
    public function allPermissionsFlat(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM permissions ORDER BY category, name'
        );
    }

    /**
     * Get permission IDs assigned to a role.
     *
     * @return int[]
     */
    public function rolePermissionIds(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT permission_id FROM role_permissions WHERE role_id = ?',
            [$roleId]
        );
        return array_column($rows, 'permission_id');
    }

    /**
     * Sync permissions for a role: replace all current permissions
     * with the provided set of permission IDs.
     *
     * @param int   $roleId
     * @param int[] $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->transaction(function () use ($roleId, $permissionIds) {
            // Remove existing
            $this->db->delete('role_permissions', 'role_id = ?', [$roleId]);

            // Insert new set
            foreach ($permissionIds as $pid) {
                $this->db->insert('role_permissions', [
                    'role_id'       => $roleId,
                    'permission_id' => (int) $pid,
                ]);
            }
        });

        // Clear permission cache — users with this role will reload on next request
        Auth::clearPermissionCache();
    }

    // ── Validation Helpers ────────────────────────────────────────

    /**
     * Check if a slug is unique (optionally excluding a role ID).
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            return !(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM roles WHERE slug = ? AND id != ?',
                [$slug, $excludeId]
            );
        }
        return !(bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM roles WHERE slug = ?',
            [$slug]
        );
    }

    /**
     * Validate that a role level is reasonable for the current user.
     * Users cannot create roles with a level >= their own role's level.
     */
    public function validateLevel(int $level): bool
    {
        return $level >= 0 && $level <= 100;
    }

    // ── User Role Assignment ──────────────────────────────────────

    /**
     * Get all roles assigned to a user.
     */
    public function getUserRoles(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, ur.assigned_at
             FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?
             ORDER BY r.level DESC, r.name ASC',
            [$userId]
        );
    }

    /**
     * Get role IDs assigned to a user.
     * @return int[]
     */
    public function getUserRoleIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT role_id FROM user_roles WHERE user_id = ?',
            [$userId]
        );
        return array_map('intval', array_column($rows, 'role_id'));
    }

    /**
     * Sync a user's role assignments. Replaces all current roles
     * and updates the primary role (highest level) on the users table.
     */
    public function syncUserRoles(int $userId, array $roleIds, ?int $assignedBy = null): void
    {
        $roleIds = array_map('intval', array_filter($roleIds));

        $this->db->transaction(function () use ($userId, $roleIds, $assignedBy) {
            $this->db->delete('user_roles', 'user_id = ?', [$userId]);
            foreach ($roleIds as $roleId) {
                $this->db->insert('user_roles', [
                    'user_id'     => $userId,
                    'role_id'     => $roleId,
                    'assigned_by' => $assignedBy,
                ]);
            }
        });

        // Update the primary role on the users table (highest level)
        $this->setPrimaryRole($userId);
        Auth::clearPermissionCache();
    }

    /**
     * Set the users.role and users.role_id to the highest-level assigned role.
     */
    public function setPrimaryRole(int $userId): void
    {
        $primaryRole = $this->db->fetch(
            'SELECT r.id, r.slug
             FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?
             ORDER BY r.level DESC
             LIMIT 1',
            [$userId]
        );

        if ($primaryRole) {
            $this->db->update('users', [
                'role'    => $primaryRole['slug'],
                'role_id' => $primaryRole['id'],
            ], 'id = ?', [$userId]);
        }
    }

    // ── Group Management ──────────────────────────────────────────

    /**
     * Get all groups with member counts.
     */
    public function allGroups(): array
    {
        return $this->db->fetchAll(
            'SELECT g.*, COUNT(ug.id) AS member_count
             FROM `groups` g
             LEFT JOIN user_groups ug ON ug.group_id = g.id
             GROUP BY g.id
             ORDER BY g.name ASC'
        );
    }

    /**
     * Find a group by ID.
     */
    public function findGroup(int $id): array|false
    {
        return $this->db->fetch('SELECT * FROM `groups` WHERE id = ?', [$id]);
    }

    /**
     * Create a new group.
     */
    public function createGroup(array $data): string
    {
        return $this->db->insert('groups', [
            'slug'        => $data['slug'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'group_type'  => $data['group_type'] ?? 'custom',
            'role_id'     => $data['role_id'] ?: null,
        ]);
    }

    /**
     * Update a group.
     */
    public function updateGroup(int $id, array $data): void
    {
        $this->db->update('groups', [
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'group_type'  => $data['group_type'] ?? 'custom',
            'role_id'     => $data['role_id'] ?: null,
        ], 'id = ?', [$id]);
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(int $id): void
    {
        $this->db->delete('groups', 'id = ?', [$id]);
    }

    /**
     * Get all groups a user belongs to.
     */
    public function getUserGroups(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT g.*, ug.assigned_at
             FROM `groups` g
             JOIN user_groups ug ON ug.group_id = g.id
             WHERE ug.user_id = ?
             ORDER BY g.name ASC',
            [$userId]
        );
    }

    /**
     * Get all members of a group.
     */
    public function getGroupMembers(int $groupId): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.display_name, u.email, u.role, ug.assigned_at
             FROM users u
             JOIN user_groups ug ON ug.user_id = u.id
             WHERE ug.group_id = ?
             ORDER BY u.display_name ASC',
            [$groupId]
        );
    }

    /**
     * Assign a user to a group.
     */
    public function assignGroup(int $userId, int $groupId, ?int $assignedBy = null): void
    {
        $exists = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM user_groups WHERE user_id = ? AND group_id = ?',
            [$userId, $groupId]
        );
        if (!$exists) {
            $this->db->insert('user_groups', [
                'user_id'     => $userId,
                'group_id'    => $groupId,
                'assigned_by' => $assignedBy,
            ]);
            Auth::clearPermissionCache();
        }
    }

    /**
     * Remove a user from a group.
     */
    public function removeGroup(int $userId, int $groupId): void
    {
        $this->db->delete('user_groups', 'user_id = ? AND group_id = ?', [$userId, $groupId]);
        Auth::clearPermissionCache();
    }

    /**
     * Sync a user's group memberships. Replaces all current groups.
     */
    public function syncUserGroups(int $userId, array $groupIds, ?int $assignedBy = null): void
    {
        $this->db->transaction(function () use ($userId, $groupIds, $assignedBy) {
            $this->db->delete('user_groups', 'user_id = ?', [$userId]);
            foreach ($groupIds as $groupId) {
                $this->db->insert('user_groups', [
                    'user_id'     => (int) $userId,
                    'group_id'    => (int) $groupId,
                    'assigned_by' => $assignedBy,
                ]);
            }
        });
        Auth::clearPermissionCache();
    }

    /**
     * Get the effective permissions for a user.
     * Union of: all directly assigned roles + all group roles.
     * @return string[] Permission slugs.
     */
    public function getUserEffectivePermissions(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.slug
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?

             UNION

             SELECT DISTINCT p.slug
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN `groups` g ON g.role_id = rp.role_id
             JOIN user_groups ug ON ug.group_id = g.id
             WHERE ug.user_id = ?',
            [$userId, $userId]
        );
        return array_column($rows, 'slug');
    }
}
