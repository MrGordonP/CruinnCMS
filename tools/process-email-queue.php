#!/usr/bin/env php
<?php
/**
 * CruinnCMS — Email Queue Processor
 *
 * Drains the email_queue table, sending batches of pending broadcast emails.
 * Designed to run every 2–5 minutes via cron:
 *
 *   * /2 * * * *  php /var/www/html/tools/process-email-queue.php >> /var/log/cruinn-email-queue.log 2>&1
 *
 * Safety features:
 *   --dry-run   Print what would be sent without actually sending.
 *   --batch N   Override the batch size (default: 50 per run).
 *   --limit N   Maximum total emails to send before exiting.
 *
 * The script uses a simple file-based lock so concurrent cron runs do not
 * process the same rows.
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
require $rootDir . '/vendor/autoload.php';

use Cruinn\App;
use Cruinn\Database;
use Cruinn\Mailer;

// ── CLI argument parsing ──────────────────────────────────────────────────────

$dryRun    = in_array('--dry-run', $argv, true);
$batchSize = 50;
$maxSend   = PHP_INT_MAX;

foreach ($argv as $i => $arg) {
    if ($arg === '--batch' && isset($argv[$i + 1])) {
        $batchSize = max(1, (int)$argv[$i + 1]);
    }
    if ($arg === '--limit' && isset($argv[$i + 1])) {
        $maxSend = max(1, (int)$argv[$i + 1]);
    }
}

// ── Lock file ─────────────────────────────────────────────────────────────────

$lockFile = sys_get_temp_dir() . '/cruinn-email-queue.lock';
$lock = fopen($lockFile, 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('[Y-m-d H:i:s]') . " Another instance is already running. Exiting.\n";
    exit(0);
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

App::boot();
$db = Database::getInstance();

$siteUrl = rtrim(App::config('site.url', 'http://localhost'), '/');
$appName = App::config('site.name', 'CruinnCMS');
$sent    = 0;
$failed  = 0;

echo date('[Y-m-d H:i:s]') . " Starting queue processor (batch={$batchSize}" . ($dryRun ? ', DRY-RUN' : '') . ")\n";

// ── Main loop ─────────────────────────────────────────────────────────────────

do {
    $rows = $db->fetchAll(
        'SELECT q.*, b.subject, b.body_html, b.body_text, b.id AS broadcast_id
         FROM email_queue q
         JOIN email_broadcasts b ON b.id = q.broadcast_id
         WHERE q.status = \'pending\'
           AND b.status IN (\'queued\', \'sending\')
           AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
         ORDER BY q.id ASC
         LIMIT ?',
        [$batchSize]
    );

    if (empty($rows)) {
        break;
    }

    // Mark broadcast as "sending" on first batch
    $broadcastIds = array_unique(array_column($rows, 'broadcast_id'));
    foreach ($broadcastIds as $bid) {
        $db->execute(
            'UPDATE email_broadcasts SET status = \'sending\', started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status = \'queued\'',
            [$bid]
        );
    }

    foreach ($rows as $row) {
        if ($sent + $failed >= $maxSend) {
            break 2;
        }

        $recipientEmail = $row['recipient_email'];
        $recipientName  = $row['recipient_name'] ?? '';

        // Personalise the email body (simple token replacement)
        $html = str_replace(
            ['{{name}}', '{{email}}'],
            [htmlspecialchars($recipientName, ENT_QUOTES | ENT_HTML5), htmlspecialchars($recipientEmail, ENT_QUOTES | ENT_HTML5)],
            $row['body_html']
        );
        $text = str_replace(
            ['{{name}}', '{{email}}'],
            [$recipientName, $recipientEmail],
            $row['body_text']
        );

        // Append unsubscribe footer
        if (!empty($row['unsubscribe_token'])) {
            $unsubUrl     = $siteUrl . '/mailing-lists/unsubscribe/' . $row['unsubscribe_token'];
            $safeUnsub    = htmlspecialchars($unsubUrl, ENT_QUOTES | ENT_HTML5);
            $safeAppName  = htmlspecialchars($appName, ENT_QUOTES | ENT_HTML5);
            $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">'
                   . '<p style="font-size:12px;color:#6b7280;">'
                   . 'You are receiving this email as a subscriber of ' . $safeAppName . '. '
                   . '<a href="' . $safeUnsub . '" style="color:#6b7280;">Unsubscribe</a>.'
                   . '</p>';
            $text .= "\n---\nYou are receiving this as a subscriber of {$appName}.\nUnsubscribe: {$unsubUrl}\n";
        }

        if ($dryRun) {
            echo date('[Y-m-d H:i:s]') . " [DRY-RUN] Would send to: {$recipientEmail} — {$row['subject']}\n";
            $sent++;
            continue;
        }

        try {
            Mailer::send($recipientEmail, $row['subject'], $html, $text);

            $db->update('email_queue', [
                'status'       => 'sent',
                'attempts'     => $row['attempts'] + 1,
                'last_error'   => null,
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$row['id']]);

            $db->execute(
                'UPDATE email_broadcasts SET sent_count = sent_count + 1, updated_at = NOW() WHERE id = ?',
                [$row['broadcast_id']]
            );

            $sent++;
            echo date('[Y-m-d H:i:s]') . " Sent → {$recipientEmail}\n";

        } catch (\Throwable $e) {
            $attempts = $row['attempts'] + 1;
            $maxAttempts = 3;

            if ($attempts >= $maxAttempts) {
                $newStatus  = 'failed';
                $nextRetry  = null;
            } else {
                $newStatus  = 'pending';
                $nextRetry  = date('Y-m-d H:i:s', time() + (60 * $attempts * 5)); // exp back-off
            }

            $db->update('email_queue', [
                'status'        => $newStatus,
                'attempts'      => $attempts,
                'last_error'    => $e->getMessage(),
                'next_retry_at' => $nextRetry,
                'processed_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$row['id']]);

            $failed++;
            echo date('[Y-m-d H:i:s]') . " FAILED → {$recipientEmail}: " . $e->getMessage() . "\n";
        }
    }

    // Mark fully-sent broadcasts as complete
    foreach ($broadcastIds as $bid) {
        $remaining = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM email_queue WHERE broadcast_id = ? AND status = \'pending\'',
            [$bid]
        );
        if ($remaining === 0) {
            $db->execute(
                'UPDATE email_broadcasts SET status = \'sent\', completed_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND status = \'sending\'',
                [$bid]
            );
            echo date('[Y-m-d H:i:s]') . " Broadcast #{$bid} complete.\n";
        }
    }

} while (count($rows) === $batchSize);

// ── Release lock ──────────────────────────────────────────────────────────────

flock($lock, LOCK_UN);
fclose($lock);

echo date('[Y-m-d H:i:s]') . " Done. Sent: {$sent}  Failed: {$failed}\n";
exit(($failed > 0 && $sent === 0) ? 1 : 0);
