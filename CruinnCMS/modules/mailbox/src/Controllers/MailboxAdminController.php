<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Mailbox\Services\MailboxService;

/**
 * MailboxAdminController — ACP routes for mailbox module.
 *
 * Covers: overview of all mailboxes, tag CRUD, manual sync trigger.
 * IMAP credential entry lives in the Organisation → Officers section
 * (handled by OrganisationAdminController) — not duplicated here.
 */
class MailboxAdminController extends BaseController
{
    private MailboxService $mailbox;

    public function __construct()
    {
        parent::__construct();
        Auth::requireRole('admin');

        $config        = require CRUINN_ROOT . '/config/config.php';
        $secret        = $config['secret_key'] ?? '';
        $this->mailbox = new MailboxService($this->db, $secret);
    }

    // -------------------------------------------------------------------------
    // Overview
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $mailboxes = $this->db->fetchAll(
            'SELECT id, position, email, imap_host, imap_user, imap_enabled,
                    (SELECT COUNT(*) FROM mailbox_messages mm WHERE mm.mailbox_id = organisation_officers.id) AS indexed_count
               FROM organisation_officers
              WHERE imap_host IS NOT NULL
              ORDER BY sort_order, position'
        );

        $this->renderAdmin('admin/mailbox/index', [
            'mailboxes'   => $mailboxes,
            'page_title'  => 'Mailbox — Overview',
            'breadcrumbs' => [['Admin', '/admin'], ['Mailbox']],
        ]);
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    public function tags(): void
    {
        $tags = $this->mailbox->getTags();

        $this->renderAdmin('admin/mailbox/tags', [
            'tags'        => $tags,
            'csrf_token'  => CSRF::getToken(),
            'page_title'  => 'Mailbox — Tags',
            'breadcrumbs' => [['Admin', '/admin'], ['Mailbox', '/admin/mailbox'], ['Tags']],
        ]);
    }

    public function createTag(): void
    {
        CSRF::verify();

        $label  = trim($this->input('label', ''));
        $colour = trim($this->input('colour', '#888888'));

        if ($label === '') {
            Auth::flash('error', 'Tag label is required.');
            $this->redirect('/admin/mailbox/tags');
        }

        $this->db->insert('mailbox_tags', [
            'label'      => $label,
            'colour'     => $colour,
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);

        Auth::flash('success', 'Tag "' . htmlspecialchars($label) . '" created.');
        $this->redirect('/admin/mailbox/tags');
    }

    public function updateTag(array $params): void
    {
        CSRF::verify();

        $id     = (int) $params['id'];
        $label  = trim($this->input('label', ''));
        $colour = trim($this->input('colour', '#888888'));

        if ($label === '') {
            Auth::flash('error', 'Tag label is required.');
            $this->redirect('/admin/mailbox/tags');
        }

        $this->db->execute(
            'UPDATE mailbox_tags SET label = ?, colour = ?, sort_order = ? WHERE id = ?',
            [$label, $colour, (int) $this->input('sort_order', 0), $id]
        );

        Auth::flash('success', 'Tag updated.');
        $this->redirect('/admin/mailbox/tags');
    }

    public function deleteTag(array $params): void
    {
        CSRF::verify();
        $id = (int) $params['id'];
        // tag_map rows CASCADE on delete (FK defined in migration)
        $this->db->execute('DELETE FROM mailbox_tags WHERE id = ?', [$id]);
        Auth::flash('success', 'Tag deleted.');
        $this->redirect('/admin/mailbox/tags');
    }

    // -------------------------------------------------------------------------
    // Manual sync
    // -------------------------------------------------------------------------

    public function sync(array $params): void
    {
        CSRF::verify();
        $mailboxId = (int) $params['mailbox_id'];

        $mb = $this->db->fetch(
            'SELECT * FROM organisation_officers WHERE id = ? AND imap_enabled = 1',
            [$mailboxId]
        );

        if (!$mb) {
            $this->json(['error' => 'Mailbox not found or not enabled.'], 404);
        }

        $folders = $this->mailbox->getFolders($mb);
        $synced  = 0;

        foreach ($folders as $folder) {
            $before = $this->mailbox->getMessageCount($mailboxId, $folder);
            $this->mailbox->syncFolder($mb, $folder);
            $after  = $this->mailbox->getMessageCount($mailboxId, $folder);
            $synced += max(0, $after - $before);
        }

        $this->json(['ok' => true, 'new_messages' => $synced]);
    }
}
