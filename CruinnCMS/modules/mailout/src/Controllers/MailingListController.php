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
     * POST /admin/mailout/lists/{id}/subscribers/add — Add a subscriber to the list.
     */
    public function addSubscriber(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $list = $this->db->fetch('SELECT id, name FROM mailing_lists WHERE id = ?', [$id]);
        if (!$list) {
            $this->json(['error' => 'List not found'], 404);
        }

        $email = strtolower(trim($this->input('email', '')));
        $name  = trim($this->input('name', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Invalid email address'], 400);
        }

        // Check if already subscribed
        $exists = $this->db->fetch(
            'SELECT id FROM mailing_list_subscriptions WHERE list_id = ? AND email = ?',
            [$id, $email]
        );

        if ($exists) {
            $this->json(['error' => 'Email is already subscribed to this list'], 400);
        }

        // Add subscriber
        $this->db->insert('mailing_list_subscriptions', [
            'list_id'           => $id,
            'email'             => $email,
            'name'              => $name !== '' ? $name : null,
            'status'            => 'active',
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->json([
            'success' => true,
            'message' => "Added {$email} to {$list['name']}"
        ]);
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
