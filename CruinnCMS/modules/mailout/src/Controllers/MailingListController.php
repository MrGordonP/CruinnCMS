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

        $this->renderAdmin('admin/lists/index', [
            'title'       => 'Mailing Lists',
            'lists'       => $lists,
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

        $this->db->insert('mailing_lists', [
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $desc !== '' ? $desc : null,
            'subscription_mode' => in_array($mode, ['open', 'request'], true) ? $mode : 'open',
            'is_public'         => $pub,
            'is_active'         => 1,
        ]);

        Auth::flash('success', "Mailing list \"{$name}\" created.");
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
}
