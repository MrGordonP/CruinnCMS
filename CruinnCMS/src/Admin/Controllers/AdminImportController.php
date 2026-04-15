<?php

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Services\HtmlImportService;

/**
 * AdminImportController
 *
 * Handles the ACP Import wizard:
 *   - Step 1: Upload a zip/single HTML file
 *   - Step 2: Review detected pages, assign slugs and import mode
 *   - Step 3: Confirm — creates pages table records + stores files or blocks
 */
class AdminImportController extends \Cruinn\Controllers\BaseController
{
    private HtmlImportService $importer;
    private Database $db;

    public function __construct()
    {
        $this->importer = new HtmlImportService();
        $this->db       = Database::getInstance();
    }

    // ── Step 1: Upload form ───────────────────────────────────────

    public function index(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/import/index', [
            'title'  => 'Import Pages',
            'tab'    => 'import',
            'errors' => [],
        ]);
    }

    // ── Step 2: Parse & review ────────────────────────────────────

    public function upload(): void
    {
        Auth::requireRole('admin');
        $this->requireCsrf();

        $file    = $_FILES['import_file'] ?? null;
        $mode    = $_POST['import_mode'] ?? 'file';   // 'file' or 'cruinn'
        $errors  = [];

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'No file uploaded or upload error.';
        }

        if (empty($errors)) {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime = $file['type'];

            if (!in_array($ext, ['html', 'htm', 'zip'])) {
                $errors[] = 'Only .html, .htm, or .zip files are allowed.';
            }
        }

        if (!empty($errors)) {
            $this->renderAdmin('admin/import/index', [
                'title'  => 'Import Pages',
                'tab'    => 'import',
                'errors' => $errors,
            ]);
            return;
        }

        // Parse the uploaded file
        $tmp  = $file['tmp_name'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $pages = [];

        if ($ext === 'zip') {
            try {
                $pages = $this->importer->parseZip($tmp);
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $content = file_get_contents($tmp);
            $slug    = pathinfo($file['name'], PATHINFO_FILENAME);
            $title   = $this->extractTitle($content) ?: $slug;
            $pages[] = [
                'filename' => $file['name'],
                'slug'     => $this->slugify($slug),
                'title'    => $title,
                'content'  => $content,
            ];
        }

        if (!empty($errors)) {
            $this->renderAdmin('admin/import/index', [
                'title'  => 'Import Pages',
                'tab'    => 'import',
                'errors' => $errors,
            ]);
            return;
        }

        // Stash the parsed pages in the session for step 3
        $_SESSION['_import_pending'] = [
            'pages' => $pages,
            'mode'  => $mode,
            'ts'    => time(),
        ];

        $this->renderAdmin('admin/import/review', [
            'title'  => 'Review Import',
            'tab'    => 'import',
            'pages'  => $pages,
            'mode'   => $mode,
            'errors' => [],
        ]);
    }

    // ── Step 3: Confirm & run import ──────────────────────────────

    public function confirm(): void
    {
        Auth::requireRole('admin');
        $this->requireCsrf();

        $pending = $_SESSION['_import_pending'] ?? null;
        if (!$pending || (time() - $pending['ts']) > 1800) {
            // Session expired — back to start
            header('Location: /admin/import');
            exit;
        }

        $pages  = $pending['pages'];
        $mode   = $_POST['import_mode'] ?? $pending['mode'];
        $rootPub = CRUINN_PUBLIC;

        $overrides = $_POST['pages'] ?? [];   // keyed by index: ['slug', 'title', 'skip']
        $imported  = [];
        $skipped   = 0;

        foreach ($pages as $i => $page) {
            $override = $overrides[$i] ?? [];
            if (!empty($override['skip'])) { $skipped++; continue; }

            $slug  = $this->slugify($override['slug'] ?? $page['slug']);
            $title = trim($override['title'] ?? $page['title']);
            $content = $page['content'];

            // Deduplicate slug against pages table
            $slug = $this->ensureUniqueSlug($slug);

            if ($mode === 'cruinn') {
                $pageId = $this->importAsCruinn($slug, $title, $content);
                $imported[] = ['slug' => $slug, 'title' => $title, 'mode' => 'cruinn', 'id' => $pageId];
            } else {
                $meta = $this->importer->importAsFile($content, $slug, $rootPub);
                $pageId = $this->createPageRecord($meta['slug'], $meta['title'], 'file', $meta['file_path']);
                $imported[] = ['slug' => $meta['slug'], 'title' => $meta['title'], 'mode' => 'file', 'id' => $pageId];
            }
        }

        unset($_SESSION['_import_pending']);

        $this->renderAdmin('admin/import/done', [
            'title'    => 'Import Complete',
            'tab'      => 'import',
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function importAsCruinn(string $slug, string $title, string $html): int
    {
        $blocks = $this->importer->convertToCruinnBlocks($html);

        $pageId = $this->createPageRecord($slug, $title, 'cruinn', null);

        $stmt = $this->db->prepare(
            'INSERT INTO cruinn_blocks (page_id, block_type, content, properties, sort_order)
             VALUES (:pid, :bt, :c, :p, :so)'
        );

        foreach ($blocks as $block) {
            $stmt->execute([
                'pid' => $pageId,
                'bt'  => $block['block_type'],
                'c'   => $block['content'] ?? '',
                'p'   => json_encode($block['properties'] ?? new \stdClass()),
                'so'  => $block['sort_order'] ?? 0,
            ]);
        }

        return $pageId;
    }

    private function createPageRecord(string $slug, string $title, string $renderMode, ?string $renderFile): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pages (slug, title, render_mode, render_file, is_published, created_at)
             VALUES (:slug, :title, :render_mode, :render_file, 1, NOW())'
        );
        $stmt->execute([
            'slug'        => $slug,
            'title'       => $title,
            'render_mode' => $renderMode,
            'render_file' => $renderFile,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $row = $this->db->query('SELECT COUNT(*) FROM pages WHERE slug = :s', ['s' => $slug])->fetchColumn();
        if ((int)$row === 0) return $slug;
        $n = 2;
        while (true) {
            $candidate = $slug . '-' . $n;
            $exists = $this->db->query('SELECT COUNT(*) FROM pages WHERE slug = :s', ['s' => $candidate])->fetchColumn();
            if ((int)$exists === 0) return $candidate;
            $n++;
        }
    }

    private function slugify(string $input): string
    {
        $s = strtolower(trim($input));
        $s = preg_replace('/[^a-z0-9-]/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-') ?: 'page';
    }

    /**
     * Extract <title> or first <h1> from raw HTML content.
     * (Exposed for use by upload() without needing the service.)
     */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return '';
    }

    private function requireCsrf(): void
    {
        if (!\Cruinn\CSRF::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}
