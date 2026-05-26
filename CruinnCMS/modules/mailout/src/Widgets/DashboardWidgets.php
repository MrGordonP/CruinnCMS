<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailout\Widgets;

use Cruinn\Database;

class DashboardWidgets
{
    public static function quickLinksData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Mailout Dashboard', 'url' => '/admin/mailout', 'icon' => '📣'],
                ['label' => 'New Mailout', 'url' => '/admin/mailout/new', 'icon' => '✉️'],
                ['label' => 'Mailing Lists', 'url' => '/admin/mailout/lists', 'icon' => '📋'],
            ],
        ];
    }

    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        try {
            $drafts = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM email_broadcasts WHERE status = 'draft'"
            );
            $inFlight = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM email_broadcasts WHERE status IN ('queued','sending')"
            );
            $pendingQueue = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM email_queue WHERE status = 'pending'"
            );
        } catch (\Throwable) {
            $drafts = 0;
            $inFlight = 0;
            $pendingQueue = 0;
        }

        return [
            'title' => 'Mailout Status',
            'stats' => [
                ['label' => 'Drafts', 'value' => $drafts],
                ['label' => 'Queued/Sending', 'value' => $inFlight],
                ['label' => 'Pending Queue', 'value' => $pendingQueue],
            ],
            'primary_url' => '/admin/mailout',
        ];
    }
}
