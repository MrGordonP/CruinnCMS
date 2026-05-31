<?php
/**
 * CruinnCMS — Site Builder Controller
 *
 * Manages the visual page-building layer: pages list, page templates
 * (CRUD + canvas + zone settings + preview), menus, site structure,
 * and the named block library.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Template;
use Cruinn\Controllers\BaseController;

class SiteBuilderController extends BaseController
{
    // ══════════════════════════════════════════════════════════════
    //  PAGES
    // ══════════════════════════════════════════════════════════════

    public function builderPages(): void
    {
        // Content pages — filter by canvas_type when available, fall back to slug prefix for pre-011 instances
        $contentPages = $this->db->fetchAll(
            "SELECT p.*, u.display_name as author_name
             FROM pages_index p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.canvas_type = 'content' OR (p.canvas_type IS NULL AND p.slug NOT LIKE '\\_%')
             ORDER BY p.updated_at DESC"
        );

        $templateCount = (int) ($this->db->fetch('SELECT COUNT(*) AS cnt FROM page_templates')['cnt'] ?? 0);
        $menuCount     = (int) ($this->db->fetch('SELECT COUNT(*) AS cnt FROM menus')['cnt'] ?? 0);

        $homePage   = $this->db->fetch("SELECT id FROM pages_index WHERE slug = 'home' LIMIT 1");
        $homePageId = $homePage ? (int)$homePage['id'] : null;

        $this->renderAdmin('admin/site-builder/pages', [
            'title'         => 'Site Builder',
            'section'       => 'builder',
            'tab'           => 'pages',
            'contentPages'  => $contentPages,
            'templateCount' => $templateCount,
            'menuCount'     => $menuCount,
            'homePageId'    => $homePageId,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  TEMPLATES
    // ══════════════════════════════════════════════════════════════

    public function builderTemplates(): void
    {
        $panel = $this->query('panel', 'page-templates');
        if (!in_array($panel, ['page-templates', 'template-layouts'], true)) {
            $panel = 'page-templates';
        }

        $templates = $this->db->fetchAll(
            'SELECT * FROM page_templates ORDER BY sort_order, name'
        );

        foreach ($templates as &$tpl) {
            $zones = $this->getTemplateDisplayZones((int) ($tpl['id'] ?? 0), (int) ($tpl['layout_page_id'] ?? 0));
            $tpl['zones'] = array_values(array_unique(array_filter(array_map(
                static fn(array $z): string => (string) ($z['zone_name'] ?? ''),
                $zones
            ))));
            if (empty($tpl['zones'])) {
                $tpl['zones'] = ['main'];
            }
            $usage = $this->db->fetch(
                'SELECT COUNT(*) AS cnt FROM pages_index WHERE template = ?',
                [$tpl['slug']]
            );
            $tpl['page_count'] = $usage['cnt'] ?? 0;
        }
        unset($tpl);

                try {
                        $templateLayouts = $this->db->fetchAll(
                                "SELECT p.id, p.title, p.slug, p.status, p.updated_at,
                                                (SELECT COUNT(*) FROM page_templates pt WHERE pt.layout_page_id = p.id) AS usage_count
                                 FROM pages_index p
                                 WHERE p.canvas_type = 'template-shell'
                                     AND p.id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
                                 ORDER BY p.title"
                        );
                } catch (\Throwable $e) {
                        // Pre-canvas_type fallback: detect standalone template layouts by legacy slug prefix.
                        $templateLayouts = $this->db->fetchAll(
                                "SELECT p.id, p.title, p.slug, p.status, p.updated_at,
                                                (SELECT COUNT(*) FROM page_templates pt WHERE pt.layout_page_id = p.id) AS usage_count
                                 FROM pages_index p
                                 WHERE p.slug LIKE '\\_layout\\_%' ESCAPE '\\\\'
                                     AND p.id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
                                 ORDER BY p.title"
                        );
                }

        $data = [
            'title'     => 'Page Templates',
            'section'   => 'builder',
            'tab'       => 'templates',
            'templates' => $templates,
            'templateLayouts' => $templateLayouts,
            'activePanel' => $panel,
        ];
        $this->renderAdmin('admin/site-builder/templates', $data);
    }

    public function builderEditTemplate(string $id): void
    {
        Auth::requireAdmin();
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $this->builderEnsureCanvas($id);
    }

    public function builderCreateTemplate(): void
    {
        $name         = trim($this->input('name', ''));
        $slug         = trim($this->input('slug', ''));
        $description  = trim($this->input('description', ''));
        $cssClass     = trim($this->input('css_class', ''));
        $templateType  = in_array($this->input('template_type', 'page'), ['page', 'content'], true)
                         ? $this->input('template_type', 'page') : 'page';
        $contextSource = $this->sanitiseContextSource($this->input('context_source', ''));
        $layoutPageId  = (int) $this->input('layout_page_id', 0);

        if (!$name || !$slug) {
            Auth::flash('error', 'Name and slug are required.');
            $this->redirect('/admin/templates');
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            Auth::flash('error', 'Slug must contain only lowercase letters, numbers, and hyphens.');
            $this->redirect('/admin/templates');
        }

        $existing = $this->db->fetch('SELECT id FROM page_templates WHERE slug = ?', [$slug]);
        if ($existing) {
            Auth::flash('error', 'A template with that slug already exists.');
            $this->redirect('/admin/templates');
        }

        $cleanZones = ['main'];
        $layoutZones = [];
        if ($templateType === 'page') {
            if ($layoutPageId <= 0) {
                Auth::flash('error', 'Page templates require a Template Layout.');
                $this->redirect('/admin/templates?panel=page-templates');
            }

                        try {
                                $layoutValid = $this->db->fetch(
                                        "SELECT id FROM pages_index
                                         WHERE id = ? AND canvas_type = 'template-shell'
                                             AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
                                         LIMIT 1",
                                        [$layoutPageId]
                                );
                        } catch (\Throwable $e) {
                                $layoutValid = $this->db->fetch(
                                        "SELECT id FROM pages_index
                                         WHERE id = ?
                                             AND slug LIKE '\\_layout\\_%' ESCAPE '\\\\'
                                             AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
                                         LIMIT 1",
                                        [$layoutPageId]
                                );
                        }
            if (!$layoutValid) {
                Auth::flash('error', 'Selected Template Layout is invalid.');
                $this->redirect('/admin/templates?panel=page-templates');
            }

            $layoutZones = $this->getLayoutZonesForTemplate($layoutPageId);
            if (empty($layoutZones)) {
                Auth::flash('error', 'Selected Template Layout has no zone blocks. Add zone blocks to the layout first.');
                $this->redirect('/admin/templates?panel=page-templates');
            }

            $cleanZones = array_values(array_map(
                fn(array $zone): string => (string) $zone['zone_name'],
                $layoutZones
            ));
        } else {
            $layoutPageId = null;
        }

        $hasHeaderZone = in_array('header', $cleanZones, true);
        $hasFooterZone = in_array('footer', $cleanZones, true);

        $maxSort = $this->db->fetch('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM page_templates');

        $templateId = (int) $this->db->insert('page_templates', [
            'slug'           => $slug,
            'name'           => $name,
            'description'    => $description,
            'zones'          => json_encode(['main']),
            'css_class'      => $cssClass,
            'is_system'      => 0,
            'sort_order'     => $maxSort['next_sort'] ?? 99,
            'template_type'  => $templateType,
            'context_source' => $contextSource ?: null,
            'layout_page_id' => $layoutPageId,
        ]);

        if ($templateType === 'page' && !empty($layoutZones)) {
            $this->syncTemplateZoneBlocksForTemplate($templateId, $layoutZones);
        }

        Auth::flash('success', "Template '{$name}' created.");
        $this->redirect('/admin/templates');
    }

    public function builderEnsureCanvas(string $id): void
    {
        Auth::requireAdmin();
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        if (!empty($tpl['canvas_page_id'])) {
            $existing = $this->db->fetch(
                'SELECT id FROM pages_index WHERE id = ? LIMIT 1',
                [(int) $tpl['canvas_page_id']]
            );
            if ($existing) {
                $this->redirect('/admin/editor/' . (int) $existing['id'] . '/edit');
                return;
            }
        }

        $canvasSlug = '_tpl_' . $tpl['slug'];
        $page = $this->db->fetch('SELECT id FROM pages_index WHERE slug = ? LIMIT 1', [$canvasSlug]);

        if ($page) {
            $canvasPageId = (int) $page['id'];
        } else {
            $this->db->execute(
                'INSERT INTO pages_index (title, slug, status, template, editor_mode, canvas_type) VALUES (?, ?, ?, ?, ?, ?)',
                ['Template: ' . $tpl['name'], $canvasSlug, 'published', 'none', 'freeform', 'template-shell']
            );
            $canvasPageId = (int) $this->db->pdo()->lastInsertId();
        }

        $this->db->execute(
            'UPDATE page_templates SET canvas_page_id = ? WHERE id = ?',
            [$canvasPageId, (int) $id]
        );

        $this->redirect('/admin/editor/' . $canvasPageId . '/edit');
    }

    /**
     * POST /admin/templates/{id}/zone-canvas — Ensure a zone canvas page exists for a
     * given template + zone, then assign it in zone_canvases and redirect to the editor.
     */
    public function builderEnsureZoneCanvas(string $id): void
    {
        Auth::requireAdmin();
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $zoneName = trim($this->input('zone_name', ''));
        if (!$zoneName || !preg_match('/^[a-z0-9_-]+$/', $zoneName)) {
            Auth::flash('error', 'Invalid zone name.');
            $this->redirect('/admin/templates');
        }

        $templateId = (int) $id;

        // Find zone block for this zone in the template tree
        $zoneBlock = null;
        try {
            $zoneBlock = $this->db->fetch(
                "SELECT block_id, block_config FROM pages
                 WHERE template_id = ? AND block_type = 'zone' AND parent_block_id IS NULL
                 AND JSON_UNQUOTE(JSON_EXTRACT(block_config, '$.zone_name')) = ?
                 LIMIT 1",
                [$templateId, $zoneName]
            );
        } catch (\Throwable $e) {
            // Migration 012 not applied yet
        }

        if ($zoneBlock) {
            // Zone block exists - check if it has a canvas assigned
            $cfg = json_decode($zoneBlock['block_config'] ?? '{}', true) ?: [];
            $canvasPageId = !empty($cfg['canvas_page_id']) ? (int) $cfg['canvas_page_id'] : null;

            if ($canvasPageId) {
                // Canvas already assigned - go to editor
                $existing = $this->db->fetch('SELECT id FROM pages_index WHERE id = ? LIMIT 1', [$canvasPageId]);
                if ($existing) {
                    $this->redirect('/admin/editor/' . $canvasPageId . '/edit');
                    return;
                }
            }
        }

        // Create a new zone canvas page
        try {
            $this->db->execute(
                'INSERT INTO pages_index (title, slug, status, template, editor_mode, canvas_type, zone_name) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    ucfirst($zoneName) . ' — ' . $tpl['name'],
                    '_zone_' . $tpl['slug'] . '_' . $zoneName,
                    'published',
                    'none',
                    'freeform',
                    'zone',
                    $zoneName,
                ]
            );
        } catch (\Throwable $e) {
            $this->db->execute(
                'INSERT INTO pages_index (title, slug, status, template, editor_mode) VALUES (?, ?, ?, ?, ?)',
                [
                    ucfirst($zoneName) . ' — ' . $tpl['name'],
                    '_zone_' . $tpl['slug'] . '_' . $zoneName,
                    'published',
                    'none',
                    'freeform',
                ]
            );
        }
        $canvasPageId = (int) $this->db->pdo()->lastInsertId();

        if ($zoneBlock) {
            // Zone block exists - update its canvas_page_id
            $cfg = json_decode($zoneBlock['block_config'] ?? '{}', true) ?: [];
            $cfg['canvas_page_id'] = $canvasPageId;
            $this->db->execute(
                'UPDATE pages SET block_config = ? WHERE template_id = ? AND block_id = ?',
                [json_encode($cfg), $templateId, $zoneBlock['block_id']]
            );
        } else {
            // No zone block yet - create it
            $blockId = 'zone-' . $zoneName . '-tpl-' . $templateId;
            $this->db->execute(
                'INSERT INTO pages (block_id, template_id, block_type, block_config, sort_order, parent_block_id)
                 VALUES (?, ?, ?, ?, ?, NULL)',
                [
                    $blockId,
                    $templateId,
                    'zone',
                    json_encode(['zone_name' => $zoneName, 'canvas_page_id' => $canvasPageId]),
                    32767,  // append at end
                ]
            );
        }

        $this->redirect('/admin/editor/' . $canvasPageId . '/edit');
    }

    public function builderDeleteTemplate(string $id): void
    {
        $id = (int) $id;

        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        if ($tpl['is_system']) {
            Auth::flash('error', 'Cannot delete system templates.');
            $this->redirect('/admin/templates');
        }

        $usage = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM pages_index WHERE template = ?',
            [$tpl['slug']]
        );
        if (($usage['cnt'] ?? 0) > 0) {
            Auth::flash('error', "Cannot delete: {$usage['cnt']} page(s) use this template.");
            $this->redirect('/admin/templates');
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->execute('DELETE FROM page_templates WHERE id = ?', [$id]);
            $stillExists = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM page_templates WHERE id = ?', [$id]);
            if ($stillExists > 0) {
                throw new \RuntimeException('Template row still exists after delete.');
            }
            $pdo->commit();
            Auth::flash('success', "Template '{$tpl['name']}' deleted.");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Auth::flash('error', 'Template could not be deleted: ' . $e->getMessage());
        }

        $this->redirect('/admin/templates');
    }

    /**
     * POST /admin/templates/layouts/new
     * Create a standalone template layout page (canvas_type=template-shell).
     */
    public function builderCreateTemplateLayout(): void
    {
        $title = trim($this->input('title', ''));
        $slugInput = trim($this->input('slug', ''));

        if ($title === '') {
            Auth::flash('error', 'Layout title is required.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }

        if ($slugInput !== '' && !preg_match('/^[a-z0-9_-]+$/', $slugInput)) {
            Auth::flash('error', 'Layout slug must contain only lowercase letters, numbers, hyphens, or underscores.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }

        $baseSlug = $slugInput !== ''
            ? trim($slugInput, '-_')
            : $this->generateUniqueSlug('layout-' . $title);
        if ($baseSlug === '') {
            $baseSlug = 'layout-' . date('YmdHis');
        }

        $slug = '_layout_' . $baseSlug;
        $suffix = 2;
        while ($this->db->fetchColumn('SELECT COUNT(*) FROM pages_index WHERE slug = ?', [$slug]) > 0) {
            $slug = '_layout_' . $baseSlug . '-' . $suffix;
            $suffix++;
        }

        try {
            $pageId = (int) $this->db->insert('pages_index', [
                'title'       => $title,
                'slug'        => $slug,
                'status'      => 'published',
                'template'    => 'none',
                'editor_mode' => 'freeform',
                'canvas_type' => 'template-shell',
                'created_by'  => Auth::userId(),
            ]);
        } catch (\Throwable $e) {
            // Pre-canvas_type fallback.
            $pageId = (int) $this->db->insert('pages_index', [
                'title'       => $title,
                'slug'        => $slug,
                'status'      => 'published',
                'template'    => 'none',
                'editor_mode' => 'freeform',
                'created_by'  => Auth::userId(),
            ]);
        }

        Auth::flash('success', "Template layout '{$title}' created.");
        $this->redirect('/admin/editor/' . $pageId . '/edit');
    }

    /**
     * POST /admin/templates/layouts/{id}/delete
     * Delete a standalone template layout page if no templates reference it.
     */
    public function builderDeleteTemplateLayout(string $id): void
    {
        $layoutId = (int) $id;

        $layout = null;
        $hasCanvasType = true;
        try {
            $layout = $this->db->fetch(
                "SELECT id, title, slug, canvas_type FROM pages_index WHERE id = ? LIMIT 1",
                [$layoutId]
            );
        } catch (\Throwable $e) {
            $hasCanvasType = false;
            $layout = $this->db->fetch(
                "SELECT id, title, slug FROM pages_index WHERE id = ? LIMIT 1",
                [$layoutId]
            );
        }
        if (!$layout) {
            Auth::flash('error', 'Template layout not found.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }
        if ($hasCanvasType && ($layout['canvas_type'] ?? '') !== 'template-shell') {
            Auth::flash('error', 'Template layout not found.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }
        if (!$hasCanvasType && !str_starts_with((string) ($layout['slug'] ?? ''), '_layout_')) {
            Auth::flash('error', 'Template layout not found.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }

        $isTemplateCanvas = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM page_templates WHERE canvas_page_id = ?',
            [$layoutId]
        );
        if ($isTemplateCanvas > 0) {
            Auth::flash('error', 'This page is a template canvas and cannot be deleted as a standalone layout.');
            $this->redirect('/admin/templates?panel=template-layouts');
        }

        $usage = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM page_templates WHERE layout_page_id = ?',
            [$layoutId]
        );
        if ($usage > 0) {
            Auth::flash('error', "Cannot delete: {$usage} template(s) currently use this layout.");
            $this->redirect('/admin/templates?panel=template-layouts');
        }

        // Delete page-owned blocks for both current and transitional schemas.
        $this->db->delete('pages_draft', 'page_id = ?', [$layoutId]);
        $this->db->delete('pages', 'page_id = ?', [$layoutId]);
        $this->db->delete('pages_index', 'id = ?', [$layoutId]);

        Auth::flash('success', "Template layout '{$layout['title']}' deleted.");
        $this->redirect('/admin/templates?panel=template-layouts');
    }

    public function builderGlobalHeader(): void
    {
        $tpl = $this->getOrCreateGlobalHeader();
        $this->redirect('/admin/templates/' . $tpl['id'] . '/edit');
    }

    private function getOrCreateGlobalHeader(): array
    {
        $tpl = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            ['_global_header']
        );
        if (!$tpl) {
            $id = $this->db->insert('page_templates', [
                'name'        => 'Default Header',
                'slug'        => '_global_header',
                'description' => 'Global default header — blocks in the Header zone appear on all pages whose template uses the Auto header source.',
                'zones'       => json_encode(['main']),
                'css_class'   => '',
                'is_system'   => 1,
                'sort_order'  => 0,
                'settings'    => json_encode([
                    'show_header'   => true,
                    'show_footer'   => false,
                    'header_source' => 'custom',
                ]),
            ]);
            $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        }
        return $tpl;
    }

    public function builderGlobalFooter(): void
    {
        $tpl = $this->getOrCreateGlobalFooter();
        $this->redirect('/admin/templates/' . $tpl['id'] . '/edit');
    }

    public function builderGlobalSidebar(): void
    {
        $tpl = $this->getOrCreateGlobalSidebar();
        $this->redirect('/admin/templates/' . $tpl['id'] . '/edit');
    }

    private function getOrCreateGlobalFooter(): array
    {
        $tpl = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            ['_global_footer']
        );
        if (!$tpl) {
            $id = $this->db->insert('page_templates', [
                'name'        => 'Default Footer',
                'slug'        => '_global_footer',
                'description' => 'Global default footer — blocks in the Footer zone appear on all pages whose template uses the Auto footer source.',
                'zones'       => json_encode(['main']),
                'css_class'   => '',
                'is_system'   => 1,
                'sort_order'  => 1,
                'settings'    => json_encode([
                    'show_header'   => false,
                    'show_footer'   => true,
                    'footer_source' => 'custom',
                ]),
            ]);
            $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        }
        return $tpl;
    }

    private function getOrCreateGlobalSidebar(): array
    {
        $tpl = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            ['_global_sidebar']
        );
        if (!$tpl) {
            $id = $this->db->insert('page_templates', [
                'name'        => 'Default Sidebar',
                'slug'        => '_global_sidebar',
                'description' => 'Global default sidebar — blocks in the Sidebar zone appear on templates using a sidebar source template.',
                'zones'       => json_encode(['main']),
                'css_class'   => '',
                'is_system'   => 1,
                'sort_order'  => 2,
                'settings'    => json_encode([
                    'show_header'    => false,
                    'show_footer'    => false,
                    'sidebar_source' => 'custom',
                ]),
            ]);
            $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        }
        return $tpl;
    }

    public function builderPreviewTemplate(string $id): void
    {
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $zoneRows = $this->getTemplateDisplayZones((int) ($tpl['id'] ?? 0), (int) ($tpl['layout_page_id'] ?? 0));
        $tpl['zones'] = array_values(array_unique(array_filter(array_map(
            static fn(array $z): string => (string) ($z['zone_name'] ?? ''),
            $zoneRows
        ))));
        if (empty($tpl['zones'])) {
            $tpl['zones'] = ['main'];
        }
        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];

        // content_blocks table no longer exists — template-attached blocks
        // were part of the pre-Cruinn editor and were never migrated.
        $blocks = [];

        foreach ($blocks as &$block) {
            $block['content'] = json_decode($block['content'], true) ?? [];
            $block['settings'] = json_decode($block['settings'] ?? '{}', true) ?? [];
            $block['children'] = [];
        }
        unset($block);

        $grouped = ['header' => [], 'body' => [], 'footer' => []];
        foreach ($blocks as $b) {
            $z = $b['zone'] ?? 'body';
            if (!isset($grouped[$z])) $grouped[$z] = [];
            $grouped[$z][] = $b;
        }

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

        Template::addGlobal('page_tpl', $tpl);

        $this->render('admin/site-builder/template-preview', [
            'title'      => 'Preview: ' . $tpl['name'],
            'tpl'        => $tpl,
            'bodyBlocks' => $grouped['body'],
            'page_tpl'   => $tpl,
        ]);
    }

    public function builderUpdateTemplate(string $id): void
    {
        $id  = (int) $id;
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $name          = trim($this->input('name', ''));
        $description   = trim($this->input('description', ''));
        $cssClass      = trim($this->input('css_class', ''));
        $sortOrder     = (int) $this->input('sort_order', $tpl['sort_order']);
        $contextSource = $this->sanitiseContextSource($this->input('context_source', $tpl['context_source'] ?? ''));

        if (!$name) {
            Auth::flash('error', 'Name is required.');
            $this->redirect("/admin/templates/{$id}/edit");
        }

        $zoneRows = $this->getTemplateDisplayZones((int) $id, (int) ($tpl['layout_page_id'] ?? 0));
        $zoneNames = array_values(array_unique(array_filter(array_map(
            static fn(array $z): string => (string) ($z['zone_name'] ?? ''),
            $zoneRows
        ))));
        if (empty($zoneNames)) {
            $zoneNames = ['main'];
        }
        $hasHeaderZone = in_array('header', $zoneNames, true);
        $hasFooterZone = in_array('footer', $zoneNames, true);

        $slug = $tpl['slug'];
        if (!$tpl['is_system']) {
            $newSlug = trim($this->input('slug', $slug));
            if ($newSlug && preg_match('/^[a-z0-9\-]+$/', $newSlug)) {
                $existing = $this->db->fetch(
                    'SELECT id FROM page_templates WHERE slug = ? AND id != ?',
                    [$newSlug, $id]
                );
                if (!$existing) {
                    if ($newSlug !== $slug) {
                        $this->db->update('pages_index', ['template' => $newSlug], 'template = ?', [$slug]);
                    }
                    $slug = $newSlug;
                } else {
                    Auth::flash('error', 'A template with that slug already exists.');
                    $this->redirect("/admin/templates/{$id}/edit");
                }
            }
        }

        $settings = [
            'show_title'       => (bool) $this->input('show_title', false),
            'show_header'      => $hasHeaderZone,
            'show_footer'      => $hasFooterZone,
            'show_breadcrumbs' => (bool) $this->input('show_breadcrumbs', false),
            'content_width'    => in_array($this->input('content_width', 'default'), ['default', 'narrow', 'wide', 'full'], true)
                                    ? $this->input('content_width', 'default') : 'default',
            'title_align'      => in_array($this->input('title_align', 'left'), ['left', 'center', 'right'], true)
                                    ? $this->input('title_align', 'left') : 'left',
            'sidebar_position' => in_array($this->input('sidebar_position', 'right'), ['left', 'right'], true)
                                    ? $this->input('sidebar_position', 'right') : 'right',
            'header_source'    => $this->resolveHeaderSource($this->input('header_source', 'default')),
            'sidebar_source'   => $this->resolveSidebarSource($this->input('sidebar_source', 'default')),
        ];

        $existingSettings = json_decode($tpl['settings'] ?? '{}', true) ?: [];
        foreach (['zone_header', 'zone_body', 'zone_footer'] as $zk) {
            if (isset($existingSettings[$zk])) {
                $settings[$zk] = $existingSettings[$zk];
            }
        }

        $this->db->update('page_templates', [
            'name'           => $name,
            'slug'           => $slug,
            'description'    => $description,
            'css_class'      => $cssClass,
            'sort_order'     => $sortOrder,
            'settings'       => json_encode($settings),
            'context_source' => $contextSource ?: null,
        ], 'id = ?', [$id]);

        Auth::flash('success', "Template '{$name}' updated.");
        $this->redirect("/admin/templates/{$id}/edit");
    }

    private function resolveHeaderSource(string $value): string
    {
        if (in_array($value, ['default', 'custom'], true)) {
            return $value;
        }
        // Allow any valid template slug that actually has a header zone.
        if (preg_match('/^[a-z0-9_\-]+$/', $value)) {
            if ($this->templateSlugHasZone($value, 'header')) {
                return $value;
            }
        }
        return 'default';
    }

    private function resolveSidebarSource(string $value): string
    {
        if (in_array($value, ['default', 'custom'], true)) {
            return $value;
        }
        // Allow any valid template slug that actually has a sidebar zone.
        if (preg_match('/^[a-z0-9_\-]+$/', $value)) {
            if ($this->templateSlugHasZone($value, 'sidebar')) {
                return $value;
            }
        }
        return 'default';
    }

    /**
     * Sanitise a context_source value.
     * Valid formats: 'content_set:{slug}' or a dotted identifier like 'blog.post'.
     * Returns empty string if invalid.
     */
    private function sanitiseContextSource(string $value): string
    {
        $value = trim($value);
        if ($value === '') { return ''; }
        if (preg_match('/^content_set:[a-z0-9_\-]+$/', $value)) { return $value; }
        if (preg_match('/^[a-z0-9_\-]+\.[a-z0-9_\-]+$/', $value)) { return $value; }
        return '';
    }

    private function templateSlugHasZone(string $slug, string $zoneName): bool
    {
        $tpl = $this->db->fetch(
            'SELECT id, layout_page_id FROM page_templates WHERE slug = ? LIMIT 1',
            [$slug]
        );
        if (!$tpl) {
            return false;
        }

        $zones = $this->getTemplateDisplayZones((int) ($tpl['id'] ?? 0), (int) ($tpl['layout_page_id'] ?? 0));
        foreach ($zones as $z) {
            if (($z['zone_name'] ?? null) === $zoneName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read top-level zone blocks from a template layout page.
     */
    private function getLayoutZonesForTemplate(int $layoutPageId): array
    {
        if ($layoutPageId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT block_id, block_config, inner_html, css_props, css_props_tablet, css_props_mobile, sort_order
             FROM pages
             WHERE page_id = ? AND block_type = 'zone' AND parent_block_id IS NULL
             ORDER BY sort_order ASC",
            [$layoutPageId]
        );

        $zones = [];
        foreach ($rows as $row) {
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $zoneName = $cfg['zone_name'] ?? null;
            if (!$zoneName || !preg_match('/^[a-z0-9_-]+$/', (string) $zoneName)) {
                continue;
            }

            $zones[] = [
                'block_id' => $row['block_id'],
                'inner_html' => $row['inner_html'] ?? null,
                'css_props' => $row['css_props'] ?? null,
                'css_props_tablet' => $row['css_props_tablet'] ?? null,
                'css_props_mobile' => $row['css_props_mobile'] ?? null,
                'block_config' => $row['block_config'] ?? '{}',
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'zone_name' => (string) $zoneName,
            ];
        }

        return $zones;
    }

    /**
     * Ensure the template has zone assignment blocks matching its layout zones.
     */
    private function syncTemplateZoneBlocksForTemplate(int $templateId, array $layoutZones): void
    {
        if ($templateId <= 0) {
            return;
        }

        $existingRows = $this->db->fetchAll(
            "SELECT block_id, block_config FROM pages
             WHERE template_id = ? AND block_type = 'zone' AND parent_block_id IS NULL",
            [$templateId]
        );

        $existingZones = [];
        foreach ($existingRows as $row) {
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $zoneName = $cfg['zone_name'] ?? null;
            if (is_string($zoneName) && $zoneName !== '') {
                $existingZones[$zoneName] = [
                    'block_id' => (string) ($row['block_id'] ?? ''),
                    'block_config' => $cfg,
                ];
            }
        }

        $layoutZoneNames = [];
        foreach ($layoutZones as $zone) {
            $zoneName = $zone['zone_name'] ?? null;
            if (is_string($zoneName) && $zoneName !== '') {
                $layoutZoneNames[$zoneName] = true;
            }
        }

        // Remove zones that no longer exist in the selected layout.
        foreach ($existingZones as $zoneName => $meta) {
            if (!isset($layoutZoneNames[$zoneName])) {
                $this->db->execute(
                    'DELETE FROM pages WHERE template_id = ? AND block_id = ?',
                    [$templateId, $meta['block_id']]
                );
            }
        }

        foreach ($layoutZones as $index => $zone) {
            $zoneName = $zone['zone_name'] ?? null;
            if (!$zoneName) {
                continue;
            }

            $layoutCfg = json_decode($zone['block_config'] ?? '{}', true) ?: [];
            $layoutCfg['zone_name'] = $zoneName;

            if (isset($existingZones[$zoneName])) {
                $existingMeta = $existingZones[$zoneName];
                $mergedCfg = $layoutCfg;
                if (isset($existingMeta['block_config']['canvas_page_id'])) {
                    $mergedCfg['canvas_page_id'] = (int) $existingMeta['block_config']['canvas_page_id'];
                }

                $this->db->execute(
                    'UPDATE pages
                        SET block_config = ?, inner_html = ?, css_props = ?, css_props_tablet = ?, css_props_mobile = ?, sort_order = ?
                      WHERE template_id = ? AND block_id = ?',
                    [
                        json_encode($mergedCfg),
                        $zone['inner_html'] ?? null,
                        $zone['css_props'] ?? null,
                        $zone['css_props_tablet'] ?? null,
                        $zone['css_props_mobile'] ?? null,
                        $index,
                        $templateId,
                        $existingMeta['block_id'],
                    ]
                );
                continue;
            }

            $this->db->execute(
                'INSERT INTO pages (block_id, template_id, block_type, inner_html, css_props, css_props_tablet, css_props_mobile, block_config, sort_order, parent_block_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)',
                [
                    'tpl-zone-' . $templateId . '-' . $zoneName,
                    $templateId,
                    'zone',
                    $zone['inner_html'] ?? null,
                    $zone['css_props'] ?? null,
                    $zone['css_props_tablet'] ?? null,
                    $zone['css_props_mobile'] ?? null,
                    json_encode($layoutCfg),
                    $index,
                ]
            );
        }
    }

    public function builderUpdateZoneSettings(string $id): void
    {
        $id = (int) $id;
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [$id]);
        if (!$tpl) {
            $this->json(['error' => 'Template not found'], 404);
        }

        $zone = $this->input('zone', '');
        if (!in_array($zone, ['header', 'body', 'footer'], true)) {
            $this->json(['error' => 'Invalid zone'], 400);
        }

        $zoneSettings = json_decode($this->input('settings', '{}'), true);
        if (!is_array($zoneSettings)) {
            $zoneSettings = [];
        }

        $settings = json_decode($tpl['settings'] ?? '{}', true) ?: [];
        $settings['zone_' . $zone] = $zoneSettings;

        $this->db->update('page_templates', [
            'settings' => json_encode($settings),
        ], 'id = ?', [$id]);

        $this->json(['success' => true]);
    }

    // ══════════════════════════════════════════════════════════════
    //  MENUS + STRUCTURE
    // ══════════════════════════════════════════════════════════════

    public function builderMenus(): void
    {
        $menus = $this->db->fetchAll('SELECT * FROM menus ORDER BY name');

        foreach ($menus as &$menu) {
            $count = $this->db->fetch(
                'SELECT COUNT(*) AS cnt FROM menu_items WHERE menu_id = ?',
                [$menu['id']]
            );
            $menu['item_count'] = $count['cnt'] ?? 0;
        }
        unset($menu);

        $data = [
            'title'   => 'Menus',
            'section' => 'builder',
            'tab'     => 'menus',
            'menus'   => $menus,
        ];
        $this->renderAdmin('admin/site-builder/menus', $data);
    }

    public function builderStructure(): void
    {
        // Content pages only (exclude internal zone pages whose slug starts with _)
        $contentPages = $this->db->fetchAll(
            "SELECT p.*, u.display_name AS author_name
             FROM pages_index p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.slug NOT LIKE '\_%'
             ORDER BY p.slug"
        );

        // Zone canvas pages (header, footer, sidebar etc.)
        try {
            $zoneCanvases = $this->db->fetchAll(
                "SELECT id, title, slug, zone_name FROM pages_index WHERE canvas_type = 'zone' ORDER BY slug"
            );
        } catch (\Throwable $e) {
            $zoneCanvases = $this->db->fetchAll(
                "SELECT id, title, slug, NULL AS zone_name
                 FROM pages_index
                 WHERE slug LIKE '\\_zone\\_%' ESCAPE '\\\\'
                 ORDER BY slug"
            );
        }
        foreach ($zoneCanvases as &$zoneCanvas) {
            if (!empty($zoneCanvas['zone_name'])) {
                continue;
            }
            $slug = (string) ($zoneCanvas['slug'] ?? '');
            if (str_starts_with($slug, '_zone_')) {
                $zoneCanvas['zone_name'] = substr($slug, 6);
            }
        }
        unset($zoneCanvas);

        // Menus with item counts
        $menus = $this->db->fetchAll('SELECT * FROM menus ORDER BY location');
        foreach ($menus as &$menu) {
            $count = $this->db->fetch(
                'SELECT COUNT(*) AS cnt FROM menu_items WHERE menu_id = ?',
                [$menu['id']]
            );
            $menu['item_count'] = $count['cnt'] ?? 0;
        }
        unset($menu);

        // Menu items keyed by menu_id for right-panel display
        $allMenuItems = $this->db->fetchAll(
            "SELECT mi.*, p.title AS page_title
             FROM menu_items mi
             LEFT JOIN pages_index p ON mi.page_id = p.id
             ORDER BY mi.menu_id, mi.sort_order"
        );
        $menuItemsByMenu = [];
        foreach ($allMenuItems as $item) {
            $menuItemsByMenu[(int)$item['menu_id']][] = $item;
        }

        // Templates with parsed settings and row-derived zones (for header/footer/sidebar resolution)
        $templates = $this->db->fetchAll('SELECT id, slug, name, layout_page_id, settings FROM page_templates ORDER BY sort_order');
        foreach ($templates as &$tpl) {
            $zones = $this->getTemplateDisplayZones((int) ($tpl['id'] ?? 0), (int) ($tpl['layout_page_id'] ?? 0));
            $tpl['zones'] = array_values(array_unique(array_filter(array_map(
                static fn(array $z): string => (string) ($z['zone_name'] ?? ''),
                $zones
            ))));
            if (empty($tpl['zones'])) {
                $tpl['zones'] = ['main'];
            }
        }
        unset($tpl);

        $homePageIdRow = $this->db->fetch("SELECT value FROM settings WHERE `key` = 'site.home_page_id' LIMIT 1");
        $homePageId    = $homePageIdRow ? (int)$homePageIdRow['value'] : 0;

        $data = [
            'title'          => 'Site Structure',
            'section'        => 'builder',
            'tab'            => 'structure',
            'contentPages'   => $contentPages,
            'zoneCanvases'   => $zoneCanvases,
            'menus'          => $menus,
            'menuItemsByMenu'=> $menuItemsByMenu,
            'templates'      => $templates,
            'homePageId'     => $homePageId,
        ];
        $this->renderAdmin('admin/site-builder/structure', $data);
    }

    public function builderZones(): void
    {
        Auth::requireAdmin();
        try {
            $zones = $this->db->fetchAll(
                "SELECT id, title, zone_name, status, updated_at
                   FROM pages_index
                  WHERE canvas_type = 'zone'
                  ORDER BY zone_name"
            );
        } catch (\Throwable $e) {
            $zones = $this->db->fetchAll(
                "SELECT id, title,
                        SUBSTRING(slug, 7) AS zone_name,
                        status,
                        updated_at
                   FROM pages_index
                  WHERE slug LIKE '\\_zone\\_%' ESCAPE '\\\\'
                  ORDER BY zone_name"
            );
        }
        $this->renderAdmin('admin/site-builder/zones', [
            'title'  => 'Zone Canvases',
            'section'=> 'builder',
            'tab'    => 'zones',
            'zones'  => $zones,
        ]);
    }

    public function builderNewZone(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $title    = trim($this->input('title', ''));
        $zoneName = trim(strtolower($this->input('zone_name', '')));

        if ($title === '' || !preg_match('/^[a-z0-9_-]+$/', $zoneName)) {
            Auth::flash('error', 'Zone name must be lowercase letters, numbers, hyphens or underscores. Title is required.');
            $this->redirect('/admin/site-builder/zones');
        }

        // Check for duplicate zone_name
        try {
            $existing = $this->db->fetch(
                "SELECT id FROM pages_index WHERE canvas_type = 'zone' AND zone_name = ? LIMIT 1",
                [$zoneName]
            );
        } catch (\Throwable $e) {
            $existing = $this->db->fetch(
                "SELECT id FROM pages_index WHERE slug = ? LIMIT 1",
                ['_zone_' . $zoneName]
            );
        }
        if ($existing) {
            Auth::flash('error', 'A zone canvas with that zone name already exists.');
            $this->redirect('/admin/site-builder/zones');
        }

        $slug = '_zone_' . $zoneName;
        try {
            $this->db->execute(
                "INSERT INTO pages_index (title, slug, status, template, editor_mode, canvas_type, zone_name)
                 VALUES (?, ?, 'published', 'none', 'freeform', 'zone', ?)",
                [$title, $slug, $zoneName]
            );
        } catch (\Throwable $e) {
            $this->db->execute(
                "INSERT INTO pages_index (title, slug, status, template, editor_mode)
                 VALUES (?, ?, 'published', 'none', 'freeform')",
                [$title, $slug]
            );
        }
        $newId = (int) $this->db->pdo()->lastInsertId();

        $this->redirect('/admin/editor/' . $newId . '/edit');
    }

    public function setHomePage(int $id): void
    {
        $page = $this->db->fetch('SELECT id FROM pages_index WHERE id = ? AND slug NOT LIKE \'\\_%\' LIMIT 1', [$id]);
        if (!$page) {
            $this->json(['success' => false, 'error' => 'Page not found']);
            return;
        }
        $this->db->execute(
            "INSERT INTO settings (`key`, value, `group`) VALUES ('site.home_page_id', ?, 'site')
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [(string)$id]
        );
        $this->json(['success' => true]);
    }

    // ══════════════════════════════════════════════════════════════
    //  NAMED BLOCK LIBRARY
    // ══════════════════════════════════════════════════════════════

    public function namedBlockList(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, slug, description, root_type, thumbnail_url, created_at
             FROM named_blocks ORDER BY name ASC'
        );

        $wantsJson = ($this->query('format', '') === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($wantsJson) {
            $this->json(['success' => true, 'blocks' => $rows]);
        }

        // Browser navigation should not land on raw API output.
        Auth::flash('info', 'Named Blocks currently uses a JSON API endpoint. Redirected to templates.');
        $this->redirect('/admin/templates?panel=page-templates');
    }

    private function getTemplateZoneAssignments(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT block_id, block_config, sort_order
             FROM pages
             WHERE template_id = ? AND block_type = 'zone' AND parent_block_id IS NULL
             ORDER BY sort_order ASC",
            [$templateId]
        );

        $zones = [];
        foreach ($rows as $row) {
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $zoneName = $cfg['zone_name'] ?? null;
            if (!$zoneName || !preg_match('/^[a-z0-9_-]+$/', (string) $zoneName)) {
                continue;
            }
            $zones[(string) $zoneName] = $cfg + [
                'block_id' => $row['block_id'],
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $zones;
    }

    private function getTemplateDisplayZones(int $templateId, int $layoutPageId): array
    {
        $layoutZones = $this->getLayoutZonesForTemplate($layoutPageId);
        if (!empty($layoutZones)) {
            return $layoutZones;
        }

        $assignments = $this->getTemplateZoneAssignments($templateId);

        $zones = [];
        foreach ($assignments as $zoneName => $cfg) {
            $zones[] = [
                'block_id' => $cfg['block_id'] ?? ('zone-' . $zoneName),
                'block_config' => json_encode($cfg),
                'sort_order' => (int) ($cfg['sort_order'] ?? 0),
                'zone_name' => $zoneName,
            ];
        }

        return $zones;
    }

    private function getTemplateZonesByTemplateId(int $templateId): array
    {
        if ($templateId <= 0) {
            return ['main'];
        }

        $layoutPageId = (int) ($this->db->fetchColumn(
            'SELECT layout_page_id FROM page_templates WHERE id = ? LIMIT 1',
            [$templateId]
        ) ?: 0);

        $zones = $this->getTemplateDisplayZones($templateId, $layoutPageId);
        $names = [];
        foreach ($zones as $zone) {
            $zn = (string) ($zone['zone_name'] ?? '');
            if ($zn !== '' && !in_array($zn, $names, true)) {
                $names[] = $zn;
            }
        }

        return !empty($names) ? $names : ['main'];
    }

    public function namedBlockSave(): void
    {
        $name  = trim($_POST['name'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $type  = trim($_POST['root_type'] ?? 'section');
        $thumb = trim($_POST['thumbnail_url'] ?? '');
        $id    = (int)($_POST['id'] ?? 0);

        $sourceBlockId = (int)($_POST['source_block_id'] ?? 0);
        if ($sourceBlockId) {
            // content_blocks table no longer exists — source_block_id import
            // was part of the pre-Cruinn editor and is not yet migrated.
            $this->json(['error' => 'Source block import not yet available'], 501);
            return;
        } else {
            $treeJson = $_POST['tree_snapshot'] ?? '';
        }

        if (!$name || !$slug || !$treeJson) {
            $this->json(['error' => 'name, slug, and tree_snapshot are required'], 400);
            return;
        }
        if (json_decode($treeJson) === null) {
            $this->json(['error' => 'tree_snapshot must be valid JSON'], 400);
            return;
        }

        if ($id) {
            $this->db->execute(
                'UPDATE named_blocks SET name=?, slug=?, description=?, root_type=?,
                 tree_snapshot=?, thumbnail_url=?, updated_at=NOW()
                 WHERE id=?',
                [$name, $slug, $desc, $type, $treeJson, $thumb, $id]
            );
        } else {
            $this->db->execute(
                'INSERT INTO named_blocks (name, slug, description, root_type, tree_snapshot, thumbnail_url)
                 VALUES (?,?,?,?,?,?)',
                [$name, $slug, $desc, $type, $treeJson, $thumb]
            );
            $id = $this->db->lastInsertId();
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    public function namedBlockDelete(string $id): void
    {
        $this->db->execute('DELETE FROM named_blocks WHERE id=?', [(int)$id]);
        $this->json(['success' => true]);
    }

    private function fetchBlockChildrenRecursive(int $blockId): array
    {
        // content_blocks table no longer exists — stub until migrated
        return [];
    }

    // ══════════════════════════════════════════════════════════════
    //  PHP TEMPLATE EDITOR
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/template-editor
     * Lists all editable PHP view templates, grouped by folder.
     * Excludes admin/, platform/, and dashboard.php (internal ACP views).
     */
    public function templateEditorList(): void
    {
        Auth::requireAdmin();

        $base    = dirname(__DIR__, 3) . '/templates';
        $exclude = ['/admin/', '/platform/'];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        $groups = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

            // Exclude internal views
            $skip = false;
            foreach ($exclude as $ex) {
                if (str_contains('/' . $rel, $ex)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Group by top-level folder (or root)
            $parts  = explode('/', $rel);
            $group  = count($parts) > 1 ? $parts[0] : 'root';
            $groups[$group][] = $rel;
        }
        ksort($groups);
        foreach ($groups as &$g) {
            sort($g);
        }
        unset($g);

        $this->renderAdmin('admin/site-builder/template-editor-list', [
            'title'   => 'PHP Templates',
            'section' => 'builder',
            'tab'     => 'php-templates',
            'groups'  => $groups,
        ]);
    }

    /**
     * GET  /admin/template-editor/edit?f={relative-path}
     * POST /admin/template-editor/edit?f={relative-path}
     * Displays and saves a single PHP template file.
     * Path is strictly sandboxed to templates/ — no traversal allowed.
     */
    public function templateEditorEdit(): void
    {
        Auth::requireAdmin();

        $raw       = $_REQUEST['f'] ?? '';
        $returnRaw = $_REQUEST['return'] ?? '';
        // Validate return URL — only allow internal admin paths
        $returnUrl = (str_starts_with($returnRaw, '/admin/') && !str_contains($returnRaw, '..') && !str_contains($returnRaw, "\0")) ? $returnRaw : null;

        // @css/ prefix → file is in public/css/
        if (str_starts_with($raw, '@css/')) {
            $cssBase = realpath(CRUINN_PUBLIC . '/css');
            $rel     = substr($raw, 5); // strip @css/
            if ($rel === '' || str_contains($rel, '/') || str_contains($rel, "\0") || str_contains($rel, '..')) {
                Auth::flash('error', 'Invalid CSS file path.');
                $this->redirect('/admin/template-editor');
            }
            $fullPath = realpath($cssBase . '/' . $rel);
            if ($fullPath === false || !str_starts_with($fullPath, $cssBase . DIRECTORY_SEPARATOR)) {
                Auth::flash('error', 'CSS file not found or access denied.');
                $this->redirect('/admin/template-editor');
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                \Cruinn\CSRF::verify();
                $content = $_POST['content'] ?? '';
                file_put_contents($fullPath, $content);
                Auth::flash('success', 'CSS file saved.');
                $qs = '/admin/template-editor/edit?f=' . rawurlencode('@css/' . $rel);
                if ($returnUrl) $qs .= '&return=' . rawurlencode($returnUrl);
                $this->redirect($qs);
            }
            $content = file_get_contents($fullPath);
            $this->renderAdmin('admin/site-builder/template-editor-edit', [
                'title'     => 'Edit CSS: ' . $rel,
                'section'   => 'builder',
                'tab'       => 'php-templates',
                'rel'       => '@css/' . $rel,
                'content'   => $content,
                'returnUrl' => $returnUrl,
            ]);
            return;
        }

        $base    = realpath(dirname(__DIR__, 3) . '/templates');
        $exclude = ['/admin/', '/platform/'];

        $rel  = ltrim($raw, '/');
        // Reject path traversal attempts
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            Auth::flash('error', 'Invalid file path.');
            $this->redirect('/admin/template-editor');
        }

        $fullPath = realpath($base . '/' . $rel);
        if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            Auth::flash('error', 'File not found or access denied.');
            $this->redirect('/admin/template-editor');
        }

        foreach ($exclude as $ex) {
            if (str_contains('/' . $rel, $ex)) {
                Auth::flash('error', 'That template is not editable here.');
                $this->redirect('/admin/template-editor');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \Cruinn\CSRF::verify();
            $content = $_POST['content'] ?? '';
            file_put_contents($fullPath, $content);
            // JSON save (from editor code view)
            if (isset($_POST['_json']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            Auth::flash('success', 'Template saved.');
            $qs = '/admin/template-editor/edit?f=' . rawurlencode($rel);
            if ($returnUrl) $qs .= '&return=' . rawurlencode($returnUrl);
            $this->redirect($qs);
        }

        $content = file_get_contents($fullPath);

        // JSON content request (from editor code view)
        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['content' => $content, 'rel' => $rel]);
            exit;
        }

        $this->renderAdmin('admin/site-builder/template-editor-edit', [
            'title'     => 'Edit: ' . $rel,
            'section'   => 'builder',
            'tab'       => 'php-templates',
            'rel'       => $rel,
            'content'   => $content,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * GET /admin/template-editor/vars?f=path
     * Returns a JSON array of variable names detected in the given template file.
     * Used by the php-include block type JS to auto-populate the properties panel.
     */
    public function templateEditorVars(): void
    {
        Auth::requireAdmin();

        $base    = realpath(dirname(__DIR__, 3) . '/templates');
        $exclude = ['/admin/', '/platform/'];
        $rel     = ltrim($_GET['f'] ?? '', '/');

        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            $this->json(['vars' => []]);
        }
        $fullPath = realpath($base . '/' . $rel);
        if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            $this->json(['vars' => []]);
        }
        foreach ($exclude as $ex) {
            if (str_contains('/' . $rel, $ex)) {
                $this->json(['vars' => []]);
            }
        }

        $source = file_get_contents($fullPath);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $skip = array_unique(array_merge(
            ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_SESSION',
             '_COOKIE', '_FILES', '_ENV', '_REQUEST', 'db'],
            array_keys(\Cruinn\Template::globals()),
            [
                'current_user', 'user', 'member', 'address', 'errors', 'old', 'flashes',
                'adminStats', 'notifications', 'unreadCount', 'upcomingEvents', 'latestSub',
                'oauth_providers', 'page', 'page_tpl', 'content', 'title', 'meta_description',
                'canonical_url', 'og_title', 'og_description', 'og_image', 'og_url', 'site_name',
            ]
        ));
        $vars = array_values(array_diff(array_unique($m[1]), $skip));
        sort($vars);

        header('Content-Type: application/json');
        echo json_encode(['vars' => $vars]);
        exit;
    }

    /**
     * Render a PHP template with stub data for visual preview.
     * Returns a standalone HTML page showing the template as it would look
     * with variable names used as placeholder values.
     */
    public function templateEditorPreview(): void
    {
        Auth::requireAdmin();

        $raw = $_GET['f'] ?? '';

        // @css/ files are not renderable as HTML — nothing to preview
        if (str_starts_with($raw, '@css/')) {
            http_response_code(204);
            exit;
        }

        $base    = realpath(dirname(__DIR__, 3) . '/templates');
        $exclude = ['/admin/', '/platform/'];
        $rel     = ltrim($raw, '/');

        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            http_response_code(400);
            exit;
        }
        $fullPath = realpath($base . '/' . $rel);
        if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit;
        }
        foreach ($exclude as $ex) {
            if (str_contains('/' . $rel, $ex)) {
                http_response_code(403);
                exit;
            }
        }

        $source = file_get_contents($fullPath);

        // Extract all top-level variable names referenced in the template
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $skipVars = ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_SESSION',
                     '_COOKIE', '_FILES', '_ENV', '_REQUEST', 'php_errormsg'];
        $varNames = array_diff(array_unique($m[1]), $skipVars);

        $stubs = [];
        foreach ($varNames as $name) {
            $stubs[$name] = new TemplatePreviewStub($name);
        }

        // Suppress undefined-variable notices from included sub-templates
        $prevErr = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
        extract($stubs, EXTR_SKIP);
        ob_start();
        try {
            include $fullPath;
        } catch (\Throwable $e) {
            echo '<div style="color:#b91c1c;font-family:monospace;padding:1rem;background:#fef2f2;border:1px solid #fecaca;margin:1rem">'
                . '<strong>Preview error:</strong> ' . htmlspecialchars($e->getMessage())
                . '<br><small>' . htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine()) . '</small></div>';
        }
        $body = ob_get_clean();
        error_reporting($prevErr);

        // Detect if the template already outputs a full HTML document
        $isFullPage = (bool) preg_match('/<html[\s>]/i', substr($body, 0, 500));

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if ($isFullPage) {
            // Inject preview banner via script
            $banner = "<script>document.addEventListener('DOMContentLoaded',function(){"
                . "var b=document.createElement('div');"
                . "b.textContent='Preview — stub data';"
                . "b.style.cssText='position:fixed;top:0;left:0;right:0;background:#1d9e75;color:#fff;font-size:0.7rem;text-align:center;padding:2px 0;z-index:99999;font-family:sans-serif';"
                . "document.body.prepend(b);});</script>";
            echo str_ireplace('</head>', $banner . '</head>', $body);
        } else {
            $basePath = \Cruinn\App::config('site.base_path', '');
            echo '<!DOCTYPE html><html lang="en"><head>'
                . '<meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>Preview: ' . htmlspecialchars($rel) . '</title>'
                . '<link rel="stylesheet" href="' . htmlspecialchars($basePath . '/css/style.css') . '">'
                . '<style>body{padding:1rem}.tpl-preview-banner{position:fixed;top:0;left:0;right:0;background:#1d9e75;color:#fff;font-size:0.7rem;text-align:center;padding:2px 0;z-index:99999;font-family:sans-serif}</style>'
                . '</head><body>'
                . '<div class="tpl-preview-banner">Preview — stub data</div>'
                . '<div style="margin-top:1.2rem">' . $body . '</div>'
                . '</body></html>';
        }
        exit;
    }

    // ══════════════════════════════════════════════════════════════
    //  DASHBOARDS (Stage 3)
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/site-builder/dashboards — List all widget dashboard canvases.
     */
    public function builderDashboards(): void
    {
        Auth::requireAdmin();

        $dashboardService = new \Cruinn\Services\DashboardService();
        $canvases = $dashboardService->listDashboardCanvases();

        // Get assignment counts for each dashboard
        foreach ($canvases as &$canvas) {
            $assignments = $this->db->fetchAll(
                'SELECT context_type, context_id FROM context_dashboards WHERE page_id = ?',
                [$canvas['id']]
            );
            $canvas['assignment_count'] = count($assignments);
        }
        unset($canvas);

        $this->renderAdmin('admin/site-builder/dashboards', [
            'title'       => 'Widget Dashboards',
            'breadcrumbs' => [
                ['Admin', '/admin'],
                ['Site Builder', '/admin/site-builder'],
                ['Dashboards'],
            ],
            'canvases' => $canvases,
        ]);
    }

    /**
     * POST /admin/site-builder/dashboards/new — Create a new dashboard canvas.
     */
    public function builderCreateDashboard(): void
    {
        Auth::requireAdmin();
        \Cruinn\CSRF::verify();

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            Auth::flash('error', 'Dashboard title is required.');
            $this->redirect('/admin/site-builder/dashboards');
        }

        // Create a new page with canvas_type='widget-dashboard' where available.
        try {
            $pageId = $this->db->insert('pages_index', [
                'title'       => $title,
                'slug'        => $this->generateUniqueSlug($title),
                'canvas_type' => 'widget-dashboard',
                'created_by'  => Auth::userId(),
            ]);
        } catch (\Throwable $e) {
            // Pre-canvas_type fallback uses slug convention for later discovery.
            $pageId = $this->db->insert('pages_index', [
                'title'       => $title,
                'slug'        => $this->generateUniqueSlug('_dashboard_' . $title),
                'status'      => 'published',
                'template'    => 'none',
                'editor_mode' => 'freeform',
                'created_by'  => Auth::userId(),
            ]);
        }

        Auth::flash('success', "Dashboard \"{$title}\" created.");
        $this->redirect("/admin/editor/{$pageId}/edit");
    }

    /**
     * POST /admin/site-builder/dashboards/{id}/delete — Delete a dashboard canvas.
     */
    public function builderDeleteDashboard(int $id): void
    {
        Auth::requireAdmin();
        \Cruinn\CSRF::verify();

        $page = null;
        $hasCanvasType = true;
        try {
            $page = $this->db->fetch('SELECT * FROM pages_index WHERE id = ?', [$id]);
        } catch (\Throwable $e) {
            $hasCanvasType = false;
            $page = $this->db->fetch('SELECT id, title, slug FROM pages_index WHERE id = ?', [$id]);
        }

        if (!$page) {
            Auth::flash('error', 'Dashboard not found.');
            $this->redirect('/admin/site-builder/dashboards');
        }
        if ($hasCanvasType && ($page['canvas_type'] ?? '') !== 'widget-dashboard') {
            Auth::flash('error', 'Dashboard not found.');
            $this->redirect('/admin/site-builder/dashboards');
        }
        if (!$hasCanvasType) {
            $isLegacyDashboardSlug = str_starts_with((string) ($page['slug'] ?? ''), '_dashboard_');
            $hasAssignments = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM context_dashboards WHERE page_id = ?',
                [$id]
            ) > 0;
            if (!$isLegacyDashboardSlug && !$hasAssignments) {
                Auth::flash('error', 'Dashboard not found.');
                $this->redirect('/admin/site-builder/dashboards');
            }
        }

        // Delete assignments first
        $this->db->delete('context_dashboards', 'page_id = ?', [$id]);

        // Delete page-owned blocks for both current and transitional schemas.
        $this->db->delete('pages_draft', 'page_id = ?', [$id]);
        $this->db->delete('pages', 'page_id = ?', [$id]);

        // Delete the page
        $this->db->delete('pages_index', 'id = ?', [$id]);

        Auth::flash('success', "Dashboard \"{$page['title']}\" deleted.");
        $this->redirect('/admin/site-builder/dashboards');
    }

    /**
     * Generate a unique slug from a title.
     */
    private function generateUniqueSlug(string $title): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
        $base = trim($base, '-');
        $slug = $base;
        $counter = 1;

        while ($this->db->fetchColumn('SELECT COUNT(*) FROM pages_index WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

}

/**
 * Stub value used when previewing PHP templates without real data.
 * Intercepts all property, array-offset, and iterator accesses,
 * returning the access path as a human-readable string placeholder.
 */
class TemplatePreviewStub implements \ArrayAccess, \Iterator, \Countable, \Stringable
{
    private string $path;
    private int    $pos = 0;

    public function __construct(string $path = '') { $this->path = $path; }

    // Outputs the variable path as its string value
    public function __toString(): string { return $this->path; }

    // Object property access: $stub->name → "stub.name"
    public function __get(string $k): static  { return new static($this->path ? "{$this->path}.{$k}" : $k); }
    public function __isset(string $k): bool  { return true; }
    public function __set(string $k, mixed $v): void {}

    // Array access: $stub['key'] → "stub.key"
    public function offsetGet(mixed $k): static  { return new static($this->path ? "{$this->path}.{$k}" : (string) $k); }
    public function offsetExists(mixed $k): bool { return true; }
    public function offsetSet(mixed $k, mixed $v): void {}
    public function offsetUnset(mixed $k): void {}

    // Iterator: foreach ($stub as $item) yields 3 rows of stub items
    public function current(): static { return new static("{$this->path}[{$this->pos}]"); }
    public function key(): int   { return $this->pos; }
    public function next(): void { $this->pos++; }
    public function rewind(): void { $this->pos = 0; }
    public function valid(): bool  { return $this->pos < 3; }
    public function count(): int   { return 3; }
}
