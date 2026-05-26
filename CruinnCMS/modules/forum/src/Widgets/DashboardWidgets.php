<?php

declare(strict_types=1);

namespace Cruinn\Module\Forum\Widgets;

use Cruinn\Database;

class DashboardWidgets
{
    public static function quickLinksData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Forum Moderation', 'url' => '/admin/forum', 'icon' => '💬'],
                ['label' => 'Reports', 'url' => '/admin/forum/reports', 'icon' => '🚩'],
            ],
        ];
    }

    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        try {
            $openReports = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM forum_post_reports WHERE status = 'open'"
            );
            $activeThreads7d = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM forum_threads WHERE last_post_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );

            $userId = (int) ($userContext['user_id'] ?? 0);
            $myPosts7d = 0;
            if ($userId > 0) {
                try {
                    $myPosts7d = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM forum_posts
                         WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                        [$userId]
                    );
                } catch (\Throwable) {
                    $myPosts7d = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM forum_posts
                         WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                        [$userId]
                    );
                }
            }
        } catch (\Throwable) {
            $openReports = 0;
            $activeThreads7d = 0;
            $myPosts7d = 0;
        }

        return [
            'title' => 'Forum Activity',
            'stats' => [
                ['label' => 'Open Reports', 'value' => $openReports],
                ['label' => 'Active Threads (7d)', 'value' => $activeThreads7d],
                ['label' => 'My Posts (7d)', 'value' => $myPosts7d],
            ],
            'primary_url' => '/admin/forum',
        ];
    }
}
