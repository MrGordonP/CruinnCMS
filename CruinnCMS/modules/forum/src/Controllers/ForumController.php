<?php

namespace Cruinn\Module\Forum\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Forum\Forum\ForumManager;

class ForumController extends BaseController
{
    public function index(): void
    {
        $categories = ForumManager::provider()->listCategoriesHierarchical(Auth::role());

        $this->render('public/forum/index', [
            'title' => 'Forum',
            'categories' => $categories,
        ]);
    }

    public function category(string $slug): void
    {
        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Category Not Found']);
            return;
        }

        $subcategories = $provider->getSubcategories((int)$category['id'], Auth::role());
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);

        $page = max(1, (int)$this->query('page', 1));
        $perPage = 25;
        $threads = $provider->listThreadsByCategory((int)$category['id'], $page, $perPage);
        $total = $provider->countThreadsByCategory((int)$category['id']);
        $totalPages = (int)max(1, ceil($total / $perPage));

        $this->render('public/forum/category', [
            'title' => $category['title'] . ' — Forum',
            'category' => $category,
            'subcategories' => $subcategories,
            'breadcrumbs' => $breadcrumbs,
            'threads' => $threads,
            'page' => $page,
            'totalPages' => $totalPages,
            'canPost' => Auth::check() && Auth::hasRole($category['access_role']),
        ]);
    }

    public function thread(string $id): void
    {
        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Thread Not Found']);
            return;
        }

        if (!Auth::hasRole($thread['access_role'])) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $page = max(1, (int)$this->query('page', 1));
        $perPage = 50;
        $posts = $provider->listPosts((int)$thread['id'], $page, $perPage);
        $postCount = $provider->countPosts((int)$thread['id']);
        $totalPages = (int)max(1, ceil($postCount / $perPage));
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$thread['category_id']);

        // Post counts per author for display in sidebars
        $authorIds = array_unique(array_map(static fn(array $p): int => (int)$p['user_id'], $posts));
        $authorPostCounts = [];
        foreach ($authorIds as $uid) {
            $authorPostCounts[$uid] = (int)$this->db->fetchColumn(
                'SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND is_deleted = 0',
                [$uid]
            );
        }

        $this->render('public/forum/thread', [
            'title'       => $thread['title'] . ' — Forum',
            'thread'      => $thread,
            'breadcrumbs' => $breadcrumbs,
            'posts'       => $posts,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'canReply'    => Auth::check() && Auth::hasRole($thread['access_role']) && !$thread['is_locked'],
            'isLoggedIn'       => Auth::check(),
            'isAdmin'          => Auth::hasRole('admin'),
            'currentUserId'    => Auth::userId(),
            'authorPostCounts' => $authorPostCounts,
        ]);
    }

    public function newThreadForm(string $slug): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect('/forum');
        }

        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);

        $this->render('public/forum/new', [
            'title' => 'New Thread — ' . $category['title'],
            'category' => $category,
            'breadcrumbs' => $breadcrumbs,
            'errors' => [],
            'old' => ['title' => '', 'body_html' => ''],
        ]);
    }

    public function createThread(string $slug): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect('/forum');
        }

        $title = trim((string)$this->input('title', ''));
        $bodyHtml = trim((string)$this->input('body_html', ''));

        $errors = [];
        if ($title === '' || mb_strlen($title) < 5) {
            $errors['title'] = 'Title must be at least 5 characters.';
        }
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 10) {
            $errors['body_html'] = 'Post content must be at least 10 characters.';
        }

        if ($errors) {
            $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);
            $this->render('public/forum/new', [
                'title' => 'New Thread — ' . $category['title'],
                'category' => $category,
                'breadcrumbs' => $breadcrumbs,
                'errors' => $errors,
                'old' => ['title' => $title, 'body_html' => $bodyHtml],
            ]);
            return;
        }

        $threadId = $provider->createThread(
            (int)$category['id'],
            (int)Auth::userId(),
            $title,
            sanitise_html($bodyHtml)
        );

        $this->logActivity('create', 'forum_thread', $threadId, $title);
        Auth::flash('success', 'Thread created.');
        $this->redirect('/forum/thread/' . $threadId);
    }

    public function reply(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/forum');
        }

        if (!Auth::hasRole($thread['access_role'])) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int)$thread['is_locked'] === 1) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect('/forum/thread/' . (int)$thread['id']);
        }

        $bodyHtml = trim((string)$this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Reply cannot be empty.');
            $this->redirect('/forum/thread/' . (int)$thread['id']);
        }

        $postId = $provider->createReply((int)$thread['id'], (int)Auth::userId(), sanitise_html($bodyHtml));
        $this->logActivity('create', 'forum_post', $postId, 'Reply in thread #' . (int)$thread['id']);

        Auth::flash('success', 'Reply posted.');
        $this->redirect('/forum/thread/' . (int)$thread['id']);
    }

    // ── Edit thread title ─────────────────────────────────────────

    public function editThreadTitleForm(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Thread Not Found']);
            return;
        }

        // Only OP or admin may edit
        if ((int)$thread['user_id'] !== (int)Auth::userId() && !Auth::hasRole('admin')) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $this->render('public/forum/edit-thread-title', [
            'title'  => 'Edit Thread Title',
            'thread' => $thread,
        ]);
    }

    public function updateThreadTitle(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/forum');
        }

        if ((int)$thread['user_id'] !== (int)Auth::userId() && !Auth::hasRole('admin')) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $newTitle = trim((string)$this->input('title', ''));
        if (mb_strlen($newTitle) < 5) {
            Auth::flash('error', 'Title must be at least 5 characters.');
            $this->redirect('/forum/thread/' . (int)$id . '/edit-title');
        }

        $this->db->execute(
            'UPDATE forum_threads SET title = ?, updated_at = NOW() WHERE id = ?',
            [htmlspecialchars($newTitle, ENT_QUOTES), (int)$id]
        );
        $this->logActivity('update', 'forum_thread', (int)$id, 'Title edited: ' . $newTitle);

        Auth::flash('success', 'Thread title updated.');
        $this->redirect('/forum/thread/' . (int)$id);
    }

    // ── Forum search ──────────────────────────────────────────────

    public function search(): void
    {
        $q          = trim((string)$this->query('q', ''));
        $categoryId = (int)$this->query('category_id', 0);
        $results    = [];
        $total      = 0;

        $provider = ForumManager::provider();
        $categories = $provider->listCategories(Auth::role());

        if ($q !== '') {
            $role    = Auth::role();
            $allowed = match ($role) {
                'admin'   => ['public', 'member', 'council', 'admin'],
                'council' => ['public', 'member', 'council'],
                'member'  => ['public', 'member'],
                default   => ['public'],
            };
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));

            $params = [
                '%' . $q . '%',
                '%' . $q . '%',
            ];
            $catWhere = '';
            if ($categoryId > 0) {
                $catWhere = 'AND t.category_id = ?';
                $params[] = $categoryId;
            }
            $params = array_merge($params, $allowed);

            $results = $this->db->fetchAll(
                "SELECT t.id AS thread_id, t.title AS thread_title, t.created_at AS thread_created,
                        p.id AS post_id, p.body_html, p.created_at,
                        u.display_name AS author_name,
                        c.title AS category_title, c.slug AS category_slug
                 FROM forum_posts p
                 JOIN forum_threads t ON t.id = p.thread_id
                 JOIN forum_categories c ON c.id = t.category_id
                 JOIN users u ON u.id = p.user_id
                 WHERE (t.title LIKE ? OR p.body_html LIKE ?)
                       {$catWhere}
                       AND p.is_deleted = 0
                       AND t.is_deleted = 0
                       AND c.access_role IN ({$placeholders})
                 ORDER BY p.created_at DESC
                 LIMIT 50",
                $params
            );
            $total = count($results);
        }

        $this->render('public/forum/search', [
            'title'      => 'Forum Search',
            'q'          => $q,
            'categoryId' => $categoryId,
            'categories' => $categories,
            'results'    => $results,
            'total'      => $total,
        ]);
    }

    // ── Edit own post ─────────────────────────────────────────────

    public function editPostForm(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost((int)$id);

        if (!$post) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Post Not Found']);
            return;
        }

        // Only the post author (or admin) may edit
        if ((int)$post['user_id'] !== (int)Auth::userId() && !Auth::hasRole('admin')) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int)$post['is_locked'] === 1 && !Auth::hasRole('admin')) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect('/forum/thread/' . (int)$post['thread_id']);
        }

        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$post['category_id']);

        $this->render('public/forum/edit-post', [
            'title'       => 'Edit Post',
            'post'        => $post,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function updatePost(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost((int)$id);

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/forum');
        }

        if ((int)$post['user_id'] !== (int)Auth::userId() && !Auth::hasRole('admin')) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int)$post['is_locked'] === 1 && !Auth::hasRole('admin')) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect('/forum/thread/' . (int)$post['thread_id']);
        }

        $bodyHtml = trim((string)$this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Post content cannot be empty.');
            $this->redirect('/forum/post/' . (int)$id . '/edit');
        }

        $provider->updatePost((int)$id, sanitise_html($bodyHtml));
        $this->logActivity('update', 'forum_post', (int)$id, 'Edited post #' . (int)$id);

        Auth::flash('success', 'Post updated.');
        $this->redirect('/forum/thread/' . (int)$post['thread_id'] . '#post-' . (int)$id);
    }

    // ── Delete own post ───────────────────────────────────────────

    public function deletePost(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost((int)$id);

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/forum');
        }

        if ((int)$post['user_id'] !== (int)Auth::userId() && !Auth::hasRole('admin')) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $provider->softDeletePost((int)$id, (int)Auth::userId());
        $this->logActivity('delete', 'forum_post', (int)$id, 'Deleted post #' . (int)$id);

        Auth::flash('success', 'Post deleted.');
        $this->redirect('/forum/thread/' . (int)$post['thread_id']);
    }

    // ── Report post ───────────────────────────────────────────────

    public function reportPostForm(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost((int)$id);

        if (!$post || (int)$post['is_deleted'] === 1) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Post Not Found']);
            return;
        }

        $this->render('public/forum/report-post', [
            'title' => 'Report Post',
            'post'  => $post,
        ]);
    }

    public function reportPost(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost((int)$id);

        if (!$post || (int)$post['is_deleted'] === 1) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/forum');
        }

        $reason = trim((string)$this->input('reason', ''));
        $body   = trim((string)$this->input('body', '')) ?: null;

        if ($reason === '') {
            Auth::flash('error', 'Please select a reason for the report.');
            $this->redirect('/forum/post/' . (int)$id . '/report');
        }

        $provider->reportPost((int)$id, (int)Auth::userId(), $reason, $body);
        $this->logActivity('create', 'forum_post_report', (int)$id, 'Report: ' . $reason);

        Auth::flash('success', 'Thank you — your report has been submitted for review.');
        $this->redirect('/forum/thread/' . (int)$post['thread_id']);
    }
}

