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
            'SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1',
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

        // Cruinn CMS: if published Cruinn blocks exist, hand off to Cruinn renderer
        $cruinn = new CruinnRenderService();
        if ($cruinn->hasPublished((int) $page['id'])) {
            Template::addGlobal('cruinn_css', $cruinn->buildCss((int) $page['id']));
            $this->render('public/cruinn-page', [
                'title'   => $page['title'],
                'page'    => $page,
                'content' => $cruinn->buildHtml((int) $page['id']),
            ]);
            return;
        }

        $blocks = $this->getBlocks($page['id']);

        $this->render('public/home', [
            'title'  => $page['title'],
            'page'   => $page,
            'blocks' => $blocks,
        ]);
    }

    /**
     * GET /{slug} — Display a published page by its slug.
     */
    public function show(string $slug): void
    {
        $page = $this->db->fetch(
            'SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'published']
        );

        if (!$page) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        $renderMode = $page['render_mode'] ?? 'cruinn';

        // ── File mode: serve raw static HTML file, no layout wrapping ──────
        if ($renderMode === 'file') {
            $filePath = $page['render_file'] ?? '';
            $absPath  = dirname(__DIR__, 2) . '/public' . $filePath;
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

        $tpl = $this->getTemplate($page['template'] ?? 'default');

        // ── Cruinn mode: block renderer ──────────────────────────────────────
        $cruinn = new CruinnRenderService();
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
        Template::addGlobal('page_tpl', $tpl);
        Template::addGlobal('tpl_footer_blocks', $templateBlocks['footer'] ?? []);

        $blocks = $this->getBlocks($page['id']);

        $this->render('public/page', [
            'title'            => $page['title'],
            'meta_description' => $page['meta_description'] ?? '',
            'page'             => $page,
            'blocks'           => $blocks,
            'page_tpl'         => $tpl,
        ]);
    }

    /**
     * Get all content blocks for a page, ordered by sort_order.
     * Each block's JSON `content` field is decoded into a PHP array.
     * Builds a nested tree using parent_block_id.
     */
    private function getBlocks(int $pageId): array
    {
        $blocks = $this->db->fetchAll(
            'SELECT * FROM content_blocks WHERE parent_type = ? AND parent_id = ? ORDER BY sort_order ASC',
            ['page', $pageId]
        );

        foreach ($blocks as &$block) {
            $block['content'] = json_decode($block['content'], true) ?? [];
            $block['settings'] = json_decode($block['settings'] ?? '{}', true) ?? [];
            $block['children'] = [];
        }
        unset($block);

        // Build tree
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
        $tplId = (int)($tpl['id'] ?? 0);
        if ($tplId === 0) {
            return ['header' => [], 'footer' => []];
        }

        $blocks = $this->db->fetchAll(
            'SELECT * FROM content_blocks WHERE parent_type = ? AND parent_id = ? AND zone IN (?, ?) ORDER BY zone, sort_order ASC',
            ['template', $tplId, 'header', 'footer']
        );

        foreach ($blocks as &$block) {
            $block['content'] = json_decode($block['content'], true) ?? [];
            $block['settings'] = json_decode($block['settings'] ?? '{}', true) ?? [];
            $block['children'] = [];
        }
        unset($block);

        // Group by zone and build trees
        $grouped = ['header' => [], 'footer' => []];
        foreach ($blocks as $b) {
            $z = $b['zone'] ?? 'body';
            if (isset($grouped[$z])) {
                $grouped[$z][] = $b;
            }
        }

        // Build trees for each zone (same tree-building as getBlocks)
        foreach ($grouped as $zone => &$zoneBlocks) {
            $indexed = [];
            foreach ($zoneBlocks as &$block) {
                $indexed[$block['id']] = &$block;
            }
            unset($block);

            $tree = [];
            foreach ($zoneBlocks as &$block) {
                $pid = $block['parent_block_id'] ?? null;
                if ($pid && isset($indexed[$pid])) {
                    $indexed[$pid]['children'][] = &$block;
                } else {
                    $tree[] = &$block;
                }
            }
            unset($block);
            $zoneBlocks = $tree;
        }
        unset($zoneBlocks);

        return $grouped;
    }
}
