<?php
/**
 * Mailbox Module — IMAP-backed webmail for organisation position mailboxes.
 *
 * Mailbox accounts are derived from organisation_officers rows that have
 * IMAP credentials set. Access is granted to the user linked to that officer
 * position. A user holding multiple positions sees multiple mailboxes.
 *
 * Requires: organisation module (organisation_officers table).
 */

use Cruinn\Module\Mailbox\Controllers\MailboxController;
use Cruinn\Module\Mailbox\Controllers\MailboxAdminController;

return [
    'slug'         => 'mailbox',
    'name'         => 'Mailbox',
    'description'  => 'IMAP webmail for organisation position mailboxes. Single login, role-derived access.',
    'version'      => '1.0.0',
    'dependencies' => ['organisation'],

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
        __DIR__ . '/migrations/002_officers_imap_columns.sql',
        __DIR__ . '/migrations/003_mailboxes_table.sql',
        __DIR__ . '/migrations/004_mailbox_sort_order.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Organisation', 'label' => 'Email',            'url' => '/mail',          'icon' => '✉️'],
        ['group' => 'Organisation', 'label' => 'Mailbox Settings', 'url' => '/admin/mailbox', 'icon' => '⚙️'],
    ],

    'dashboard_sections' => [
        ['group' => 'Mail', 'label' => 'Mailbox', 'url' => '/mail', 'icon' => '✉️', 'roles' => ['admin', 'council', 'member']],
    ],

    'routes' => static function (object $router): void {

        // --- Public / member-facing routes ---

        // Mailbox index — lists accessible mailboxes for the logged-in user
        $router->get('/mail', [MailboxController::class, 'index']);

        // Folder list for a mailbox (mailbox identified by officer id)
        $router->get('/mail/{mailbox_id}', [MailboxController::class, 'folders']);

        // Message list for a folder
        $router->get('/mail/{mailbox_id}/{folder}', [MailboxController::class, 'messages']);

        // Single message view
        $router->get('/mail/{mailbox_id}/{folder}/{uid}', [MailboxController::class, 'message']);

        // Message preview fragment (no layout — loaded into pl-detail panel via fetch)
        $router->get('/mail/{mailbox_id}/{folder}/{uid}/preview', [MailboxController::class, 'preview']);

        // Mark read/unread (POST, returns JSON)
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/read',   [MailboxController::class, 'markRead']);
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/unread', [MailboxController::class, 'markUnread']);

        // Move message to folder
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/move', [MailboxController::class, 'move']);

        // Delete (move to Trash)
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/delete', [MailboxController::class, 'delete']);

        // Compose new message
        $router->get('/mail/{mailbox_id}/compose',  [MailboxController::class, 'compose']);
        $router->post('/mail/{mailbox_id}/compose', [MailboxController::class, 'send']);

        // Reply / forward
        $router->get('/mail/{mailbox_id}/{folder}/{uid}/reply',   [MailboxController::class, 'reply']);
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/reply',  [MailboxController::class, 'sendReply']);
        $router->get('/mail/{mailbox_id}/{folder}/{uid}/forward', [MailboxController::class, 'forward']);
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/forward',[MailboxController::class, 'sendForward']);

        // Tags (JSON endpoints)
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/tag',   [MailboxController::class, 'addTag']);
        $router->post('/mail/{mailbox_id}/{folder}/{uid}/untag', [MailboxController::class, 'removeTag']);

        // Search
        $router->get('/mail/{mailbox_id}/search', [MailboxController::class, 'search']);

        // --- Admin routes ---

        // Overview: three-panel mailbox settings
        $router->get('/admin/mailbox', [MailboxAdminController::class, 'index']);

        // New mailbox — blank credentials fragment
        $router->get('/admin/mailbox/new', [MailboxAdminController::class, 'newForm']);

        // Create new mailbox
        $router->post('/admin/mailbox', [MailboxAdminController::class, 'create']);

        // Credentials panel fragment (fetched into middle panel)
        $router->get('/admin/mailbox/{id}/credentials-panel', [MailboxAdminController::class, 'credentialsPanel']);

        // Save credentials for existing mailbox
        $router->post('/admin/mailbox/{id}/credentials', [MailboxAdminController::class, 'saveCredentials']);

        // Delete mailbox
        $router->post('/admin/mailbox/{id}/delete', [MailboxAdminController::class, 'delete']);

        // Access panel fragment (fetched into right panel)
        $router->get('/admin/mailbox/{id}/access', [MailboxAdminController::class, 'accessPanel']);

        // Grant / revoke access
        $router->post('/admin/mailbox/{id}/access/grant',              [MailboxAdminController::class, 'grantAccess']);
        $router->post('/admin/mailbox/{id}/access/{grant_id}/revoke',  [MailboxAdminController::class, 'revokeAccess']);

        // Tag management
        $router->get('/admin/mailbox/tags',              [MailboxAdminController::class, 'tags']);
        $router->post('/admin/mailbox/tags',             [MailboxAdminController::class, 'createTag']);
        $router->post('/admin/mailbox/tags/{id}/update', [MailboxAdminController::class, 'updateTag']);
        $router->post('/admin/mailbox/tags/{id}/delete', [MailboxAdminController::class, 'deleteTag']);

        // Trigger manual sync for a mailbox (AJAX or form POST)
        $router->post('/admin/mailbox/{id}/sync', [MailboxAdminController::class, 'sync']);
    },
];
