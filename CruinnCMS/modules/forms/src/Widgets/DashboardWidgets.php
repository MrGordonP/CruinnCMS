<?php

declare(strict_types=1);

namespace Cruinn\Module\Forms\Widgets;

use Cruinn\Database;

class DashboardWidgets
{
    public static function quickLinksData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Forms Dashboard', 'url' => '/admin/forms', 'icon' => '📋'],
                ['label' => 'New Form', 'url' => '/admin/forms/new', 'icon' => '➕'],
            ],
        ];
    }

    public static function statusSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        try {
            $publishedForms = (int) $db->fetchColumn("SELECT COUNT(*) FROM forms WHERE status = 'published'");
            $pendingSubmissions = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM form_submissions WHERE status = 'pending'"
            );
            $todaySubmissions = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM form_submissions WHERE DATE(submitted_at) = CURDATE()"
            );
        } catch (\Throwable) {
            $publishedForms = 0;
            $pendingSubmissions = 0;
            $todaySubmissions = 0;
        }

        return [
            'title' => 'Forms Status',
            'stats' => [
                ['label' => 'Published Forms', 'value' => $publishedForms],
                ['label' => 'Pending', 'value' => $pendingSubmissions],
                ['label' => 'Submitted Today', 'value' => $todaySubmissions],
            ],
            'primary_url' => '/admin/forms',
        ];
    }
}
