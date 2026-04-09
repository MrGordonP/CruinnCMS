<?php

namespace Cruinn\Module\Organisation\Services;

use Cruinn\Database;

class OrganisationDashboardService
{
    public static function dashboardSummary(array $settings): array
    {
        $db = Database::getInstance();
        $limit = max(1, (int) ($settings['limit'] ?? 4));

        $recentDocuments = $db->fetchAll(
            'SELECT id, title, category, status, updated_at
             FROM documents
             ORDER BY updated_at DESC
             LIMIT ' . $limit
        );

        $recentDiscussions = $db->fetchAll(
            'SELECT id, title, pinned, locked, post_count, last_post_at, created_at
             FROM discussions
             ORDER BY pinned DESC, last_post_at DESC, created_at DESC
             LIMIT ' . $limit
        );

        return [
            'documents' => (int) $db->fetchColumn('SELECT COUNT(*) FROM documents'),
            'pending' => (int) $db->fetchColumn("SELECT COUNT(*) FROM documents WHERE status = 'submitted'"),
            'discussions' => (int) $db->fetchColumn('SELECT COUNT(*) FROM discussions'),
            'posts' => (int) $db->fetchColumn('SELECT COUNT(*) FROM discussion_posts'),
            'recent_documents' => $recentDocuments,
            'recent_discussions' => $recentDiscussions,
        ];
    }
}
