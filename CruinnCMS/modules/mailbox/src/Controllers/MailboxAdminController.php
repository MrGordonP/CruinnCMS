<?php
declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Mailbox\Services\MailboxService;

/**
 * MailboxAdminController â€” ACP routes for mailbox configuration.
 *
 * Manages:
 *  - Mailbox credential records (mailboxes table)
 *  - Access grants (mailbox_access: direct user or officer position)
 *  - Tag CRUD
 *  - Manual IMAP sync trigger
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
    // Overview â€” three-panel shell
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $mailboxes = $this->db->fetchAll(
            'SELECT id, label, email, imap_host, imap_port, imap_encryption,
                    imap_user, smtp_host, smtp_port, smtp_encryption, smtp_user,
                    enabled,
                    (SELECT COUNT(*) FROM mailbox_messages mm WHERE mm.mailbox_id = mailboxes.id) AS indexed_count
               FROM mailboxes
              ORDER BY label'
        );

        $this->renderAdmin('admin/mailbox/index', [
            'mailboxes'   => $mailboxes,
            'csrf_token'  => CSRF::getToken(),
            'page_title'  => 'Mailbox â€” Settings',
            'breadcrumbs' => [['Admin', '/admin'], ['Mailbox']],
        ]);
    }

    // -------------------------------------------------------------------------
    // Credentials panel (HTML fragment â€” fetched into middle pl-main)
    // -------------------------------------------------------------------------

    /**
     * GET /admin/mailbox/new â€” blank credentials form fragment
     */
    public function newForm(): void
    {
        $this->template->setLayout(null);
        $mb = [
            'id'              => null,
            'label'           => '',
            'email'           => '',
            'imap_host'       => \Cruinn\App::config('imap.host', ''),
            'imap_port'       => \Cruinn\App::config('imap.port', 993),
            'imap_encryption' => 'ssl',
            'imap_user'       => '',
            'smtp_host'       => \Cruinn\App::config('mail.host', ''),
            'smtp_port'       => \Cruinn\App::config('mail.port', 587),
            'smtp_encryption' => \Cruinn\App::config('mail.encryption', 'tls'),
            'smtp_user'       => '',
            'enabled'         => 0,
            'has_imap_pass'   => false,
            'has_smtp_pass'   => false,
        ];
        echo $this->template->render('admin/mailbox/credentials-panel', [
            'mb'         => $mb,
            'is_new'     => true,
            'csrf_token' => CSRF::getToken(),
        ]);
    }

    /**
     * GET /admin/mailbox/{id}/credentials-panel â€” credentials form fragment for existing mailbox
     */
    public function credentialsPanel(string $id): void
    {
        $this->template->setLayout(null);
        $id = (int) $id;

        $mb = $this->db->fetch(
            'SELECT id, label, email,
                    imap_host, imap_port, imap_encryption, imap_user,
                    smtp_host, smtp_port, smtp_encryption, smtp_user,
                    enabled,
                    (imap_pass_enc IS NOT NULL AND imap_pass_enc != \'\') AS has_imap_pass,
                    (smtp_pass_enc IS NOT NULL AND smtp_pass_enc != \'\') AS has_smtp_pass
               FROM mailboxes WHERE id = ?',
            [$id]
        );

        if (!$mb) {
            http_response_code(404);
            echo '<p style="padding:1rem;color:#c00">Mailbox not found.</p>';
            return;
        }

        echo $this->template->render('admin/mailbox/credentials-panel', [
            'mb'         => $mb,
            'is_new'     => false,
            'csrf_token' => CSRF::getToken(),
        ]);
    }

    /**
     * POST /admin/mailbox â€” create new mailbox
     */
    public function create(): void
    {
        CSRF::verify();

        $fields = $this->collectCredentialFields();

        if ($fields['label'] === '') {
            Auth::flash('error', 'Label is required.');
            $this->redirect('/admin/mailbox');
        }

        $imapPass = $this->input('imap_pass', '');
        if ($imapPass !== '') {
            $fields['imap_pass_enc'] = $this->mailbox->encryptPassword($imapPass);
        }
        $smtpPass = $this->input('smtp_pass', '');
        if ($smtpPass !== '') {
            $fields['smtp_pass_enc'] = $this->mailbox->encryptPassword($smtpPass);
        }

        $this->db->insert('mailboxes', $fields);
        $newId = $this->db->lastInsertId();

        Auth::flash('success', 'Mailbox "' . htmlspecialchars($fields['label']) . '" created.');
        $this->redirect('/admin/mailbox?selected=' . $newId);
    }

    /**
     * POST /admin/mailbox/{id}/credentials â€” update existing mailbox
     */
    public function saveCredentials(string $id): void
    {
        CSRF::verify();
        $id = (int) $id;

        $mb = $this->db->fetch('SELECT id FROM mailboxes WHERE id = ?', [$id]);
        if (!$mb) {
            Auth::flash('error', 'Mailbox not found.');
            $this->redirect('/admin/mailbox');
        }

        $fields = $this->collectCredentialFields();

        $imapPass = $this->input('imap_pass', '');
        if ($imapPass !== '') {
            $fields['imap_pass_enc'] = $this->mailbox->encryptPassword($imapPass);
        }
        $smtpPass = $this->input('smtp_pass', '');
        if ($smtpPass !== '') {
            $fields['smtp_pass_enc'] = $this->mailbox->encryptPassword($smtpPass);
        }

        $set    = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
        $values = array_values($fields);
        $values[] = $id;

        $this->db->execute("UPDATE mailboxes SET {$set} WHERE id = ?", $values);

        Auth::flash('success', 'Mailbox credentials saved.');
        $this->redirect('/admin/mailbox?selected=' . $id);
    }

    /**
     * POST /admin/mailbox/{id}/delete
     */
    public function delete(string $id): void
    {
        CSRF::verify();
        $id = (int) $id;

        $this->db->execute('DELETE FROM mailbox_access WHERE mailbox_id = ?', [$id]);
        $this->db->execute('DELETE FROM mailbox_messages WHERE mailbox_id = ?', [$id]);
        $this->db->execute('DELETE FROM mailboxes WHERE id = ?', [$id]);

        Auth::flash('success', 'Mailbox deleted.');
        $this->redirect('/admin/mailbox');
    }

    // -------------------------------------------------------------------------
    // Access panel (HTML fragment â€” fetched into right pl-detail)
    // -------------------------------------------------------------------------

    /**
     * GET /admin/mailbox/{id}/access â€” access list + grant form fragment
     */
    public function accessPanel(string $id): void
    {
        $this->template->setLayout(null);
        $id = (int) $id;

        $mb = $this->db->fetch('SELECT id, label, email FROM mailboxes WHERE id = ?', [$id]);
        if (!$mb) {
            http_response_code(404);
            echo '<p style="padding:1rem;color:#c00">Mailbox not found.</p>';
            return;
        }

        $grants = $this->db->fetchAll(
            'SELECT ma.id AS grant_id, ma.user_id, ma.officer_position_id, ma.granted_at,
                    u.display_name AS user_name, u.email AS user_email,
                    o.position AS officer_position, o.email AS officer_email
               FROM mailbox_access ma
               LEFT JOIN users u ON u.id = ma.user_id
               LEFT JOIN organisation_officers o ON o.id = ma.officer_position_id
              WHERE ma.mailbox_id = ?
              ORDER BY ma.granted_at',
            [$id]
        );

        $availableUsers = $this->db->fetchAll(
            'SELECT id, display_name, email FROM users ORDER BY display_name'
        );
        $availablePositions = $this->db->fetchAll(
            'SELECT id, position, email FROM organisation_officers ORDER BY sort_order, position'
        );

        $grantedUserIds     = array_values(array_filter(array_column($grants, 'user_id')));
        $grantedPositionIds = array_values(array_filter(array_column($grants, 'officer_position_id')));

        echo $this->template->render('admin/mailbox/access-panel', [
            'mb'                   => $mb,
            'grants'               => $grants,
            'granted_user_ids'     => $grantedUserIds,
            'granted_position_ids' => $grantedPositionIds,
            'available_users'      => $availableUsers,
            'available_positions'  => $availablePositions,
            'csrf_token'           => CSRF::getToken(),
        ]);
    }

    /**
     * POST /admin/mailbox/{id}/access/grant
     */
    public function grantAccess(string $id): void
    {
        CSRF::verify();
        $mailboxId = (int) $id;
        $type      = $this->input('grant_type', ''); // 'user' or 'position'
        $targetId  = (int) $this->input('target_id', 0);

        if (!$targetId || !in_array($type, ['user', 'position'], true)) {
            Auth::flash('error', 'Invalid grant.');
            $this->redirect('/admin/mailbox?selected=' . $mailboxId);
        }

        $row = ['mailbox_id' => $mailboxId, 'granted_by' => Auth::userId()];
        if ($type === 'user') {
            $row['user_id'] = $targetId;
        } else {
            $row['officer_position_id'] = $targetId;
        }

        $this->db->insert('mailbox_access', $row);
        Auth::flash('success', 'Access granted.');
        $this->redirect('/admin/mailbox?selected=' . $mailboxId);
    }

    /**
     * POST /admin/mailbox/{id}/access/{grant_id}/revoke
     */
    public function revokeAccess(string $id, string $grant_id): void
    {
        CSRF::verify();
        $mailboxId = (int) $id;
        $grantId   = (int) $grant_id;

        $this->db->execute(
            'DELETE FROM mailbox_access WHERE id = ? AND mailbox_id = ?',
            [$grantId, $mailboxId]
        );

        Auth::flash('success', 'Access revoked.');
        $this->redirect('/admin/mailbox?selected=' . $mailboxId);
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
            'page_title'  => 'Mailbox â€” Tags',
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
        $this->db->execute('DELETE FROM mailbox_tags WHERE id = ?', [$id]);
        Auth::flash('success', 'Tag deleted.');
        $this->redirect('/admin/mailbox/tags');
    }

    // -------------------------------------------------------------------------
    // Manual sync
    // -------------------------------------------------------------------------

    public function sync(string $id): void
    {
        CSRF::verify();
        $mailboxId = (int) $id;

        $mb = $this->db->fetch(
            'SELECT * FROM mailboxes WHERE id = ? AND enabled = 1',
            [$mailboxId]
        );

        if (!$mb) {
            $this->json(['error' => 'Mailbox not found or not enabled.'], 404);
        }

        $mb['position'] = $mb['label']; // alias for service compatibility

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function collectCredentialFields(): array
    {
        return [
            'label'           => trim($this->input('label', '')),
            'email'           => trim($this->input('email', '')),
            'imap_host'       => trim($this->input('imap_host', '')) ?: null,
            'imap_port'       => (int) $this->input('imap_port', 993),
            'imap_encryption' => $this->input('imap_encryption', 'ssl'),
            'imap_user'       => trim($this->input('imap_user', '')) ?: null,
            'smtp_host'       => trim($this->input('smtp_host', '')) ?: null,
            'smtp_port'       => (int) $this->input('smtp_port', 587),
            'smtp_encryption' => $this->input('smtp_encryption', 'tls'),
            'smtp_user'       => trim($this->input('smtp_user', '')) ?: null,
            'enabled'         => $this->input('enabled', '0') === '1' ? 1 : 0,
        ];
    }
}
