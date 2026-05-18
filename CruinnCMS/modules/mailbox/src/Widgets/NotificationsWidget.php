<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Widgets;

use Cruinn\Database;
use Cruinn\Module\Mailbox\Services\MailboxService;
use Cruinn\App;

/**
 * Notifications Widget — Unread mailbox message counts for dashboard.
 *
 * Queries accessible mailboxes for the user and returns unread counts per mailbox.
 */
class NotificationsWidget
{
    /**
     * Data provider for notifications widget.
     *
     * @param array $settings Widget configuration settings
     * @param array $userContext User context: ['user_id', 'role_id', 'role_level', 'position_ids']
     * @return array Widget data: ['mailboxes' => [...]]
     */
    public static function getData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();
        $userId = (int) ($userContext['user_id'] ?? 0);
        $roleLevel = (int) ($userContext['role_level'] ?? 0);

        if (!$userId) {
            return ['mailboxes' => [], 'total_unread' => 0];
        }

        // Determine role string for MailboxService (admin if level >= 90)
        $role = $roleLevel >= 90 ? 'admin' : 'member';

        // Get accessible mailboxes
        $mailboxService = new MailboxService($db, App::config('secret_key', 'default-secret'));
        $mailboxes = $mailboxService->getAccessibleMailboxes($userId, $role);

        // For each mailbox, count unread messages (messages with no read entry for this user)
        $results = [];
        $totalUnread = 0;

        foreach ($mailboxes as $mailbox) {
            $mailboxId = (int) $mailbox['id'];

            // Count total messages in INBOX for this mailbox
            $totalMessages = $db->fetchColumn(
                'SELECT COUNT(*) FROM mailbox_messages
                 WHERE mailbox_id = ? AND folder = ?',
                [$mailboxId, 'INBOX']
            );

            // Count read messages for this user
            $readCount = $db->fetchColumn(
                'SELECT COUNT(DISTINCT mr.imap_uid)
                 FROM mailbox_reads mr
                 JOIN mailbox_messages mm ON mm.mailbox_id = mr.mailbox_id
                                          AND mm.folder = mr.folder
                                          AND mm.imap_uid = mr.imap_uid
                 WHERE mr.mailbox_id = ? AND mr.folder = ? AND mr.user_id = ?',
                [$mailboxId, 'INBOX', $userId]
            );

            $unreadCount = (int) $totalMessages - (int) $readCount;

            $results[] = [
                'id'           => $mailboxId,
                'label'        => $mailbox['label'] ?? 'Unknown',
                'email'        => $mailbox['email'] ?? '',
                'unread_count' => $unreadCount,
            ];

            $totalUnread += $unreadCount;
        }

        return [
            'mailboxes'   => $results,
            'total_unread' => $totalUnread,
        ];
    }
}
