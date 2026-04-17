<?php
/**
 * CruinnCMS — Admin Page Controller
 *
 * Handles page CRUD in the admin panel.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;

class AdminPageController extends \Cruinn\Controllers\BaseController
{
    /**
     * GET /admin/pages — List all pages.
     */
    public function listPages(): void
    {
        $pages = $this->db->fetchAll(
            "SELECT p.*, u.display_name as author_name
             FROM pages p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.slug NOT LIKE '\_%'
             ORDER BY p.updated_at DESC"
        );

        $this->renderAdmin('admin/pages/index', [
            'title'         => 'Pages',
            'pages'         => $pages,
            'breadcrumbs'   => [['Admin', '/admin'], ['Pages']],
        ]);
    }

    /**
     * GET /admin/pages/new — Show the new page form.
     */
    public function newPage(): void
    {
        $templates = $this->db->fetchAll('SELECT slug, name, description FROM page_templates ORDER BY sort_order');

        $this->renderAdmin('admin/pages/edit', [
            'title'       => 'New Page',
            'page'        => null,
            'blocks'      => [],
            'templates'   => $templates,
            'breadcrumbs' => [['Admin', '/admin'], ['Pages', '/admin/pages'], ['New Page']],
        ]);
    }

    /**
     * POST /admin/pages — Create a new page.
     */
    public function createPage(): void
    {
        $errors = $this->validateRequired([
            'title' => 'Title',
            'slug'  => 'URL Slug',
        ]);

        if (!empty($errors)) {
            Auth::flash('error', implode(' ', $errors));
            $this->redirect('/admin/pages/new');
        }

        $slug = $this->sanitiseSlug($this->input('slug'));

        // Check for duplicate slug
        $existing = $this->db->fetch('SELECT id FROM pages WHERE slug = ?', [$slug]);
        if ($existing) {
            Auth::flash('error', 'A page with that URL slug already exists.');
            $this->redirect('/admin/pages/new');
        }

        $renderMode = $this->input('render_mode', 'cruinn');
        if (!in_array($renderMode, ['cruinn', 'html', 'file'], true)) {
            $renderMode = 'cruinn';
        }

        $id = $this->db->insert('pages', [
            'title'            => $this->input('title'),
            'slug'             => $slug,
            'status'           => $this->input('status', 'draft'),
            'template'         => $this->input('template', 'default'),
            'meta_description' => $this->input('meta_description', ''),
            'render_mode'      => $renderMode,
            'created_by'       => Auth::userId(),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'page', (int) $id, $this->input('title'));

        if ($renderMode === 'html') {
            Auth::flash('success', 'Page created. Edit the HTML content below.');
            $this->redirect("/admin/pages/{$id}/html");
        } else {
            Auth::flash('success', 'Page created. Now add some content blocks.');
            $this->redirect("/admin/pages/{$id}/edit");
        }
    }

    /**
     * GET /admin/pages/{id}/edit — Redirect to the Cruinn editor.
     */
    public function editPage(string $id): void
    {
        $this->redirect('/admin/editor/' . (int)$id . '/edit');
    }

    /**
     * POST /admin/pages/{id} — Update page metadata.
     */
    public function updatePage(string $id): void
    {
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            Auth::flash('error', 'Page not found.');
            $this->redirect('/admin/pages');
        }

        $slug = $this->sanitiseSlug($this->input('slug'));

        // Check slug uniqueness (excluding this page)
        $existing = $this->db->fetch('SELECT id FROM pages WHERE slug = ? AND id != ?', [$slug, $id]);
        if ($existing) {
            Auth::flash('error', 'A page with that URL slug already exists.');
            $this->redirect("/admin/pages/{$id}/edit");
        }

        $renderMode = $this->input('render_mode', $page['render_mode'] ?? 'cruinn');
        if (!in_array($renderMode, ['cruinn', 'html', 'file'], true)) {
            $renderMode = 'cruinn';
        }

        $this->db->update('pages', [
            'title'            => $this->input('title'),
            'slug'             => $slug,
            'status'           => $this->input('status', 'draft'),
            'template'         => $this->input('template', 'default'),
            'meta_description' => $this->input('meta_description', ''),
            'render_mode'      => $renderMode,
            'updated_at'       => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'page', (int) $id, $this->input('title'));
        Auth::flash('success', 'Page updated.');
        $this->redirect("/admin/pages/{$id}/edit");
    }

    /**
     * POST /admin/pages/{id}/delete — Delete a page and its blocks.
     */
    public function deletePage(string $id): void
    {
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            Auth::flash('error', 'Page not found.');
            $this->redirect('/admin/pages');
        }

        $this->db->transaction(function () use ($id, $page) {
            $this->db->delete('content_blocks', 'parent_type = ? AND parent_id = ?', ['page', $id]);
            $this->db->delete('pages', 'id = ?', [$id]);
            $this->logActivity('delete', 'page', (int) $id, $page['title']);
        });

        Auth::flash('success', 'Page deleted.');
        $this->redirect('/admin/pages');
    }

    /**
     * GET /admin/pages/{id}/html — Raw HTML code editor for html-mode pages.
     */
    public function htmlEditor(string $id): void
    {
        Auth::requireRole('admin');
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            Auth::flash('error', 'Page not found.');
            $this->redirect('/admin/pages');
        }

        $this->renderAdmin('admin/pages/html-editor', [
            'title'       => 'HTML Editor: ' . $page['title'],
            'page'        => $page,
            'breadcrumbs' => [['Admin', '/admin'], ['Pages', '/admin/pages'], [$page['title']]],
        ]);
    }

    /**
     * POST /admin/pages/{id}/html — Save raw HTML body.
     */
    public function saveHtml(string $id): void
    {
        Auth::requireRole('admin');
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            http_response_code(404);
            $this->json(['error' => 'Page not found']);
            return;
        }

        $html = $_POST['body_html'] ?? '';

        $this->db->update('pages', [
            'body_html'   => $html,
            'render_mode' => 'html',
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'page', (int) $id, $page['title'] . ' [html]');
        Auth::flash('success', 'HTML content saved.');
        $this->redirect("/admin/pages/{$id}/html");
    }

    /**
     * POST /admin/pages/{id}/convert-to-blocks
     * Parse the page's body_html into Cruinn blocks, persist as a draft,
     * flip render_mode to 'cruinn', and redirect to the block editor.
     */
    public function convertToBlocks(string $id): void
    {
        Auth::requireRole('admin');
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            Auth::flash('error', 'Page not found.');
            $this->redirect('/admin/pages');
        }

        if (($page['render_mode'] ?? '') !== 'html') {
            Auth::flash('error', 'Only HTML-mode pages can be converted.');
            $this->redirect('/admin/pages');
        }

        $importSvc = new \Cruinn\Services\ImportService();
        $blocks    = $importSvc->autoImport($page, (int) $id, null);

        if (empty($blocks)) {
            Auth::flash('error', 'Nothing to import — the page has no HTML content.');
            $this->redirect('/admin/pages');
        }

        $importSvc->persistImportedBlocks($blocks, (int) $id, $this->db);

        $this->db->update('pages', [
            'render_mode' => 'cruinn',
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'page', (int) $id, $page['title'] . ' [convert-to-blocks]');
        Auth::flash('success', 'Page converted. Review your blocks and publish when ready.');
        $this->redirect('/admin/editor/' . (int) $id . '/edit');
    }

    /**
     * POST /admin/pages/{id}/export-html
     * Renders a Cruinn page to a flat .html file and flips render_mode to 'file'.
     */
    public function exportHtml(string $id): void
    {
        Auth::requireRole('admin');
        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
        if (!$page) {
            Auth::flash('error', 'Page not found.');
            $this->redirect('/admin/pages');
        }

        $cruinn = new \Cruinn\Services\CruinnRenderService();
        if (!$cruinn->hasPublished((int) $id)) {
            Auth::flash('error', 'Page has no published Cruinn blocks to export.');
            $this->redirect('/admin/pages');
        }

        $html   = $cruinn->buildHtml((int) $id);
        $css    = $cruinn->buildCss((int) $id);
        $title  = htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8');
        $siteUrl = \Cruinn\App::config('site.url', '');

        // Wrap in a minimal, self-contained HTML document
        $document = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
{$css}
</style>
</head>
<body>
{$html}
</body>
</html>
HTML;

        $filename  = $page['slug'] . '.html';
        $storageDir = CRUINN_PUBLIC . '/storage/pages';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $filePath = $storageDir . '/' . $filename;
        file_put_contents($filePath, $document);

        $webPath = '/storage/pages/' . $filename;
        $this->db->update('pages', [
            'render_mode' => 'file',
            'render_file' => $webPath,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'page', (int) $id, $page['title'] . ' [export-html]');
        Auth::flash('success', 'Page exported to ' . $webPath . ' and set to file mode.');
        $this->redirect('/admin/pages');
    }
}
