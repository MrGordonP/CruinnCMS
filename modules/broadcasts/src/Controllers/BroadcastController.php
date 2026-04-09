<?php
/**
 * IGA Portal — Email Broadcast Controller
 *
 * Admin UI for composing and sending email broadcasts to mailing lists.
 * Broadcasts are queued in email_queue and processed by tools/process-email-queue.php.
 */

namespace IGA\Module\Broadcasts\Controllers;

use IGA\Auth;
use IGA\App;
use IGA\Mailer;
use IGA\Controllers\BaseController;

class BroadcastController extends BaseController
{
    public function index(): void
    {
        $broadcasts = $this->db->fetchAll(
            'SELECT b.*, ml.name AS list_name,
                    u.display_name AS created_by_name
             FROM email_broadcasts b
             LEFT JOIN mailing_lists ml ON ml.id = b.list_id
             LEFT JOIN users u          ON u.id  = b.created_by
             ORDER BY b.created_at DESC
             LIMIT 100'
        );

        $this->renderAdmin('admin/broadcasts/index', [
            'title'       => 'Email Broadcasts',
            'broadcasts'  => $broadcasts,
            'breadcrumbs' => [['label' => 'Email Broadcasts']],
        ]);
    }

    public function newForm(): void
    {
        $lists = $this->db->fetchAll(
            'SELECT ml.*, COUNT(mls.id) AS subscriber_count
             FROM mailing_lists ml
             LEFT JOIN mailing_list_subscriptions mls
                    ON mls.list_id = ml.id AND mls.subscribed = 1
             WHERE ml.is_active = 1
             GROUP BY ml.id
             ORDER BY ml.name'
        );

        $memberStatusCounts = [];
        foreach (['active', 'lapsed', 'honorary', 'deceased', 'removed'] as $s) {
            $memberStatusCounts[$s] = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM members WHERE status = ? AND email IS NOT NULL AND email != ''",
                [$s]
            );
        }
        $portalUserCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE active = 1 AND email IS NOT NULL AND email != ''"
        );
        $yearOptions = range((int) date('Y'), (int) date('Y') - 9);

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'New Broadcast',
            'broadcast'            => null,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'breadcrumbs' => [
                ['label' => 'Email Broadcasts', 'url' => '/admin/broadcasts'],
                ['label' => 'New'],
            ],
        ]);
    }

    public function create(): void
    {
        $data = $this->collectFormData();

        $id = $this->db->insert('email_broadcasts', array_merge($data, [
            'status'     => 'draft',
            'created_by' => Auth::userId(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        $this->logActivity('create', 'email_broadcast', (int) $id, "Draft: {$data['subject']}");
        Auth::flash('success', 'Broadcast saved as draft.');
        $this->redirect('/admin/broadcasts/' . $id . '/edit');
    }

    public function show(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        $queueStats = $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'sent')    AS sent,
                SUM(status = 'failed')  AS failed,
                SUM(status = 'pending') AS pending,
                SUM(status = 'skipped') AS skipped
             FROM email_queue WHERE broadcast_id = ?",
            [$id]
        );

        $this->renderAdmin('admin/broadcasts/show', [
            'title'      => $broadcast['subject'],
            'broadcast'  => $broadcast,
            'stats'      => $queueStats,
            'breadcrumbs' => [
                ['label' => 'Email Broadcasts', 'url' => '/admin/broadcasts'],
                ['label' => $broadcast['subject']],
            ],
        ]);
    }

    public function editForm(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'Only draft broadcasts can be edited.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $lists = $this->db->fetchAll(
            'SELECT ml.*, COUNT(mls.id) AS subscriber_count
             FROM mailing_lists ml
             LEFT JOIN mailing_list_subscriptions mls
                    ON mls.list_id = ml.id AND mls.subscribed = 1
             WHERE ml.is_active = 1
             GROUP BY ml.id
             ORDER BY ml.name'
        );

        $memberStatusCounts = [];
        foreach (['active', 'lapsed', 'honorary', 'deceased', 'removed'] as $s) {
            $memberStatusCounts[$s] = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM members WHERE status = ? AND email IS NOT NULL AND email != ''",
                [$s]
            );
        }
        $portalUserCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE active = 1 AND email IS NOT NULL AND email != ''"
        );
        $yearOptions = range((int) date('Y'), (int) date('Y') - 9);

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'Edit Broadcast',
            'broadcast'            => $broadcast,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'breadcrumbs' => [
                ['label' => 'Email Broadcasts', 'url' => '/admin/broadcasts'],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function update(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'Only draft broadcasts can be edited.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $data = $this->collectFormData();
        $this->db->update('email_broadcasts', array_merge($data, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]), 'id = ?', [$id]);

        Auth::flash('success', 'Broadcast updated.');
        $this->redirect('/admin/broadcasts/' . $id . '/edit');
    }

    public function queue(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'This broadcast has already been queued or sent.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $targetType = $broadcast['target_type'] ?? 'members';

        if ($targetType === 'members') {
            $config   = json_decode($broadcast['target_config'] ?? '{}', true) ?: [];
            $statuses = $config['member_status'] ?? ['active', 'honorary'];
            $year     = !empty($config['membership_year']) ? (int) $config['membership_year'] : null;

            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $params       = $statuses;
            $yearClause   = '';
            if ($year !== null) {
                $yearClause = ' AND m.membership_year = ?';
                $params[]   = $year;
            }

            $recipients = $this->db->fetchAll(
                "SELECT m.email,
                        TRIM(CONCAT(COALESCE(m.forenames,''), ' ', COALESCE(m.surnames,''))) AS display_name,
                        NULL AS unsubscribe_token
                 FROM members m
                 WHERE m.status IN ({$placeholders})
                   AND m.email IS NOT NULL AND m.email != ''
                   {$yearClause}
                   AND NOT EXISTS (
                       SELECT 1 FROM email_unsubscribes eu WHERE eu.email = m.email
                   )
                 ORDER BY m.surnames, m.forenames",
                $params
            );
        } elseif ($targetType === 'portal_users') {
            $recipients = $this->db->fetchAll(
                "SELECT u.email, u.display_name, NULL AS unsubscribe_token
                 FROM users u
                 WHERE u.active = 1
                   AND u.email IS NOT NULL AND u.email != ''
                   AND NOT EXISTS (
                       SELECT 1 FROM email_unsubscribes eu WHERE eu.email = u.email
                   )
                 ORDER BY u.display_name"
            );
        } else {
            // list — mailing list subscribers
            if (empty($broadcast['list_id'])) {
                Auth::flash('error', 'A mailing list must be assigned before queuing.');
                $this->redirect('/admin/broadcasts/' . $id . '/edit');
            }

            $recipients = $this->db->fetchAll(
                'SELECT u.email, u.display_name, mls.unsubscribe_token
                 FROM mailing_list_subscriptions mls
                 JOIN users u ON u.id = mls.user_id
                 WHERE mls.list_id = ? AND mls.subscribed = 1 AND u.active = 1',
                [$broadcast['list_id']]
            );
        }

        if (empty($recipients)) {
            Auth::flash('warning', 'No eligible recipients found for this broadcast.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $this->db->transaction(function () use ($id, $recipients) {
            foreach ($recipients as $sub) {
                $this->db->insert('email_queue', [
                    'broadcast_id'      => $id,
                    'recipient_email'   => $sub['email'],
                    'recipient_name'    => trim($sub['display_name'] ?? ''),
                    'unsubscribe_token' => $sub['unsubscribe_token'] ?? null,
                    'status'            => 'pending',
                ]);
            }

            $this->db->update('email_broadcasts', [
                'status'          => 'queued',
                'recipient_count' => count($recipients),
                'updated_at'      => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
        });

        $count = count($recipients);
        $this->logActivity('queue', 'email_broadcast', $id,
            "Queued broadcast \"{$broadcast['subject']}\" to {$count} recipients");
        Auth::flash('success', "Broadcast queued for {$count} recipients. Run the email queue processor to send.");
        $this->redirect('/admin/broadcasts/' . $id);
    }

    public function cancel(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if (!in_array($broadcast['status'], ['queued', 'sending'], true)) {
            Auth::flash('error', 'Only queued or in-progress broadcasts can be cancelled.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $this->db->execute(
            "UPDATE email_queue SET status = 'skipped' WHERE broadcast_id = ? AND status = 'pending'",
            [$id]
        );

        $this->db->update('email_broadcasts', [
            'status'     => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Auth::flash('success', 'Broadcast cancelled. It has been moved back to draft.');
        $this->redirect('/admin/broadcasts/' . $id);
    }

    public function delete(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] === 'sending') {
            Auth::flash('error', 'A broadcast that is currently sending cannot be deleted.');
            $this->redirect('/admin/broadcasts/' . $id);
        }

        $this->db->execute('DELETE FROM email_broadcasts WHERE id = ?', [$id]);
        $this->logActivity('delete', 'email_broadcast', $id, "Deleted: {$broadcast['subject']}");

        Auth::flash('success', 'Broadcast deleted.');
        $this->redirect('/admin/broadcasts');
    }

    // ── Private helpers ───────────────────────────────────────────

    private function collectFormData(): array
    {
        $targetType   = $this->input('target_type', 'members');
        $listId       = null;
        $targetConfig = null;

        $subject  = trim($this->input('subject', ''));
        $bodyHtml = $this->input('body_html', '');
        $bodyText = trim($this->input('body_text', ''));

        if ($subject === '') {
            Auth::flash('error', 'Subject is required.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/broadcasts');
        }

        if ($targetType === 'list') {
            $listId = (int) $this->input('list_id') ?: null;
            if (!$listId) {
                Auth::flash('error', 'Please select a mailing list.');
                $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/broadcasts');
            }
        } elseif ($targetType === 'members') {
            $validStatuses = ['active', 'lapsed', 'honorary', 'deceased', 'removed'];
            $statuses = array_values(array_intersect(
                (array) ($_POST['member_status'] ?? []),
                $validStatuses
            ));
            if (empty($statuses)) {
                Auth::flash('error', 'Please select at least one member status.');
                $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/broadcasts');
            }
            $config = ['member_status' => $statuses];
            $year = $this->input('membership_year') ? (int) $this->input('membership_year') : null;
            if ($year !== null && $year >= 2000 && $year <= (int) date('Y') + 1) {
                $config['membership_year'] = $year;
            }
            $targetConfig = json_encode($config);
        }

        return [
            'target_type'   => $targetType,
            'list_id'       => $listId,
            'target_config' => $targetConfig,
            'subject'       => $subject,
            'body_html'     => $bodyHtml,
            'body_text'     => $bodyText,
        ];
    }

    private function findOrFail(int $id): array
    {
        $broadcast = $this->db->fetch(
            'SELECT b.*, ml.name AS list_name
             FROM email_broadcasts b
             LEFT JOIN mailing_lists ml ON ml.id = b.list_id
             WHERE b.id = ?',
            [$id]
        );

        if (!$broadcast) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Not Found']);
            exit;
        }

        return $broadcast;
    }
}
