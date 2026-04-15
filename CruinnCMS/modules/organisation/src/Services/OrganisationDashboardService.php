<?php

namespace Cruinn\Module\Organisation\Services;

use Cruinn\Database;

class OrganisationDashboardService
{
    public static function dashboardSummary(array $settings): array
    {
        $db    = Database::getInstance();
        $limit = max(1, (int) ($settings['limit'] ?? 4));

        $recentDiscussions = $db->fetchAll(
            'SELECT id, title, pinned, locked, post_count, last_post_at, created_at
             FROM discussions
             ORDER BY pinned DESC, last_post_at DESC, created_at DESC
             LIMIT ' . $limit
        );

        $result = [
            'discussions'        => (int) $db->fetchColumn('SELECT COUNT(*) FROM discussions'),
            'posts'              => (int) $db->fetchColumn('SELECT COUNT(*) FROM discussion_posts'),
            'recent_discussions' => $recentDiscussions,
            // Document stats — soft dependency on documents module
            'documents'          => 0,
            'pending'            => 0,
            'recent_documents'   => [],
        ];

        // Query document stats only if the documents table is available
        try {
            $result['documents']        = (int) $db->fetchColumn('SELECT COUNT(*) FROM documents');
            $result['pending']          = (int) $db->fetchColumn("SELECT COUNT(*) FROM documents WHERE status = 'submitted'");
            $result['recent_documents'] = $db->fetchAll(
                'SELECT id, title, category, status, updated_at FROM documents ORDER BY updated_at DESC LIMIT ' . $limit
            );
        } catch (\Throwable $e) {
            // Documents module not installed — skip silently
        }

        return $result;
    }
}
