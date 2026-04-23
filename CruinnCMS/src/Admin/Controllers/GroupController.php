<?php
/**
 * CruinnCMS — Group Controller
 *
 * Admin CRUD for user groups.
 * All routes require 'admin' role + 'roles.manage' permission.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Services\RoleService;

class GroupController extends \Cruinn\Controllers\BaseController
{
    private RoleService $roles;

    public function __construct()
    {
        parent::__construct();
        $this->roles = new RoleService();
    }

    /**
     * GET /admin/groups — List all groups.
     */
    public function groupIndex(): void
    {
        Auth::requirePermission('roles.manage');
        $groupId = (int) $this->query('group', 0);
        $group   = $groupId ? $this->roles->findGroup($groupId) : null;
        $this->renderAdmin('admin/groups/index', [
            'title'           => 'Groups',
            'allGroups'       => $this->roles->allGroups(),
            'group'           => $group ?: null,
            'allRoles'        => $this->roles->all(),
            'members'         => $group ? $this->roles->getGroupMembersWithPositions($groupId) : [],
            'usersNotInGroup' => $group ? $this->roles->getUsersNotInGroup($groupId) : [],
            'positions'       => $group ? $this->roles->getGroupPositions($groupId) : [],
            'errors'          => [],
            'breadcrumbs'     => [['Admin', '/admin'], ['Groups']],
        ]);
    }

    /**
     * GET /admin/groups/new — New group form.
     */
    public function groupCreate(): void
    {
        Auth::requirePermission('roles.manage');
        $this->renderAdmin('admin/groups/edit', [
            'title'       => 'New Group',
            'group'       => null,
            'allRoles'    => $this->roles->all(),
            'members'     => [],
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Groups', '/admin/groups'], ['New Group']],
        ]);
    }

    /**
     * POST /admin/groups — Store new group.
     */
    public function groupStore(): void
    {
        Auth::requirePermission('roles.manage');

        $data = [
            'slug'        => $this->sanitiseSlug($this->input('slug', '')),
            'name'        => $this->input('name', ''),
            'description' => $this->input('description', ''),
            'group_type'  => $this->input('group_type', 'custom'),
            'role_id'     => $this->input('role_id', '') ?: null,
        ];

        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Group name is required.';
        if (empty($data['slug'])) $errors['slug'] = 'Slug is required.';

        if (empty($errors['slug'])) {
            $existing = $this->db->fetchColumn('SELECT COUNT(*) FROM `groups` WHERE slug = ?', [$data['slug']]);
            if ($existing) $errors['slug'] = 'A group with this slug already exists.';
        }

        if ($errors) {
            $this->renderAdmin('admin/groups/edit', [
                'title'       => 'New Group',
                'group'       => $data,
                'allRoles'    => $this->roles->all(),
                'members'     => [],
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Groups', '/admin/groups'], ['New Group']],
            ]);
            return;
        }

        $id = $this->roles->createGroup($data);
        Auth::flash('success', "Group \"{$data['name']}\" created.");
        $this->redirect("/admin/groups?group={$id}");
    }

    /**
     * GET /admin/groups/{id}/edit — Edit group form.
     */
    public function groupEdit(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $this->redirect("/admin/groups?group={$id}");
    }

    /**
     * POST /admin/groups/{id} — Update group.
     */
    public function groupUpdate(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $group = $this->roles->findGroup($id);
        if (!$group) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = [
            'name'        => $this->input('name', ''),
            'description' => $this->input('description', ''),
            'group_type'  => $this->input('group_type', 'custom'),
            'role_id'     => $this->input('role_id', '') ?: null,
        ];

        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Group name is required.';

        if ($errors) {
            $this->renderAdmin('admin/groups/index', [
                'title'           => 'Groups',
                'allGroups'       => $this->roles->allGroups(),
                'group'           => array_merge($group, $data),
                'allRoles'        => $this->roles->all(),
                'members'         => $this->roles->getGroupMembersWithPositions($id),
                'usersNotInGroup' => $this->roles->getUsersNotInGroup($id),
                'positions'       => $this->roles->getGroupPositions($id),
                'errors'          => $errors,
                'breadcrumbs'     => [['Admin', '/admin'], ['Groups']],
            ]);
            return;
        }

        $this->roles->updateGroup($id, $data);
        Auth::flash('success', "Group \"{$data['name']}\" updated.");
        $this->redirect("/admin/groups?group={$id}");
    }

    /**
     * POST /admin/groups/{id}/delete — Delete group.
     */
    public function groupDelete(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $group = $this->roles->findGroup($id);
        if (!$group) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->roles->deleteGroup($id);
        Auth::flash('success', "Group \"{$group['name']}\" deleted.");
        $this->redirect('/admin/groups');
    }

    /**
     * GET /admin/groups/{id}/members-json — AJAX: return members with positions as JSON.
     */
    public function groupMembersJson(int $id): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'members' => $this->roles->getGroupMembersWithPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/users/add — AJAX: add a user to a group.
     */
    public function addGroupUser(int $id): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $userId = (int) $this->input('user_id', 0);
        if (!$userId) {
            echo json_encode(['ok' => false, 'error' => 'Invalid user.']);
            return;
        }
        $this->roles->assignGroup($userId, $id, Auth::userId());
        echo json_encode(['ok' => true, 'members' => $this->roles->getGroupMembersWithPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/users/remove — AJAX: remove a user from a group.
     */
    public function removeGroupUser(int $id): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $userId = (int) $this->input('user_id', 0);
        if (!$userId) {
            echo json_encode(['ok' => false, 'error' => 'Invalid user.']);
            return;
        }
        $this->roles->removeGroup($userId, $id);
        echo json_encode(['ok' => true, 'members' => $this->roles->getGroupMembersWithPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/positions/add — AJAX: add a position to a group.
     */
    public function addGroupPosition(int $id): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $name = trim($this->input('name', ''));
        if ($name === '') {
            echo json_encode(['ok' => false, 'error' => 'Position name required.']);
            return;
        }
        $this->roles->createGroupPosition($id, $name);
        echo json_encode(['ok' => true, 'positions' => $this->roles->getGroupPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/positions/{positionId}/delete — AJAX: delete a position.
     */
    public function deleteGroupPosition(int $id, int $positionId): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $this->roles->deleteGroupPosition($positionId);
        echo json_encode(['ok' => true, 'positions' => $this->roles->getGroupPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/users/{userId}/positions/assign — AJAX: assign a position to a user.
     */
    public function assignUserPosition(int $id, int $userId): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $positionId = (int) $this->input('position_id', 0);
        if (!$positionId) {
            echo json_encode(['ok' => false, 'error' => 'Invalid position.']);
            return;
        }
        $this->roles->assignUserPosition($userId, $id, $positionId, Auth::userId());
        echo json_encode(['ok' => true, 'members' => $this->roles->getGroupMembersWithPositions($id)]);
    }

    /**
     * POST /admin/groups/{id}/users/{userId}/positions/{positionId}/remove — AJAX: remove a position from a user.
     */
    public function removeUserPosition(int $id, int $userId, int $positionId): void
    {
        Auth::requirePermission('roles.manage');
        header('Content-Type: application/json');
        $this->roles->removeUserPosition($userId, $positionId);
        echo json_encode(['ok' => true, 'members' => $this->roles->getGroupMembersWithPositions($id)]);
    }
}
