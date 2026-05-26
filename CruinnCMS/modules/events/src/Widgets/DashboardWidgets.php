<?php

declare(strict_types=1);

namespace Cruinn\Module\Events\Widgets;

use Cruinn\Database;

class DashboardWidgets
{
    public static function quickLinksData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Events Dashboard', 'url' => '/admin/events', 'icon' => '📅'],
                ['label' => 'Event List', 'url' => '/admin/events/list', 'icon' => '🗓️'],
                ['label' => 'New Event', 'url' => '/admin/events/new', 'icon' => '➕'],
            ],
        ];
    }

    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        try {
            $upcoming = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM events WHERE status = 'published' AND date_start >= CURDATE()"
            );
            $drafts = (int) $db->fetchColumn("SELECT COUNT(*) FROM events WHERE status = 'draft'");
            $registrations = (int) $db->fetchColumn(
                "SELECT COUNT(*)
                 FROM event_registrations er
                 JOIN events e ON e.id = er.event_id
                 WHERE e.date_start >= CURDATE() AND er.status = 'confirmed'"
            );
        } catch (\Throwable) {
            $upcoming = 0;
            $drafts = 0;
            $registrations = 0;
        }

        return [
            'title' => 'Events Status',
            'stats' => [
                ['label' => 'Upcoming', 'value' => $upcoming],
                ['label' => 'Drafts', 'value' => $drafts],
                ['label' => 'Registrations', 'value' => $registrations],
            ],
            'primary_url' => '/admin/events',
        ];
    }
}
