<?php
/**
 * CruinnCMS — Page Controller
 *
 * Handles public page display using the block-based content model.
 * Pages are stored in the `pages` table with content blocks in `page_blocks`.
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;
use Cruinn\Template;
use Cruinn\Services\CruinnRenderService;

class PageController extends BaseController
{
    /**
     * GET / — Homepage.
     * The home page is designated via the site.home_page_id setting (set in Site Builder → Structure).
     */
    public function home(): void
    {
        $setting    = $this->db->fetch("SELECT value FROM settings WHERE `key` = 'site.home_page_id' LIMIT 1");
        $homePageId = $setting ? (int)$setting['value'] : 0;

        $page = $homePageId
            ? $this->db->fetch('SELECT * FROM pages_index WHERE id = ? AND status = ? LIMIT 1', [$homePageId, 'published'])
            : null;

        if (!$page) {
            // No home page designated — send admins to Site Builder, everyone else gets 404.
            if (Auth::hasRole('admin')) {
                header('Location: ' . url('/admin/site-builder/structure'));
                exit;
            }
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        $blocks = $this->getBlocks($page['id']);
        $tpl = $this->getTemplate($page['template'] ?? 'default');

        // Cruinn CMS: if published Cruinn blocks exist, hand off to Cruinn renderer
        $cruinn = new CruinnRenderService();
        $sidebar = $this->resolveSidebarRender($tpl, $cruinn);
        $this->setZoneGlobals($cruinn, $tpl, (int) $page['id']);
        Template::addGlobal('page_tpl', $tpl);
        Template::addGlobal('tpl_sidebar_html', $sidebar['html']);
        Template::addGlobal('tpl_sidebar_css', $sidebar['css']);

        if ($cruinn->hasPublished((int) $page['id'])) {
            Template::addGlobal('cruinn_css', $cruinn->buildCss((int) $page['id']));
            $this->render('public/cruinn-page', [
                'title'   => $page['title'],
                'page'    => $page,
                'content' => $cruinn->buildHtml((int) $page['id']),
            ]);
            return;
        }

        $this->render('public/home', [
            'title'  => $page['title'],
            'page'   => $page,
            'blocks' => $blocks,
            'page_tpl' => $tpl,
        ]);
    }

    /**
     * GET /{slug} — Display a published page by its slug.
     */
    public function show(string $slug): void
    {
        $page = $this->db->fetch(
            'SELECT * FROM pages_index WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'published']
        );

        if (!$page) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        $renderMode = $page['render_mode'] ?? 'block';

        // ── File mode: serve raw static HTML file, no layout wrapping ──────
        if ($renderMode === 'file') {
            $filePath = $page['render_file'] ?? '';
            $absPath  = CRUINN_PUBLIC . $filePath;
            if ($filePath && file_exists($absPath)) {
                header('Content-Type: text/html; charset=UTF-8');
                readfile($absPath);
            } else {
                http_response_code(404);
                $this->render('errors/404', ['title' => 'Page Not Found']);
            }
            return;
        }

        // Resolve template and sidebar for all layout-wrapped render modes
        $tpl = $this->getTemplate($page['template'] ?? 'default');
        $cruinn = new CruinnRenderService();
        $sidebar = $this->resolveSidebarRender($tpl, $cruinn);
        $this->setZoneGlobals($cruinn, $tpl, (int) $page['id']);
        Template::addGlobal('page_tpl', $tpl);
        Template::addGlobal('tpl_sidebar_html', $sidebar['html']);
        Template::addGlobal('tpl_sidebar_css', $sidebar['css']);

        // ── HTML mode: raw HTML body stored in DB, wrapped in site layout ───
        if ($renderMode === 'html') {
            $this->render('public/html-page', [
                'title'            => $page['title'],
                'meta_description' => $page['meta_description'] ?? '',
                'page'             => $page,
                'body_html'        => $page['body_html'] ?? '',
            ]);
            return;
        }

        $blocks = $this->getBlocks($page['id']);

        // ── Cruinn mode: block renderer ──────────────────────────────────────

        if ($cruinn->hasPublished((int) $page['id'])) {
            // If the template has a canvas page, merge template layout with page content
            $canvasPageId = isset($tpl['canvas_page_id']) ? (int) $tpl['canvas_page_id'] : 0;
            $pageZone = (string) ($page['page_zone'] ?? 'main');
            if ($canvasPageId > 0 && $cruinn->hasPublished($canvasPageId)) {
                $merged = $cruinn->buildWithTemplate((int) $page['id'], $canvasPageId, $pageZone);
                Template::addGlobal('cruinn_css', $merged['css']);
                $this->render('public/cruinn-page', [
                    'title'            => $page['title'],
                    'meta_description' => $page['meta_description'] ?? '',
                    'page'             => $page,
                    'content'          => $merged['html'],
                ]);
            } else {
                Template::addGlobal('cruinn_css', $cruinn->buildCss((int) $page['id']));
                $this->render('public/cruinn-page', [
                    'title'            => $page['title'],
                    'meta_description' => $page['meta_description'] ?? '',
                    'page'             => $page,
                    'content'          => $cruinn->buildHtml((int) $page['id']),
                ]);
            }
            return;
        }

        $this->render('public/page', [
            'title'            => $page['title'],
            'meta_description' => $page['meta_description'] ?? '',
            'page'             => $page,
            'blocks'           => $blocks,
            'page_tpl'         => $tpl,
        ]);
    }

    /**
     * Override the zone globals (tpl_header/footer_html/css) that were set
     * by the global middleware, using template + page context for proper resolution.
     * Called by home() and show() once the template and page are known.
     */
    private function setZoneGlobals(CruinnRenderService $cruinn, array $tpl, int $pageId): void
    {
        $templateId = isset($tpl['id']) ? (int) $tpl['id'] : null;
        try {
            $header = $cruinn->buildZone('header', $templateId, $pageId);
            $footer = $cruinn->buildZone('footer', $templateId, $pageId);
        } catch (\Throwable $e) {
            return; // Leave middleware-set fallback globals intact
        }
        // Only override if a more-specific canvas was resolved; otherwise leave the
        // global middleware's fallback value (legacy _header / _footer slug lookup) intact.
        if ($header !== null) {
            Template::addGlobal('tpl_header_html', $header['html']);
            Template::addGlobal('tpl_header_css',  $header['css']);
        }
        if ($footer !== null) {
            Template::addGlobal('tpl_footer_html', $footer['html']);
            Template::addGlobal('tpl_footer_css',  $footer['css']);
        }
    }

    /**
     * Resolve a named zone (e.g. header, footer) to its rendered HTML and CSS.
     * Uses CruinnRenderService::buildZone() which looks up the zone canvas page by slug convention (_zoneName).
     * Stage 2 will replace the slug convention with an explicit zone-canvas mapping.
     */
    private function resolveZoneRender(string $zoneName, CruinnRenderService $cruinn): array
    {
        $result = $cruinn->buildZone($zoneName);
        return $result
            ? ['html' => $result['html'], 'css' => $result['css']]
            : ['html' => '', 'css' => ''];
    }

    private function resolveSidebarRender(array $tpl, CruinnRenderService $cruinn): array
    {
        $zones = $tpl['zones'] ?? ['main'];
        if (!in_array('sidebar', $zones, true)) {
            return ['html' => '', 'css' => ''];
        }

        $settings = $tpl['settings'] ?? [];
        $source = (string) ($settings['sidebar_source'] ?? 'default');
        $targetSlug = $source === 'default'
            ? '_global_sidebar'
            : ($source === 'custom' ? ($tpl['slug'] ?? '') : $source);
        if (!preg_match('/^[a-z0-9_\-]+$/', $targetSlug)) {
            return ['html' => '', 'css' => ''];
        }

        $sourceTpl = $this->db->fetch(
            "SELECT canvas_page_id, zones FROM page_templates
             WHERE slug = ? AND JSON_CONTAINS(zones, '\"sidebar\"')
             LIMIT 1",
            [$targetSlug]
        );
        if (!$sourceTpl) {
            return ['html' => '', 'css' => ''];
        }

        $canvasPageId = (int) ($sourceTpl['canvas_page_id'] ?? 0);
        if ($canvasPageId <= 0 || !$cruinn->hasPublished($canvasPageId)) {
            return ['html' => '', 'css' => ''];
        }

        // For custom sidebar source, render only the sidebar zone blocks
        // For global or other templates, render the entire canvas (backward compatible)
        $html = ($source === 'custom')
            ? $cruinn->buildZoneHtml($canvasPageId, 'sidebar')
            : $cruinn->buildHtml($canvasPageId);

        return [
            'html' => $html,
            'css'  => $cruinn->buildCss($canvasPageId),
        ];
    }

    /**
     * Legacy content_blocks fallback — table no longer exists.
     * Pages without pages simply render empty.
     */
    private function getBlocks(int $pageId): array
    {
        return [];
    }

    /**
     * Load a page template definition by slug.
     */
    private function getTemplate(string $slug): array
    {
        $tpl = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            [$slug]
        );

        if (!$tpl) {
            return [
                'id' => 0,
                'slug' => 'default',
                'name' => 'Default',
                'zones' => ['main'],
                'css_class' => 'layout-default',
                'settings' => [],
            ];
        }

        $tpl['zones'] = json_decode($tpl['zones'], true) ?? ['main'];
        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];
        return $tpl;
    }

}

