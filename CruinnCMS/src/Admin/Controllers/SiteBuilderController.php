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
        // Content pages (exclude zone/template internal pages whose slug starts with _)
        $contentPages = $this->db->fetchAll(
            "SELECT p.*, u.display_name as author_name
             FROM pages_index p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.slug NOT LIKE '\\_%'
             ORDER BY p.updated_at DESC"
        );

        // Zone pages
        $headerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM pages_index p
             LEFT JOIN page_templates pt ON pt.canvas_page_id = p.id
             WHERE p.slug = '_header'
             LIMIT 5"
        );
        $footerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM pages_index p
             LEFT JOIN page_templates pt ON pt.canvas_page_id = p.id
             WHERE p.slug = '_footer'
             LIMIT 5"
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
            'headerPages'   => $headerPages,
            'footerPages'   => $footerPages,
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
        $templates = $this->db->fetchAll(
            'SELECT * FROM page_templates ORDER BY sort_order, name'
        );

        foreach ($templates as &$tpl) {
            $usage = $this->db->fetch(
                'SELECT COUNT(*) AS cnt FROM pages_index WHERE template = ?',
                [$tpl['slug']]
            );
            $tpl['page_count'] = $usage['cnt'] ?? 0;
        }
        unset($tpl);

        $data = [
            'title'     => 'Page Templates',
            'section'   => 'builder',
            'tab'       => 'templates',
            'templates' => $templates,
        ];
        $this->renderAdmin('admin/site-builder/templates', $data);
    }

    public function builderCreateTemplate(): void
    {
        $name         = trim($this->input('name', ''));
        $slug         = trim($this->input('slug', ''));
        $description  = trim($this->input('description', ''));
        $zones        = trim($this->input('zones', '["main"]'));
        $cssClass     = trim($this->input('css_class', ''));
        $templateType  = in_array($this->input('template_type', 'page'), ['page', 'content'], true)
                         ? $this->input('template_type', 'page') : 'page';
        $contextSource = $this->sanitiseContextSource($this->input('context_source', ''));

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

        $zonesDecoded = json_decode($zones);
        if (!is_array($zonesDecoded)) {
            $zones = '["main"]';
        }

        $maxSort = $this->db->fetch('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM page_templates');

        $this->db->execute(
            'INSERT INTO page_templates (slug, name, description, zones, css_class, is_system, sort_order, template_type, context_source)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [$slug, $name, $description, $zones, $cssClass, $maxSort['next_sort'] ?? 99, $templateType, $contextSource ?: null]
        );

        Auth::flash('success', "Template '{$name}' created.");
        $this->redirect('/admin/templates');
    }

    public function builderEnsureCanvas(string $id): void
    {
        Auth::requireRole('admin');
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
                'INSERT INTO pages_index (title, slug, status, template, editor_mode) VALUES (?, ?, ?, ?, ?)',
                ['Template: ' . $tpl['name'], $canvasSlug, 'published', 'none', 'freeform']
            );
            $canvasPageId = (int) $this->db->pdo()->lastInsertId();
        }

        $this->db->execute(
            'UPDATE page_templates SET canvas_page_id = ? WHERE id = ?',
            [$canvasPageId, (int) $id]
        );

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

        $this->db->execute('DELETE FROM page_templates WHERE id = ?', [$id]);
        Auth::flash('success', "Template '{$tpl['name']}' deleted.");
        $this->redirect('/admin/templates');
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
                'zones'       => json_encode(['header']),
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
                'zones'       => json_encode(['footer']),
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
                'zones'       => json_encode(['sidebar']),
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

    public function builderEditTemplate(string $id): void
    {
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $usage = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM pages_index WHERE template = ?',
            [$tpl['slug']]
        );
        $tpl['page_count'] = $usage['cnt'] ?? 0;
        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];

        $pages = $this->db->fetchAll(
            'SELECT id, title, slug, status FROM pages_index WHERE template = ? ORDER BY title LIMIT 20',
            [$tpl['slug']]
        );

        // All templates that have a 'header' zone — these are valid header_source choices.
        $headerTemplates = $this->db->fetchAll(
            "SELECT id, slug, name, canvas_page_id FROM page_templates
              WHERE JSON_CONTAINS(zones, '\"header\"') AND id != ?
              ORDER BY sort_order, name",
            [(int) $id]
        );

                // All templates that have a 'sidebar' zone — valid sidebar_source choices.
                $sidebarTemplates = $this->db->fetchAll(
                        "SELECT id, slug, name, canvas_page_id FROM page_templates
                            WHERE JSON_CONTAINS(zones, '\"sidebar\"') AND id != ?
                            ORDER BY sort_order, name",
                        [(int) $id]
                );

        $contentSets = $this->db->fetchAll(
            'SELECT slug, name FROM content_sets ORDER BY name'
        );

        $this->renderAdmin('admin/site-builder/template-settings', [
            'title'           => 'Edit Template: ' . $tpl['name'],
            'section'         => 'builder',
            'tab'             => 'templates',
            'tpl'             => $tpl,
            'pages'           => $pages,
            'headerTemplates' => $headerTemplates,
            'sidebarTemplates'=> $sidebarTemplates,
            'contentSets'     => $contentSets,
        ]);
    }

    public function builderPreviewTemplate(string $id): void
    {
        $tpl = $this->db->fetch('SELECT * FROM page_templates WHERE id = ?', [(int) $id]);
        if (!$tpl) {
            Auth::flash('error', 'Template not found.');
            $this->redirect('/admin/templates');
        }

        $tpl['zones'] = json_decode($tpl['zones'] ?? '["main"]', true) ?: ['main'];
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
        Template::addGlobal('tpl_header_blocks', $grouped['header']);
        Template::addGlobal('tpl_footer_blocks', $grouped['footer']);

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
        $zones         = trim($this->input('zones', '["main"]'));
        $cssClass      = trim($this->input('css_class', ''));
        $sortOrder     = (int) $this->input('sort_order', $tpl['sort_order']);
        $contextSource = $this->sanitiseContextSource($this->input('context_source', $tpl['context_source'] ?? ''));

        if (!$name) {
            Auth::flash('error', 'Name is required.');
            $this->redirect("/admin/templates/{$id}/edit");
        }

        $zonesDecoded = json_decode($zones);
        if (!is_array($zonesDecoded)) {
            $zones = '["main"]';
        }

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
            'show_header'      => (bool) $this->input('show_header', false),
            'show_footer'      => (bool) $this->input('show_footer', false),
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
            'zones'          => $zones,
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
            $tpl = $this->db->fetch(
                "SELECT id FROM page_templates WHERE slug = ? AND JSON_CONTAINS(zones, '\"header\"') LIMIT 1",
                [$value]
            );
            if ($tpl) {
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
            $tpl = $this->db->fetch(
                "SELECT id FROM page_templates WHERE slug = ? AND JSON_CONTAINS(zones, '\"sidebar\"') LIMIT 1",
                [$value]
            );
            if ($tpl) {
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

        // Header and footer zone pages
        $headerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug FROM pages_index p WHERE p.slug = '_header' LIMIT 5"
        );
        $footerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug FROM pages_index p WHERE p.slug = '_footer' LIMIT 5"
        );

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

        // Templates with parsed settings (for header/footer/sidebar resolution)
        $templates = $this->db->fetchAll('SELECT slug, name, zones, settings FROM page_templates ORDER BY sort_order');

        $data = [
            'title'          => 'Site Structure',
            'section'        => 'builder',
            'tab'            => 'structure',
            'contentPages'   => $contentPages,
            'headerPages'    => $headerPages,
            'footerPages'    => $footerPages,
            'menus'          => $menus,
            'menuItemsByMenu'=> $menuItemsByMenu,
            'templates'      => $templates,
        ];
        $this->renderAdmin('admin/site-builder/structure', $data);
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
        $this->json(['success' => true, 'blocks' => $rows]);
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
        Auth::requireRole('admin');

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
        Auth::requireRole('admin');

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
        Auth::requireRole('admin');

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
        $skip = ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_SESSION',
                 '_COOKIE', '_FILES', '_ENV', '_REQUEST', 'db'];
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
        Auth::requireRole('admin');

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
