<?php

namespace Cruinn\Module\Forum\Forum;

use Cruinn\Auth;
use Cruinn\Database;

class NativeForumProvider implements ForumProviderInterface
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listCategories(?string $viewerRole = null): array
    {
        $role = $viewerRole ?? Auth::role() ?? 'public';
        $allowed = $this->allowedRolesForViewer($role);
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        // Top-level categories only (parent_id IS NULL)
        $topLevel = $this->db->fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM forum_threads t WHERE t.category_id = c.id) AS thread_count,
                    (SELECT COUNT(*) FROM forum_posts p
                        JOIN forum_threads t2 ON t2.id = p.thread_id
                        WHERE t2.category_id = c.id) AS post_count,
                    (SELECT MAX(t3.last_post_at) FROM forum_threads t3 WHERE t3.category_id = c.id) AS last_post_at
             FROM forum_categories c
             WHERE c.is_active = 1 AND c.parent_id IS NULL AND c.access_role IN ({$placeholders})
             ORDER BY c.sort_order ASC, c.title ASC",
            $allowed
        );

        // Attach direct children count for display
        foreach ($topLevel as &$cat) {
            $cat['subcategory_count'] = (int)$this->db->fetchColumn(
                'SELECT COUNT(*) FROM forum_categories WHERE parent_id = ? AND is_active = 1',
                [$cat['id']]
            );
        }
        unset($cat);

        return $topLevel;
    }

    public function getCategoryBySlug(string $slug, ?string $viewerRole = null): ?array
    {
        $category = $this->db->fetch('SELECT * FROM forum_categories WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if (!$category) {
            return null;
        }

        $role = $viewerRole ?? Auth::role();
        if (!$this->canAccessCategoryRole($role, $category['access_role'])) {
            return null;
        }

        return $category;
    }

    public function getSubcategories(int $parentId, ?string $viewerRole = null): array
    {
        $role = $viewerRole ?? Auth::role();
        $allowed = $this->allowedRolesForViewer($role);
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        $params = array_merge([$parentId], $allowed);

        $subs = $this->db->fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM forum_threads t WHERE t.category_id = c.id) AS thread_count,
                    (SELECT COUNT(*) FROM forum_posts p
                        JOIN forum_threads t2 ON t2.id = p.thread_id
                        WHERE t2.category_id = c.id) AS post_count,
                    (SELECT MAX(t3.last_post_at) FROM forum_threads t3 WHERE t3.category_id = c.id) AS last_post_at
             FROM forum_categories c
             WHERE c.is_active = 1 AND c.parent_id = ? AND c.access_role IN ({$placeholders})
             ORDER BY c.sort_order ASC, c.title ASC",
            $params
        );

        foreach ($subs as &$cat) {
            $cat['subcategory_count'] = (int)$this->db->fetchColumn(
                'SELECT COUNT(*) FROM forum_categories WHERE parent_id = ? AND is_active = 1',
                [$cat['id']]
            );
        }
        unset($cat);

        return $subs;
    }

    public function getCategoryBreadcrumbs(int $categoryId): array
    {
        $crumbs = [];
        $current = $categoryId;

        while ($current) {
            $cat = $this->db->fetch(
                'SELECT id, parent_id, title, slug FROM forum_categories WHERE id = ? LIMIT 1',
                [$current]
            );
            if (!$cat) break;
            array_unshift($crumbs, $cat);
            $current = $cat['parent_id'] ? (int)$cat['parent_id'] : 0;
        }

        return $crumbs;
    }

    public function listThreadsByCategory(int $categoryId, int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->db->fetchAll(
            'SELECT t.*, u.display_name AS author_name, lu.display_name AS last_post_user_name
             FROM forum_threads t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users lu ON lu.id = t.last_post_user_id
             WHERE t.category_id = ?
             ORDER BY t.is_pinned DESC, t.last_post_at DESC
             LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            [$categoryId]
        );
    }

    public function countThreadsByCategory(int $categoryId): int
    {
        return (int)$this->db->fetchColumn('SELECT COUNT(*) FROM forum_threads WHERE category_id = ?', [$categoryId]);
    }

    public function getThread(int $threadId): ?array
    {
        $thread = $this->db->fetch(
            'SELECT t.*, c.title AS category_title, c.slug AS category_slug, c.access_role, u.display_name AS author_name
             FROM forum_threads t
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = t.user_id
             WHERE t.id = ?
             LIMIT 1',
            [$threadId]
        );

        return $thread ?: null;
    }

    public function listPosts(int $threadId, int $page = 1, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->db->fetchAll(
            'SELECT p.*, u.display_name AS author_name, u.id AS author_user_id,
                    du.display_name AS deleted_by_name
             FROM forum_posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN users du ON du.id = p.deleted_by
             WHERE p.thread_id = ?
             ORDER BY p.created_at ASC
             LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            [$threadId]
        );
    }

    public function countPosts(int $threadId): int
    {
        return (int)$this->db->fetchColumn('SELECT COUNT(*) FROM forum_posts WHERE thread_id = ?', [$threadId]);
    }

    public function getPost(int $postId): ?array
    {
        $post = $this->db->fetch(
            'SELECT p.*, u.display_name AS author_name, t.category_id, t.is_locked, c.access_role
             FROM forum_posts p
             JOIN forum_threads t ON t.id = p.thread_id
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = p.user_id
             WHERE p.id = ? LIMIT 1',
            [$postId]
        );

        return $post ?: null;
    }

    public function updatePost(int $postId, string $bodyHtml): void
    {
        $this->db->execute(
            'UPDATE forum_posts SET body_html = ?, edit_count = edit_count + 1, edited_at = NOW(), updated_at = NOW() WHERE id = ?',
            [$bodyHtml, $postId]
        );
    }

    public function softDeletePost(int $postId, int $deletedBy): void
    {
        $this->db->execute(
            'UPDATE forum_posts SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ?',
            [$deletedBy, $postId]
        );
    }

    public function reportPost(int $postId, int $reporterId, string $reason, ?string $body): int
    {
        return (int)$this->db->insert('forum_post_reports', [
            'post_id'     => $postId,
            'reporter_id' => $reporterId,
            'reason'      => $reason,
            'body'        => $body,
            'status'      => 'open',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function listReports(string $status = 'open', int $limit = 100): array
    {
        $where = $status === 'all' ? '' : 'WHERE r.status = ?';
        $params = $status === 'all' ? [] : [$status];

        return $this->db->fetchAll(
            "SELECT r.*, u.display_name AS reporter_name, ru.display_name AS reviewer_name,
                    p.body_html, p.thread_id, t.title AS thread_title
             FROM forum_post_reports r
             JOIN users u ON u.id = r.reporter_id
             LEFT JOIN users ru ON ru.id = r.reviewed_by
             JOIN forum_posts p ON p.id = r.post_id
             JOIN forum_threads t ON t.id = p.thread_id
             {$where}
             ORDER BY r.created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    public function reviewReport(int $reportId, string $status, int $reviewedBy): void
    {
        $this->db->execute(
            'UPDATE forum_post_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$status, $reviewedBy, $reportId]
        );
    }

    public function moveThread(int $threadId, int $newCategoryId): void
    {
        $this->db->execute(
            'UPDATE forum_threads SET category_id = ?, updated_at = NOW() WHERE id = ?',
            [$newCategoryId, $threadId]
        );
    }

    public function softDeleteThread(int $threadId, int $deletedBy): void
    {
        $this->db->execute(
            'UPDATE forum_threads SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ?',
            [$deletedBy, $threadId]
        );
    }

    public function createThread(int $categoryId, int $userId, string $title, string $bodyHtml): int
    {
        $slug = $this->slugify($title);

        return $this->db->transaction(function () use ($categoryId, $userId, $title, $slug, $bodyHtml) {
            $threadId = (int)$this->db->insert('forum_threads', [
                'category_id' => $categoryId,
                'user_id' => $userId,
                'title' => $title,
                'slug' => $slug,
                'is_pinned' => 0,
                'is_locked' => 0,
                'reply_count' => 0,
                'last_post_at' => date('Y-m-d H:i:s'),
                'last_post_user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->insert('forum_posts', [
                'thread_id' => $threadId,
                'user_id' => $userId,
                'body_html' => $bodyHtml,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $watchers = $this->db->fetchAll(
                "SELECT id
                 FROM users
                 WHERE active = 1
                   AND role IN ('admin', 'council')
                   AND id != ?",
                [$userId]
            );

            // Notifications stub — NotificationService not yet implemented.

            return $threadId;
        });
    }

    public function createReply(int $threadId, int $userId, string $bodyHtml): int
    {
        return $this->db->transaction(function () use ($threadId, $userId, $bodyHtml) {
            $postId = (int)$this->db->insert('forum_posts', [
                'thread_id' => $threadId,
                'user_id' => $userId,
                'body_html' => $bodyHtml,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->execute(
                'UPDATE forum_threads
                 SET reply_count = GREATEST(reply_count + 1, 0),
                     last_post_at = NOW(),
                     last_post_user_id = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                [$userId, $threadId]
            );

            $thread = $this->db->fetch('SELECT title, user_id FROM forum_threads WHERE id = ? LIMIT 1', [$threadId]);

            $participants = $this->db->fetchAll(
                'SELECT DISTINCT user_id
                 FROM forum_posts
                 WHERE thread_id = ? AND user_id != ?',
                [$threadId, $userId]
            );

            $participantIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['user_id'], $participants)));

            // Notifications stub — NotificationService not yet implemented.

            return $postId;
        });
    }

    private function allowedRolesForViewer(string $viewerRole): array
    {
        return match ($viewerRole) {
            'admin' => ['public', 'member', 'council', 'admin'],
            'council' => ['public', 'member', 'council'],
            'member' => ['public', 'member'],
            default => ['public'],
        };
    }

    private function canAccessCategoryRole(string $viewerRole, string $accessRole): bool
    {
        $hierarchy = ['public' => 0, 'member' => 1, 'council' => 2, 'admin' => 3];
        return ($hierarchy[$viewerRole] ?? 0) >= ($hierarchy[$accessRole] ?? 0);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-');
    }
}
