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
        $this->renderAdmin('admin/groups/index', [
            'title'       => 'Groups',
            'groups'      => $this->roles->allGroups(),
            'breadcrumbs' => [['Admin', '/admin'], ['Groups']],
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
            'nonMembers'  => [],
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
                'nonMembers'  => [],
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Groups', '/admin/groups'], ['New Group']],
            ]);
            return;
        }

        $id = $this->roles->createGroup($data);
        Auth::flash('success', "Group \"{$data['name']}\" created.");
        $this->redirect("/admin/groups/{$id}/edit");
    }

    /**
     * GET /admin/groups/{id}/edit — Edit group form.
     */
    public function groupEdit(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $group = $this->roles->findGroup($id);
        if (!$group) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $members = $this->roles->getGroupMembers($id);
        $memberIds = array_column($members, 'id');
        $allUsers = $this->db->fetchAll(
            'SELECT id, display_name, email FROM users WHERE active = 1 ORDER BY display_name ASC'
        );
        $nonMembers = array_filter($allUsers, fn($u) => !in_array((int)$u['id'], $memberIds, true));
        $this->renderAdmin('admin/groups/edit', [
            'title'       => 'Edit Group — ' . $group['name'],
            'group'       => $group,
            'allRoles'    => $this->roles->all(),
            'members'     => $members,
            'nonMembers'  => array_values($nonMembers),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Groups', '/admin/groups'], [$group['name']]],
        ]);
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
            $members = $this->roles->getGroupMembers($id);
            $memberIds = array_column($members, 'id');
            $allUsers = $this->db->fetchAll(
                'SELECT id, display_name, email FROM users WHERE active = 1 ORDER BY display_name ASC'
            );
            $nonMembers = array_filter($allUsers, fn($u) => !in_array((int)$u['id'], $memberIds, true));
            $this->renderAdmin('admin/groups/edit', [
                'title'       => 'Edit Group — ' . $group['name'],
                'group'       => array_merge($group, $data),
                'allRoles'    => $this->roles->all(),
                'members'     => $members,
                'nonMembers'  => array_values($nonMembers),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Groups', '/admin/groups'], [$group['name']]],
            ]);
            return;
        }

        $this->roles->updateGroup($id, $data);
        Auth::flash('success', "Group \"{$data['name']}\" updated.");
        $this->redirect("/admin/groups/{$id}/edit");
    }

    /**
     * POST /admin/groups/{id}/members/add — Add a user to a group.
     */
    public function groupMemberAdd(int $id): void
    {
        Auth::requirePermission('roles.manage');
        $group = $this->roles->findGroup($id);
        if (!$group) { http_response_code(404); $this->render('errors/404'); return; }

        $userId = (int) $this->input('user_id', 0);
        if ($userId > 0) {
            $this->roles->assignGroup($userId, $id, Auth::userId());
            Auth::flash('success', 'Member added.');
        }
        $this->redirect("/admin/groups/{$id}/edit");
    }

    /**
     * POST /admin/groups/{id}/members/{uid}/remove — Remove a user from a group.
     */
    public function groupMemberRemove(int $id, int $uid): void
    {
        Auth::requirePermission('roles.manage');
        $group = $this->roles->findGroup($id);
        if (!$group) { http_response_code(404); $this->render('errors/404'); return; }

        $this->roles->removeGroup($uid, $id);
        Auth::flash('success', 'Member removed.');
        $this->redirect("/admin/groups/{$id}/edit");
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
}
