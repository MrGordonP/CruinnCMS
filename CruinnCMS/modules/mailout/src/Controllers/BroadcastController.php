<?php
/**
 * CruinnCMS - Mailout Controller
 *
 * Admin UI for composing and sending email campaigns to mailing lists.
 * Mailouts are queued in email_queue and processed by tools/process-email-queue.php.
 */

namespace Cruinn\Module\Mailout\Controllers;

use Cruinn\Auth;
use Cruinn\App;
use Cruinn\Mailer;
use Cruinn\Controllers\BaseController;

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
            'title'       => 'Mailout',
            'broadcasts'  => $broadcasts,
            'breadcrumbs' => [['Mailout']],
        ]);
    }

    public function articleImport(): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $this->query('article_id', 0);
        if (!$articleId) {
            $this->json(['error' => 'No article_id provided'], 400);
        }

        $article = $this->db->fetch('SELECT id, title FROM articles WHERE id = ?', [$articleId]);
        if (!$article) {
            $this->json(['error' => 'Article not found'], 404);
        }

        $blocks = $this->db->fetchAll(
            'SELECT inner_html FROM article_blocks WHERE article_id = ? ORDER BY sort_order ASC',
            [$articleId]
        );

        $html = implode("\n", array_column($blocks, 'inner_html'));

        $this->json(['title' => $article['title'], 'html' => $html]);
    }

    public function newForm(): void
    {
        $lists = $this->db->fetchAll(
            'SELECT ml.*, COUNT(mls.id) AS subscriber_count
             FROM mailing_lists ml
             LEFT JOIN mailing_list_subscriptions mls
                    ON mls.list_id = ml.id AND mls.status = \'active\'
             WHERE ml.is_active = 1
             GROUP BY ml.id
             ORDER BY ml.name'
        );

        $memberStatusCounts = [];
        try {
            foreach (['active', 'lapsed', 'honorary', 'deceased', 'removed'] as $s) {
                $memberStatusCounts[$s] = (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM members WHERE status = ? AND email IS NOT NULL AND email != ''",
                    [$s]
                );
            }
        } catch (\Throwable $e) {
            // members table not available (membership module not installed)
        }
        $portalUserCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE active = 1 AND email IS NOT NULL AND email != ''"
        );
        $yearOptions = range((int) date('Y'), (int) date('Y') - 9);

        $articles = $this->db->fetchAll(
            "SELECT id, title FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 100"
        );

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'New Mailout',
            'broadcast'            => null,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'articles'             => $articles,
            'breadcrumbs' => [
                ['Mailout', '/admin/mailout'],
                ['New'],
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
        Auth::flash('success', 'Mailout saved as draft.');
        $this->redirect('/admin/mailout/' . $id . '/edit');
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
                ['Mailout', '/admin/mailout'],
                [$broadcast['subject']],
            ],
        ]);
    }

    public function editForm(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'Only draft mailouts can be edited.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $lists = $this->db->fetchAll(
            'SELECT ml.*, COUNT(mls.id) AS subscriber_count
             FROM mailing_lists ml
             LEFT JOIN mailing_list_subscriptions mls
                    ON mls.list_id = ml.id AND mls.status = \'active\'
             WHERE ml.is_active = 1
             GROUP BY ml.id
             ORDER BY ml.name'
        );

        $memberStatusCounts = [];
        try {
            foreach (['active', 'lapsed', 'honorary', 'deceased', 'removed'] as $s) {
                $memberStatusCounts[$s] = (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM members WHERE status = ? AND email IS NOT NULL AND email != ''",
                    [$s]
                );
            }
        } catch (\Throwable $e) {
            // members table not available (membership module not installed)
        }
        $portalUserCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE active = 1 AND email IS NOT NULL AND email != ''"
        );
        $yearOptions = range((int) date('Y'), (int) date('Y') - 9);

        $articles = $this->db->fetchAll(
            "SELECT id, title FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 100"
        );

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'Edit Mailout',
            'broadcast'            => $broadcast,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'articles'             => $articles,
            'breadcrumbs' => [
                ['Mailout', '/admin/mailout'],
                ['Edit'],
            ],
        ]);
    }

    public function update(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'Only draft mailouts can be edited.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $data = $this->collectFormData();
        $this->db->update('email_broadcasts', array_merge($data, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]), 'id = ?', [$id]);

        Auth::flash('success', 'Mailout updated.');
        $this->redirect('/admin/mailout/' . $id . '/edit');
    }

    public function queue(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] !== 'draft') {
            Auth::flash('error', 'This mailout has already been queued or sent.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $targetType = $broadcast['target_type'] ?? 'members';

        $recipients = $this->resolveRecipients($broadcast);

        if ($targetType === 'list' && empty($broadcast['list_id'])) {
            Auth::flash('error', 'A mailing list must be assigned before queuing.');
            $this->redirect('/admin/mailout/' . $id . '/edit');
        }

        if (empty($recipients)) {
            Auth::flash('warning', 'No eligible recipients found for this mailout.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $this->db->transaction(function () use ($id, $recipients) {
            foreach ($recipients as $sub) {
                $this->db->insert('email_queue', [
                    'broadcast_id'      => $id,
                    'recipient_email'   => $sub['email'],
                    'recipient_name'    => trim($sub['display_name'] ?? ''),
                    'unsubscribe_token' => $sub['unsubscribe_token'] ?? null,
                    'status'            => 'pending',
                    'next_retry_at'     => $this->input('scheduled_at') ?: null,
                ]);
            }

            $this->db->update('email_broadcasts', [
                'status'          => 'queued',
                'recipient_count' => count($recipients),
                'scheduled_at'    => $this->input('scheduled_at') ?: null,
                'updated_at'      => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
        });

        $count = count($recipients);
        $scheduleNote = $this->input('scheduled_at') ? ' scheduled for ' . $this->input('scheduled_at') : '';
        $this->logActivity('queue', 'email_broadcast', $id,
            "Queued broadcast \"{$broadcast['subject']}\" to {$count} recipients{$scheduleNote}");
        $scheduleMsg = $this->input('scheduled_at') ? ' Scheduled for ' . $this->input('scheduled_at') . '.' : ' Run the email queue processor to send.';
        Auth::flash('success', "Mailout queued for {$count} recipients.{$scheduleMsg}");
        $this->redirect('/admin/mailout/' . $id);
    }

    public function sendNow(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if (!in_array($broadcast['status'], ['draft', 'queued'], true)) {
            Auth::flash('error', 'Only draft or queued mailouts can be sent.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $recipients = $this->resolveRecipients($broadcast);

        if (empty($recipients)) {
            Auth::flash('warning', 'No eligible recipients found for this mailout.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $siteUrl = \Cruinn\App::config('site.url', '');
        $appName = \Cruinn\App::config('site.name', 'CruinnCMS');
        $sent    = 0;
        $failed  = 0;

        $this->db->update('email_broadcasts', [
            'status'          => 'sending',
            'recipient_count' => count($recipients),
            'started_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);\n
        // Discard any pending queue rows (in case this was previously queued)
        $this->db->execute(
            "UPDATE email_queue SET status = 'skipped' WHERE broadcast_id = ? AND status = 'pending'",
            [$id]
        );

        foreach ($recipients as $recipient) {
            $name  = trim($recipient['display_name'] ?? '');
            $email = $recipient['email'];

            $html = str_replace(['{{name}}', '{{email}}'],
                [htmlspecialchars($name, ENT_QUOTES | ENT_HTML5), htmlspecialchars($email, ENT_QUOTES | ENT_HTML5)],
                $broadcast['body_html']);
            $text = str_replace(['{{name}}', '{{email}}'], [$name, $email], $broadcast['body_text']);

            if (!empty($recipient['unsubscribe_token'])) {
                $unsubUrl  = rtrim($siteUrl, '/') . '/mailing-lists/unsubscribe/' . $recipient['unsubscribe_token'];
                $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">'
                       . '<p style="font-size:12px;color:#6b7280;">You are receiving this email as a subscriber of '
                       . htmlspecialchars($appName, ENT_QUOTES | ENT_HTML5) . '. '
                       . '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES | ENT_HTML5) . '" style="color:#6b7280;">Unsubscribe</a>.</p>';
                $text .= "\n---\nUnsubscribe: {$unsubUrl}\n";
            }

            try {
                \Cruinn\Mailer::send($email, $broadcast['subject'], $html, $text);
                $sent++;
                $this->db->execute(
                    'UPDATE email_broadcasts SET sent_count = sent_count + 1 WHERE id = ?', [$id]
                );
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $this->db->update('email_broadcasts', [
            'status'       => $failed === count($recipients) ? 'failed' : 'sent',
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('send', 'email_broadcast', $id,
            "Sent broadcast \"{$broadcast['subject']}\" — {$sent} sent, {$failed} failed");

        $msg = "Sent to {$sent} recipient" . ($sent !== 1 ? 's' : '');
        if ($failed > 0) $msg .= ", {$failed} failed";
        Auth::flash($failed > 0 ? 'warning' : 'success', $msg);
        $this->redirect('/admin/mailout/' . $id);
    }

    public function cancel(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if (!in_array($broadcast['status'], ['queued', 'sending'], true)) {
            Auth::flash('error', 'Only queued or in-progress mailouts can be cancelled.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $this->db->execute(
            "UPDATE email_queue SET status = 'skipped' WHERE broadcast_id = ? AND status = 'pending'",
            [$id]
        );

        $this->db->update('email_broadcasts', [
            'status'     => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Auth::flash('success', 'Mailout cancelled. It has been moved back to draft.');
        $this->redirect('/admin/mailout/' . $id);
    }

    public function delete(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if ($broadcast['status'] === 'sending') {
            Auth::flash('error', 'A mailout that is currently sending cannot be deleted.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $this->db->execute('DELETE FROM email_broadcasts WHERE id = ?', [$id]);
        $this->logActivity('delete', 'email_broadcast', $id, "Deleted: {$broadcast['subject']}");

        Auth::flash('success', 'Mailout deleted.');
        $this->redirect('/admin/mailout');
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
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mailout');
        }

        if ($targetType === 'list') {
            $listId = (int) $this->input('list_id') ?: null;
            if (!$listId) {
                Auth::flash('error', 'Please select a mailing list.');
                $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mailout');
            }
        } elseif ($targetType === 'members') {
            $validStatuses = ['active', 'lapsed', 'honorary', 'deceased', 'removed'];
            $statuses = array_values(array_intersect(
                (array) ($_POST['member_status'] ?? []),
                $validStatuses
            ));
            if (empty($statuses)) {
                Auth::flash('error', 'Please select at least one member status.');
                $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mailout');
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

    private function resolveRecipients(array $broadcast): array
    {
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

            return $this->db->fetchAll(
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
        }

        if ($targetType === 'portal_users') {
            return $this->db->fetchAll(
                "SELECT u.email, u.display_name, NULL AS unsubscribe_token
                 FROM users u
                 WHERE u.active = 1
                   AND u.email IS NOT NULL AND u.email != ''
                   AND NOT EXISTS (
                       SELECT 1 FROM email_unsubscribes eu WHERE eu.email = u.email
                   )
                 ORDER BY u.display_name"
            );
        }

        // list
        if (empty($broadcast['list_id'])) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT u.email, u.display_name, mls.unsubscribe_token
             FROM mailing_list_subscriptions mls
             JOIN users u ON u.id = mls.user_id
             WHERE mls.list_id = ? AND mls.status = \'active\' AND u.active = 1',
            [$broadcast['list_id']]
        );
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
