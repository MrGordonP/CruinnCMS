<?php
/**
 * Cruinn CMS — Article Controller
 *
 * Public-facing blog listing and detail pages,
 * plus admin CRUD with block editor support.
 */

namespace Cruinn\Module\Blog\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ArticleController extends BaseController
{
    /**
     * GET /blog — List published blog posts.
     */
    public function index(): void
    {
        $page    = max(1, (int) $this->query('page', 1));
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;

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
            'title'         => 'Blog',
            'articles'      => $articles,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'sidebarEvents' => $this->getSidebarEvents(),
        ]);
    }

    /**
     * GET /blog/{slug} — Show a single blog post.
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

        $bodyHtml = $this->renderArticleBlocks((int) $article['id']);
        $siteUrl  = \Cruinn\App::config('site.url', '');

        $this->render('public/articles/show', [
            'title'            => $article['title'],
            'article'          => $article,
            'body_html'        => $bodyHtml,
            'sidebarEvents'    => $this->getSidebarEvents(),
            'meta_description' => $article['excerpt'] ?: truncate(strip_tags($bodyHtml), 200),
            'og_title'         => $article['title'],
            'og_type'          => 'article',
            'og_url'           => $siteUrl . '/blog/' . $article['slug'],
            'og_description'   => $article['excerpt'] ?: truncate(strip_tags($bodyHtml), 200),
        ]);
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
            $where[]  = 'a.subject_id = ?';
            $params[] = $subject;
        }

        $whereSQL   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total      = $this->db->fetchColumn("SELECT COUNT(*) FROM articles a {$whereSQL}", $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset     = ($page - 1) * $perPage;

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
            'title'       => 'Blog',
            'articles'    => $articles,
            'subjects'    => $subjects,
            'search'      => $search,
            'status'      => $status,
            'subjectId'   => $subject,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'breadcrumbs' => [['Admin', '/admin'], ['Blog']],
        ]);
    }

    /**
     * GET /admin/blog/new — New blog post form.
     */
    public function adminNew(): void
    {
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );

        $this->renderAdmin('admin/articles/edit', [
            'title'       => 'New Blog Post',
            'article'     => null,
            'blocks'      => [],
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['New Blog Post']],
        ]);
    }

    /**
     * POST /admin/blog — Create a new blog post.
     */
    public function adminCreate(): void
    {
        $errors = $this->validateRequired(['title' => 'Title']);

        $manualSlug = trim($this->input('slug', ''));
        $slug = $manualSlug
            ? $this->sanitiseSlug($manualSlug)
            : $this->generateDateSlug('articles');

        $existing = $this->db->fetchColumn('SELECT COUNT(*) FROM articles WHERE slug = ?', [$slug]);
        if ($existing) {
            $errors['slug'] = 'An article with this URL slug already exists.';
        }

        if ($errors) {
            $subjects = $this->db->fetchAll(
                'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
                ['active']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'       => 'New Blog Post',
                'article'     => $_POST,
                'blocks'      => [],
                'subjects'    => $subjects,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], ['New Blog Post']],
            ]);
            return;
        }

        $status      = $this->input('status', 'draft');
        $publishedAt = $this->input('published_at') ?: null;

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
            'author_id'      => Auth::userId(),
            'status'         => $status,
            'published_at'   => $publishedAt,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'article', (int) $id, $this->input('title'));
        Auth::flash('success', 'Blog post created. Now add some content blocks.');
        $this->redirect('/admin/blog/' . $id . '/edit');
    }

    /**
     * GET /admin/blog/{id}/edit — Edit blog post metadata and blocks.
     */
    public function adminEdit(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Blog post not found.');
            $this->redirect('/admin/blog');
        }

        $blocks   = $this->getArticleBlocks((int) $id);
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
            'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], [$article['title']]],
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
            $this->redirect('/admin/blog');
        }

        $errors = $this->validateRequired(['title' => 'Title']);
        $slug   = $this->sanitiseSlug($this->input('slug') ?: $this->input('title'));

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
                'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
                ['active']
            );
            $this->renderAdmin('admin/articles/edit', [
                'title'       => 'Edit: ' . $article['title'],
                'article'     => array_merge($article, $_POST),
                'blocks'      => $blocks,
                'subjects'    => $subjects,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Blog', '/admin/blog'], [$article['title']]],
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
        Auth::flash('success', 'Blog post updated.');
        $this->redirect("/admin/blog/{$id}/edit");
    }

    /**
     * POST /admin/blog/{id}/delete — Delete a blog post and its blocks.
     */
    public function adminDelete(string $id): void
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        if (!$article) {
            Auth::flash('error', 'Blog post not found.');
            $this->redirect('/admin/blog');
        }

        $this->db->transaction(function () use ($id, $article) {
            $this->db->delete('article_blocks', 'article_id = ?', [$id]);
            $this->db->delete('articles', 'id = ?', [$id]);
            $this->logActivity('delete', 'article', (int) $id, $article['title']);
        });

        Auth::flash('success', 'Blog post deleted.');
        $this->redirect('/admin/blog');
    }

    // ── Helpers ───────────────────────────────────────────────

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
}
