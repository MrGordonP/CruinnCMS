<?php

declare(strict_types=1);

namespace Cruinn\Module\Notifications\Widgets;

use Cruinn\Module\Notifications\Services\NotificationService;

// Last edit: 2026-06-11 16:00 UTC.

class NotificationsWidgets
{
    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $svc = new NotificationService();
        $summary = $svc->hubSummary();

        return [
            'title' => 'Notifications Hub',
            'stats' => [
                ['label' => 'Delivered', 'value' => (int) ($summary['delivered'] ?? 0)],
                ['label' => 'Queued', 'value' => (int) ($summary['queued'] ?? 0)],
                ['label' => 'Skipped', 'value' => (int) ($summary['skipped'] ?? 0)],
                ['label' => 'Failed', 'value' => (int) ($summary['failed'] ?? 0)],
            ],
            'primary_url' => '/admin/notifications/hub',
        ];
    }

    public static function userInboxData(array $settings, array $userContext): array
    {
        $svc = new NotificationService();
        $userId = (int) ($userContext['user_id'] ?? 0);
        $limit = max(1, min((int) ($settings['limit'] ?? 5), 20));

        return [
            'title' => 'My Notifications',
            'unreadCount' => $userId > 0 ? $svc->unreadCount($userId) : 0,
            'notifications' => $userId > 0 ? array_slice($svc->forUser($userId), 0, $limit) : [],
            'primary_url' => '/admin/notifications',
        ];
    }
}
