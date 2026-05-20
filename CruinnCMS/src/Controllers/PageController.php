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
            if (Auth::isAdmin()) {
                header('Location: ' . url('/admin/site-builder/structure'));
                exit;
            }
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        $blocks = $this->getBlocks($page['id']);
        $tpl    = $this->getTemplate($page['template'] ?? 'default');
        $cruinn = new CruinnRenderService();
        Template::addGlobal('page_tpl', $tpl);

        if ($cruinn->hasPublished((int) $page['id'])) {
            $templateId    = (int)($tpl['id'] ?? 0);
            $pageZone      = (string)($page['page_zone'] ?? 'main');
            $merged = $cruinn->buildWithTemplate($templateId, $pageZone, (int) $page['id']);
            Template::addGlobal('cruinn_css', $merged['css']);
            $this->render('public/cruinn-page', [
                'title'   => $page['title'],
                'page'    => $page,
                'content' => $merged['html'],
            ]);
            return;
        }

        $this->render('public/home', [
            'title'    => $page['title'],
            'page'     => $page,
            'blocks'   => $blocks,
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

        $tpl    = $this->getTemplate($page['template'] ?? 'default');
        $cruinn = new CruinnRenderService();
        Template::addGlobal('page_tpl', $tpl);

        $templateId    = (int)($tpl['id'] ?? 0);
        $pageZone      = (string)($page['page_zone'] ?? 'main');

        // ── HTML mode: raw body HTML, injected into template layout ─────────
        if ($renderMode === 'html') {
            if ($templateId > 0) {
                $merged = $cruinn->buildWithTemplate($templateId, $pageZone, null, $page['body_html'] ?? '');
                Template::addGlobal('cruinn_css', $merged['css']);
                $this->render('public/cruinn-page', [
                    'title'            => $page['title'],
                    'meta_description' => $page['meta_description'] ?? '',
                    'page'             => $page,
                    'content'          => $merged['html'],
                ]);
            } else {
                $this->render('public/html-page', [
                    'title'            => $page['title'],
                    'meta_description' => $page['meta_description'] ?? '',
                    'page'             => $page,
                    'body_html'        => $page['body_html'] ?? '',
                ]);
            }
            return;
        }

        $blocks = $this->getBlocks($page['id']);

        // ── Cruinn mode: block renderer ──────────────────────────────────────
        if ($cruinn->hasPublished((int) $page['id'])) {
            $merged = $cruinn->buildWithTemplate($templateId, $pageZone, (int) $page['id']);
            Template::addGlobal('cruinn_css', $merged['css']);
            $this->render('public/cruinn-page', [
                'title'            => $page['title'],
                'meta_description' => $page['meta_description'] ?? '',
                'page'             => $page,
                'content'          => $merged['html'],
            ]);
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
     * Build a zone-canvas map for all zones in the template except the page's own zone.
     * Returns [zoneName => canvasPageId] for every resolvable zone canvas.
     */
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
                'css_class' => 'layout-default',
                'settings' => [],
            ];
        }

        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];
        return $tpl;
    }

}

