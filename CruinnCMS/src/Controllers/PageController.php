<?php
/**
 * CruinnCMS — Page Controller
 *
 * Handles public page display using the block-based content model.
 * Pages are stored in the `pages` table with content blocks in `page_blocks`.
 */

namespace Cruinn\Controllers;

use Cruinn\Template;
use Cruinn\Services\CruinnRenderService;

class PageController extends BaseController
{
    /**
     * GET / — Homepage.
     */
    public function home(): void
    {
        $page = $this->db->fetch(
            'SELECT * FROM pages_index WHERE slug = ? AND status = ? LIMIT 1',
            ['home', 'published']
        );

        if (!$page) {
            // Fallback: render a default homepage even if no page exists yet
            $this->render('public/home', [
                'title'  => 'My Site',
                'page'   => null,
                'blocks' => [],
            ]);
            return;
        }

        $blocks = $this->getBlocks($page['id']);
        $tpl = $this->getTemplate($page['template'] ?? 'default');

        // Cruinn CMS: if published Cruinn blocks exist, hand off to Cruinn renderer
        $cruinn = new CruinnRenderService();
        $sidebar = $this->resolveSidebarRender($tpl, $cruinn);
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
        $tpl = $this->getTemplate($page['template'] ?? 'default');

        // ── Cruinn mode: block renderer ──────────────────────────────────────
        $cruinn = new CruinnRenderService();
        $sidebar = $this->resolveSidebarRender($tpl, $cruinn);
        Template::addGlobal('page_tpl', $tpl);
        Template::addGlobal('tpl_sidebar_html', $sidebar['html']);
        Template::addGlobal('tpl_sidebar_css', $sidebar['css']);

        if ($cruinn->hasPublished((int) $page['id'])) {
            // If the template has a canvas page, merge template layout with page content
            $canvasPageId = isset($tpl['canvas_page_id']) ? (int) $tpl['canvas_page_id'] : 0;
            if ($canvasPageId > 0 && $cruinn->hasPublished($canvasPageId)) {
                $merged = $cruinn->buildWithTemplate((int) $page['id'], $canvasPageId);
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

        // Load template-level blocks for header/footer rendering
        $templateBlocks = $this->getTemplateBlocks($tpl);

        // Decide which header blocks to use based on header_source setting.
        // 'default' = load from the shared _global_header template (if built);
        // 'custom'  = use this template's own header zone blocks;
        // any other value = a named header template slug.
        $headerSource = ($tpl['settings']['header_source'] ?? 'default');
        if ($headerSource === 'custom') {
            Template::addGlobal('tpl_header_blocks', $templateBlocks['header'] ?? []);
        } else {
            // 'default' resolves to '_global_header'; any other value is an explicit template slug.
            $headerTplSlug = ($headerSource === 'default') ? '_global_header' : $headerSource;
            $headerTpl = $this->db->fetch(
                'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
                [$headerTplSlug]
            );
            if ($headerTpl) {
                $headerTpl['zones']    = json_decode($headerTpl['zones'],    true) ?? ['main'];
                $headerTpl['settings'] = json_decode($headerTpl['settings'] ?? '{}', true) ?: [];
                $namedBlocks = $this->getTemplateBlocks($headerTpl);
                Template::addGlobal('tpl_header_blocks', $namedBlocks['header'] ?? []);
            } else {
                Template::addGlobal('tpl_header_blocks', []); // falls back to PHP header in layout.php
            }
        }

        // Set template-level globals so layout.php can use them
        Template::addGlobal('tpl_footer_blocks', $templateBlocks['footer'] ?? []);

        $this->render('public/page', [
            'title'            => $page['title'],
            'meta_description' => $page['meta_description'] ?? '',
            'page'             => $page,
            'blocks'           => $blocks,
            'page_tpl'         => $tpl,
        ]);
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

        return [
            'html' => $cruinn->buildHtml($canvasPageId),
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
    private function getGlobalHeaderTemplate(): ?array
    {
        $tpl = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            ['_global_header']
        );
        if (!$tpl) {
            return null;
        }
        $tpl['zones'] = json_decode($tpl['zones'], true) ?? ['main'];
        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];
        return $tpl;
    }

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

    /**
     * Load template-level blocks (header/footer zones) for a template.
     * Returns blocks grouped by zone, each zone as a nested tree.
     */
    private function getTemplateBlocks(array $tpl): array
    {
        // Legacy content_blocks zone system — table no longer exists.
        // Template zones now resolved via canvas_page_id on page_templates.
        return ['header' => [], 'footer' => []];
    }
}
