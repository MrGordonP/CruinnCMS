<?php
/**
 * Cruinn CMS — Maintenance Controller
 *
 * ACP maintenance tools: broken link scanner, storage audit, etc.
 * All routes require 'admin' role.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\CSRF;
use Cruinn\Modules\ModuleRegistry;

class MaintenanceController extends \Cruinn\Controllers\BaseController
{
    /**
     * GET /admin/maintenance/links — Link checker page.
     */
    public function linkCheck(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/maintenance/link-check', [
            'title'       => 'Broken Link Scanner',
            'results'     => null,
            'breadcrumbs' => [['Admin', '/admin'], ['Maintenance'], ['Broken Link Scanner']],
        ]);
    }

    /**
     * POST /admin/maintenance/links — Run the link scan.
     */
    public function runLinkCheck(): void
    {
        Auth::requireRole('admin');

        $siteUrl  = rtrim(App::config('site.url', ''), '/');
        $results  = $this->scanLinks($siteUrl);

        $this->renderAdmin('admin/maintenance/link-check', [
            'title'       => 'Broken Link Scanner',
            'results'     => $results,
            'breadcrumbs' => [['Admin', '/admin'], ['Maintenance'], ['Broken Link Scanner']],
        ]);
    }

    // ── Scanner ───────────────────────────────────────────────────

    private function scanLinks(string $siteUrl): array
    {
        $links   = [];   // ['source' => string, 'source_id' => int|null, 'href' => string, 'type' => string]
        $results = [];   // ['...link...', 'status' => 'ok'|'broken'|'external'|'skipped', 'detail' => string]

        // 1. Collect links from pages table (render_file paths, body_html)
        $pages = $this->db->fetchAll("SELECT id, title, slug, render_mode, render_file, body_html FROM pages_index");
        foreach ($pages as $p) {
            if ($p['render_mode'] === 'file' && !empty($p['render_file'])) {
                $links[] = ['source' => 'pages: ' . $p['slug'], 'source_id' => (int)$p['id'], 'href' => $p['render_file'], 'type' => 'render_file'];
            }
            if ($p['render_mode'] === 'html' && !empty($p['body_html'])) {
                foreach ($this->extractHrefs($p['body_html']) as $href) {
                    $links[] = ['source' => 'pages: ' . $p['slug'], 'source_id' => (int)$p['id'], 'href' => $href, 'type' => 'body_html'];
                }
            }
        }

        // 2. Collect links from pages (properties JSON and content)
        $blocks = $this->db->fetchAll("SELECT id, page_id, block_type, properties, content FROM pages_index WHERE status = 'published'");
        foreach ($blocks as $b) {
            $pageSlug = $this->getPageSlug((int)$b['page_id'], $pages);
            $source   = "block #{$b['id']} ({$b['block_type']}) on {$pageSlug}";

            $props = json_decode($b['properties'] ?? '{}', true) ?? [];
            foreach ($this->extractHrefsFromProps($props) as $href) {
                $links[] = ['source' => $source, 'source_id' => (int)$b['id'], 'href' => $href, 'type' => 'block_props'];
            }

            if (!empty($b['content'])) {
                foreach ($this->extractHrefs($b['content']) as $href) {
                    $links[] = ['source' => $source, 'source_id' => (int)$b['id'], 'href' => $href, 'type' => 'block_content'];
                }
            }
        }

        // 3. Settings table (logo, banner, any /storage/ or /uploads/ values)
        $settings = $this->db->fetchAll("SELECT `key`, `value` FROM settings WHERE value LIKE '/%'");
        foreach ($settings as $s) {
            if (filter_var($s['value'], FILTER_VALIDATE_URL) === false && str_starts_with($s['value'], '/')) {
                $links[] = ['source' => 'settings: ' . $s['key'], 'source_id' => null, 'href' => $s['value'], 'type' => 'setting'];
            }
        }

        // 4. Resolve each link
        $pageSlugIndex = array_column($pages, null, 'slug');
        $root = dirname(__DIR__, 3);

        foreach ($links as $link) {
            $href   = trim($link['href']);
            $status = 'skipped';
            $detail = '';

            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                $status = 'skipped';
                $detail = 'anchor/mailto/tel';
            } elseif (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                // Strip our own domain to check internally
                if (str_starts_with($href, $siteUrl)) {
                    $localPath = substr($href, strlen($siteUrl));
                    $status = $this->checkInternalPath($localPath, $pageSlugIndex, $root);
                } else {
                    $status = 'external';
                    $detail = 'external link (not checked)';
                }
            } elseif (str_starts_with($href, '/')) {
                $status = $this->checkInternalPath($href, $pageSlugIndex, $root);
            } else {
                $status = 'skipped';
                $detail = 'relative URL';
            }

            $result = $link;
            $result['status'] = $status;
            $result['detail'] = $detail;
            $results[] = $result;
        }

        return $results;
    }

    private function checkInternalPath(string $path, array $pageSlugIndex, string $root): string
    {
        // Strip query string and fragment
        $path = strtok($path, '?#');

        // Check physical file (static assets, storage/, uploads/)
        $absPath = $root . '/public' . $path;
        if (file_exists($absPath)) {
            return 'ok';
        }

        // Check known page slugs
        $slug = ltrim($path, '/');
        if (isset($pageSlugIndex[$slug])) {
            return $pageSlugIndex[$slug]['status'] === 'published' ? 'ok' : 'broken';
        }

        // Module routes: /news, /events, /forum etc. — don't flag these
        $moduleRoots = ['news', 'events', 'forum', 'files', 'forms', 'admin', 'login', 'logout', 'register',
                        'members', 'council', 'reset-password', 'forgot-password', 'notifications', 'mailing-lists',
                        'directory', 'subjects', 'storage', 'uploads', 'brand', 'cms', 'install.php'];
        $firstSegment = explode('/', $slug)[0];
        if (in_array($firstSegment, $moduleRoots)) {
            return 'ok';
        }

        return 'broken';
    }

    private function extractHrefs(string $html): array
    {
        $hrefs = [];
        if (!preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/', $html, $m)) {
            return $hrefs;
        }
        foreach ($m[1] as $href) {
            $href = trim($href);
            if ($href !== '') $hrefs[] = $href;
        }
        return array_unique($hrefs);
    }

    private function extractHrefsFromProps(array $props): array
    {
        $hrefs = [];
        $urlKeys = ['url', 'href', 'src', 'image', 'link', 'background'];
        array_walk_recursive($props, function ($value, $key) use (&$hrefs, $urlKeys) {
            if (is_string($value) && in_array(strtolower($key), $urlKeys) && trim($value) !== '') {
                $hrefs[] = trim($value);
            }
        });
        return array_unique($hrefs);
    }

    private function getPageSlug(int $pageId, array $pages): string
    {
        foreach ($pages as $p) {
            if ((int)$p['id'] === $pageId) return $p['slug'];
        }
        return "page #{$pageId}";
    }

    // ── Migrations ────────────────────────────────────────────────

    /**
     * POST /admin/maintenance/migrations/rerun
     * Delete the tracking record for a single migration and re-execute it.
     */
    public function rerunMigration(): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $db     = Database::getInstance();
        $module = preg_replace('/[^a-z0-9_\-]/', '', (string) ($_POST['module'] ?? ''));
        $file   = preg_replace('/[^a-z0-9_\-.]/', '', (string) ($_POST['file'] ?? ''));

        if ($module === '' || $file === '') {
            Auth::flash('error', 'Invalid migration reference.');
            $this->redirect('/admin/maintenance/migrations');
        }

        [$all] = $this->collectMigrationState();
        $target = null;
        foreach ($all as $m) {
            if ($m['module'] === $module && $m['file'] === $file) {
                $target = $m;
                break;
            }
        }

        if (!$target) {
            Auth::flash('error', "Migration not found: [{$module}] {$file}");
            $this->redirect('/admin/maintenance/migrations');
        }

        // Delete tracking row so it runs again
        $db->execute(
            "DELETE FROM module_migrations WHERE module = ? AND filename = ?",
            [$module, $file]
        );

        $sql = file_get_contents($target['path']);
        if ($sql === false || trim($sql) === '') {
            Auth::flash('warning', "Migration file was empty — tracking record removed but nothing executed.");
            $this->redirect('/admin/maintenance/migrations');
        }

        try {
            $this->execSqlWithDelimiters($db->pdo(), $sql);
            $db->execute(
                "INSERT IGNORE INTO module_migrations (module, filename) VALUES (?, ?)",
                [$module, $file]
            );
            Auth::flash('success', "Migration [{$module}] {$file} re-applied successfully.");
        } catch (\Throwable $e) {
            Auth::flash('error', "Rerun failed: " . $e->getMessage());
        }

        $this->redirect('/admin/maintenance/migrations');
    }

    /**
     * GET /admin/maintenance/migrations — Show migration status.
     */
    public function migrations(): void
    {
        Auth::requireRole('admin');

        [$all, $applied, $slugRemapped] = $this->collectMigrationState();

        $rows = [];
        foreach ($all as $m) {
            $key = $m['module'] . '::' . $m['file'];
            $rows[] = [
                'module'  => $m['module'],
                'file'    => $m['file'],
                'applied' => isset($applied[$key]),
            ];
        }

        $pending = count(array_filter($rows, fn($r) => !$r['applied']));

        $this->renderAdmin('admin/maintenance/migrations', [
            'title'        => 'Database Migrations',
            'breadcrumbs'  => [['Admin', '/admin'], ['Maintenance'], ['Migrations']],
            'rows'         => $rows,
            'pending'      => $pending,
            'slugRemapped' => $slugRemapped,
            'results'      => null,
        ]);
    }

    /**
     * POST /admin/maintenance/migrations — Apply all pending migrations.
     */
    public function runMigrations(): void
    {
        Auth::requireRole('admin');
        CSRF::validate();

        $db = Database::getInstance();

        [$all, $applied, $slugRemapped] = $this->collectMigrationState();

        $results = [];
        $errors  = 0;

        foreach ($all as $m) {
            $key = $m['module'] . '::' . $m['file'];

            if (isset($applied[$key])) {
                $results[] = ['module' => $m['module'], 'file' => $m['file'], 'status' => 'skipped', 'error' => null];
                continue;
            }

            if (!file_exists($m['path'])) {
                $results[] = ['module' => $m['module'], 'file' => $m['file'], 'status' => 'missing', 'error' => 'File not found'];
                continue;
            }

            $sql = file_get_contents($m['path']);
            if ($sql === false || trim($sql) === '') {
                $results[] = ['module' => $m['module'], 'file' => $m['file'], 'status' => 'skipped', 'error' => 'Empty file'];
                continue;
            }

            try {
                $this->execSqlWithDelimiters($db->pdo(), $sql);
                $db->execute(
                    "INSERT IGNORE INTO module_migrations (module, filename) VALUES (?, ?)",
                    [$m['module'], $m['file']]
                );
                $results[] = ['module' => $m['module'], 'file' => $m['file'], 'status' => 'ok', 'error' => null];
            } catch (\Throwable $e) {
                $results[] = ['module' => $m['module'], 'file' => $m['file'], 'status' => 'failed', 'error' => $e->getMessage()];
                $errors++;
            }
        }

        // Re-collect for updated status
        [$all, $applied,] = $this->collectMigrationState();
        $rows = [];
        foreach ($all as $m) {
            $key = $m['module'] . '::' . $m['file'];
            $rows[] = ['module' => $m['module'], 'file' => $m['file'], 'applied' => isset($applied[$key])];
        }
        $pending = count(array_filter($rows, fn($r) => !$r['applied']));

        $this->renderAdmin('admin/maintenance/migrations', [
            'title'        => 'Database Migrations',
            'breadcrumbs'  => [['Admin', '/admin'], ['Maintenance'], ['Migrations']],
            'rows'         => $rows,
            'pending'      => $pending,
            'slugRemapped' => $slugRemapped,
            'results'      => $results,
            'errors'       => $errors,
        ]);
    }

    /**
     * Execute a SQL file that may contain DELIMITER directives (e.g. stored procedures).
     * PDO does not understand DELIMITER — this strips them and splits on the active delimiter.
     */
    private function execSqlWithDelimiters(\PDO $pdo, string $sql): void
    {
        $delimiter = ';';
        $buffer    = '';

        foreach (explode("\n", $sql) as $line) {
            $trimmed = rtrim($line);

            // DELIMITER directive — switch active delimiter, do not execute
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $trimmed, $m)) {
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";

            // If buffer ends with the current delimiter, execute it
            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $stmt = rtrim($buffer);
                // Strip the trailing delimiter
                $stmt = substr($stmt, 0, strlen($stmt) - strlen($delimiter));
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
                $buffer = '';
            }
        }

        // Execute any remaining buffered SQL
        $stmt = trim($buffer);
        if ($stmt !== '' && $stmt !== $delimiter) {
            $pdo->exec($stmt);
        }
    }

    /**
     * Collect all declared migrations + applied set from DB.
     * Also fixes the articles→blog slug rename in module_migrations if needed.
     *
     * @return array{0: array, 1: array<string,bool>, 2: int}
     *               [all migrations, applied key map, count of rows remapped]
     */
    private function collectMigrationState(): array
    {
        $db = Database::getInstance();

        // Ensure tracking table exists
        try {
            $db->execute("SELECT 1 FROM module_migrations LIMIT 1");
        } catch (\Throwable) {
            $db->execute("
                CREATE TABLE IF NOT EXISTS module_migrations (
                    id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
                    module     VARCHAR(64)     NOT NULL,
                    filename   VARCHAR(255)    NOT NULL,
                    applied_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_module_file (module, filename)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $remapped = 0;

        // Collect declared migrations
        $all = [];

        // Core
        $coreDir = CRUINN_ROOT . '/migrations/core';
        if (is_dir($coreDir)) {
            $files = glob($coreDir . '/*.sql') ?: [];
            natsort($files);
            foreach ($files as $path) {
                $all[] = ['module' => 'core', 'file' => basename($path), 'path' => $path];
            }
        }

        // Modules
        ModuleRegistry::load();
        foreach (ModuleRegistry::all() as $slug => $def) {
            foreach ($def['migrations'] as $path) {
                $all[] = ['module' => $slug, 'file' => basename($path), 'path' => $path];
            }
        }

        // Applied set
        $appliedRows = $db->fetchAll("SELECT module, filename FROM module_migrations");
        $applied = [];
        foreach ($appliedRows as $row) {
            $applied[$row['module'] . '::' . $row['filename']] = true;
        }

        return [$all, $applied, $remapped];
    }
}
