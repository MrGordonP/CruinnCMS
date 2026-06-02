<?php
/**
 * Cruinn CMS — Article Controller
 *
 * Public-facing blog listing and detail pages,
 * plus admin CRUD with block editor support.
 */

namespace Cruinn\Module\Blog\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Database;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\SubjectThreadProvisionService;

class ArticleController extends BaseController
{
    /**
     * GET /blog — List published blog posts.
     */
    public function index(): void
    {
        $basePath = $this->adminBlogBasePath() ?: '/blog';
        $data = self::buildBlogListViewData($this->db, $basePath, ['per_page' => 10]);

        $this->render('public/blog.list', $data);
    }

    /**
     * GET /blog/{slug} — Show a single blog post.
     */
    public function show(string $slug): void
    {
        $article = self::findPublishedArticleBySlug($this->db, $slug);

        if (!$article) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Article Not Found']);
            return;
        }

        $basePath = $this->adminBlogBasePath() ?: '/blog';
        $this->render('public/blog.post', self::buildBlogPostViewData($article, $this->renderArticleBlocks((int) $article['id']), $basePath));
    }

    public function dashboard(): void
    {
        Auth::requireAdmin();

        $settings = self::readBlogSettings($this->db);
        $profileCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM blog_profiles');
        $recentArticles = $this->db->fetchAll(
            'SELECT id, title, status, published_at, updated_at
             FROM articles
             ORDER BY updated_at DESC
             LIMIT 8'
        );

        $draftCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM articles WHERE status = ?', ['draft']);
        $publishedCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM articles WHERE status = ?', ['published']);
        $listPage = !empty($settings['list_page_id'])
            ? $this->db->fetch('SELECT id, title, slug FROM pages_index WHERE id = ? LIMIT 1', [(int) $settings['list_page_id']])
            : null;

        $this->renderAdmin('admin/blog/dashboard', [
            'title' => 'Blog',
            'settings' => $settings,
            'recentArticles' => $recentArticles,
            'draftCount' => $draftCount,
            'publishedCount' => $publishedCount,
            'profileCount' => $profileCount,
            'listPage' => $listPage,
            'breadcrumbs' => [['Admin', '/admin'], ['Blog']],
        ]);
    }

    public function settings(): void
    {
        Auth::requireAdmin();

        $settings = self::readBlogSettings($this->db);
        $pages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index WHERE canvas_type = 'content' ORDER BY title ASC"
        );

        $this->renderAdmin('admin/blog/settings', [
            'title' => 'Blog Settings',
            'settings' => $settings,
            'pages' => $pages,
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Settings']],
        ]);
    }

    public function saveSettings(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $listPageId = max(0, (int) $this->input('list_page_id', 0));
        $postPageId = max(0, (int) $this->input('post_page_id', 0));
        $defaultPostsPerPage = self::normalisePerPage($this->input('default_posts_per_page', 10));
        $showReturnToList = $this->input('show_return_to_list', '0') === '1' ? '1' : '0';
        $showPostNavigation = $this->input('show_post_navigation', '0') === '1' ? '1' : '0';

        $this->upsertBlogSetting('blog.list_page_id', $listPageId > 0 ? (string) $listPageId : null);
        $this->upsertBlogSetting('blog.post_page_id', $postPageId > 0 ? (string) $postPageId : null);
        $this->upsertBlogSetting('blog.default_posts_per_page', (string) $defaultPostsPerPage);
        $this->upsertBlogSetting('blog.show_return_to_list', $showReturnToList);
        $this->upsertBlogSetting('blog.show_post_navigation', $showPostNavigation);

        Auth::flash('success', 'Blog settings saved.');
        $this->redirect('/admin/blog/settings');
    }

    public function profiles(): void
    {
        Auth::requireAdmin();

        $profiles = self::readBlogProfiles($this->db);

        $this->renderAdmin('admin/blog/profiles/index', [
            'title' => 'Blog Profiles',
            'profiles' => $profiles,
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Profiles']],
        ]);
    }

    public function profileNew(): void
    {
        Auth::requireAdmin();

        $this->renderAdmin('admin/blog/profiles/edit', [
            'title' => 'New Blog Profile',
            'profile' => null,
            'errors' => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Profiles', '/admin/blog/profiles'], ['New Profile']],
        ]);
    }

    public function profileCreate(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        [$profile, $errors] = $this->normaliseBlogProfileInput();
        if ($profile['slug'] !== '' && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM blog_profiles WHERE slug = ?', [$profile['slug']]) > 0) {
            $errors['slug'] = 'A blog profile with this slug already exists.';
        }

        if ($errors) {
            $this->renderAdmin('admin/blog/profiles/edit', [
                'title' => 'New Blog Profile',
                'profile' => $profile,
                'errors' => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Profiles', '/admin/blog/profiles'], ['New Profile']],
            ]);
            return;
        }

        $this->db->insert('blog_profiles', [
            'name' => $profile['name'],
            'slug' => $profile['slug'],
            'description' => $profile['description'],
            'display_mode' => $profile['display_mode'],
            'posts_per_page' => $profile['posts_per_page'],
            'show_return_to_list' => $profile['show_return_to_list'] ? 1 : 0,
            'show_post_navigation' => $profile['show_post_navigation'] ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', 'Blog profile created.');
        $this->redirect('/admin/blog/profiles');
    }

    public function profileEdit(string $id): void
    {
        Auth::requireAdmin();

        $profile = self::readBlogProfile($this->db, (int) $id);
        if (!$profile) {
            Auth::flash('error', 'Blog profile not found.');
            $this->redirect('/admin/blog/profiles');
        }

        $this->renderAdmin('admin/blog/profiles/edit', [
            'title' => 'Edit Blog Profile',
            'profile' => $profile,
            'errors' => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Profiles', '/admin/blog/profiles'], [$profile['name']]],
        ]);
    }

    public function profileUpdate(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $profileId = (int) $id;
        $existingProfile = self::readBlogProfile($this->db, $profileId);
        if (!$existingProfile) {
            Auth::flash('error', 'Blog profile not found.');
            $this->redirect('/admin/blog/profiles');
        }

        [$profile, $errors] = $this->normaliseBlogProfileInput($existingProfile);
        if ($profile['slug'] !== '' && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM blog_profiles WHERE slug = ? AND id != ?', [$profile['slug'], $profileId]) > 0) {
            $errors['slug'] = 'A blog profile with this slug already exists.';
        }

        if ($errors) {
            $profile['id'] = $profileId;
            $this->renderAdmin('admin/blog/profiles/edit', [
                'title' => 'Edit Blog Profile',
                'profile' => $profile,
                'errors' => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Profiles', '/admin/blog/profiles'], [$existingProfile['name']]],
            ]);
            return;
        }

        $this->db->update('blog_profiles', [
            'name' => $profile['name'],
            'slug' => $profile['slug'],
            'description' => $profile['description'],
            'display_mode' => $profile['display_mode'],
            'posts_per_page' => $profile['posts_per_page'],
            'show_return_to_list' => $profile['show_return_to_list'] ? 1 : 0,
            'show_post_navigation' => $profile['show_post_navigation'] ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$profileId]);

        Auth::flash('success', 'Blog profile updated.');
        $this->redirect('/admin/blog/profiles');
    }

    public function profileDelete(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $profile = self::readBlogProfile($this->db, (int) $id);
        if (!$profile) {
            Auth::flash('error', 'Blog profile not found.');
            $this->redirect('/admin/blog/profiles');
        }

        $this->db->delete('blog_profiles', 'id = ?', [(int) $id]);

        Auth::flash('success', 'Blog profile deleted.');
        $this->redirect('/admin/blog/profiles');
    }

    // ══════════════════════════════════════════════════════════════
    //  ADMIN CRUD
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/blog — List all blog posts with search + filters.
     */
    public function adminList(): void
    {
        $search  = $this->query('q', '');
        $status  = $this->query('status', '');
        $subject = $this->query('subject', '');
        $page    = max(1, (int) $this->query('page', 1));
        $perPage = 25;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $where[]  = 'a.status = ?';
            $params[] = $status;
        }
        if ($subject !== '') {
            $where[]  = 'EXISTS (SELECT 1 FROM subject_content sc WHERE sc.item_type = \'article\' AND sc.item_id = a.id AND sc.subject_id = ?)';
            $params[] = $subject;
        }

        $whereSQL   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total      = $this->db->fetchColumn("SELECT COUNT(*) FROM articles a {$whereSQL}", $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset     = ($page - 1) * $perPage;

        $articles = $this->db->fetchAll(
            "SELECT a.*, u.display_name as author_name,
                    GROUP_CONCAT(s.title ORDER BY s.title SEPARATOR ', ') AS subject_titles
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subject_content sc2 ON sc2.item_type = 'article' AND sc2.item_id = a.id
             LEFT JOIN subjects s ON s.id = sc2.subject_id
             {$whereSQL}
             GROUP BY a.id
             ORDER BY a.updated_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC',
            ['archived']
        );

        $this->renderAdmin('admin/articles/index', [
            'title'       => 'Blog',
            'articles'    => $articles,
            'blogBasePath'=> $this->adminBlogBasePath(),
            'subjects'    => $subjects,
            'search'      => $search,
            'status'      => $status,
            'subjectId'   => $subject,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Posts']],
        ]);
    }

    /**
     * GET /admin/blog/new — New blog post form.
     */
    public function adminNew(): void
    {
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC',
            ['archived']
        );

        $this->renderAdmin('admin/articles/edit', [
            'title'             => 'New Blog Post',
            'article'           => null,
            'blocks'            => [],
            'blogBasePath'      => $this->adminBlogBasePath(),
            'subjects'          => $subjects,
            'articleSubjectIds' => [],
            'errors'            => [],
            'breadcrumbs'       => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Posts', '/admin/blog/posts'], ['New Blog Post']],
        ]);
    }

    /**
     * POST /admin/blog — Create a new blog post.
     */
    public function adminCreate(): void
    {
        $errors = $this->validateRequired(['title' => 'Title']);

        $manualSlug = trim($this->input('slug', ''));
        $slug = $manualSlug !== ''
            ? $this->sanitiseSlug($manualSlug)
            : $this->generateTimestampSlug('articles');

        $existing = $this->db->fetchColumn('SELECT COUNT(*) FROM articles WHERE slug = ?', [$slug]);
        if ($existing) {
            $errors['slug'] = 'An article with this URL slug already exists.';
        }

        if ($errors) {
            $subjects = $this->db->fetchAll(
                'SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC',
                ['archived']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'             => 'New Blog Post',
                'article'           => $_POST,
                'blocks'            => [],
                'blogBasePath'      => $this->adminBlogBasePath(),
                'subjects'          => $subjects,
                'articleSubjectIds' => array_map('intval', (array) ($_POST['subject_ids'] ?? [])),
                'errors'            => $errors,
                'breadcrumbs'       => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['New Blog Post']],
            ]);
            return;
        }

        $status      = $this->input('status', 'draft');
        $publishedAt = $this->input('published_at') ?: null;

        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $subjectIds = array_filter(array_map('intval', (array) ($_POST['subject_ids'] ?? [])));

        $id = $this->db->insert('articles', [
            'title'          => $this->input('title'),
            'slug'           => $slug,
            'excerpt'        => $this->input('excerpt', ''),
            'featured_image' => $this->input('featured_image', ''),
            'author_id'      => Auth::userId(),
            'status'         => $status,
            'published_at'   => $publishedAt,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Sync subject associations
        foreach ($subjectIds as $sid) {
            try {
                $this->db->query(
                    'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                    [$sid, 'article', (int) $id]
                );
            } catch (\Throwable) {}
        }

        if ($status === 'published') {
            $this->provisionForumThreadForArticle(
                (int) $id,
                (int) ($subjectIds[0] ?? 0),
                (string) $this->input('title'),
                $slug,
                (string) $this->input('excerpt', ''),
                (int) Auth::userId()
            );
        }

        $this->logActivity('create', 'article', (int) $id, $this->input('title'));
        Auth::flash('success', 'Blog post created. Now add some content blocks.');
        $this->redirect('/admin/blog/editor/' . $id . '/edit');
    }

    /**
     * GET /admin/blog/{id}/edit — Edit blog post metadata and blocks.
     */
    public function adminEdit(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Blog post not found.');
            $this->redirect('/admin/blog/posts');
        }

        $blocks   = $this->getArticleBlocks((int) $id);
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC',
            ['archived']
        );
        $articleSubjectIds = array_column(
            $this->db->fetchAll('SELECT subject_id FROM subject_content WHERE item_type = ? AND item_id = ?', ['article', (int) $id]),
            'subject_id'
        );

        $this->renderAdmin('admin/articles/edit', [
            'title'             => 'Edit: ' . $article['title'],
            'article'           => $article,
            'blocks'            => $blocks,
            'blogBasePath'      => $this->adminBlogBasePath(),
            'subjects'          => $subjects,
            'articleSubjectIds' => $articleSubjectIds,
            'errors'            => [],
            'breadcrumbs'       => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Posts', '/admin/blog/posts'], [$article['title']]],
        ]);
    }

    /**
     * POST /admin/blog/{id} — Update blog post metadata.
     */
    public function adminUpdate(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Blog post not found.');
            $this->redirect('/admin/blog/posts');
        }

        $errors = $this->validateRequired(['title' => 'Title']);
        $manualSlug = trim((string) $this->input('slug', ''));
        $slug = $manualSlug !== ''
            ? $this->sanitiseSlug($manualSlug)
            : (string) ($article['slug'] ?? '');

        if ($slug === '') {
            $slug = $this->generateTimestampSlug('articles');
        }

        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM articles WHERE slug = ? AND id != ?',
            [$slug, $id]
        );
        if ($existing) {
            $errors['slug'] = 'An article with this URL slug already exists.';
        }

        if ($errors) {
            $blocks   = $this->getArticleBlocks((int) $id);
            $subjects = $this->db->fetchAll(
                'SELECT id, title FROM subjects WHERE status != ? ORDER BY title ASC',
                ['archived']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'             => 'Edit: ' . $article['title'],
                'article'           => array_merge($article, $_POST),
                'blocks'            => $blocks,
                'blogBasePath'      => $this->adminBlogBasePath(),
                'subjects'          => $subjects,
                'articleSubjectIds' => array_map('intval', (array) ($_POST['subject_ids'] ?? [])),
                'errors'            => $errors,
                'breadcrumbs'       => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['Posts', '/admin/blog/posts'], [$article['title']]],
            ]);
            return;
        }

        $status      = $this->input('status', 'draft');
        $publishedAt = $this->input('published_at') ?: null;

        if ($status === 'published' && !$publishedAt && empty($article['published_at'])) {
            $publishedAt = date('Y-m-d H:i:s');
        } elseif (!$publishedAt) {
            $publishedAt = $article['published_at'];
        }

        $subjectIds = array_filter(array_map('intval', (array) ($_POST['subject_ids'] ?? [])));

        $this->db->update('articles', [
            'title'          => $this->input('title'),
            'slug'           => $slug,
            'excerpt'        => $this->input('excerpt', ''),
            'featured_image' => $this->input('featured_image', ''),
            'status'         => $status,
            'published_at'   => $publishedAt,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Sync subject associations — replace all existing for this article
        $this->db->query('DELETE FROM subject_content WHERE item_type = ? AND item_id = ?', ['article', (int) $id]);
        foreach ($subjectIds as $sid) {
            try {
                $this->db->query(
                    'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                    [$sid, 'article', (int) $id]
                );
            } catch (\Throwable) {}
        }

        if ($status === 'published') {
            $this->provisionForumThreadForArticle(
                (int) $id,
                (int) ($subjectIds[0] ?? 0),
                (string) $this->input('title'),
                $slug,
                (string) $this->input('excerpt', ''),
                (int) ($article['author_id'] ?? Auth::userId())
            );
        }

        $this->logActivity('update', 'article', (int) $id, $this->input('title'));
        Auth::flash('success', 'Blog post updated.');
        $this->redirect("/admin/blog/posts/{$id}/edit");
    }

    /**
     * POST /admin/blog/{id}/delete — Delete a blog post and its blocks.
     */
    public function adminDelete(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Blog post not found.');
            $this->redirect('/admin/blog/posts');
        }

        $this->db->transaction(function () use ($id, $article) {
            $this->db->delete('article_blocks', 'article_id = ?', [$id]);
            $this->db->delete('articles', 'id = ?', [$id]);
            $this->logActivity('delete', 'article', (int) $id, $article['title']);
        });

        Auth::flash('success', 'Blog post deleted.');
        $this->redirect('/admin/blog/posts');
    }

    /**
     * Module content provider for blog listing blocks.
     * Returns template data consumed by templates/public/articles/module-content/list.php.
     */
    public static function contentProviderBlogList(array $settings = [], array $context = []): array
    {
        $settings = self::applyBlogProfileSettings($settings);
        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['blog_base_path'] ?? '/blog')));

        if (!empty($context['articles']) && is_array($context['articles'])) {
            $articles = self::hydrateArticlePreviewData(Database::getInstance(), $context['articles']);
            $articles = self::attachPublicUrls($articles, $basePath);
            return [
                'articles'   => $articles,
                'page'       => (int) ($context['page'] ?? 1),
                'totalPages' => (int) ($context['totalPages'] ?? 1),
                'blog_base_path' => $basePath,
            ];
        }

        $db = Database::getInstance();
        return self::buildBlogListViewData($db, $basePath, $settings);
    }

    public static function contentProviderBlogContent(array $settings = [], array $context = []): array
    {
        $settings = self::applyBlogProfileSettings($settings);
        $mode = strtolower(trim((string) ($settings['mode'] ?? 'both')));
        if (!in_array($mode, ['list', 'post', 'both'], true)) {
            $mode = 'both';
        }

        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['blog_base_path'] ?? '/blog')));
        $listData = [];
        $postData = [];
        $hasArticle = !empty($context['article']) && is_array($context['article']);
        $showList = $mode === 'list' || ($mode === 'both' && !$hasArticle);
        $showPost = $mode === 'post' || ($mode === 'both' && $hasArticle);

        if ($showList) {
            $listData = self::contentProviderBlogList(array_merge($settings, ['base_path' => $basePath]), $context);
        }

        if ($showPost && $hasArticle) {
            $postData = self::contentProviderBlogPost(array_merge($settings, ['base_path' => $basePath]), $context);
        }

        return array_merge($listData, $postData, [
            'show_list' => $showList,
            'show_post' => $showPost && $hasArticle,
            'blog_base_path' => $basePath,
        ]);
    }

    /**
     * Module content provider for a single blog post block.
     */
    public static function contentProviderBlogPost(array $settings = [], array $context = []): array
    {
        $article = $context['article'] ?? null;
        if (!is_array($article) || empty($article['id'])) {
            return [];
        }

        $settings = self::applyBlogProfileSettings($settings);
        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['blog_base_path'] ?? '/blog')));

        $bodyHtml = (string) ($context['body_html'] ?? '');
        if ($bodyHtml === '') {
            $controller = new self();
            $bodyHtml = $controller->renderArticleBlocks((int) $article['id']);
        }

        return self::buildBlogPostViewData($article, $bodyHtml, $basePath, $settings);
    }

    public static function resolvePublicPath(string $path, array $settings = [], string $moduleSlug = 'blog'): ?array
    {
        $db = Database::getInstance();
        $blogSettings = self::readBlogSettings($db);
        $listPageId = (int) ($blogSettings['list_page_id'] ?? 0);
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
        $basePath = self::normalisePublicBasePath($baseSlug);

        if ($normalisedPath === $baseSlug) {
            return [
                'page_id' => $listPageId,
                'data' => self::buildBlogListViewData($db, $basePath, $settings),
            ];
        }

        $prefix = $baseSlug . '/';
        if (!str_starts_with($normalisedPath, $prefix)) {
            return null;
        }

        $articleSlug = substr($normalisedPath, strlen($prefix));
        if ($articleSlug === '' || str_contains($articleSlug, '/')) {
            return null;
        }

        $article = self::findPublishedArticleBySlug($db, $articleSlug);
        if (!$article) {
            return null;
        }

        $controller = new self();
        $bodyHtml = $controller->renderArticleBlocks((int) $article['id']);
        $postPageId = (int) ($blogSettings['post_page_id'] ?? 0);

        return [
            'page_id' => $postPageId > 0 ? $postPageId : $listPageId,
            'data' => self::buildBlogPostViewData($article, $bodyHtml, $basePath, $settings),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function adminBlogBasePath(): string
    {
        $settings = self::readBlogSettings($this->db);
        $listPageId = (int) ($settings['list_page_id'] ?? 0);
        if ($listPageId <= 0) {
            return '';
        }

        $slug = (string) ($this->db->fetchColumn('SELECT slug FROM pages_index WHERE id = ? LIMIT 1', [$listPageId]) ?: '');
        return self::normalisePublicBasePath($slug);
    }

    private function generateTimestampSlug(string $table, string $column = 'slug'): string
    {
        $prefix = date('Y-m-d-H-i-s');
        $sequence = 1;

        do {
            $slug = sprintf('%s-%02d', $prefix, $sequence);
            $exists = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?",
                [$slug]
            );
            $sequence++;
        } while ($exists > 0);

        return $slug;
    }

    private function provisionForumThreadForArticle(
        int $articleId,
        int $subjectId,
        string $title,
        string $slug,
        string $excerpt,
        int $authorId
    ): void {
        if ($articleId <= 0 || $subjectId <= 0) {
            return;
        }

        try {
            (new SubjectThreadProvisionService($this->db))->ensurePublishedContentThread(
                'article',
                $articleId,
                $subjectId,
                $title,
                $slug,
                $excerpt,
                $authorId
            );
        } catch (\Throwable $e) {
            error_log('Article forum-thread provisioning failed for article #' . $articleId . ': ' . $e->getMessage());
        }
    }

    private static function buildBlogListViewData(Database $db, string $basePath, array $settings = []): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $blogSettings = self::readBlogSettings($db);
        $perPage = self::normalisePerPage($settings['per_page'] ?? ($blogSettings['default_posts_per_page'] ?? 10));
        $offset = ($page - 1) * $perPage;

        $articles = $db->fetchAll(
            "SELECT a.*, u.display_name as author_name, s.title as subject_title
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subject_content sc ON sc.item_type = 'article' AND sc.item_id = a.id
             LEFT JOIN subjects s ON s.id = sc.subject_id
             WHERE a.status = ? AND (a.published_at IS NULL OR a.published_at <= NOW())
             GROUP BY a.id
             ORDER BY a.published_at DESC
             LIMIT ? OFFSET ?",
            ['published', $perPage, $offset]
        );
        $articles = self::hydrateArticlePreviewData($db, $articles);
        $articles = self::attachPublicUrls($articles, $basePath);

        $totalCount = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM articles WHERE status = ? AND (published_at IS NULL OR published_at <= NOW())',
            ['published']
        );

        return [
            'title' => 'Blog',
            'articles' => $articles,
            'page' => $page,
            'per_page' => $perPage,
            'totalPages' => max(1, (int) ceil($totalCount / $perPage)),
            'canonical_url' => $basePath,
            'blog_base_path' => $basePath,
        ];
    }

    private static function buildBlogPostViewData(array $article, string $bodyHtml, string $basePath, array $settings = []): array
    {
        $siteUrl = \Cruinn\App::config('site.url', '');
        $blogSettings = self::readBlogSettings(Database::getInstance());
        $publicArticle = self::attachPublicUrl($article, $basePath);
        $canonicalUrl = (string) ($publicArticle['public_url'] ?? $basePath);
        $navigation = self::buildArticleNavigation(Database::getInstance(), $article, $basePath, self::normalisePerPage($settings['per_page'] ?? ($blogSettings['default_posts_per_page'] ?? 10)));
        $showReturnToList = array_key_exists('show_return_to_list', $settings)
            ? (bool) $settings['show_return_to_list']
            : (bool) ($blogSettings['show_return_to_list'] ?? true);
        $showPostNavigation = array_key_exists('show_post_navigation', $settings)
            ? (bool) $settings['show_post_navigation']
            : (bool) ($blogSettings['show_post_navigation'] ?? true);

        return array_merge([
            'title' => $article['title'],
            'article' => $publicArticle,
            'body_html' => $bodyHtml,
            'blog_base_path' => $basePath,
            'show_return_to_list' => $showReturnToList,
            'show_post_navigation' => $showPostNavigation,
            'canonical_url' => $canonicalUrl,
            'meta_description' => $article['excerpt'] ?: truncate(strip_tags($bodyHtml), 200),
            'og_title' => $article['title'],
            'og_type' => 'article',
            'og_url' => rtrim($siteUrl, '/') . $canonicalUrl,
            'og_description' => $article['excerpt'] ?: truncate(strip_tags($bodyHtml), 200),
        ], $navigation);
    }

    private static function findPublishedArticleBySlug(Database $db, string $slug): ?array
    {
        $article = $db->fetch(
            'SELECT a.*, u.display_name as author_name,
                    GROUP_CONCAT(s.title ORDER BY s.title SEPARATOR ", ") AS subject_title
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subject_content sc ON sc.item_type = \'article\' AND sc.item_id = a.id
             LEFT JOIN subjects s ON s.id = sc.subject_id
             WHERE a.slug = ? AND a.status = ? AND (a.published_at IS NULL OR a.published_at <= NOW())
             GROUP BY a.id
             LIMIT 1',
            [$slug, 'published']
        );

        return is_array($article) ? $article : null;
    }

    private static function attachPublicUrls(array $articles, string $basePath): array
    {
        foreach ($articles as &$article) {
            $article = self::attachPublicUrl($article, $basePath);
        }
        unset($article);

        return $articles;
    }

    private static function attachPublicUrl(array $article, string $basePath): array
    {
        $article['public_url'] = rtrim($basePath, '/') . '/' . ltrim((string) ($article['slug'] ?? ''), '/');
        return $article;
    }

    private static function normalisePublicBasePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '/blog';
        }

        return '/' . trim($trimmed, '/');
    }

    private static function normalisePerPage(mixed $value): int
    {
        return max(1, min(100, (int) $value ?: 10));
    }

    private static function readBlogSettings(?Database $db = null): array
    {
        $db ??= Database::getInstance();

        $defaults = [
            'list_page_id' => 0,
            'post_page_id' => 0,
            'default_posts_per_page' => 10,
            'show_return_to_list' => true,
            'show_post_navigation' => true,
        ];

        $rows = $db->fetchAll(
            "SELECT `key`, `value` FROM settings WHERE `group` = 'blog' AND `key` IN (?, ?, ?, ?, ?)",
            [
                'blog.list_page_id',
                'blog.post_page_id',
                'blog.default_posts_per_page',
                'blog.show_return_to_list',
                'blog.show_post_navigation',
            ]
        );

        $raw = [];
        foreach ($rows as $row) {
            $raw[(string) ($row['key'] ?? '')] = $row['value'] ?? null;
        }

        if ($raw === []) {
            $legacyJson = $db->fetchColumn('SELECT settings FROM module_config WHERE slug = ? LIMIT 1', ['blog']);
            $legacy = is_string($legacyJson) ? (json_decode($legacyJson, true) ?: []) : [];
            if (is_array($legacy) && !empty($legacy)) {
                $legacyMap = [
                    'blog.list_page_id' => isset($legacy['blog_list_page_id']) ? (string) $legacy['blog_list_page_id'] : null,
                    'blog.post_page_id' => isset($legacy['blog_post_page_id']) ? (string) $legacy['blog_post_page_id'] : null,
                ];

                foreach ($legacyMap as $legacyKey => $legacyValue) {
                    if ($legacyValue === null || $legacyValue === '' || $legacyValue === '0') {
                        continue;
                    }
                    $db->execute(
                        "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, 'blog')"
                        . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)",
                        [$legacyKey, $legacyValue]
                    );
                    $raw[$legacyKey] = $legacyValue;
                }
            }
        }

        $settings = $defaults;
        $settings['list_page_id'] = max(0, (int) ($raw['blog.list_page_id'] ?? 0));
        $settings['post_page_id'] = max(0, (int) ($raw['blog.post_page_id'] ?? 0));
        $settings['default_posts_per_page'] = self::normalisePerPage($raw['blog.default_posts_per_page'] ?? 10);
        $settings['show_return_to_list'] = ($raw['blog.show_return_to_list'] ?? '1') === '1';
        $settings['show_post_navigation'] = ($raw['blog.show_post_navigation'] ?? '1') === '1';

        return $settings;
    }

    private static function readBlogProfiles(Database $db): array
    {
        $rows = $db->fetchAll(
            'SELECT id, name, slug, description, display_mode, posts_per_page, show_return_to_list, show_post_navigation, updated_at
             FROM blog_profiles
             ORDER BY name ASC'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'display_mode' => (string) ($row['display_mode'] ?? 'both'),
                'posts_per_page' => self::normalisePerPage($row['posts_per_page'] ?? 10),
                'show_return_to_list' => (int) ($row['show_return_to_list'] ?? 1) === 1,
                'show_post_navigation' => (int) ($row['show_post_navigation'] ?? 1) === 1,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);
    }

    private static function readBlogProfile(Database $db, int $profileId): ?array
    {
        if ($profileId <= 0) {
            return null;
        }

        $row = $db->fetch(
            'SELECT id, name, slug, description, display_mode, posts_per_page, show_return_to_list, show_post_navigation, updated_at
             FROM blog_profiles
             WHERE id = ?
             LIMIT 1',
            [$profileId]
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'display_mode' => (string) ($row['display_mode'] ?? 'both'),
            'posts_per_page' => self::normalisePerPage($row['posts_per_page'] ?? 10),
            'show_return_to_list' => (int) ($row['show_return_to_list'] ?? 1) === 1,
            'show_post_navigation' => (int) ($row['show_post_navigation'] ?? 1) === 1,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function applyBlogProfileSettings(array $settings, ?Database $db = null): array
    {
        $profileId = max(0, (int) ($settings['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $settings;
        }

        $db ??= Database::getInstance();
        $profile = self::readBlogProfile($db, $profileId);
        if (!$profile) {
            return $settings;
        }

        if (!array_key_exists('mode', $settings) || trim((string) $settings['mode']) === '') {
            $settings['mode'] = $profile['display_mode'];
        }

        if (!array_key_exists('per_page', $settings) || (int) $settings['per_page'] <= 0) {
            $settings['per_page'] = $profile['posts_per_page'];
        }

        if (!array_key_exists('show_return_to_list', $settings)) {
            $settings['show_return_to_list'] = $profile['show_return_to_list'];
        }

        if (!array_key_exists('show_post_navigation', $settings)) {
            $settings['show_post_navigation'] = $profile['show_post_navigation'];
        }

        return $settings;
    }

    private function upsertBlogSetting(string $key, ?string $value): void
    {
        $this->db->execute(
            "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, 'blog')"
            . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)",
            [$key, $value]
        );
    }

    private static function buildArticleNavigation(Database $db, array $article, string $basePath, int $perPage): array
    {
        $publishedArticles = $db->fetchAll(
            'SELECT id, slug, title, published_at
             FROM articles
             WHERE status = ? AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY published_at DESC, id DESC',
            ['published']
        );

        $currentIndex = null;
        $currentId = (int) ($article['id'] ?? 0);

        foreach ($publishedArticles as $index => $publishedArticle) {
            if ((int) ($publishedArticle['id'] ?? 0) === $currentId) {
                $currentIndex = $index;
                break;
            }
        }

        $returnUrl = $basePath;
        $previousArticle = null;
        $nextArticle = null;

        if ($currentIndex !== null) {
            $returnPage = (int) floor($currentIndex / $perPage) + 1;
            $returnUrl = $basePath;
            if ($returnPage > 1) {
                $returnUrl .= '?page=' . $returnPage;
            }
            $returnUrl .= '#blog-post-' . $currentId;

            if (isset($publishedArticles[$currentIndex - 1])) {
                $nextArticle = self::attachPublicUrl($publishedArticles[$currentIndex - 1], $basePath);
            }

            if (isset($publishedArticles[$currentIndex + 1])) {
                $previousArticle = self::attachPublicUrl($publishedArticles[$currentIndex + 1], $basePath);
            }
        }

        return [
            'return_to_list_url' => $returnUrl,
            'previous_article' => $previousArticle,
            'next_article' => $nextArticle,
        ];
    }

    private function normaliseBlogProfileInput(?array $existing = null): array
    {
        $name = trim((string) $this->input('name', $existing['name'] ?? ''));
        $manualSlug = trim((string) $this->input('slug', $existing['slug'] ?? ''));
        $slugSource = $manualSlug !== '' ? $manualSlug : $name;
        $slug = $slugSource !== '' ? $this->sanitiseSlug($slugSource) : '';
        if ($slug === '' && $name !== '') {
            $slug = 'blog-profile-' . date('YmdHis');
        }

        $displayMode = strtolower(trim((string) $this->input('display_mode', $existing['display_mode'] ?? 'both')));
        if (!in_array($displayMode, ['list', 'post', 'both'], true)) {
            $displayMode = 'both';
        }

        $profile = [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $this->input('description', $existing['description'] ?? '')),
            'display_mode' => $displayMode,
            'posts_per_page' => self::normalisePerPage($this->input('posts_per_page', $existing['posts_per_page'] ?? 10)),
            'show_return_to_list' => $this->input('show_return_to_list', !empty($existing['show_return_to_list']) ? '1' : '0') === '1',
            'show_post_navigation' => $this->input('show_post_navigation', !empty($existing['show_post_navigation']) ? '1' : '0') === '1',
        ];

        $errors = [];
        if ($profile['name'] === '') {
            $errors['name'] = 'Profile name is required.';
        }
        if ($profile['slug'] === '') {
            $errors['slug'] = 'Profile slug is required.';
        }

        return [$profile, $errors];
    }

    /**
     * Render article_blocks for an article to HTML string (for public show page).
     */
    private function renderArticleBlocks(int $articleId): string
    {
        $flat = $this->db->fetchAll(
            'SELECT * FROM article_blocks
              WHERE article_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$articleId]
        );

        if (empty($flat)) {
            return '';
        }

        $byId       = [];
        $childrenOf = [];
        foreach ($flat as $row) {
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        return $this->renderBlockTree('__root', $byId, $childrenOf);
    }

    /**
     * Recursively render a block tree to HTML.
     */
    private function renderBlockTree(string $parentKey, array $byId, array $childrenOf): string
    {
        if (empty($childrenOf[$parentKey])) {
            return '';
        }
        $html = '';
        foreach ($childrenOf[$parentKey] as $blockId) {
            $row        = $byId[$blockId];
            $cfg        = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $cssProps   = json_decode($row['css_props'] ?? '{}', true) ?: [];
            $tag        = $cfg['_tag'] ?? 'div';
            $tag        = preg_replace('/[^a-z0-9]/', '', strtolower($tag)) ?: 'div';
            $id         = htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8');
            $extraAttrs = '';
            $cssClass   = $cssProps['_class'] ?? '';
            if ($cssClass !== '') {
                $extraAttrs .= ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"';
            }
            foreach ($cfg['_attrs'] ?? [] as $k => $v) {
                if ($k === 'id' || $k === 'class') { continue; }
                $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                             . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }
            $inner  = $row['inner_html'] ?? '';
            $inner .= $this->renderBlockTree($blockId, $byId, $childrenOf);
            $html  .= "<{$tag} id=\"{$id}\"{$extraAttrs}>{$inner}</{$tag}>\n";
        }
        return $html;
    }

    /**
     * Get raw article_blocks rows for the admin block editor.
     */
    private function getArticleBlocks(int $articleId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM article_blocks WHERE article_id = ? ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$articleId]
        );
    }

    // Legacy stub — kept to avoid breakage if called elsewhere.
    private function getBlocks(int $articleId): array
    {
        return $this->getArticleBlocks($articleId);
    }

    /**
     * Populate excerptless list items with teaser text derived from article blocks.
     */
    private static function hydrateArticlePreviewData(Database $db, array $articles): array
    {
        if (empty($articles)) {
            return [];
        }

        $articleIds = [];
        foreach ($articles as $article) {
            $articleId = (int) ($article['id'] ?? 0);
            if ($articleId > 0) {
                $articleIds[] = $articleId;
            }
        }

        $articleIds = array_values(array_unique($articleIds));
        if (empty($articleIds)) {
            return $articles;
        }

        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $rows = $db->fetchAll(
            "SELECT article_id, GROUP_CONCAT(COALESCE(inner_html, '') ORDER BY sort_order ASC SEPARATOR ' ') AS preview_source
             FROM article_blocks
             WHERE article_id IN ({$placeholders}) AND parent_block_id IS NULL
             GROUP BY article_id",
            $articleIds
        );

        $previewByArticle = [];
        foreach ($rows as $row) {
            $previewByArticle[(int) $row['article_id']] = self::buildPreviewText((string) ($row['preview_source'] ?? ''));
        }

        foreach ($articles as &$article) {
            $articleId = (int) ($article['id'] ?? 0);
            $excerpt = trim((string) ($article['excerpt'] ?? ''));
            $article['preview_text'] = $excerpt !== ''
                ? $excerpt
                : ($previewByArticle[$articleId] ?? '');
        }
        unset($article);

        return $articles;
    }

    private static function buildPreviewText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '') ?? '';
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return truncate($text, 250);
    }
}
