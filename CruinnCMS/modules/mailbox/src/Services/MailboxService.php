<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Services;

use Cruinn\Database;

/**
 * MailboxService — IMAP access, header sync, send, thread resolution.
 *
 * All IMAP interaction goes through this service. Controllers never touch
 * IMAP protocol directly — that is handled by ImapSocket.
 *
 * Encryption: IMAP/SMTP passwords are stored AES-256-CBC encrypted.
 * The key is derived from the instance secret (config 'secret_key').
 * Use encryptPassword() / decryptPassword() for storage/retrieval.
 */
class MailboxService
{
    private Database    $db;
    private string      $encryptionKey;
    private ?ImapSocket $socket        = null;
    private ?int        $socketMailbox = null; // mailbox id currently connected
    private ?string     $lastError     = null;

    public function __construct(Database $db, string $instanceSecret)
    {
        $this->db            = $db;
        // Derive a 32-byte key from the instance secret
        $this->encryptionKey = hash('sha256', $instanceSecret, true);
    }

    // -------------------------------------------------------------------------
    // Mailbox access control
    // -------------------------------------------------------------------------

    /**
     * Return all imap-enabled officer rows the given user has access to.
     * Admin users see all enabled mailboxes.
     */
    public function getAccessibleMailboxes(int $userId, string $role): array
    {
        if ($role === 'admin') {
            return $this->db->fetchAll(
                'SELECT id, label AS position, email,
                        imap_host, imap_port, imap_encryption,
                        imap_user, imap_pass_enc,
                        smtp_host, smtp_port, smtp_encryption,
                        smtp_user, smtp_pass_enc, imap_last_uid, enabled
                   FROM mailboxes
                  WHERE enabled = 1
                  ORDER BY label'
            );
        }

        return $this->db->fetchAll(
            'SELECT DISTINCT mb.id, mb.label AS position, mb.email,
                    mb.imap_host, mb.imap_port, mb.imap_encryption,
                    mb.imap_user, mb.imap_pass_enc,
                    mb.smtp_host, mb.smtp_port, mb.smtp_encryption,
                    mb.smtp_user, mb.smtp_pass_enc, mb.imap_last_uid, mb.enabled
               FROM mailboxes mb
               JOIN mailbox_access ma ON ma.mailbox_id = mb.id
              WHERE mb.enabled = 1
                AND (
                    ma.user_id = ?
                    OR ma.officer_position_id IN (
                        SELECT id FROM organisation_officers WHERE user_id = ?
                    )
                )
              ORDER BY mb.label',
            [$userId, $userId]
        );
    }

    /**
     * Return a single officer/mailbox row, asserting access for the given user.
     * Returns null if not found or access denied.
     */
    public function getMailbox(int $mailboxId, int $userId, string $role): ?array
    {
        if ($role === 'admin') {
            return $this->db->fetch(
                'SELECT id, label AS position, email,
                        imap_host, imap_port, imap_encryption,
                        imap_user, imap_pass_enc,
                        smtp_host, smtp_port, smtp_encryption,
                        smtp_user, smtp_pass_enc, imap_last_uid, enabled
                   FROM mailboxes WHERE id = ? AND enabled = 1',
                [$mailboxId]
            ) ?: null;
        }

        return $this->db->fetch(
            'SELECT DISTINCT mb.id, mb.label AS position, mb.email,
                    mb.imap_host, mb.imap_port, mb.imap_encryption,
                    mb.imap_user, mb.imap_pass_enc,
                    mb.smtp_host, mb.smtp_port, mb.smtp_encryption,
                    mb.smtp_user, mb.smtp_pass_enc, mb.imap_last_uid, mb.enabled
               FROM mailboxes mb
               JOIN mailbox_access ma ON ma.mailbox_id = mb.id
              WHERE mb.id = ? AND mb.enabled = 1
                AND (
                    ma.user_id = ?
                    OR ma.officer_position_id IN (
                        SELECT id FROM organisation_officers WHERE user_id = ?
                    )
                )
              LIMIT 1',
            [$mailboxId, $userId, $userId]
        ) ?: null;
    }

    // -------------------------------------------------------------------------
    // IMAP connection
    // -------------------------------------------------------------------------

    /**
     * Open (or reuse) an ImapSocket for the given mailbox row.
     * Returns the connected ImapSocket or throws on failure.
     *
     * @throws \RuntimeException
     */
    public function connect(array $mailbox): ImapSocket
    {
        if ($this->socket !== null && $this->socketMailbox === (int) $mailbox['id']) {
            return $this->socket;
        }

        $this->closeAll();

        $enc      = strtolower($mailbox['imap_encryption'] ?? 'ssl');
        $port     = (int) ($mailbox['imap_port'] ?? 993);
        $password = $this->decryptPassword((string) $mailbox['imap_pass_enc']);

        $socket = new ImapSocket();
        $socket->connect($mailbox['imap_host'], $port, $enc, $mailbox['imap_user'], $password);

        $this->socket        = $socket;
        $this->socketMailbox = (int) $mailbox['id'];

        return $socket;
    }

    /**
     * Close the open IMAP connection. Call at end of request if needed.
     */
    public function closeAll(): void
    {
        if ($this->socket !== null) {
            $this->socket->disconnect();
            $this->socket        = null;
            $this->socketMailbox = null;
        }
    }

    // -------------------------------------------------------------------------
    // Folder list
    // -------------------------------------------------------------------------

    /**
     * Return a list of folders for a mailbox as plain strings.
     *
     * @return string[]
     */
    public function getFolders(array $mailbox): array
    {
        try {
            $socket = $this->connect($mailbox);
            return $socket->listFolders();
        } catch (\RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // -------------------------------------------------------------------------
    // Message list (with sync)
    // -------------------------------------------------------------------------

    /**
     * Return paginated messages for a folder.
     *
     * Syncs new headers from IMAP first (incremental from last known UID).
     * Returns DB rows (already indexed) enriched with read-state for $userId.
     */
    public function getMessages(
        array $mailbox,
        string $folder,
        int $userId,
        string $role,
        int $page = 1,
        int $perPage = 50
    ): array {
        $this->syncFolder($mailbox, $folder);

        $offset = ($page - 1) * $perPage;

        $messages = $this->db->fetchAll(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM mailbox_reads r
                      WHERE r.mailbox_id = m.mailbox_id
                        AND r.folder = m.folder
                        AND r.imap_uid = m.imap_uid) AS read_count,
                    (SELECT COUNT(*) FROM mailbox_access ma
                      WHERE ma.mailbox_id = m.mailbox_id) AS holder_count,
                    (SELECT 1 FROM mailbox_reads r2
                      WHERE r2.mailbox_id = m.mailbox_id
                        AND r2.folder = m.folder
                        AND r2.imap_uid = m.imap_uid
                        AND r2.user_id = ?) AS read_by_me
               FROM mailbox_messages m
              WHERE m.mailbox_id = ?
                AND m.folder = ?
              ORDER BY m.sent_at DESC
              LIMIT ? OFFSET ?',
            [$userId, $mailbox['id'], $folder, $perPage, $offset]
        );

        // Derive three-state read indicator on each row
        foreach ($messages as &$msg) {
            $msg['read_state'] = $this->deriveReadState(
                (int) $msg['read_count'],
                (int) $msg['holder_count']
            );
        }
        unset($msg);

        return $messages;
    }

    /**
     * Return total message count for a folder (for pagination).
     */
    public function getMessageCount(int $mailboxId, string $folder): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM mailbox_messages WHERE mailbox_id = ? AND folder = ?',
            [$mailboxId, $folder]
        );
    }

    // -------------------------------------------------------------------------
    // Single message fetch
    // -------------------------------------------------------------------------

    /**
     * Fetch the full message body from IMAP for the given UID.
     * Returns an array with keys: headers, text_body, html_body, attachments[].
     *
     * @throws \RuntimeException if message not found
     */
    public function fetchBody(array $mailbox, string $folder, int $uid): array
    {
        $socket    = $this->connect($mailbox);
        $structure = $socket->uidFetchStructure($folder, $uid);
        $headers   = $socket->uidFetchEnvelope($folder, $uid);

        if ($structure === null || $headers === null) {
            throw new \RuntimeException('Message UID ' . $uid . ' not found in ' . $folder);
        }

        $textBody    = '';
        $htmlBody    = '';
        $attachments = [];

        $this->parseStructure($socket, $folder, $uid, $structure, $textBody, $htmlBody, $attachments);

        return [
            'headers'     => $headers,
            'text_body'   => $textBody,
            'html_body'   => $htmlBody,
            'attachments' => $attachments,
        ];
    }

    // -------------------------------------------------------------------------
    // Send
    // -------------------------------------------------------------------------

    /**
     * Send a message via the mailbox's SMTP config.
     * Uses PHPMailer if available, otherwise falls back to PHP mail().
     * $data keys: to, cc (optional), subject, text_body, html_body (optional),
     *             in_reply_to (optional, for threading)
     *
     * @throws \RuntimeException on send failure
     */
    public function send(array $mailbox, array $data): void
    {
        $to      = $data['to']      ?? '';
        $cc      = $data['cc']      ?? '';
        $subject = $data['subject'] ?? '';
        $text    = $data['text_body'] ?? '';
        $html    = $data['html_body'] ?? nl2br(htmlspecialchars($text, ENT_QUOTES));

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet  = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = $mailbox['smtp_host'];
            $mail->Port       = (int) ($mailbox['smtp_port'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailbox['smtp_user'];
            $mail->Password   = $this->decryptPassword((string) $mailbox['smtp_pass_enc']);

            $enc = strtolower($mailbox['smtp_encryption'] ?? 'tls');
            $mail->SMTPSecure = match ($enc) {
                'ssl'   => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
                default => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            };

            $mail->setFrom($mailbox['email'], $mailbox['position'] ?? '');

            foreach ($this->parseAddressList($to) as [$addr, $name]) {
                $mail->addAddress($addr, $name);
            }
            if ($cc !== '') {
                foreach ($this->parseAddressList($cc) as [$addr, $name]) {
                    $mail->addCC($addr, $name);
                }
            }

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = $text;

            if (!empty($data['in_reply_to'])) {
                $mail->addCustomHeader('In-Reply-To', $data['in_reply_to']);
                $mail->addCustomHeader('References',  $data['in_reply_to']);
            }

            $mail->send();
            return;
        }

        // Fallback: PHP mail() via the server MTA
        $fromName  = $mailbox['position'] ?? '';
        $fromEmail = $mailbox['email'];
        $boundary  = md5(uniqid('', true));

        // For the mail() $to argument, use bare email addresses only.
        // PHP's mail() passes $to to the MTA directly; "Name <email>" can
        // trigger notices on some builds. The To: header carries the full form.
        $toParsed   = $this->parseAddressList($to);
        $toEnvelope = implode(', ', array_column($toParsed, 0)); // bare emails

        $headers  = 'To: ' . $to . "\r\n";
        $headers .= 'From: ' . ($fromName ? $fromName . ' <' . $fromEmail . '>' : $fromEmail) . "\r\n";
        $headers .= 'Reply-To: ' . $fromEmail . "\r\n";
        if ($cc !== '') {
            $headers .= 'Cc: ' . $cc . "\r\n";
        }
        if (!empty($data['in_reply_to'])) {
            $headers .= 'In-Reply-To: ' . $data['in_reply_to'] . "\r\n";
            $headers .= 'References: '  . $data['in_reply_to'] . "\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $text . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n\r\n";
        $body .= "--{$boundary}--";

        if (!mail($toEnvelope, $subject, $body, $headers)) {
            throw new \RuntimeException('mail() failed — check server MTA configuration.');
        }
    }

    /**
     * Parse one or more email addresses in RFC 5321 format.
     * Accepts "Name <email>", "<email>", or plain "email".
     * Returns array of [email, name] pairs.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function parseAddressList(string $input): array
    {
        $result = [];
        foreach (explode(',', $input) as $part) {
            $part = trim($part);
            if (preg_match('/^(.+?)\s*<([^>]+)>\s*$/', $part, $m)) {
                $result[] = [trim($m[2]), trim($m[1], " \t\"'")];
            } elseif ($part !== '') {
                $result[] = [$part, ''];
            }
        }
        return $result ?: [[$input, '']];
    }

    // -------------------------------------------------------------------------
    // Move / Delete
    // -------------------------------------------------------------------------

    public function moveMessage(array $mailbox, string $fromFolder, int $uid, string $toFolder): void
    {
        $socket = $this->connect($mailbox);
        $socket->uidCopy($fromFolder, $uid, $toFolder);
        $socket->uidStore($fromFolder, $uid, '\\Deleted', true);
        $socket->expunge($fromFolder);

        $this->db->execute(
            'DELETE FROM mailbox_messages WHERE mailbox_id = ? AND folder = ? AND imap_uid = ?',
            [$mailbox['id'], $fromFolder, $uid]
        );
        $this->pruneReads((int) $mailbox['id'], $fromFolder, $uid);
        $this->pruneTagMap((int) $mailbox['id'], $fromFolder, $uid);
    }

    public function deleteMessage(array $mailbox, string $folder, int $uid): void
    {
        $folders = $this->getFolders($mailbox);
        $trash   = $this->findSpecialFolder($folders, ['Trash', 'INBOX.Trash', 'Deleted Messages', 'Deleted Items']);

        if ($trash && $folder !== $trash) {
            $this->moveMessage($mailbox, $folder, $uid, $trash);
        } else {
            $socket = $this->connect($mailbox);
            $socket->uidStore($folder, $uid, '\\Deleted', true);
            $socket->expunge($folder);

            $this->db->execute(
                'DELETE FROM mailbox_messages WHERE mailbox_id = ? AND folder = ? AND imap_uid = ?',
                [$mailbox['id'], $folder, $uid]
            );
            $this->pruneReads((int) $mailbox['id'], $folder, $uid);
            $this->pruneTagMap((int) $mailbox['id'], $folder, $uid);
        }
    }

    // -------------------------------------------------------------------------
    // Read state
    // -------------------------------------------------------------------------

    public function markRead(int $mailboxId, string $folder, int $uid, int $userId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO mailbox_reads (mailbox_id, folder, imap_uid, user_id) VALUES (?, ?, ?, ?)',
            [$mailboxId, $folder, $uid, $userId]
        );
    }

    public function markUnread(int $mailboxId, string $folder, int $uid, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM mailbox_reads WHERE mailbox_id = ? AND folder = ? AND imap_uid = ? AND user_id = ?',
            [$mailboxId, $folder, $uid, $userId]
        );
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    public function getTags(): array
    {
        return $this->db->fetchAll('SELECT * FROM mailbox_tags ORDER BY sort_order, label');
    }

    public function getTagsForMessage(int $mailboxId, string $folder, int $uid): array
    {
        return $this->db->fetchAll(
            'SELECT t.* FROM mailbox_tags t
               JOIN mailbox_tag_map m ON m.tag_id = t.id
              WHERE m.mailbox_id = ? AND m.folder = ? AND m.imap_uid = ?
              ORDER BY t.sort_order, t.label',
            [$mailboxId, $folder, $uid]
        );
    }

    public function addTag(int $tagId, int $mailboxId, string $folder, int $uid, int $userId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO mailbox_tag_map (tag_id, mailbox_id, folder, imap_uid, tagged_by)
             VALUES (?, ?, ?, ?, ?)',
            [$tagId, $mailboxId, $folder, $uid, $userId]
        );
    }

    public function removeTag(int $tagId, int $mailboxId, string $folder, int $uid): void
    {
        $this->db->execute(
            'DELETE FROM mailbox_tag_map WHERE tag_id = ? AND mailbox_id = ? AND folder = ? AND imap_uid = ?',
            [$tagId, $mailboxId, $folder, $uid]
        );
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Search messages in a folder using IMAP server-side search.
     * Returns matching UIDs synced to the DB index.
     */
    public function search(array $mailbox, string $folder, string $query): array
    {
        try {
            $socket = $this->connect($mailbox);
        } catch (\RuntimeException) {
            return [];
        }

        $criteria = 'OR SUBJECT "' . addslashes($query) . '" BODY "' . addslashes($query) . '"';
        $uids     = $socket->uidSearch($folder, $criteria);

        if (empty($uids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($uids), '?'));
        return $this->db->fetchAll(
            'SELECT * FROM mailbox_messages
              WHERE mailbox_id = ? AND folder = ? AND imap_uid IN (' . $placeholders . ')
              ORDER BY sent_at DESC',
            array_merge([$mailbox['id'], $folder], $uids)
        );
    }

    // -------------------------------------------------------------------------
    // Sync
    // -------------------------------------------------------------------------

    /**
     * Incremental sync: fetch headers for UIDs above the last known UID
     * and insert into mailbox_messages. Updates imap_last_uid on the officer row.
     */
    public function syncFolder(array $mailbox, string $folder): void
    {
        $lastUidMap = json_decode($mailbox['imap_last_uid'] ?? '{}', true) ?: [];
        $lastUid    = (int) ($lastUidMap[$folder] ?? 0);

        try {
            $socket = $this->connect($mailbox);
        } catch (\RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return;
        }

        try {
            $uids = $socket->uidSearch($folder, 'UID ' . ($lastUid + 1) . ':*');
        } catch (\RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return;
        }

        if (empty($uids)) {
            return;
        }

        foreach ($uids as $uid) {
            if ($uid <= $lastUid) {
                continue;
            }
            $this->syncMessage($socket, (int) $mailbox['id'], $folder, $uid);
        }

        $lastUidMap[$folder] = max($uids);
        $this->db->execute(
            'UPDATE mailboxes SET imap_last_uid = ? WHERE id = ?',
            [json_encode($lastUidMap), $mailbox['id']]
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncMessage(ImapSocket $socket, int $mailboxId, string $folder, int $uid): void
    {
        $headers = $socket->uidFetchEnvelope($folder, $uid);
        if ($headers === null) {
            return;
        }

        $flags   = $this->parseFlags($headers);

        $messageId  = trim($headers->message_id ?? '');
        $inReplyTo  = trim($headers->in_reply_to ?? '');
        $subject    = $this->decodeHeader($headers->subject ?? '');
        $fromName   = $this->decodeHeader($headers->fromaddress ?? '');
        $fromEmail  = isset($headers->from[0])
            ? ($headers->from[0]->mailbox . '@' . ($headers->from[0]->host ?? ''))
            : '';
        $toAddress  = $headers->toaddress ?? '';
        $ccAddress  = $headers->ccaddress ?? '';
        $sentAt     = date('Y-m-d H:i:s', $headers->udate ?? time());

        $hasAttachments = $this->checkAttachments($socket, $folder, $uid);
        $threadId       = $this->resolveThread($mailboxId, $messageId, $inReplyTo, $subject, $sentAt);

        $this->db->execute(
            'INSERT IGNORE INTO mailbox_messages
             (mailbox_id, folder, imap_uid, message_id, in_reply_to, thread_id,
              subject, from_address, from_name, to_address, cc_address,
              sent_at, has_attachments, imap_flags)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $mailboxId, $folder, $uid,
                $messageId ?: null,
                $inReplyTo  ?: null,
                $threadId,
                $subject,
                $fromEmail,
                $fromName,
                $toAddress,
                $ccAddress,
                $sentAt,
                $hasAttachments ? 1 : 0,
                $flags,
            ]
        );

        // Update thread last_message_at
        if ($threadId) {
            $this->db->execute(
                'UPDATE mailbox_threads SET last_message_at = ?, message_count = message_count + 1
                  WHERE id = ?',
                [$sentAt, $threadId]
            );
        }
    }

    /**
     * Resolve or create a thread ID for a message.
     * Uses In-Reply-To first, then Message-ID lookup. Never subject matching.
     */
    private function resolveThread(
        int $mailboxId,
        string $messageId,
        string $inReplyTo,
        string $subject,
        string $sentAt
    ): ?int {
        // 1. Check if this message's In-Reply-To matches a known message
        if ($inReplyTo) {
            $threadId = $this->db->fetchColumn(
                'SELECT thread_id FROM mailbox_messages
                  WHERE mailbox_id = ? AND message_id = ? AND thread_id IS NOT NULL
                  LIMIT 1',
                [$mailboxId, $inReplyTo]
            );
            if ($threadId) {
                return (int) $threadId;
            }
        }

        // 2. Check if a thread already tracks this message_id as root
        if ($messageId) {
            $threadId = $this->db->fetchColumn(
                'SELECT id FROM mailbox_threads WHERE mailbox_id = ? AND root_message_id = ? LIMIT 1',
                [$mailboxId, $messageId]
            );
            if ($threadId) {
                return (int) $threadId;
            }
        }

        // 3. Create new thread
        return (int) $this->db->insert('mailbox_threads', [
            'mailbox_id'      => $mailboxId,
            'root_message_id' => $messageId ?: null,
            'subject'         => $subject,
            'last_message_at' => $sentAt,
            'message_count'   => 1,
        ]);
    }

    /**
     * Parse a multipart IMAP structure recursively, extracting text/html bodies
     * and recording attachment metadata (name, encoding, part number).
     */
    private function parseStructure(
        ImapSocket $socket,
        string     $folder,
        int        $uid,
        object     $structure,
        string     &$textBody,
        string     &$htmlBody,
        array      &$attachments,
        string     $partNum = ''
    ): void {
        if ($structure->type === ImapSocket::TYPEMULTIPART) {
            foreach ($structure->parts as $i => $part) {
                $num = $partNum ? $partNum . '.' . ($i + 1) : (string) ($i + 1);
                $this->parseStructure($socket, $folder, $uid, $part, $textBody, $htmlBody, $attachments, $num);
            }
            return;
        }

        $partNum = $partNum ?: '1';

        $disposition = strtolower($structure->disposition ?? '');
        $filename    = $this->getFilename($structure);

        if ($disposition === 'attachment' || $filename) {
            $attachments[] = [
                'part'     => $partNum,
                'filename' => $filename,
                'size'     => $structure->bytes ?? 0,
                'subtype'  => $structure->subtype ?? '',
            ];
            return;
        }

        $rawBody = $socket->uidFetchBodyPart($folder, $uid, $partNum);
        $body    = $this->decodeBody($rawBody, $structure->encoding ?? ImapSocket::ENC7BIT);

        if ($structure->type === ImapSocket::TYPETEXT) {
            if (strtolower($structure->subtype) === 'plain') {
                $textBody .= $body;
            } elseif (strtolower($structure->subtype) === 'html') {
                $htmlBody .= $body;
            }
        }
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ImapSocket::ENCBASE64          => base64_decode($body),
            ImapSocket::ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default                        => $body,
        };
    }

    private function getFilename(object $structure): string
    {
        foreach ($structure->parameters ?? [] as $p) {
            if (strtolower($p->attribute) === 'name') {
                return mb_decode_mimeheader($p->value);
            }
        }
        foreach ($structure->dparameters ?? [] as $p) {
            if (strtolower($p->attribute) === 'filename') {
                return mb_decode_mimeheader($p->value);
            }
        }
        return '';
    }

    private function checkAttachments(ImapSocket $socket, string $folder, int $uid): bool
    {
        $structure = $socket->uidFetchStructure($folder, $uid);
        if ($structure === null || $structure->type !== ImapSocket::TYPEMULTIPART) {
            return false;
        }
        foreach ($structure->parts ?? [] as $part) {
            if (strtolower($part->disposition ?? '') === 'attachment' || $this->getFilename($part)) {
                return true;
            }
        }
        return false;
    }

    private function parseFlags(object $headers): string
    {
        $flags = [];
        if (!($headers->Unseen  ?? false)) { $flags[] = '\\Seen'; }
        if ($headers->Answered ?? false)   { $flags[] = '\\Answered'; }
        if ($headers->Flagged  ?? false)   { $flags[] = '\\Flagged'; }
        return implode(' ', array_filter($flags));
    }

    private function decodeHeader(string $value): string
    {
        return mb_decode_mimeheader($value);
    }

    /**
     * Derive three-state read status:
     *   'unread'   — nobody has read it
     *   'partial'  — some but not all holders have read it
     *   'read'     — all current holders have read it
     */
    private function deriveReadState(int $readCount, int $holderCount): string
    {
        if ($readCount === 0)              return 'unread';
        if ($readCount >= $holderCount)    return 'read';
        return 'partial';
    }

    private function findSpecialFolder(array $folders, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if (in_array($name, $folders, true)) {
                return $name;
            }
        }
        return null;
    }

    private function pruneReads(int $mailboxId, string $folder, int $uid): void
    {
        $this->db->execute(
            'DELETE FROM mailbox_reads WHERE mailbox_id = ? AND folder = ? AND imap_uid = ?',
            [$mailboxId, $folder, $uid]
        );
    }

    private function pruneTagMap(int $mailboxId, string $folder, int $uid): void
    {
        $this->db->execute(
            'DELETE FROM mailbox_tag_map WHERE mailbox_id = ? AND folder = ? AND imap_uid = ?',
            [$mailboxId, $folder, $uid]
        );
    }

    // -------------------------------------------------------------------------
    // Encryption helpers
    // -------------------------------------------------------------------------

    public function encryptPassword(string $plaintext): string
    {
        $iv         = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public function decryptPassword(string $stored): string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv         = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $plain      = openssl_decrypt($ciphertext, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : '';
    }
}
