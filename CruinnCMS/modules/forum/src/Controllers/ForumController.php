<?php

namespace Cruinn\Module\Forum\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Database;
use Cruinn\Module\Forum\Forum\ForumManager;

class ForumController extends BaseController
{
    public function index(): void
    {
        $this->render('public/forum/index', self::buildIndexViewData($this->forumBasePath()));
    }

    public function category(string $slug): void
    {
        $data = self::buildCategoryViewData($slug, $this->forumBasePath());
        if ($data === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Category Not Found']);
            return;
        }

        $this->render('public/forum/category', $data);
    }

    public function thread(string $id): void
    {
        $data = self::buildThreadViewData((int) $id, $this->forumBasePath());
        if ($data === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Thread Not Found']);
            return;
        }
        if (($data['forum_view'] ?? '') === 'forbidden') {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $this->render('public/forum/thread', $data);
    }

    public function newThreadForm(string $slug): void
    {
        $data = self::buildNewThreadViewData($slug, $this->forumBasePath());
        if ($data === null) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect(self::indexPath($this->forumBasePath()));
        }

        $this->render('public/forum/new', $data);
    }

    public function createThread(string $slug): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::roleLevel());

        if (!$category) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect(self::indexPath($basePath));
        }

        $title = trim((string) $this->input('title', ''));
        $bodyHtml = trim((string) $this->input('body_html', ''));

        $errors = [];
        if ($title === '' || mb_strlen($title) < 5) {
            $errors['title'] = 'Title must be at least 5 characters.';
        }
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 10) {
            $errors['body_html'] = 'Post content must be at least 10 characters.';
        }

        if ($errors) {
            $breadcrumbs = $provider->getCategoryBreadcrumbs((int) $category['id']);
            $this->render('public/forum/new', array_merge(self::templateGlobals($basePath), [
                'title' => 'New Thread — ' . $category['title'],
                'forum_view' => 'new',
                'category' => $category,
                'breadcrumbs' => $breadcrumbs,
                'errors' => $errors,
                'old' => ['title' => $title, 'body_html' => $bodyHtml],
            ]));
            return;
        }

        $threadId = $provider->createThread(
            (int) $category['id'],
            (int) Auth::userId(),
            $title,
            sanitise_html($bodyHtml)
        );

        $this->logActivity('create', 'forum_thread', $threadId, $title);
        Auth::flash('success', 'Thread created.');
        $this->redirect(self::threadPath($basePath, $threadId));
    }

    public function reply(string $id): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $thread = $provider->getThread((int) $id);

        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect(self::indexPath($basePath));
        }

        if (Auth::roleLevel() < self::roleSlugToLevel((string) ($thread['access_role'] ?? 'public'))) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int) ($thread['is_locked'] ?? 0) === 1) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect(self::threadPath($basePath, (int) $thread['id']));
        }

        $bodyHtml = trim((string) $this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Reply cannot be empty.');
            $this->redirect(self::threadPath($basePath, (int) $thread['id']));
        }

        $postId = $provider->createReply((int) $thread['id'], (int) Auth::userId(), sanitise_html($bodyHtml));
        $this->logActivity('create', 'forum_post', $postId, 'Reply in thread #' . (int) $thread['id']);

        Auth::flash('success', 'Reply posted.');
        $this->redirect(self::threadPath($basePath, (int) $thread['id']) . '#post-' . $postId);
    }

    public function editThreadTitleForm(string $id): void
    {
        $data = self::buildEditThreadTitleViewData((int) $id, $this->forumBasePath());
        if ($data === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Thread Not Found']);
            return;
        }
        if (($data['forum_view'] ?? '') === 'forbidden') {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $this->render('public/forum/edit-thread-title', $data);
    }

    public function updateThreadTitle(string $id): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $thread = $provider->getThread((int) $id);

        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect(self::indexPath($basePath));
        }

        if ((int) ($thread['user_id'] ?? 0) !== (int) Auth::userId() && !Auth::isAdmin()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $newTitle = trim((string) $this->input('title', ''));
        if (mb_strlen($newTitle) < 5) {
            Auth::flash('error', 'Title must be at least 5 characters.');
            $this->redirect(self::editThreadTitlePath($basePath, (int) $id));
        }

        $this->db->execute(
            'UPDATE forum_threads SET title = ?, updated_at = NOW() WHERE id = ?',
            [htmlspecialchars($newTitle, ENT_QUOTES), (int) $id]
        );
        $this->logActivity('update', 'forum_thread', (int) $id, 'Title edited: ' . $newTitle);

        Auth::flash('success', 'Thread title updated.');
        $this->redirect(self::threadPath($basePath, (int) $id));
    }

    public function search(): void
    {
        $this->render('public/forum/search', self::buildSearchViewData($this->forumBasePath()));
    }

    public function editPostForm(string $id): void
    {
        $data = self::buildEditPostViewData((int) $id, $this->forumBasePath());
        if ($data === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Post Not Found']);
            return;
        }
        if (($data['forum_view'] ?? '') === 'forbidden') {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $this->render('public/forum/edit-post', $data);
    }

    public function updatePost(string $id): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $post = $provider->getPost((int) $id);

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect(self::indexPath($basePath));
        }

        if ((int) ($post['user_id'] ?? 0) !== (int) Auth::userId() && !Auth::isAdmin()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int) ($post['is_locked'] ?? 0) === 1 && !Auth::isAdmin()) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect(self::threadPath($basePath, (int) $post['thread_id']));
        }

        $bodyHtml = trim((string) $this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Post content cannot be empty.');
            $this->redirect(self::editPostPath($basePath, (int) $id));
        }

        $provider->updatePost((int) $id, sanitise_html($bodyHtml));
        $this->logActivity('update', 'forum_post', (int) $id, 'Edited post #' . (int) $id);

        Auth::flash('success', 'Post updated.');
        $this->redirect(self::threadPath($basePath, (int) $post['thread_id']) . '#post-' . (int) $id);
    }

    public function deletePost(string $id): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $post = $provider->getPost((int) $id);

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect(self::indexPath($basePath));
        }

        if ((int) ($post['user_id'] ?? 0) !== (int) Auth::userId() && !Auth::isAdmin()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $provider->softDeletePost((int) $id, (int) Auth::userId());
        $this->logActivity('delete', 'forum_post', (int) $id, 'Deleted post #' . (int) $id);

        Auth::flash('success', 'Post deleted.');
        $this->redirect(self::threadPath($basePath, (int) $post['thread_id']));
    }

    public function reportPostForm(string $id): void
    {
        $data = self::buildReportPostViewData((int) $id, $this->forumBasePath());
        if ($data === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Post Not Found']);
            return;
        }
        if (($data['forum_view'] ?? '') === 'forbidden') {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $this->render('public/forum/report-post', $data);
    }

    public function reportPost(string $id): void
    {
        Auth::requireLogin();

        $basePath = $this->forumBasePath();
        $provider = ForumManager::provider();
        $post = $provider->getPost((int) $id);

        if (!$post || (int) ($post['is_deleted'] ?? 0) === 1) {
            Auth::flash('error', 'Post not found.');
            $this->redirect(self::indexPath($basePath));
        }

        $reason = trim((string) $this->input('reason', ''));
        $body = trim((string) $this->input('body', '')) ?: null;

        if ($reason === '') {
            Auth::flash('error', 'Please select a reason for the report.');
            $this->redirect(self::reportPostPath($basePath, (int) $id));
        }

        $provider->reportPost((int) $id, (int) Auth::userId(), $reason, $body);
        $this->logActivity('create', 'forum_post_report', (int) $id, 'Report: ' . $reason);

        Auth::flash('success', 'Thank you — your report has been submitted for review.');
        $this->redirect(self::threadPath($basePath, (int) $post['thread_id']));
    }

    public static function resolvePublicPath(string $path, array $settings = [], string $moduleSlug = 'forum'): ?array
    {
        $db = Database::getInstance();
        $listPageId = self::listPageId($settings, $db);
        if ($listPageId <= 0) {
            return null;
        }

        $listPage = $db->fetch('SELECT id, slug, status FROM pages_index WHERE id = ? LIMIT 1', [$listPageId]);
        if (!$listPage || ($listPage['status'] ?? '') !== 'published') {
            return null;
        }

        $baseSlug = trim((string) ($listPage['slug'] ?? ''), '/');
        if ($baseSlug === '') {
            return null;
        }

        $normalisedPath = trim($path, '/');
        if ($normalisedPath !== $baseSlug && !str_starts_with($normalisedPath, $baseSlug . '/')) {
            return null;
        }

        $basePath = self::normalisePublicBasePath($baseSlug);
        $relativePath = trim(substr($normalisedPath, strlen($baseSlug)), '/');

        $data = match (true) {
            $relativePath === '' => self::buildIndexViewData($basePath),
            $relativePath === 'search' => self::buildSearchViewData($basePath),
            preg_match('#^thread/(\d+)$#', $relativePath, $m) === 1 => self::buildThreadViewData((int) $m[1], $basePath),
            preg_match('#^thread/(\d+)/edit-title$#', $relativePath, $m) === 1 => self::buildEditThreadTitleViewData((int) $m[1], $basePath),
            preg_match('#^post/(\d+)/edit$#', $relativePath, $m) === 1 => self::buildEditPostViewData((int) $m[1], $basePath),
            preg_match('#^post/(\d+)/report$#', $relativePath, $m) === 1 => self::buildReportPostViewData((int) $m[1], $basePath),
            preg_match('#^([a-zA-Z0-9_.+%-]+)/new$#', $relativePath, $m) === 1 => self::buildNewThreadViewData($m[1], $basePath),
            preg_match('#^([a-zA-Z0-9_.+%-]+)$#', $relativePath, $m) === 1 => self::buildCategoryViewData($m[1], $basePath),
            default => null,
        };

        if (!is_array($data)) {
            return null;
        }

        return [
            'page_id' => $listPageId,
            'data' => $data,
        ];
    }

    public static function contentProviderForumContent(array $settings = [], array $context = []): array
    {
        $basePath = trim((string) ($context['forum_base_path'] ?? self::publicBasePath(null, $settings)));
        $mode = trim((string) ($settings['mode'] ?? ''));

        if ($mode === 'subject-thread') {
            return self::buildSubjectThreadViewData(self::resolveSubjectId($settings, $context), $basePath, $context);
        }

        if (!isset($context['forum_view'])) {
            return self::buildIndexViewData($basePath);
        }

        return array_merge(self::templateGlobals($basePath), $context);
    }

    public static function publicBasePath(?Database $db = null, array $settings = []): string
    {
        $db ??= Database::getInstance();
        $listPageId = self::listPageId($settings, $db);
        if ($listPageId <= 0) {
            return '';
        }

        $slug = (string) ($db->fetchColumn('SELECT slug FROM pages_index WHERE id = ? LIMIT 1', [$listPageId]) ?: '');
        return self::normalisePublicBasePath($slug);
    }

    private static function buildIndexViewData(string $basePath): array
    {
        $categories = ForumManager::provider()->listCategoriesHierarchical(Auth::roleLevel());

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'Forum',
            'forum_view' => 'index',
            'categories' => $categories,
        ]);
    }

    private static function buildCategoryViewData(string $slug, string $basePath): ?array
    {
        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::roleLevel());
        if (!$category) {
            return null;
        }

        $subcategories = $provider->getSubcategories((int) $category['id'], Auth::roleLevel());
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int) $category['id']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $threads = $provider->listThreadsByCategory((int) $category['id'], $page, $perPage);
        $total = $provider->countThreadsByCategory((int) $category['id']);
        $totalPages = (int) max(1, ceil($total / $perPage));

        return array_merge(self::templateGlobals($basePath), [
            'title' => $category['title'] . ' — Forum',
            'forum_view' => 'category',
            'category' => $category,
            'subcategories' => $subcategories,
            'breadcrumbs' => $breadcrumbs,
            'threads' => $threads,
            'page' => $page,
            'totalPages' => $totalPages,
            'canPost' => Auth::check() && Auth::roleLevel() >= self::roleSlugToLevel((string) ($category['access_role'] ?? 'public')),
        ]);
    }

    private static function buildThreadViewData(int $threadId, string $basePath): ?array
    {
        $provider = ForumManager::provider();
        $thread = $provider->getThread($threadId);
        if (!$thread) {
            return null;
        }

        if (Auth::roleLevel() < self::roleSlugToLevel((string) ($thread['access_role'] ?? 'public'))) {
            return [
                'title' => 'Access Denied',
                'forum_view' => 'forbidden',
                'forum_base_path' => $basePath,
                'forum_action_base_path' => '/forum',
                'forum_search_url' => self::searchPath($basePath),
            ];
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $posts = $provider->listPosts((int) $thread['id'], $page, $perPage);
        $postCount = $provider->countPosts((int) $thread['id']);
        $totalPages = (int) max(1, ceil($postCount / $perPage));
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int) $thread['category_id']);

        $authorIds = array_unique(array_map(static fn(array $post): int => (int) ($post['user_id'] ?? 0), $posts));
        $authorPostCounts = [];
        $db = Database::getInstance();
        foreach ($authorIds as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $authorPostCounts[$uid] = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND is_deleted = 0',
                [$uid]
            );
        }

        return array_merge(self::templateGlobals($basePath), [
            'title' => $thread['title'] . ' — Forum',
            'forum_view' => 'thread',
            'thread' => $thread,
            'breadcrumbs' => $breadcrumbs,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'canReply' => Auth::check() && Auth::roleLevel() >= self::roleSlugToLevel((string) ($thread['access_role'] ?? 'public')) && empty($thread['is_locked']),
            'isLoggedIn' => Auth::check(),
            'isAdmin' => Auth::isAdmin(),
            'currentUserId' => Auth::userId(),
            'authorPostCounts' => $authorPostCounts,
        ]);
    }

    private static function buildNewThreadViewData(string $slug, string $basePath): ?array
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::roleLevel());
        if (!$category) {
            return null;
        }

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'New Thread — ' . $category['title'],
            'forum_view' => 'new',
            'category' => $category,
            'breadcrumbs' => $provider->getCategoryBreadcrumbs((int) $category['id']),
            'errors' => [],
            'old' => ['title' => '', 'body_html' => ''],
        ]);
    }

    private static function buildEditThreadTitleViewData(int $threadId, string $basePath): ?array
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $thread = $provider->getThread($threadId);
        if (!$thread) {
            return null;
        }
        if ((int) ($thread['user_id'] ?? 0) !== (int) Auth::userId() && !Auth::isAdmin()) {
            return [
                'title' => 'Access Denied',
                'forum_view' => 'forbidden',
                'forum_base_path' => $basePath,
                'forum_action_base_path' => '/forum',
                'forum_search_url' => self::searchPath($basePath),
            ];
        }

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'Edit Thread Title',
            'forum_view' => 'edit-thread-title',
            'thread' => $thread,
        ]);
    }

    private static function buildEditPostViewData(int $postId, string $basePath): ?array
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost($postId);
        if (!$post) {
            return null;
        }
        if ((int) ($post['user_id'] ?? 0) !== (int) Auth::userId() && !Auth::isAdmin()) {
            return [
                'title' => 'Access Denied',
                'forum_view' => 'forbidden',
                'forum_base_path' => $basePath,
                'forum_action_base_path' => '/forum',
                'forum_search_url' => self::searchPath($basePath),
            ];
        }
        if ((int) ($post['is_locked'] ?? 0) === 1 && !Auth::isAdmin()) {
            return [
                'title' => 'Access Denied',
                'forum_view' => 'forbidden',
                'forum_base_path' => $basePath,
                'forum_action_base_path' => '/forum',
                'forum_search_url' => self::searchPath($basePath),
            ];
        }

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'Edit Post',
            'forum_view' => 'edit-post',
            'post' => $post,
            'breadcrumbs' => $provider->getCategoryBreadcrumbs((int) $post['category_id']),
        ]);
    }

    private static function buildReportPostViewData(int $postId, string $basePath): ?array
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $post = $provider->getPost($postId);
        if (!$post || (int) ($post['is_deleted'] ?? 0) === 1) {
            return null;
        }

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'Report Post',
            'forum_view' => 'report-post',
            'post' => $post,
        ]);
    }

    private static function buildSearchViewData(string $basePath): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $results = [];
        $total = 0;

        $provider = ForumManager::provider();
        $categories = $provider->listCategories(Auth::roleLevel());
        $db = Database::getInstance();

        if ($q !== '') {
            $level = Auth::roleLevel();
            $allowed = $level >= 100 ? ['public', 'member', 'editor', 'council', 'admin'] :
                ($level >= 50 ? ['public', 'member', 'editor', 'council'] :
                ($level >= 20 ? ['public', 'member', 'editor'] :
                ($level >= 10 ? ['public', 'member'] : ['public'])));
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));

            $params = ['%' . $q . '%', '%' . $q . '%'];
            $catWhere = '';
            if ($categoryId > 0) {
                $catWhere = 'AND t.category_id = ?';
                $params[] = $categoryId;
            }
            $params = array_merge($params, $allowed);

            $results = $db->fetchAll(
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

        return array_merge(self::templateGlobals($basePath), [
            'title' => 'Forum Search',
            'forum_view' => 'search',
            'q' => $q,
            'categoryId' => $categoryId,
            'categories' => $categories,
            'results' => $results,
            'total' => $total,
        ]);
    }

    private static function buildSubjectThreadViewData(int $subjectId, string $basePath, array $context = []): array
    {
        $discussionTitle = trim((string) ($context['discussion_title'] ?? 'Discussion'));

        if ($subjectId <= 0) {
            return array_merge(self::templateGlobals($basePath), [
                'title' => $discussionTitle,
                'forum_view' => 'subject-empty',
                'discussion_title' => $discussionTitle,
            ]);
        }

        $thread = ForumManager::provider()->getThreadBySubjectId($subjectId, Auth::roleLevel());
        if (!$thread) {
            return array_merge(self::templateGlobals($basePath), [
                'title' => $discussionTitle,
                'forum_view' => 'subject-empty',
                'discussion_title' => $discussionTitle,
            ]);
        }

        $data = self::buildThreadViewData((int) $thread['id'], $basePath);
        if (!is_array($data)) {
            return array_merge(self::templateGlobals($basePath), [
                'title' => $discussionTitle,
                'forum_view' => 'subject-empty',
                'discussion_title' => $discussionTitle,
            ]);
        }

        $data['discussion_title'] = $discussionTitle;
        return $data;
    }

    private function forumBasePath(): string
    {
        return self::publicBasePath($this->db);
    }

    private static function resolveSubjectId(array $settings = [], array $context = []): int
    {
        $subjectId = (int) ($settings['subject_id'] ?? 0);
        if ($subjectId > 0) {
            return $subjectId;
        }

        $subjectId = (int) ($context['subject_id'] ?? 0);
        if ($subjectId > 0) {
            return $subjectId;
        }

        // Articles and events no longer carry subject_id — look up via bridge.
        $db = \Cruinn\Database::getInstance();

        $article = $context['article'] ?? null;
        if (is_array($article) && !empty($article['id'])) {
            $sid = (int) ($db->fetchColumn(
                'SELECT subject_id FROM subject_content WHERE item_type = ? AND item_id = ? LIMIT 1',
                ['article', (int) $article['id']]
            ) ?: 0);
            if ($sid > 0) return $sid;
        }

        $event = $context['event'] ?? null;
        if (is_array($event) && !empty($event['id'])) {
            $sid = (int) ($db->fetchColumn(
                'SELECT subject_id FROM subject_content WHERE item_type = ? AND item_id = ? LIMIT 1',
                ['event', (int) $event['id']]
            ) ?: 0);
            if ($sid > 0) return $sid;
        }

        return 0;
    }

    private static function templateGlobals(string $basePath): array
    {
        return [
            'forum_base_path' => $basePath,
            'forum_action_base_path' => '/forum',
            'forum_search_url' => self::searchPath($basePath),
        ];
    }

    private static function listPageId(array $settings = [], ?Database $db = null): int
    {
        if (!empty($settings['forum_list_page_id'])) {
            return max(0, (int) $settings['forum_list_page_id']);
        }

        $db ??= Database::getInstance();
        $raw = $db->fetchColumn('SELECT settings FROM module_config WHERE slug = ? LIMIT 1', ['forum']);
        $decoded = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        return max(0, (int) ($decoded['forum_list_page_id'] ?? 0));
    }

    private static function roleSlugToLevel(string $slug): int
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

    private static function normalisePublicBasePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }

    private static function indexPath(string $basePath): string
    {
        return $basePath !== '' ? $basePath : '/';
    }

    private static function searchPath(string $basePath): string
    {
        return rtrim(self::indexPath($basePath), '/') . '/search';
    }

    private static function categoryPath(string $basePath, string $slug): string
    {
        return rtrim(self::indexPath($basePath), '/') . '/' . ltrim($slug, '/');
    }

    private static function newThreadPath(string $basePath, string $slug): string
    {
        return rtrim(self::categoryPath($basePath, $slug), '/') . '/new';
    }

    private static function threadPath(string $basePath, int $threadId): string
    {
        return rtrim(self::indexPath($basePath), '/') . '/thread/' . $threadId;
    }

    private static function editThreadTitlePath(string $basePath, int $threadId): string
    {
        return self::threadPath($basePath, $threadId) . '/edit-title';
    }

    private static function editPostPath(string $basePath, int $postId): string
    {
        return rtrim(self::indexPath($basePath), '/') . '/post/' . $postId . '/edit';
    }

    private static function reportPostPath(string $basePath, int $postId): string
    {
        return rtrim(self::indexPath($basePath), '/') . '/post/' . $postId . '/report';
    }
}

