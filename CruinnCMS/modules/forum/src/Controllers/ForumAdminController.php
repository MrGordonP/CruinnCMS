<?php

namespace Cruinn\Module\Forum\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ForumAdminController extends BaseController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $forumBasePath = $this->forumBasePath();

        $search = trim((string)$this->query('q', ''));
        $categoryId = (int)$this->query('category_id', 0);
        $status = (string)$this->query('status', 'all');

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where[] = 't.category_id = ?';
            $params[] = $categoryId;
        }

        if ($status === 'locked') {
            $where[] = 't.is_locked = 1';
        } elseif ($status === 'open') {
            $where[] = 't.is_locked = 0';
        } elseif ($status === 'pinned') {
            $where[] = 't.is_pinned = 1';
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $threads = $this->db->fetchAll(
            'SELECT t.*, c.title AS category_title, c.slug AS category_slug, u.display_name AS author_name
             FROM forum_threads t
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = t.user_id
             ' . $whereSql . '
             ORDER BY t.is_pinned DESC, t.last_post_at DESC
             LIMIT 300',
            $params
        );

        $categories = $this->db->fetchAll(
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
             ORDER BY c.sort_order ASC, c.title ASC"
        );

        $threadsByCategory = [];
        foreach ($threads as $thread) {
            $cid = (int) ($thread['category_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            if (!isset($threadsByCategory[$cid])) {
                $threadsByCategory[$cid] = [];
            }
            $threadsByCategory[$cid][] = $thread;
        }

        $categoryTree = $this->buildCategoryTreeWithThreads($categories, $threadsByCategory);
        $categoryOptions = $this->flattenCategoryOptions($categoryTree);

        $this->renderAdmin('admin/forum/index', [
            'title' => 'Forum Moderation',
            'threads' => $threads,
            'categories' => $categories,
            'categoryTree' => $categoryTree,
            'categoryOptions' => $categoryOptions,
            'forumBasePath' => $forumBasePath,
            'filters' => [
                'q' => $search,
                'category_id' => $categoryId,
                'status' => $status,
            ],
            'breadcrumbs' => [['Admin', '/admin'], ['Forum Moderation']],
        ]);
    }

    public function createCategory(): void
    {
        Auth::requireAdmin();

        $title = trim((string) $this->input('title', ''));
        $slugInput = trim((string) $this->input('slug', ''));
        $description = trim((string) $this->input('description', ''));
        $accessRole = (string) $this->input('access_role', 'public');
        $sortOrder = max(0, (int) $this->input('sort_order', 0));
        $parentId = max(0, (int) $this->input('parent_id', 0));
        $isActive = (int) $this->input('is_active', 0) === 1 ? 1 : 0;

        if ($title === '') {
            Auth::flash('error', 'Section title is required.');
            $this->redirect('/admin/forum');
        }

        $allowedRoles = ['public', 'member', 'council', 'admin'];
        if (!in_array($accessRole, $allowedRoles, true)) {
            $accessRole = 'public';
        }

        if ($parentId > 0) {
            $parent = $this->db->fetch('SELECT id FROM forum_categories WHERE id = ? LIMIT 1', [$parentId]);
            if (!$parent) {
                Auth::flash('error', 'Selected parent section was not found.');
                $this->redirect('/admin/forum');
            }
        }

        $baseSlug = $slugInput !== '' ? $slugInput : $title;
        $slug = $this->generateUniqueCategorySlug($baseSlug);

        $categoryId = (int) $this->db->insert('forum_categories', [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'title' => $title,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'access_role' => $accessRole,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'forum_category', $categoryId, 'Created section: ' . $title);
        Auth::flash('success', 'Forum section created.');
        $this->redirect('/admin/forum');
    }

    public function updateCategory(string $id): void
    {
        Auth::requireAdmin();

        $categoryId = (int) $id;
        $category = $this->db->fetch('SELECT id, title FROM forum_categories WHERE id = ? LIMIT 1', [$categoryId]);
        if (!$category) {
            Auth::flash('error', 'Section not found.');
            $this->redirect('/admin/forum');
        }

        $title = trim((string) $this->input('title', ''));
        $slugInput = trim((string) $this->input('slug', ''));
        $description = trim((string) $this->input('description', ''));
        $accessRole = (string) $this->input('access_role', 'public');
        $sortOrder = max(0, (int) $this->input('sort_order', 0));
        $parentId = max(0, (int) $this->input('parent_id', 0));
        $isActive = (int) $this->input('is_active', 0) === 1 ? 1 : 0;

        if ($title === '') {
            Auth::flash('error', 'Section title is required.');
            $this->redirect('/admin/forum');
        }

        $allowedRoles = ['public', 'member', 'council', 'admin'];
        if (!in_array($accessRole, $allowedRoles, true)) {
            $accessRole = 'public';
        }

        if ($parentId === $categoryId) {
            Auth::flash('error', 'A section cannot be its own parent.');
            $this->redirect('/admin/forum');
        }

        if ($parentId > 0) {
            $parent = $this->db->fetch('SELECT id FROM forum_categories WHERE id = ? LIMIT 1', [$parentId]);
            if (!$parent) {
                Auth::flash('error', 'Selected parent section was not found.');
                $this->redirect('/admin/forum');
            }

            if ($this->wouldCreateCategoryCycle($categoryId, $parentId)) {
                Auth::flash('error', 'Cannot move a section into its own descendant.');
                $this->redirect('/admin/forum');
            }
        }

        $baseSlug = $slugInput !== '' ? $slugInput : $title;
        $slug = $this->generateUniqueCategorySlug($baseSlug, $categoryId);

        $this->db->update('forum_categories', [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'title' => $title,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'access_role' => $accessRole,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$categoryId]);

        $this->logActivity('update', 'forum_category', $categoryId, 'Updated section: ' . $title);
        Auth::flash('success', 'Forum section updated.');
        $this->redirect('/admin/forum');
    }

    public function deleteCategory(string $id): void
    {
        Auth::requireAdmin();

        $categoryId = (int) $id;
        $category = $this->db->fetch('SELECT id, title FROM forum_categories WHERE id = ? LIMIT 1', [$categoryId]);
        if (!$category) {
            Auth::flash('error', 'Section not found.');
            $this->redirect('/admin/forum');
        }

        $childCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM forum_categories WHERE parent_id = ?', [$categoryId]);
        if ($childCount > 0) {
            Auth::flash('error', 'Cannot delete a section that still has sub-forums.');
            $this->redirect('/admin/forum');
        }

        $threadCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM forum_threads WHERE category_id = ?', [$categoryId]);
        if ($threadCount > 0) {
            Auth::flash('error', 'Cannot delete a section that still has threads. Move or delete those threads first.');
            $this->redirect('/admin/forum');
        }

        $this->db->delete('forum_categories', 'id = ?', [$categoryId]);

        $this->logActivity('delete', 'forum_category', $categoryId, 'Deleted section: ' . (string) $category['title']);
        Auth::flash('success', 'Forum section deleted.');
        $this->redirect('/admin/forum');
    }

    public function bulkModerate(): void
    {
        Auth::requireAdmin();

        $action = trim((string) $this->input('bulk_action', ''));
        $rawThreadIds = (array) $this->input('thread_ids', []);
        $threadIds = array_values(array_unique(array_filter(array_map('intval', $rawThreadIds), static fn(int $id): bool => $id > 0)));

        if (empty($threadIds)) {
            Auth::flash('error', 'Select at least one thread.');
            $this->redirect($this->forumReturnUrl());
        }

        $allowedActions = ['pin', 'unpin', 'lock', 'unlock', 'move', 'delete'];
        if (!in_array($action, $allowedActions, true)) {
            Auth::flash('error', 'Select a valid bulk action.');
            $this->redirect($this->forumReturnUrl());
        }

        $placeholders = implode(', ', array_fill(0, count($threadIds), '?'));
        $threads = $this->db->fetchAll(
            'SELECT id, title FROM forum_threads WHERE id IN (' . $placeholders . ')',
            $threadIds
        );

        if (empty($threads)) {
            Auth::flash('error', 'No matching threads were found.');
            $this->redirect($this->forumReturnUrl());
        }

        $threadIds = array_map(static fn(array $thread): int => (int) $thread['id'], $threads);
        $placeholders = implode(', ', array_fill(0, count($threadIds), '?'));

        if ($action === 'move') {
            $targetCategoryId = (int) $this->input('target_category_id', 0);
            if ($targetCategoryId < 1) {
                Auth::flash('error', 'Choose a destination category for move.');
                $this->redirect($this->forumReturnUrl());
            }

            $targetCategory = $this->db->fetch(
                'SELECT id FROM forum_categories WHERE id = ? AND is_active = 1 LIMIT 1',
                [$targetCategoryId]
            );
            if (!$targetCategory) {
                Auth::flash('error', 'Destination category was not found.');
                $this->redirect($this->forumReturnUrl());
            }

            $params = array_merge([$targetCategoryId], $threadIds);
            $this->db->execute(
                'UPDATE forum_threads SET category_id = ?, updated_at = NOW() WHERE id IN (' . $placeholders . ')',
                $params
            );

            foreach ($threads as $thread) {
                $this->logActivity('update', 'forum_thread', (int) $thread['id'], 'Moved thread: ' . (string) $thread['title']);
            }

            Auth::flash('success', count($threads) . ' thread(s) moved.');
            $this->redirect($this->forumReturnUrl());
        }

        if ($action === 'delete') {
            $this->db->transaction(function () use ($placeholders, $threadIds): void {
                $this->db->execute(
                    'DELETE FROM forum_threads WHERE id IN (' . $placeholders . ')',
                    $threadIds
                );
            });

            foreach ($threads as $thread) {
                $this->logActivity('delete', 'forum_thread', (int) $thread['id'], 'Deleted: ' . (string) $thread['title']);
            }

            Auth::flash('success', count($threads) . ' thread(s) deleted.');
            $this->redirect($this->forumReturnUrl());
        }

        $statusMap = [
            'pin' => ['field' => 'is_pinned', 'value' => 1, 'verb' => 'Pinned'],
            'unpin' => ['field' => 'is_pinned', 'value' => 0, 'verb' => 'Unpinned'],
            'lock' => ['field' => 'is_locked', 'value' => 1, 'verb' => 'Locked'],
            'unlock' => ['field' => 'is_locked', 'value' => 0, 'verb' => 'Unlocked'],
        ];

        $config = $statusMap[$action] ?? null;
        if (!$config) {
            Auth::flash('error', 'Bulk action is not supported.');
            $this->redirect($this->forumReturnUrl());
        }

        $params = array_merge([(int) $config['value']], $threadIds);
        $this->db->execute(
            'UPDATE forum_threads SET ' . $config['field'] . ' = ?, updated_at = NOW() WHERE id IN (' . $placeholders . ')',
            $params
        );

        foreach ($threads as $thread) {
            $this->logActivity('update', 'forum_thread', (int) $thread['id'], $config['verb'] . ': ' . (string) $thread['title']);
        }

        Auth::flash('success', count($threads) . ' thread(s) updated.');
        $this->redirect($this->forumReturnUrl());
    }

    public function togglePin(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title, is_pinned FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newValue = (int)$thread['is_pinned'] === 1 ? 0 : 1;

        $this->db->update('forum_threads', [
            'is_pinned' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity(
            'update',
            'forum_thread',
            (int)$thread['id'],
            ($newValue === 1 ? 'Pinned: ' : 'Unpinned: ') . $thread['title']
        );

        Auth::flash('success', $newValue === 1 ? 'Thread pinned.' : 'Thread unpinned.');
        $this->redirect($this->forumReturnUrl());
    }

    public function toggleLock(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title, is_locked FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newValue = (int)$thread['is_locked'] === 1 ? 0 : 1;

        $this->db->update('forum_threads', [
            'is_locked' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity(
            'update',
            'forum_thread',
            (int)$thread['id'],
            ($newValue === 1 ? 'Locked: ' : 'Unlocked: ') . $thread['title']
        );

        Auth::flash('success', $newValue === 1 ? 'Thread locked.' : 'Thread unlocked.');
        $this->redirect($this->forumReturnUrl());
    }

    public function deleteThread(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $this->db->transaction(function () use ($id): void {
            $this->db->delete('forum_threads', 'id = ?', [$id]);
        });

        $this->logActivity('delete', 'forum_thread', (int)$thread['id'], 'Deleted: ' . $thread['title']);

        Auth::flash('success', 'Thread deleted.');
        $this->redirect('/admin/forum');
    }

    // ── Admin: edit thread title ──────────────────────────────────

    public function editThreadTitle(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newTitle = trim((string)$this->input('title', ''));
        if (mb_strlen($newTitle) < 5) {
            Auth::flash('error', 'Title must be at least 5 characters.');
            $this->redirect($this->publicThreadPath((int) $id));
        }

        $this->db->execute(
            'UPDATE forum_threads SET title = ?, updated_at = NOW() WHERE id = ?',
            [htmlspecialchars($newTitle, ENT_QUOTES), (int)$id]
        );
        $this->logActivity('update', 'forum_thread', (int)$id, 'Admin edited title: ' . $newTitle);

        Auth::flash('success', 'Thread title updated.');
        $this->redirect($this->publicThreadPath((int) $id));
    }

    // ── Admin: edit any post ──────────────────────────────────────

    public function editPost(string $id): void
    {
        Auth::requireAdmin();

        $post = $this->db->fetch(
            'SELECT p.*, t.title AS thread_title, t.category_id FROM forum_posts p
             JOIN forum_threads t ON t.id = p.thread_id WHERE p.id = ? LIMIT 1',
            [(int) $id]
        );

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $this->renderAdmin('admin/forum/edit-post', [
            'title'       => 'Edit Post',
            'post'        => $post,
            'forumBasePath' => $this->forumBasePath(),
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Edit Post']],
        ]);
    }

    public function updatePost(string $id): void
    {
        Auth::requireAdmin();

        $post = $this->db->fetch('SELECT id, thread_id FROM forum_posts WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $bodyHtml = trim((string)$this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Post content cannot be empty.');
            $this->redirect('/admin/forum/post/' . (int)$id . '/edit');
        }

        $this->db->execute(
            'UPDATE forum_posts SET body_html = ?, edit_count = edit_count + 1, edited_at = NOW(), updated_at = NOW() WHERE id = ?',
            [sanitise_html($bodyHtml), (int)$id]
        );

        $this->logActivity('update', 'forum_post', (int)$id, 'Admin edited post #' . (int)$id);
        Auth::flash('success', 'Post updated.');
        $this->redirect($this->publicThreadPath((int) $post['thread_id']) . '#post-' . (int) $id);
    }

    // ── Admin: delete any post ────────────────────────────────────

    public function deletePost(string $id): void
    {
        Auth::requireAdmin();

        $post = $this->db->fetch('SELECT id, thread_id FROM forum_posts WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $this->db->execute(
            'UPDATE forum_posts SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ?',
            [(int)Auth::userId(), (int)$id]
        );

        $this->logActivity('delete', 'forum_post', (int)$id, 'Admin deleted post #' . (int)$id);
        Auth::flash('success', 'Post deleted.');
        $this->redirect($this->publicThreadPath((int) $post['thread_id']));
    }

    // ── Admin: move thread ────────────────────────────────────────

    public function moveThreadForm(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title, category_id FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $categories = $this->db->fetchAll(
            'SELECT id, title, parent_id FROM forum_categories WHERE is_active = 1 ORDER BY sort_order ASC, title ASC'
        );

        $this->renderAdmin('admin/forum/move-thread', [
            'title'       => 'Move Thread',
            'thread'      => $thread,
            'categories'  => $categories,
            'forumBasePath' => $this->forumBasePath(),
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Move Thread']],
        ]);
    }

    public function moveThread(string $id): void
    {
        Auth::requireAdmin();

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newCategoryId = (int)$this->input('category_id', 0);
        if ($newCategoryId < 1) {
            Auth::flash('error', 'Please select a category.');
            $this->redirect('/admin/forum/' . (int)$id . '/move');
        }

        $cat = $this->db->fetch('SELECT id FROM forum_categories WHERE id = ? AND is_active = 1 LIMIT 1', [$newCategoryId]);
        if (!$cat) {
            Auth::flash('error', 'Category not found.');
            $this->redirect('/admin/forum/' . (int)$id . '/move');
        }

        $this->db->execute(
            'UPDATE forum_threads SET category_id = ?, updated_at = NOW() WHERE id = ?',
            [$newCategoryId, (int)$id]
        );

        $this->logActivity('update', 'forum_thread', (int)$thread['id'], 'Moved thread: ' . $thread['title']);
        Auth::flash('success', 'Thread moved.');
        $this->redirect($this->publicThreadPath((int) $id));
    }

    // ── Admin: post reports ───────────────────────────────────────

    public function listReports(): void
    {
        Auth::requireAdmin();

        $status = (string)$this->query('status', 'open');

        $where = $status === 'all' ? '' : 'WHERE r.status = ?';
        $params = $status === 'all' ? [] : [$status];

        $reports = $this->db->fetchAll(
            "SELECT r.*, u.display_name AS reporter_name, ru.display_name AS reviewer_name,
                    p.body_html, p.thread_id, t.title AS thread_title
             FROM forum_post_reports r
             JOIN users u ON u.id = r.reporter_id
             LEFT JOIN users ru ON ru.id = r.reviewed_by
             JOIN forum_posts p ON p.id = r.post_id
             JOIN forum_threads t ON t.id = p.thread_id
             {$where}
             ORDER BY r.created_at DESC
             LIMIT 200",
            $params
        );

        $this->renderAdmin('admin/forum/reports', [
            'title'       => 'Post Reports',
            'reports'     => $reports,
            'status'      => $status,
            'forumBasePath' => $this->forumBasePath(),
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Post Reports']],
        ]);
    }

    public function reviewReport(string $id): void
    {
        Auth::requireAdmin();

        $report = $this->db->fetch('SELECT id FROM forum_post_reports WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$report) {
            Auth::flash('error', 'Report not found.');
            $this->redirect('/admin/forum/reports');
        }

        $newStatus = (string)$this->input('status', 'reviewed');
        if (!in_array($newStatus, ['reviewed', 'dismissed'], true)) {
            $newStatus = 'reviewed';
        }

        $this->db->execute(
            'UPDATE forum_post_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$newStatus, (int)Auth::userId(), (int)$id]
        );

        $this->logActivity('update', 'forum_post_report', (int)$id, 'Report marked: ' . $newStatus);
        Auth::flash('success', 'Report marked as ' . $newStatus . '.');
        $this->redirect('/admin/forum/reports');
    }

    private function forumReturnUrl(): string
    {
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $path = (string)parse_url($referer, PHP_URL_PATH);
        $query = (string)parse_url($referer, PHP_URL_QUERY);

        if ($path === '/admin/forum') {
            return $query !== '' ? '/admin/forum?' . $query : '/admin/forum';
        }

        return '/admin/forum';
    }

    private function forumBasePath(): string
    {
        return ForumController::publicBasePath($this->db);
    }

    private function publicThreadPath(int $threadId): string
    {
        $basePath = $this->forumBasePath();
        if ($basePath === '') {
            return '/';
        }

        return rtrim($basePath, '/') . '/thread/' . $threadId;
    }

    private function buildCategoryTreeWithThreads(array $categories, array $threadsByCategory): array
    {
        $map = [];

        foreach ($categories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $category['children'] = [];
            $category['threads'] = $threadsByCategory[$id] ?? [];
            $map[$id] = $category;
        }

        $roots = [];
        foreach ($map as $id => &$category) {
            $parentId = (int) ($category['parent_id'] ?? 0);
            if ($parentId > 0 && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$category;
                continue;
            }

            $roots[] = &$category;
        }
        unset($category);

        return $roots;
    }

    private function flattenCategoryOptions(array $tree, int $depth = 0): array
    {
        $options = [];

        foreach ($tree as $node) {
            $options[] = [
                'id' => (int) ($node['id'] ?? 0),
                'title' => (string) ($node['title'] ?? ''),
                'depth' => $depth,
                'is_active' => (int) ($node['is_active'] ?? 0),
            ];

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if (!empty($children)) {
                $options = array_merge($options, $this->flattenCategoryOptions($children, $depth + 1));
            }
        }

        return $options;
    }

    private function wouldCreateCategoryCycle(int $categoryId, int $newParentId): bool
    {
        $current = $newParentId;

        while ($current > 0) {
            if ($current === $categoryId) {
                return true;
            }

            $parent = $this->db->fetch('SELECT parent_id FROM forum_categories WHERE id = ? LIMIT 1', [$current]);
            if (!$parent) {
                break;
            }

            $current = (int) ($parent['parent_id'] ?? 0);
        }

        return false;
    }

    private function generateUniqueCategorySlug(string $source, int $excludeId = 0): string
    {
        $base = $this->slugify($source);
        if ($base === '') {
            $base = 'forum-section';
        }

        $slug = $base;
        $suffix = 2;

        while (true) {
            if ($excludeId > 0) {
                $exists = (int) $this->db->fetchColumn(
                    'SELECT COUNT(*) FROM forum_categories WHERE slug = ? AND id != ?',
                    [$slug, $excludeId]
                );
            } else {
                $exists = (int) $this->db->fetchColumn(
                    'SELECT COUNT(*) FROM forum_categories WHERE slug = ?',
                    [$slug]
                );
            }

            if ($exists === 0) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
