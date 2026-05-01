<?php
/**
 * CruinnCMS — Mailing Lists Controller
 *
 * Admin UI for managing mailing lists and their subscribers.
 */

namespace Cruinn\Module\Mailout\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;

class MailingListController extends BaseController
{
    /**
     * GET /admin/mailout/lists — 3-panel list manager.
     */
    public function index(): void
    {
        $lists = $this->db->fetchAll(
            'SELECT ml.*,
                    COUNT(CASE WHEN mls.status = "active" THEN 1 END) AS subscriber_count
             FROM mailing_lists ml
             LEFT JOIN mailing_list_subscriptions mls ON mls.list_id = ml.id
             GROUP BY ml.id
             ORDER BY ml.name'
        );

        $groups = $this->db->fetchAll('SELECT id, name FROM groups ORDER BY name');

        $this->renderAdmin('admin/lists/index', [
            'title'       => 'Mailing Lists',
            'lists'       => $lists,
            'groups'      => $groups,
            'breadcrumbs' => [['Mailout', '/admin/mailout'], ['Mailing Lists']],
        ]);
    }

    /**
     * GET /admin/mailout/lists/{id}/subscribers — JSON subscriber list.
     */
    public function subscribers(int $id): void
    {
        $list = $this->db->fetch('SELECT id, name FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            $this->json(['error' => 'List not found'], 404);
        }

        $q      = trim($this->query('q', ''));
        $status = $this->query('status', '');

        $where  = ['mls.list_id = ?'];
        $params = [$id];

        if ($status !== '') {
            $where[]  = 'mls.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[]  = '(mls.email LIKE ? OR mls.name LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $sql = 'SELECT mls.id, mls.email, mls.name, mls.status, mls.subscribed_at
                FROM mailing_list_subscriptions mls
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY mls.subscribed_at DESC
                LIMIT 500';

        $subscribers = $this->db->fetchAll($sql, $params);
        $this->json(['subscribers' => $subscribers]);
    }

    /**
     * POST /admin/mailout/lists — Create a new mailing list.
     */
    public function create(): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $name = trim($this->input('name', ''));
        $slug = trim($this->input('slug', ''));
        $desc = trim($this->input('description', ''));
        $mode = $this->input('subscription_mode', 'open');
        $pub  = (int)(bool)$this->input('is_public', 1);
        $isDynamic = (int)(bool)$this->input('is_dynamic', 0);
        $sourceTable = $isDynamic ? $this->input('source_table', '') : null;

        if ($name === '' || $slug === '') {
            Auth::flash('error', 'Name and slug are required.');
            $this->redirect('/admin/mailout/lists');
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            Auth::flash('error', 'Slug may only contain lowercase letters, numbers and hyphens.');
            $this->redirect('/admin/mailout/lists');
        }

        $existing = $this->db->fetch('SELECT id FROM mailing_lists WHERE slug = ?', [$slug]);
        if ($existing) {
            Auth::flash('error', 'A list with that slug already exists.');
            $this->redirect('/admin/mailout/lists');
        }

        // Build source criteria JSON for dynamic lists
        $sourceCriteria = null;
        if ($isDynamic && $sourceTable) {
            $criteria = [];

            if ($sourceTable === 'members') {
                if ($status = $this->input('criteria_status')) {
                    $criteria['status'] = $status;
                }
                if ($year = $this->input('criteria_year')) {
                    $criteria['year'] = (int)$year;
                }
            } elseif ($sourceTable === 'users') {
                if ($active = $this->input('criteria_active')) {
                    $criteria['active'] = (int)$active;
                }
            } elseif ($sourceTable === 'groups') {
                if ($groupId = $this->input('criteria_group_id')) {
                    $criteria['group_id'] = (int)$groupId;
                }
            }

            $sourceCriteria = !empty($criteria) ? json_encode($criteria) : null;
        }

        $newId = $this->db->insert('mailing_lists', [
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $desc !== '' ? $desc : null,
            'subscription_mode' => $isDynamic ? 'open' : (in_array($mode, ['open', 'request'], true) ? $mode : 'open'),
            'is_public'         => $pub,
            'is_active'         => 1,
            'is_dynamic'        => $isDynamic,
            'source_table'      => $sourceTable,
            'source_criteria'   => $sourceCriteria,
        ]);

        // Auto-sync if dynamic
        if ($isDynamic && $newId) {
            $this->syncMailingList((int)$newId);
        }

        Auth::flash('success', "Mailing list \"{$name}\" created" . ($isDynamic ? ' and synced.' : '.'));
        $this->redirect('/admin/mailout/lists');
    }

    /**
     * POST /admin/mailout/lists/{id} — Update a mailing list's settings.
     */
    public function update(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $list = $this->db->fetch('SELECT id FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            Auth::flash('error', 'List not found.');
            $this->redirect('/admin/mailout/lists');
        }

        $name = trim($this->input('name', ''));
        $desc = trim($this->input('description', ''));
        $mode = $this->input('subscription_mode', 'open');
        $pub  = (int)(bool)$this->input('is_public', 1);
        $active = (int)(bool)$this->input('is_active', 1);

        if ($name === '') {
            Auth::flash('error', 'Name is required.');
            $this->redirect('/admin/mailout/lists');
        }

        $this->db->execute(
            'UPDATE mailing_lists SET name = ?, description = ?, subscription_mode = ?, is_public = ?, is_active = ? WHERE id = ?',
            [$name, $desc !== '' ? $desc : null, in_array($mode, ['open', 'request'], true) ? $mode : 'open', $pub, $active, $id]
        );

        Auth::flash('success', 'List updated.');
        $this->redirect('/admin/mailout/lists');
    }

    /**
     * POST /admin/mailout/lists/{id}/delete — Delete a mailing list.
     */
    public function delete(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $list = $this->db->fetch('SELECT name FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            Auth::flash('error', 'List not found.');
            $this->redirect('/admin/mailout/lists');
        }

        $this->db->execute('DELETE FROM mailing_lists WHERE id = ?', [$id]);
        Auth::flash('success', "List \"{$list['name']}\" deleted.");
        $this->redirect('/admin/mailout/lists');
    }

    /**
     * GET /admin/mailout/lists/{id}/members — Three-panel member browsing and bulk addition.
     */
    public function listMembers(int $id): void
    {
        $list = $this->db->fetch('SELECT * FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            Auth::flash('danger', 'Mailing list not found.');
            $this->redirect('/admin/mailout/lists');
        }

        // Current list members (for the right panel and email exclusion)
        $members = $this->db->fetchAll(
            "SELECT mls.id AS sub_id, mls.email, mls.name, mls.status, mls.subscribed_at, u.id AS user_id, u.display_name
             FROM mailing_list_subscriptions mls
             LEFT JOIN users u ON u.id = mls.user_id
             WHERE mls.list_id = ? AND mls.status != 'pending'
             ORDER BY mls.email",
            [$id]
        );

        $pendingMembers = $this->db->fetchAll(
            "SELECT mls.id AS sub_id, mls.email, mls.name, mls.subscribed_at, u.id AS user_id, u.display_name
             FROM mailing_list_subscriptions mls
             LEFT JOIN users u ON u.id = mls.user_id
             WHERE mls.list_id = ? AND mls.status = 'pending'
             ORDER BY mls.subscribed_at",
            [$id]
        );

        $memberEmails = array_column($members, 'email');
        $pendingEmails = array_column($pendingMembers, 'email');
        $excludeEmails = array_merge($memberEmails, $pendingEmails);

        // Source selection and filters
        $source        = $this->query('source', 'users');
        $filterStatus  = $this->query('status', '');
        $filterYear    = $this->query('year', '');
        $filterActive  = $this->query('active', '');
        $filterGroupId = (int) $this->query('group_id', 0);

        $availableUsers = [];

        // Fetch available users based on source
        if ($source === 'manual') {
            // Manual entry - no available users list
            $availableUsers = [];
        } elseif ($source === 'groups' && $filterGroupId > 0) {
            // Get users from selected group
            $availableUsers = $this->db->fetchAll(
                "SELECT DISTINCT u.id, u.display_name, u.email, u.active,
                        m.status AS member_status, m.membership_year
                 FROM users u
                 INNER JOIN group_members gm ON gm.user_id = u.id
                 LEFT JOIN members m ON m.user_id = u.id
                 WHERE gm.group_id = ?
                 ORDER BY u.display_name",
                [$filterGroupId]
            );
        } elseif ($source === 'members') {
            // Members only - ALL members from members table regardless of user account status
            $where  = ['1=1'];
            $params = [];

            if ($filterStatus !== '') {
                $where[]  = 'm.status = ?';
                $params[] = $filterStatus;
            }
            if ($filterYear !== '') {
                $where[]  = 'm.membership_year = ?';
                $params[] = (int)$filterYear;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $availableUsers = $this->db->fetchAll(
                "SELECT CONCAT('m_', m.id) AS id,
                        COALESCE(u.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS display_name,
                        m.email, u.active,
                        m.status AS member_status, m.membership_year,
                        m.id AS member_id, u.id AS user_id
                 FROM members m
                 LEFT JOIN users u ON u.id = m.user_id
                 {$whereClause}
                 ORDER BY display_name",
                $params
            );
        } else {
            // Users (all users, with optional member filters)
            $where  = ['u.id IS NOT NULL'];
            $params = [];

            if ($filterActive !== '') {
                $where[]  = 'u.active = ?';
                $params[] = (int)$filterActive;
            }
            if ($filterStatus !== '') {
                $where[]  = 'm.status = ?';
                $params[] = $filterStatus;
            }
            if ($filterYear !== '') {
                $where[]  = 'm.membership_year = ?';
                $params[] = (int)$filterYear;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $availableUsers = $this->db->fetchAll(
                "SELECT u.id, u.display_name, u.email, u.active,
                        m.status AS member_status, m.membership_year
                 FROM users u
                 LEFT JOIN members m ON m.user_id = u.id
                 {$whereClause}
                 ORDER BY u.display_name",
                $params
            );
        }

        // Exclude already-subscribed
        $availableUsers = array_values(array_filter($availableUsers, fn($u) => !in_array($u['email'], $excludeEmails)));

        // Fetch groups for group source selector
        $groups = $this->db->fetchAll('SELECT id, name FROM groups ORDER BY name');

        // Distinct years for filter dropdown
        $years = $this->db->fetchAll(
            'SELECT DISTINCT membership_year FROM members WHERE membership_year IS NOT NULL ORDER BY membership_year DESC'
        );

        $this->renderAdmin('admin/lists/members', [
            'title'          => 'Contacts: ' . $list['name'],
            'breadcrumbs'    => [
                ['Mailout', '/admin/mailout'],
                ['Mailing Lists', '/admin/mailout/lists'],
                [$list['name']],
            ],
            'list'           => $list,
            'members'        => $members,
            'pendingMembers' => $pendingMembers,
            'availableUsers' => $availableUsers,
            'groups'         => $groups,
            'years'          => array_column($years, 'membership_year'),
            'source'         => $source,
            'filterStatus'   => $filterStatus,
            'filterYear'     => $filterYear,
            'filterActive'   => $filterActive,
            'filterGroupId'  => $filterGroupId,
        ]);
    }

    /**
     * POST /admin/mailout/lists/{id}/subscribers/add — Add subscriber(s) to the list (bulk or single).
     */
    public function addSubscriber(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $list = $this->db->fetch('SELECT id, name FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            // Check if AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['error' => 'List not found'], 404);
            }
            Auth::flash('danger', 'Mailing list not found.');
            $this->redirect('/admin/mailout/lists');
        }

        $selectedIds = array_filter((array)($_POST['user_ids'] ?? []));
        $email   = strtolower(trim($this->input('email', '')));
        $name    = trim($this->input('name', ''));
        $added   = 0;

        // Bulk add selected users/members (from members page checkboxes)
        foreach ($selectedIds as $selectedId) {
            // Check if it's a member ID (prefixed with 'm_') or user ID
            if (str_starts_with($selectedId, 'm_')) {
                // Member-only record
                $memberId = (int)substr($selectedId, 2);
                $member = $this->db->fetch(
                    'SELECT m.email, CONCAT(m.first_name, " ", m.last_name) AS name, u.id AS user_id
                     FROM members m
                     LEFT JOIN users u ON u.id = m.user_id
                     WHERE m.id = ?',
                    [$memberId]
                );
                if (!$member) continue;

                $exists = $this->db->fetch(
                    'SELECT id FROM mailing_list_subscriptions WHERE list_id = ? AND email = ?',
                    [$id, $member['email']]
                );
                if ($exists) continue;

                $this->db->insert('mailing_list_subscriptions', [
                    'list_id'           => $id,
                    'user_id'           => $member['user_id'],
                    'email'             => $member['email'],
                    'name'              => $member['name'],
                    'unsubscribe_token' => bin2hex(random_bytes(32)),
                    'status'            => 'active',
                    'subscribed_at'     => date('Y-m-d H:i:s'),
                ]);
                $added++;
            } else {
                // User record
                $userId = (int)$selectedId;
                $user = $this->db->fetch('SELECT id, display_name, email FROM users WHERE id = ?', [$userId]);
                if (!$user) continue;

                $exists = $this->db->fetch(
                    'SELECT id FROM mailing_list_subscriptions WHERE list_id = ? AND email = ?',
                    [$id, $user['email']]
                );
                if ($exists) continue;

                $this->db->insert('mailing_list_subscriptions', [
                    'list_id'           => $id,
                    'user_id'           => $userId,
                    'email'             => $user['email'],
                    'name'              => $user['display_name'],
                    'unsubscribe_token' => bin2hex(random_bytes(32)),
                    'status'            => 'active',
                    'subscribed_at'     => date('Y-m-d H:i:s'),
                ]);
                $added++;
            }
        }

        // Single email add (from AJAX form or manual entry)
        if ($email && empty($userIds)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Check if AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $this->json(['error' => 'Invalid email address'], 400);
                }
                Auth::flash('danger', 'Invalid email address.');
                $this->redirect('/admin/mailout/lists/' . $id . '/members');
            }

            $exists = $this->db->fetch(
                'SELECT id FROM mailing_list_subscriptions WHERE list_id = ? AND email = ?',
                [$id, $email]
            );

            if ($exists) {
                // Check if AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $this->json(['error' => 'Email is already subscribed to this list'], 400);
                }
                Auth::flash('warning', $email . ' is already on this list.');
                $this->redirect('/admin/mailout/lists/' . $id . '/members');
            }

            $this->db->insert('mailing_list_subscriptions', [
                'list_id'           => $id,
                'user_id'           => null,
                'email'             => $email,
                'name'              => $name !== '' ? $name : null,
                'unsubscribe_token' => bin2hex(random_bytes(32)),
                'status'            => 'active',
                'subscribed_at'     => date('Y-m-d H:i:s'),
            ]);
            $added++;
        }

        // Return based on request type
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX request - return JSON
            $this->json([
                'success' => true,
                'message' => "Added {$email} to {$list['name']}"
            ]);
        } else {
            // Form submission from members page - redirect
            if ($added === 0 && empty($selectedIds) && !$email) {
                Auth::flash('warning', 'No users selected.');
            } else {
                Auth::flash('success', $added . ' contact(s) added to ' . $list['name'] . '.');
            }
            $this->redirect('/admin/mailout/lists/' . $id . '/members');
        }
    }

    /**
     * POST /admin/mailout/lists/{id}/subscribers/{subId}/remove
     */
    public function removeSubscriber(int $id, int $subId): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $this->db->execute(
            'DELETE FROM mailing_list_subscriptions WHERE id = ? AND list_id = ?',
            [$subId, $id]
        );
        $this->json(['success' => true]);
    }

    /**
     * POST /admin/mailout/lists/{id}/sync — Manually sync a dynamic mailing list.
     */
    public function syncList(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $affected = $this->syncMailingList($id);

        if ($affected === false) {
            $this->json(['error' => 'List not found or not configured as dynamic'], 400);
        } else {
            $this->json([
                'success' => true,
                'message' => "List synced successfully. {$affected} member(s) added.",
                'added'   => $affected,
            ]);
        }
    }

    /**
     * Sync a dynamic mailing list from its source criteria.
     * Returns number of members added, or false if not a dynamic list.
     */
    private function syncMailingList(int $listId): int|false
    {
        $list = $this->db->fetch('SELECT * FROM mailing_lists WHERE id = ?', [$listId]);

        if (!$list || !$list['is_dynamic'] || !$list['source_table']) {
            return false;
        }

        $criteria = $list['source_criteria'] ? json_decode($list['source_criteria'], true) : [];
        $sourceTable = $list['source_table'];

        $users = [];

        // Build query based on source table
        if ($sourceTable === 'members') {
            $where = ['m.id IS NOT NULL'];
            $params = [];

            if (!empty($criteria['status'])) {
                $where[] = 'm.status = ?';
                $params[] = $criteria['status'];
            }
            if (!empty($criteria['year'])) {
                $where[] = 'm.membership_year = ?';
                $params[] = (int)$criteria['year'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $users = $this->db->fetchAll(
                "SELECT m.user_id AS id, m.email, CONCAT(m.forenames, ' ', m.surnames) AS display_name
                 FROM members m
                 {$whereClause}
                 ORDER BY m.email",
                $params
            );
        } elseif ($sourceTable === 'users') {
            $where = ['u.id IS NOT NULL'];
            $params = [];

            if (isset($criteria['active'])) {
                $where[] = 'u.active = ?';
                $params[] = (int)$criteria['active'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $users = $this->db->fetchAll(
                "SELECT u.id, u.email, u.display_name
                 FROM users u
                 {$whereClause}
                 ORDER BY u.email",
                $params
            );
        } elseif ($sourceTable === 'groups') {
            if (!empty($criteria['group_id'])) {
                $users = $this->db->fetchAll(
                    "SELECT DISTINCT u.id, u.email, u.display_name
                     FROM users u
                     INNER JOIN group_members gm ON gm.user_id = u.id
                     WHERE gm.group_id = ?
                     ORDER BY u.email",
                    [(int)$criteria['group_id']]
                );
            }
        }

        $added = 0;

        foreach ($users as $user) {
            // Check if already subscribed
            $exists = $this->db->fetch(
                'SELECT id FROM mailing_list_subscriptions WHERE list_id = ? AND email = ?',
                [$listId, $user['email']]
            );

            if (!$exists) {
                $this->db->insert('mailing_list_subscriptions', [
                    'list_id'           => $listId,
                    'user_id'           => $user['id'] ? (int)$user['id'] : null,
                    'email'             => $user['email'],
                    'name'              => $user['display_name'],
                    'unsubscribe_token' => bin2hex(random_bytes(32)),
                    'status'            => 'active',
                    'subscribed_at'     => date('Y-m-d H:i:s'),
                ]);
                $added++;
            }
        }

        // Update last_synced_at
        $this->db->update('mailing_lists', [
            'last_synced_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$listId]);

        return $added;
    }
}
