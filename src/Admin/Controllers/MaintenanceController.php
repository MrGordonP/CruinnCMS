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
        $pages = $this->db->fetchAll("SELECT id, title, slug, render_mode, render_file, body_html FROM pages");
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

        // 2. Collect links from cruinn_blocks (properties JSON and content)
        $blocks = $this->db->fetchAll("SELECT id, page_id, block_type, properties, content FROM cruinn_blocks WHERE status = 'published'");
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

        // Module routes: /blog, /events, /forum etc. — don't flag these
        $moduleRoots = ['blog', 'events', 'forum', 'files', 'forms', 'admin', 'login', 'logout', 'register',
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
}
