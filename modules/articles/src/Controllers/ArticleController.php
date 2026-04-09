<?php
/**
 * IGA Portal — Article Controller
 *
 * Public-facing news/blog article listing and detail pages,
 * plus admin CRUD with block editor support.
 */

namespace IGA\Module\Articles\Controllers;

use IGA\Auth;
use IGA\Controllers\BaseController;

class ArticleController extends BaseController
{
    /**
     * GET /news — List published articles.
     */
    public function index(): void
    {
        $page = max(1, (int) $this->query('page', 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $articles = $this->db->fetchAll(
            'SELECT a.*, u.display_name as author_name, s.title as subject_title
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subjects s ON a.subject_id = s.id
             WHERE a.status = ? AND (a.published_at IS NULL OR a.published_at <= NOW())
             ORDER BY a.published_at DESC
             LIMIT ? OFFSET ?',
            ['published', $perPage, $offset]
        );

        $totalCount = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM articles WHERE status = ? AND (published_at IS NULL OR published_at <= NOW())',
            ['published']
        );

        $totalPages = (int) ceil($totalCount / $perPage);

        $this->render('public/articles/index', [
            'title'      => 'News',
            'articles'      => $articles,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'sidebarEvents' => $this->getSidebarEvents(),
        ]);
    }

    /**
     * GET /news/{slug} — Show a single article.
     */
    public function show(string $slug): void
    {
        $article = $this->db->fetch(
            'SELECT a.*, u.display_name as author_name, s.title as subject_title
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subjects s ON a.subject_id = s.id
             WHERE a.slug = ? AND a.status = ? AND (a.published_at IS NULL OR a.published_at <= NOW())
             LIMIT 1',
            [$slug, 'published']
        );

        if (!$article) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Article Not Found']);
            return;
        }

        // Fetch blocks for this article
        $blocks = $this->getBlocks((int) $article['id']);

        $siteUrl = \IGA\App::config('site.url', '');
        $ogImage = !empty($article['featured_image'])
            ? $siteUrl . $article['featured_image']
            : ($siteUrl . \IGA\App::config('social.default_image', ''));

        $this->render('public/articles/show', [
            'title'            => $article['title'],
            'article'          => $article,
            'blocks'           => $blocks,
            'sidebarEvents'    => $this->getSidebarEvents(),
            'meta_description' => $article['excerpt'] ?: truncate(strip_tags($article['body'] ?? ''), 200),
            'og_title'         => $article['title'],
            'og_type'          => 'article',
            'og_url'           => $siteUrl . '/news/' . $article['slug'],
            'og_description'   => $article['excerpt'] ?: truncate(strip_tags($article['body'] ?? ''), 200),
            'og_image'         => $ogImage,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  ADMIN CRUD
    // ══════════════════════════════════════════════════════════════

    private function getSidebarEvents(int $limit = 5): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT id, title, slug, date_start FROM events
                 WHERE date_start >= NOW() AND status = ?
                 ORDER BY date_start ASC
                 LIMIT ?',
                ['published', $limit]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * GET /admin/articles — List all articles with search + filters.
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
            $where[]  = 'a.subject_id = ?';
            $params[] = $subject;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM articles a {$whereSQL}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $articles = $this->db->fetchAll(
            "SELECT a.*, u.display_name as author_name, s.title as subject_title
             FROM articles a
             LEFT JOIN users u ON a.author_id = u.id
             LEFT JOIN subjects s ON a.subject_id = s.id
             {$whereSQL}
             ORDER BY a.updated_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );

        $this->renderAdmin('admin/articles/index', [
            'title'       => 'Articles',
            'articles'    => $articles,
            'subjects'    => $subjects,
            'search'      => $search,
            'status'      => $status,
            'subjectId'   => $subject,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'breadcrumbs' => [['Admin', '/admin'], ['Articles']],
        ]);
    }

    /**
     * GET /admin/articles/new — New article form.
     */
    public function adminNew(): void
    {
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );

        $this->renderAdmin('admin/articles/edit', [
            'title'       => 'New Article',
            'article'     => null,
            'blocks'      => [],
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Articles', '/admin/articles'], ['New Article']],
        ]);
    }

    /**
     * POST /admin/articles — Create a new article.
     */
    public function adminCreate(): void
    {
        $errors = $this->validateRequired([
            'title' => 'Title',
        ]);

        // Use date-based slug (YYYYMMDD##) by default; fall back to title only if explicitly provided
        $manualSlug = trim($this->input('slug', ''));
        $slug = $manualSlug
            ? $this->sanitiseSlug($manualSlug)
            : $this->generateDateSlug('articles');

        // Check slug uniqueness
        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM articles WHERE slug = ?',
            [$slug]
        );
        if ($existing) {
            // Manual slug collision — surface the error; auto-slug collision shouldn't occur
            $errors['slug'] = 'An article with this URL slug already exists.';
        }

        if ($errors) {
            $subjects = $this->db->fetchAll(
                'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
                ['active']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'       => 'New Article',
                'article'     => $_POST,
                'blocks'      => [],
                'subjects'    => $subjects,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Articles', '/admin/articles'], ['New Article']],
            ]);
            return;
        }

        $status = $this->input('status', 'draft');
        $publishedAt = $this->input('published_at') ?: null;

        // Auto-set published_at when publishing
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $subjectId = $this->input('subject_id');

        $id = $this->db->insert('articles', [
            'subject_id'     => $subjectId ?: null,
            'title'          => $this->input('title'),
            'slug'           => $slug,
            'excerpt'        => $this->input('excerpt', ''),
            'featured_image' => $this->input('featured_image', ''),
            'body'           => '', // Block-based articles use content_blocks
            'author_id'      => Auth::userId(),
            'status'         => $status,
            'published_at'   => $publishedAt,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'article', (int) $id, $this->input('title'));
        Auth::flash('success', 'Article created. Now add some content blocks.');
        $this->redirect("/admin/articles/{$id}/edit");
    }

    /**
     * GET /admin/articles/{id}/edit — Edit article metadata and blocks.
     */
    public function adminEdit(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
        }

        $blocks = $this->getBlocks((int) $id);

        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );

        $this->renderAdmin('admin/articles/edit', [
            'title'       => 'Edit: ' . $article['title'],
            'article'     => $article,
            'blocks'      => $blocks,
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Articles', '/admin/articles'], [$article['title']]],
        ]);
    }

    /**
     * POST /admin/articles/{id} — Update article metadata.
     */
    public function adminUpdate(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
        }

        $errors = $this->validateRequired([
            'title' => 'Title',
        ]);

        $slug = $this->sanitiseSlug($this->input('slug') ?: $this->input('title'));

        // Check slug uniqueness (excluding self)
        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM articles WHERE slug = ? AND id != ?',
            [$slug, $id]
        );
        if ($existing) {
            $errors['slug'] = 'An article with this URL slug already exists.';
        }

        if ($errors) {
            $blocks = $this->getBlocks((int) $id);
            $subjects = $this->db->fetchAll(
                'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
                ['active']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'       => 'Edit: ' . $article['title'],
                'article'     => array_merge($article, $_POST),
                'blocks'      => $blocks,
                'subjects'    => $subjects,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Articles', '/admin/articles'], [$article['title']]],
            ]);
            return;
        }

        $status = $this->input('status', 'draft');
        $publishedAt = $this->input('published_at') ?: null;

        // Auto-set published_at when publishing for the first time
        if ($status === 'published' && !$publishedAt && empty($article['published_at'])) {
            $publishedAt = date('Y-m-d H:i:s');
        } elseif ($publishedAt) {
            // Keep user-specified date
        } else {
            $publishedAt = $article['published_at'];
        }

        $subjectId = $this->input('subject_id');

        $this->db->update('articles', [
            'subject_id'     => $subjectId ?: null,
            'title'          => $this->input('title'),
            'slug'           => $slug,
            'excerpt'        => $this->input('excerpt', ''),
            'featured_image' => $this->input('featured_image', ''),
            'status'         => $status,
            'published_at'   => $publishedAt,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'article', (int) $id, $this->input('title'));
        Auth::flash('success', 'Article updated.');
        $this->redirect("/admin/articles/{$id}/edit");
    }

    /**
     * POST /admin/articles/{id}/delete — Delete an article and its blocks.
     */
    public function adminDelete(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Article not found.');
            $this->redirect('/admin/articles');
        }

        $this->db->transaction(function () use ($id, $article) {
            $this->db->delete('content_blocks', 'parent_type = ? AND parent_id = ?', ['article', $id]);
            $this->db->delete('articles', 'id = ?', [$id]);
            $this->logActivity('delete', 'article', (int) $id, $article['title']);
        });

        Auth::flash('success', 'Article deleted.');
        $this->redirect('/admin/articles');
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Get decoded content blocks for an article (tree structure).
     */
    private function getBlocks(int $articleId): array
    {
        $blocks = $this->db->fetchAll(
            'SELECT * FROM content_blocks WHERE parent_type = ? AND parent_id = ? ORDER BY sort_order ASC',
            ['article', $articleId]
        );

        foreach ($blocks as &$block) {
            $block['content'] = json_decode($block['content'], true) ?? [];
            $block['settings'] = json_decode($block['settings'] ?? '{}', true) ?? [];
            $block['children'] = [];
        }
        unset($block);

        $indexed = [];
        foreach ($blocks as &$block) {
            $indexed[$block['id']] = &$block;
        }
        unset($block);

        $tree = [];
        foreach ($blocks as &$block) {
            $pid = $block['parent_block_id'] ?? null;
            if ($pid && isset($indexed[$pid])) {
                $indexed[$pid]['children'][] = &$block;
            } else {
                $tree[] = &$block;
            }
        }
        unset($block);

        return $tree;
    }
}
