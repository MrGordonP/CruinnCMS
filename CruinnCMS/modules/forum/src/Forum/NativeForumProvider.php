<?php

namespace Cruinn\Module\Forum\Forum;

use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Module\Notifications\Services\NotificationService;

// Last edit: 2026-06-11 16:00 UTC.

class NativeForumProvider implements ForumProviderInterface
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function listCategories(?int $viewerLevel = null): array
    {
        $level = $viewerLevel ?? Auth::roleLevel();
        $allowed = $this->allowedRolesForViewer($level);
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

    /**
     * Fetch all categories in hierarchical structure for PHPBB-style display.
     * Returns array of parent categories with 'children' key containing sub-forums.
     */
    public function listCategoriesHierarchical(?int $viewerLevel = null): array
    {
        $level = $viewerLevel ?? Auth::roleLevel();
        $allowed = $this->allowedRolesForViewer($level);
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        // Fetch ALL categories with stats
        $all = $this->db->fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM forum_threads t WHERE t.category_id = c.id) AS thread_count,
                    (SELECT COUNT(*) FROM forum_posts p
                        JOIN forum_threads t2 ON t2.id = p.thread_id
                        WHERE t2.category_id = c.id) AS post_count,
                    (SELECT MAX(t3.last_post_at) FROM forum_threads t3 WHERE t3.category_id = c.id) AS last_post_at,
                    (SELECT t.title FROM forum_threads t WHERE t.category_id = c.id ORDER BY t.last_post_at DESC LIMIT 1) AS last_thread_title,
                    (SELECT t.id FROM forum_threads t WHERE t.category_id = c.id ORDER BY t.last_post_at DESC LIMIT 1) AS last_thread_id,
                    (SELECT u.display_name FROM forum_threads t LEFT JOIN users u ON u.id = t.last_post_user_id WHERE t.category_id = c.id ORDER BY t.last_post_at DESC LIMIT 1) AS last_post_user_name
             FROM forum_categories c
             WHERE c.is_active = 1 AND c.access_role IN ({$placeholders})
             ORDER BY c.sort_order ASC, c.title ASC",
            $allowed
        );

        // Build hierarchical structure
        $categoryMap = [];
        $rootCategories = [];

        // First pass: index all categories
        foreach ($all as $cat) {
            $cat['children'] = [];
            $categoryMap[(int)$cat['id']] = $cat;
        }

        // Second pass: build hierarchy
        foreach ($categoryMap as $id => $cat) {
            $parentId = $cat['parent_id'] ? (int)$cat['parent_id'] : null;

            if ($parentId === null) {
                // Top-level category
                $rootCategories[] = &$categoryMap[$id];
            } elseif (isset($categoryMap[$parentId])) {
                // Add as child of parent
                $categoryMap[$parentId]['children'][] = &$categoryMap[$id];
            }
        }

        return $rootCategories;
    }

    public function getCategoryBySlug(string $slug, ?int $viewerLevel = null): ?array
    {
        $category = $this->db->fetch('SELECT * FROM forum_categories WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if (!$category) {
            return null;
        }

        $level = $viewerLevel ?? Auth::roleLevel();
        if (!$this->canAccessCategoryRole($level, $category['access_role'])) {
            return null;
        }

        return $category;
    }

    public function getSubcategories(int $parentId, ?int $viewerLevel = null): array
    {
        $level = $viewerLevel ?? Auth::roleLevel();
        $allowed = $this->allowedRolesForViewer($level);
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

    public function getThreadBySubjectId(int $subjectId, ?int $viewerLevel = null): ?array
    {
        if ($subjectId <= 0) {
            return null;
        }

        $thread = $this->fetchThreadBySubjectId($subjectId);
        if (!$thread) {
            return null;
        }

        $level = $viewerLevel ?? Auth::roleLevel();
        if ($level < $this->roleSlugToLevel((string) ($thread['access_role'] ?? 'public'))) {
            return null;
        }

        return $thread;
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

    public function createThread(int $categoryId, int $userId, string $title, string $bodyHtml, ?int $subjectId = null): int
    {
        $slug = $this->slugify($title);

        if (($subjectId ?? 0) > 0) {
            $existingThread = $this->fetchThreadBySubjectId((int) $subjectId);
            if ($existingThread) {
                return (int) $existingThread['id'];
            }
        }

        return $this->db->transaction(function () use ($categoryId, $userId, $title, $slug, $bodyHtml, $subjectId) {
            if (($subjectId ?? 0) > 0) {
                $existingThread = $this->fetchThreadBySubjectId((int) $subjectId);
                if ($existingThread) {
                    return (int) $existingThread['id'];
                }
            }

            $threadId = (int)$this->db->insert('forum_threads', [
                'category_id' => $categoryId,
                'subject_id' => ($subjectId ?? 0) > 0 ? (int) $subjectId : null,
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

            $categoryOwner = $this->db->fetch(
                'SELECT user_id FROM forum_categories WHERE id = ? LIMIT 1',
                [$categoryId]
            );
            $recipients = [];
            if (!empty($categoryOwner['user_id'])) {
                $ownerId = (int) $categoryOwner['user_id'];
                if ($ownerId > 0 && $ownerId !== $userId) {
                    $recipients[] = $ownerId;
                }
            }

            if (!empty($recipients)) {
                $notif = new NotificationService();
                $notif->publishHubEvent([
                    'source_module' => 'forum',
                    'source_event' => 'thread_created',
                    'category' => 'forum',
                    'title' => 'New forum thread: ' . $title,
                    'body' => 'A new discussion thread was created.',
                    'url' => '/forum/thread/' . $threadId,
                    'subject_id' => ($subjectId ?? 0) > 0 ? (int) $subjectId : null,
                    'actor_user_id' => $userId,
                    'recipient_user_ids' => $recipients,
                    'dedupe_key' => 'forum:thread_created:' . $threadId,
                    'metadata' => ['thread_id' => $threadId, 'category_id' => $categoryId],
                ]);
            }

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

            if (!empty($participantIds)) {
                $notif = new NotificationService();
                $notif->publishHubEvent([
                    'source_module' => 'forum',
                    'source_event' => 'reply_created',
                    'category' => 'forum',
                    'title' => 'New reply in: ' . (string) ($thread['title'] ?? 'Forum thread'),
                    'body' => 'A new reply was posted in a thread you participated in.',
                    'url' => '/forum/thread/' . $threadId,
                    'actor_user_id' => $userId,
                    'recipient_user_ids' => $participantIds,
                    'dedupe_key' => 'forum:reply_created:' . $postId,
                    'metadata' => ['thread_id' => $threadId, 'post_id' => $postId],
                ]);
            }

            return $postId;
        });
    }

    /**
     * Convert role slug to numeric level.
     * Maps legacy role names to the engine's level system.
     */
    private function roleSlugToLevel(string $slug): int
    {
        return match ($slug) {
            'admin' => 100,
            'council' => 50,
            'editor' => 20,
            'member' => 10,
            'public' => 0,
            default => 0,
        };
    }

    /**
     * Get all role slugs accessible by a given level.
     * e.g. level 50 (council) can access: public, member, council
     */
    private function levelToAllowedSlugs(int $level): array
    {
        if ($level >= 100) return ['public', 'member', 'editor', 'council', 'admin'];
        if ($level >= 50) return ['public', 'member', 'editor', 'council'];
        if ($level >= 20) return ['public', 'member', 'editor'];
        if ($level >= 10) return ['public', 'member'];
        return ['public'];
    }

    private function allowedRolesForViewer(?int $viewerLevel = null): array
    {
        $level = $viewerLevel ?? Auth::roleLevel();
        return $this->levelToAllowedSlugs($level);
    }

    private function canAccessCategoryRole(?int $viewerLevel, string $accessRole): bool
    {
        $level = $viewerLevel ?? Auth::roleLevel();
        return $level >= $this->roleSlugToLevel($accessRole);
    }

    private function fetchThreadBySubjectId(int $subjectId): ?array
    {
        $thread = $this->db->fetch(
            'SELECT t.*, c.title AS category_title, c.slug AS category_slug, c.access_role, u.display_name AS author_name
             FROM forum_threads t
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = t.user_id
             WHERE t.subject_id = ?
             LIMIT 1',
            [$subjectId]
        );

        return $thread ?: null;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-');
    }
}
