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

    public static function formSummaryData(array $settings, array $userContext): array
    {
        $db = Database::getInstance();

        $formId = (int) ($settings['form_id'] ?? 0);
        $formSlug = trim((string) ($settings['form_slug'] ?? ''));

        $form = null;

        try {
            if ($formId > 0) {
                $form = $db->fetch("SELECT id, title, slug, status FROM forms WHERE id = ? LIMIT 1", [$formId]);
            } elseif ($formSlug !== '') {
                $form = $db->fetch("SELECT id, title, slug, status FROM forms WHERE slug = ? LIMIT 1", [$formSlug]);
            }

            if (!$form) {
                $form = $db->fetch("SELECT id, title, slug, status FROM forms ORDER BY id DESC LIMIT 1");
            }
        } catch (\Throwable) {
            $form = null;
        }

        if (!$form) {
            return [
                'title' => 'Form Summary',
                'stats' => [
                    ['label' => 'Forms', 'value' => 0],
                    ['label' => 'Submissions', 'value' => 0],
                ],
                'primary_url' => '/admin/forms',
            ];
        }

        $selectedId = (int) ($form['id'] ?? 0);
        $formTitle = trim((string) ($settings['title'] ?? $form['title'] ?? ('Form #' . $selectedId)));

        try {
            $totalSubmissions = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM form_submissions WHERE form_id = ?',
                [$selectedId]
            );
            $pending = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM form_submissions WHERE form_id = ? AND status = 'pending'",
                [$selectedId]
            );
            $today = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM form_submissions WHERE form_id = ? AND DATE(submitted_at) = CURDATE()',
                [$selectedId]
            );
        } catch (\Throwable) {
            $totalSubmissions = 0;
            $pending = 0;
            $today = 0;
        }

        return [
            'title' => 'Form: ' . $formTitle,
            'stats' => [
                ['label' => 'Submissions', 'value' => $totalSubmissions],
                ['label' => 'Pending', 'value' => $pending],
                ['label' => 'Today', 'value' => $today],
            ],
            'primary_url' => '/admin/forms/' . $selectedId . '/submissions',
        ];
    }
}
