<?php
/**
 * CruinnCMS - Mailout Controller
 *
 * Admin UI for composing and sending email campaigns to mailing lists.
 * Mailouts can be queued (email_queue table) or sent immediately.
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
        Auth::requireAdmin();
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

    public function broadcastImport(): void
    {
        Auth::requireAdmin();
        $broadcastId = (int) $this->query('broadcast_id', 0);
        if (!$broadcastId) {
            $this->json(['error' => 'No broadcast_id provided'], 400);
        }

        $broadcast = $this->db->fetch(
            'SELECT subject, body_html, body_text FROM email_broadcasts WHERE id = ?',
            [$broadcastId]
        );
        if (!$broadcast) {
            $this->json(['error' => 'Mailout not found'], 404);
        }

        $this->json([
            'subject'   => $broadcast['subject'],
            'body_html' => $broadcast['body_html'],
            'body_text' => $broadcast['body_text'],
        ]);
    }

    public function documentImport(): void
    {
        Auth::requireAdmin();
        $documentId = (int) $this->query('document_id', 0);
        if (!$documentId) {
            $this->json(['error' => 'No document_id provided'], 400);
        }

        $doc = $this->db->fetch(
            'SELECT id, title, description FROM documents WHERE id = ? LIMIT 1',
            [$documentId]
        );
        if (!$doc) {
            $this->json(['error' => 'Document not found'], 404);
        }

        $title = (string) ($doc['title'] ?? 'Document Update');
        $desc = trim((string) ($doc['description'] ?? ''));
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $safeDesc = $desc !== ''
            ? nl2br(htmlspecialchars($desc, ENT_QUOTES | ENT_HTML5))
            : 'Please review the linked document for full details.';
        $link = '/documents/' . (int) ($doc['id'] ?? 0);

        $html = '<h2>' . $safeTitle . '</h2>'
              . '<p>' . $safeDesc . '</p>'
              . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES | ENT_HTML5) . '">View Document</a></p>';
        $text = $title . "\n\n"
              . ($desc !== '' ? $desc : 'Please review the linked document for full details.')
              . "\n\nView Document: " . $link;

        $this->json([
            'subject' => $title,
            'body_html' => $html,
            'body_text' => $text,
        ]);
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

        $previousBroadcasts = $this->db->fetchAll(
            "SELECT id, subject, created_at FROM email_broadcasts ORDER BY created_at DESC LIMIT 50"
        );

        try {
            $documents = $this->db->fetchAll(
                'SELECT id, title FROM documents ORDER BY updated_at DESC LIMIT 100'
            );
        } catch (\Throwable $e) {
            $documents = [];
        }

        try {
            $subjectOptions = $this->db->fetchAll(
                "SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC",
                ['archived']
            );
        } catch (\Throwable $e) {
            $subjectOptions = [];
        }

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'New Mailout',
            'broadcast'            => null,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'articles'             => $articles,
            'previous_broadcasts'  => $previousBroadcasts,
            'documents'            => $documents,
            'subject_options'      => $subjectOptions,
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

        $recipients = $this->db->fetchAll(
            "SELECT recipient_email, recipient_name, status, processed_at AS sent_at, last_error AS error
             FROM email_queue
             WHERE broadcast_id = ?
             ORDER BY status DESC, recipient_email ASC",
            [$id]
        );

        $this->renderAdmin('admin/broadcasts/show', [
            'title'       => $broadcast['subject'],
            'broadcast'   => $broadcast,
            'stats'       => $queueStats,
            'recipients'  => $recipients,
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

        $previousBroadcasts = $this->db->fetchAll(
            "SELECT id, subject, created_at FROM email_broadcasts WHERE id != ? ORDER BY created_at DESC LIMIT 50",
            [$id]
        );

        try {
            $documents = $this->db->fetchAll(
                'SELECT id, title FROM documents ORDER BY updated_at DESC LIMIT 100'
            );
        } catch (\Throwable $e) {
            $documents = [];
        }

        try {
            $subjectOptions = $this->db->fetchAll(
                "SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC",
                ['archived']
            );
        } catch (\Throwable $e) {
            $subjectOptions = [];
        }

        $this->renderAdmin('admin/broadcasts/edit', [
            'title'                => 'Edit Mailout',
            'broadcast'            => $broadcast,
            'lists'                => $lists,
            'member_status_counts' => $memberStatusCounts,
            'portal_user_count'    => $portalUserCount,
            'year_options'         => $yearOptions,
            'articles'             => $articles,
            'previous_broadcasts'  => $previousBroadcasts,
            'documents'            => $documents,
            'subject_options'      => $subjectOptions,
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
        ], 'id = ?', [$id]);

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

            // Convert relative URLs to absolute for email compatibility
            $html = preg_replace_callback(
                '/(src|href)=["\'](\/)([^"\']+)["\']/i',
                function($matches) use ($siteUrl) {
                    $attr = $matches[1];  // 'src' or 'href'
                    $path = $matches[3];  // path without leading slash
                    return $attr . '="' . rtrim($siteUrl, '/') . '/' . $path . '"';
                },
                $html
            );

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

    public function reopen(int $id): void
    {
        $broadcast = $this->findOrFail($id);

        if (!in_array($broadcast['status'], ['sent', 'failed'], true)) {
            Auth::flash('error', 'Only sent or failed mailouts can be reopened.');
            $this->redirect('/admin/mailout/' . $id);
        }

        $this->db->update('email_broadcasts', [
            'status'       => 'draft',
            'scheduled_at' => null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('reopen', 'email_broadcast', $id, "Reopened draft: {$broadcast['subject']}");

        Auth::flash('success', 'Mailout reopened as draft. You can now edit and resend it.');
        $this->redirect('/admin/mailout/' . $id . '/edit');
    }

    public function processQueue(int $limit = 200): array
    {
        $rows = $this->db->fetchAll(
            'SELECT q.*, b.subject, b.body_html, b.body_text, b.id AS broadcast_id
             FROM email_queue q
             JOIN email_broadcasts b ON b.id = q.broadcast_id
             WHERE q.status = \'pending\'
               AND b.status IN (\'queued\', \'sending\')
               AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
             ORDER BY q.id ASC
             LIMIT ?',
            [$limit]
        );

        if (empty($rows)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $siteUrl = \Cruinn\App::config('site.url', '');
        $appName = \Cruinn\App::config('site.name', 'CruinnCMS');
        $sent = 0;
        $failed = 0;

        // Mark broadcasts as "sending" on first batch
        $broadcastIds = array_unique(array_column($rows, 'broadcast_id'));
        foreach ($broadcastIds as $bid) {
            $this->db->execute(
                'UPDATE email_broadcasts SET status = \'sending\', started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status = \'queued\'',
                [$bid]
            );
        }

        foreach ($rows as $row) {
            $recipientEmail = $row['recipient_email'];
            $recipientName  = $row['recipient_name'] ?? '';

            $html = str_replace(['{{name}}', '{{email}}'],
                [htmlspecialchars($recipientName, ENT_QUOTES | ENT_HTML5), htmlspecialchars($recipientEmail, ENT_QUOTES | ENT_HTML5)],
                $row['body_html']);
            $text = str_replace(['{{name}}', '{{email}}'], [$recipientName, $recipientEmail], $row['body_text']);

            // Convert relative URLs to absolute
            $html = preg_replace_callback(
                '/(src|href)=["\'](\/)([^"\']+)["\']/i',
                function($matches) use ($siteUrl) {
                    return $matches[1] . '="' . rtrim($siteUrl, '/') . '/' . $matches[3] . '"';
                },
                $html
            );

            if (!empty($row['unsubscribe_token'])) {
                $unsubUrl = rtrim($siteUrl, '/') . '/mailing-lists/unsubscribe/' . $row['unsubscribe_token'];
                $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">'
                       . '<p style="font-size:12px;color:#6b7280;">You are receiving this email as a subscriber of '
                       . htmlspecialchars($appName, ENT_QUOTES | ENT_HTML5) . '. '
                       . '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES | ENT_HTML5) . '" style="color:#6b7280;">Unsubscribe</a>.</p>';
                $text .= "\n---\nUnsubscribe: {$unsubUrl}\n";
            }

            try {
                \Cruinn\Mailer::send($recipientEmail, $row['subject'], $html, $text);
                $this->db->update('email_queue', [
                    'status'       => 'sent',
                    'attempts'     => $row['attempts'] + 1,
                    'last_error'   => null,
                    'processed_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$row['id']]);
                $this->db->execute(
                    'UPDATE email_broadcasts SET sent_count = sent_count + 1, updated_at = NOW() WHERE id = ?',
                    [$row['broadcast_id']]
                );
                $sent++;
            } catch (\Throwable $e) {
                $attempts = $row['attempts'] + 1;
                $maxAttempts = 3;
                $newStatus = $attempts >= $maxAttempts ? 'failed' : 'pending';
                $nextRetry = $attempts >= $maxAttempts ? null : date('Y-m-d H:i:s', time() + (60 * $attempts * 5));

                $this->db->update('email_queue', [
                    'status'        => $newStatus,
                    'attempts'      => $attempts,
                    'last_error'    => $e->getMessage(),
                    'next_retry_at' => $nextRetry,
                    'processed_at'  => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$row['id']]);
                $failed++;
            }
        }

        // Mark fully-sent broadcasts as complete
        foreach ($broadcastIds as $bid) {
            $remaining = (int)$this->db->fetchColumn(
                'SELECT COUNT(*) FROM email_queue WHERE broadcast_id = ? AND status = \'pending\'',
                [$bid]
            );
            if ($remaining === 0) {
                $this->db->execute(
                    'UPDATE email_broadcasts SET status = \'sent\', completed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = \'sending\'',
                    [$bid]
                );
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function duplicate(int $id): void
    {
        $source = $this->findOrFail($id);

        $newId = $this->db->insert('email_broadcasts', [
            'target_type'   => $source['target_type'],
            'list_id'       => $source['list_id'],
            'target_config' => $source['target_config'],
            'subject'       => $source['subject'] . ' (Copy)',
            'body_html'     => $source['body_html'],
            'body_text'     => $source['body_text'],
            'status'        => 'draft',
            'created_by'    => Auth::userId(),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('duplicate', 'email_broadcast', (int) $newId, "Duplicated from: {$source['subject']}");

        Auth::flash('success', 'Mailout duplicated successfully. You can now edit it.');
        $this->redirect('/admin/mailout/' . $newId . '/edit');
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
            'SELECT mls.email,
                    COALESCE(NULLIF(TRIM(mls.name), \'\'), u.display_name, mls.email) AS display_name,
                    mls.unsubscribe_token
             FROM mailing_list_subscriptions mls
             LEFT JOIN users u ON u.id = mls.user_id
             WHERE mls.list_id = ?
               AND mls.status = \'active\'
               AND mls.email IS NOT NULL
               AND mls.email != \'\'
               AND NOT EXISTS (
                   SELECT 1 FROM email_unsubscribes eu WHERE eu.email = mls.email
               )
             ORDER BY mls.email',
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
