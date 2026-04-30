<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Mailbox\Services\MailboxService;

/**
 * MailboxController — public-facing webmail routes.
 *
 * Access is derived from organisation_officers.user_id.
 * Admin users see all imap-enabled mailboxes.
 */
class MailboxController extends BaseController
{
    private MailboxService $mailbox;

    public function __construct()
    {
        parent::__construct();
        Auth::requireRole('member');

        $config        = require CRUINN_ROOT . '/config/config.php';
        $secret        = $config['secret_key'] ?? '';
        $this->mailbox = new MailboxService($this->db, $secret);
    }

    // -------------------------------------------------------------------------
    // Index — list accessible mailboxes
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $userId   = Auth::userId();
        $role     = Auth::role();
        $mailboxes = $this->mailbox->getAccessibleMailboxes($userId, $role);

        $this->renderAdmin('mailbox/index', [
            'mailboxes'  => $mailboxes,
            'page_title' => 'Mailbox',
        ]);
    }

    // -------------------------------------------------------------------------
    // Folders
    // -------------------------------------------------------------------------

    public function folders(string $mailbox_id): void
    {
        $mb = $this->resolveMailbox((int) $mailbox_id);

        $folders = $this->mailbox->getFolders($mb);

        $this->renderAdmin('mailbox/folders', [
            'mailbox'    => $mb,
            'folders'    => $folders,
            'page_title' => $mb['position'] . ' — Folders',
        ]);
    }

    // -------------------------------------------------------------------------
    // Message list
    // -------------------------------------------------------------------------

    public function messages(string $mailbox_id, string $folder): void
    {
        $mb     = $this->resolveMailbox((int) $mailbox_id);
        $folder = urldecode($folder);
        $page   = max(1, (int) ($this->query('page', 1)));

        $messages = $this->mailbox->getMessages($mb, $folder, Auth::userId(), Auth::role(), $page);
        $total    = $this->mailbox->getMessageCount((int) $mb['id'], $folder);
        $folders  = $this->mailbox->getFolders($mb);

        $this->renderAdmin('mailbox/messages', [
            'mailbox'    => $mb,
            'folder'     => $folder,
            'folders'    => $folders,
            'messages'   => $messages,
            'page'       => $page,
            'total'      => $total,
            'per_page'   => 50,
            'imap_error' => $this->mailbox->getLastError(),
            'page_title' => $mb['position'] . ' — ' . $folder,
        ]);
    }

    // -------------------------------------------------------------------------
    // Single message
    // -------------------------------------------------------------------------

    public function message(string $mailbox_id, string $folder, string $uid): void
    {
        $mb     = $this->resolveMailbox((int) $mailbox_id);
        $folder = urldecode($folder);
        $uid    = (int) $uid;

        try {
            $body = $this->mailbox->fetchBody($mb, $folder, $uid);
        } catch (\RuntimeException $e) {
            Auth::flash('error', 'This message could not be found — it may have been moved or deleted.');
            $this->redirect('/admin/mailbox/' . $mailbox_id . '/folder/' . rawurlencode($folder));
            return;
        }
        $tags    = $this->mailbox->getTagsForMessage((int) $mb['id'], $folder, $uid);
        $allTags = $this->mailbox->getTags();
        $folders = $this->mailbox->getFolders($mb);

        // Record read receipt
        $this->mailbox->markRead((int) $mb['id'], $folder, $uid, Auth::userId());

        $this->renderAdmin('mailbox/message', [
            'mailbox'    => $mb,
            'folder'     => $folder,
            'uid'        => $uid,
            'body'       => $body,
            'tags'       => $tags,
            'all_tags'   => $allTags,
            'folders'    => $folders,
            'csrf_token' => CSRF::getToken(),
            'page_title' => $body['headers']->subject ?? '(no subject)',
        ]);
    }

    // -------------------------------------------------------------------------
    // Message preview (HTML fragment — no layout, loaded into pl-detail panel)
    // -------------------------------------------------------------------------

    public function preview(string $mailbox_id, string $folder, string $uid): void
    {
        $mb     = $this->resolveMailbox((int) $mailbox_id);
        $folder = urldecode($folder);
        $uid    = (int) $uid;

        $body = $this->mailbox->fetchBody($mb, $folder, $uid);
        $this->mailbox->markRead((int) $mb['id'], $folder, $uid, Auth::userId());

        $this->template->setLayout(null);
        echo $this->template->render('mailbox/preview', [
            'mailbox'    => $mb,
            'folder'     => $folder,
            'uid'        => $uid,
            'body'       => $body,
            'csrf_token' => CSRF::getToken(),
            'base_url'   => '/mail/' . (int) $mb['id'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Mark read / unread (JSON)
    // -------------------------------------------------------------------------

    public function markRead(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb  = $this->resolveMailbox((int) $mailbox_id);
        $this->mailbox->markRead((int) $mb['id'], urldecode($folder), (int) $uid, Auth::userId());
        $this->json(['ok' => true]);
    }

    public function markUnread(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb  = $this->resolveMailbox((int) $mailbox_id);
        $this->mailbox->markUnread((int) $mb['id'], urldecode($folder), (int) $uid, Auth::userId());
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Move / Delete
    // -------------------------------------------------------------------------

    public function move(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb       = $this->resolveMailbox((int) $mailbox_id);
        $folder   = urldecode($folder);
        $toFolder = $this->input('folder');

        if (!$toFolder) {
            $this->json(['error' => 'No target folder specified.'], 400);
        }

        $this->mailbox->moveMessage($mb, $folder, (int) $uid, $toFolder);
        $this->json(['ok' => true]);
    }

    public function delete(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb = $this->resolveMailbox((int) $mailbox_id);
        $this->mailbox->deleteMessage($mb, urldecode($folder), (int) $uid);

        Auth::flash('success', 'Message moved to Trash.');
        $this->redirect('/mail/' . $mb['id'] . '/' . urlencode($folder));
    }

    // -------------------------------------------------------------------------
    // Compose / Send
    // -------------------------------------------------------------------------

    public function compose(string $mailbox_id): void
    {
        $mb = $this->resolveMailbox((int) $mailbox_id);

        $this->renderAdmin('mailbox/compose', [
            'mailbox'    => $mb,
            'csrf_token' => CSRF::getToken(),
            'page_title' => 'New Message — ' . $mb['position'],
            'prefill'    => [],
        ]);
    }

    public function send(string $mailbox_id): void
    {
        CSRF::verify();
        $mb = $this->resolveMailbox((int) $mailbox_id);

        $data = [
            'to'        => $this->input('to'),
            'cc'        => $this->input('cc'),
            'subject'   => $this->input('subject'),
            'text_body' => $this->input('body'),
        ];

        $errors = $this->validateCompose($data);
        if ($errors) {
            $this->renderAdmin('mailbox/compose', [
                'mailbox'    => $mb,
                'csrf_token' => CSRF::getToken(),
                'page_title' => 'New Message — ' . $mb['position'],
                'prefill'    => $data,
                'errors'     => $errors,
            ]);
            return;
        }

        $this->mailbox->send($mb, $data);
        Auth::flash('success', 'Message sent.');
        $this->redirect('/mail/' . $mb['id'] . '/INBOX');
    }

    // -------------------------------------------------------------------------
    // Reply / Forward
    // -------------------------------------------------------------------------

    public function reply(string $mailbox_id, string $folder, string $uid): void
    {
        $mb   = $this->resolveMailbox((int) $mailbox_id);
        $uid  = (int) $uid;
        $body = $this->mailbox->fetchBody($mb, urldecode($folder), $uid);
        $orig = $body['headers'];

        $this->renderAdmin('mailbox/compose', [
            'mailbox'    => $mb,
            'csrf_token' => CSRF::getToken(),
            'page_title' => 'Reply — ' . ($orig->subject ?? ''),
            'prefill'    => [
                'to'          => $orig->reply_toaddress ?? $orig->fromaddress ?? '',
                'subject'     => 'Re: ' . ltrim(preg_replace('/^Re:\s*/i', '', $orig->subject ?? ''), ' '),
                'in_reply_to' => $orig->message_id ?? '',
                'quote'       => $body['text_body'],
            ],
        ]);
    }

    public function sendReply(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb   = $this->resolveMailbox((int) $mailbox_id);

        $data = [
            'to'          => $this->input('to'),
            'subject'     => $this->input('subject'),
            'text_body'   => $this->input('body'),
            'in_reply_to' => $this->input('in_reply_to'),
        ];

        $errors = $this->validateCompose($data);
        if ($errors) {
            $this->renderAdmin('mailbox/compose', [
                'mailbox'    => $mb,
                'csrf_token' => CSRF::getToken(),
                'page_title' => 'Reply',
                'prefill'    => $data,
                'errors'     => $errors,
            ]);
            return;
        }

        $this->mailbox->send($mb, $data);
        Auth::flash('success', 'Reply sent.');
        $this->redirect('/mail/' . $mb['id'] . '/' . urlencode($folder));
    }

    public function forward(string $mailbox_id, string $folder, string $uid): void
    {
        $mb   = $this->resolveMailbox((int) $mailbox_id);
        $uid  = (int) $uid;
        $body = $this->mailbox->fetchBody($mb, urldecode($folder), $uid);
        $orig = $body['headers'];

        $this->renderAdmin('mailbox/compose', [
            'mailbox'    => $mb,
            'csrf_token' => CSRF::getToken(),
            'page_title' => 'Forward — ' . ($orig->subject ?? ''),
            'prefill'    => [
                'subject'  => 'Fwd: ' . ($orig->subject ?? ''),
                'quote'    => $body['text_body'],
                'to'       => '',
            ],
        ]);
    }

    public function sendForward(string $mailbox_id, string $folder, string $uid): void
    {
        $this->sendReply($mailbox_id, $folder, $uid); // same logic, no In-Reply-To
    }

    // -------------------------------------------------------------------------
    // Tags (JSON)
    // -------------------------------------------------------------------------

    public function addTag(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb    = $this->resolveMailbox((int) $mailbox_id);
        $tagId = (int) $this->input('tag_id');
        $this->mailbox->addTag($tagId, (int) $mb['id'], urldecode($folder), (int) $uid, Auth::userId());
        $this->json(['ok' => true]);
    }

    public function removeTag(string $mailbox_id, string $folder, string $uid): void
    {
        CSRF::verify();
        $mb    = $this->resolveMailbox((int) $mailbox_id);
        $tagId = (int) $this->input('tag_id');
        $this->mailbox->removeTag($tagId, (int) $mb['id'], urldecode($folder), (int) $uid);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public function search(string $mailbox_id): void
    {
        $mb     = $this->resolveMailbox((int) $mailbox_id);
        $query  = trim($this->query('q', ''));
        $folder = $this->query('folder', 'INBOX');

        $results = $query ? $this->mailbox->search($mb, $folder, $query) : [];

        $this->renderAdmin('mailbox/search', [
            'mailbox'    => $mb,
            'query'      => $query,
            'folder'     => $folder,
            'results'    => $results,
            'page_title' => 'Search — ' . $mb['position'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a mailbox and assert access, aborting with 403 if denied.
     */
    private function resolveMailbox(int $mailboxId): array
    {
        $mb = $this->mailbox->getMailbox($mailboxId, Auth::userId(), Auth::role());
        if (!$mb) {
            http_response_code(403);
            $this->render('errors/403', []);
            exit;
        }
        return $mb;
    }

    private function validateCompose(array $data): array
    {
        $errors = [];
        if (empty($data['to']))      $errors[] = 'Recipient (To) is required.';
        if (empty($data['subject'])) $errors[] = 'Subject is required.';
        if (empty($data['text_body'])) $errors[] = 'Message body is required.';
        return $errors;
    }
}
