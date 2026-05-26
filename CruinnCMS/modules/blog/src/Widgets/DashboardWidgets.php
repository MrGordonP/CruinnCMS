<?php

declare(strict_types=1);

namespace Cruinn\Module\Blog\Widgets;

use Cruinn\Database;

class DashboardWidgets
{
    public static function quickLinksData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Blog Dashboard', 'url' => '/admin/blog', 'icon' => '📰'],
                ['label' => 'All Posts', 'url' => '/admin/blog/posts', 'icon' => '📚'],
                ['label' => 'New Post', 'url' => '/admin/blog/posts/new', 'icon' => '✍️'],
            ],
        ];
    }

    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        try {
            $drafts = (int) $db->fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'draft'");
            $published = (int) $db->fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");
            $recentUpdates = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM articles WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        } catch (\Throwable) {
            $drafts = 0;
            $published = 0;
            $recentUpdates = 0;
        }

        return [
            'title' => 'Blog Status',
            'stats' => [
                ['label' => 'Drafts', 'value' => $drafts],
                ['label' => 'Published', 'value' => $published],
                ['label' => 'Updated (7d)', 'value' => $recentUpdates],
            ],
            'primary_url' => '/admin/blog/posts',
        ];
    }
}
