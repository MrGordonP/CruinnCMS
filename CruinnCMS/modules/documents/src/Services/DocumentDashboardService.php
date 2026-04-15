<?php

namespace Cruinn\Module\Documents\Services;

use Cruinn\Database;

class DocumentDashboardService
{
    public static function dashboardSummary(array $settings): array
    {
        $db    = Database::getInstance();
        $limit = max(1, (int) ($settings['limit'] ?? 5));

        $recent = $db->fetchAll(
            'SELECT id, title, category, status, updated_at
             FROM documents
             ORDER BY updated_at DESC
             LIMIT ' . $limit
        );

        return [
            'documents'        => (int) $db->fetchColumn('SELECT COUNT(*) FROM documents'),
            'pending'          => (int) $db->fetchColumn("SELECT COUNT(*) FROM documents WHERE status = 'submitted'"),
            'recent_documents' => $recent,
        ];
    }
}
