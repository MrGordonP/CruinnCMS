<?php
/**
 * Cruinn CMS — Page Editor Controller
 *
 * Handles the full-screen page editor, draft/publish/discard flow, and the
 * server-side undo/redo history backed by cruinn_draft_blocks.
 *
 * Routes:
 *   GET  /admin/editor/{pageId}/edit     → edit()
 *   POST /admin/editor/{pageId}/action   → recordAction()
 *   POST /admin/editor/{pageId}/undo     → undo()
 *   POST /admin/editor/{pageId}/redo     → redo()
 *   POST /admin/editor/{pageId}/publish  → publish()
 *   POST /admin/editor/{pageId}/discard  → discardDraft()
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Platform\PlatformAuth;

class CruinnController extends BaseController
{
    /**
     * Accept either instance admin auth or platform auth for editor AJAX.
     * Platform editor routes share the same controller methods.
     */
    private function requireEditorAuth(): void
    {
        $editorInstance = $_SESSION['_platform_editor_instance'] ?? null;
        if ($editorInstance !== null && PlatformAuth::check()) {
            // Swap the DB singleton to the correct DB (platform or instance)
            // based on which instance the platform editor is targeting.
            \Cruinn\Database::resetInstance();
            $this->db = \Cruinn\Database::getInstance();
            return;
        }
        Auth::requireRole('admin');
    }

    // ── Public actions ─────────────────────────────────────────────

    /**
     * GET /admin/editor
     * Redirect to the editor for the first accessible Cruinn content page.
     * Falls back to the site-builder pages list if no pages exist yet.
     */
    public function openEditor(): void
    {
        Auth::requireRole('admin');

        // Build nav data — identical to edit(), but with no page loaded
        $headerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"header\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $hp0 = $this->db->fetch("SELECT id FROM pages WHERE slug = '_header' LIMIT 1");
        if ($hp0) {
            array_unshift($headerPages, [
                'id' => (int) $hp0['id'], 'title' => 'Header Zone Page',
                'slug' => '_header', 'template_name' => null,
            ]);
        }
        $footerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"footer\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $fp0 = $this->db->fetch("SELECT id FROM pages WHERE slug = '_footer' LIMIT 1");
        if ($fp0) {
            array_unshift($footerPages, [
                'id' => (int) $fp0['id'], 'title' => 'Footer Zone Page',
                'slug' => '_footer', 'template_name' => null,
            ]);
        }

        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages
             WHERE slug NOT LIKE '\\_\\_%'
             ORDER BY title ASC"
        );
        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id
             FROM page_templates pt
             LEFT JOIN pages p ON p.id = pt.canvas_page_id
             WHERE pt.slug NOT LIKE '\\_\\_%'
             ORDER BY pt.sort_order, pt.name"
        );
        try {
            $navMenus = $this->db->fetchAll('SELECT id, name, block_page_id FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $navMenus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        $cssDir   = CRUINN_PUBLIC . '/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') as $f) {
            $cssFiles[] = basename($f);
        }
        sort($cssFiles);

        $tplBase    = dirname(__DIR__, 2) . '/templates';
        $tplExclude = ['/admin/', '/platform/'];
        $tplIter    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplBase, \FilesystemIterator::SKIP_DOTS)
        );
        $phpGroups = [];
        foreach ($tplIter as $tplFile) {
            if ($tplFile->getExtension() !== 'php') continue;
            $rel  = str_replace('\\', '/', substr($tplFile->getPathname(), strlen($tplBase) + 1));
            $skip = false;
            foreach ($tplExclude as $ex) {
                if (str_contains('/' . $rel, $ex)) { $skip = true; break; }
            }
            if ($skip) continue;
            $parts = explode('/', $rel);
            $group = count($parts) > 1 ? $parts[0] : 'root';
            $phpGroups[$group][] = $rel;
        }
        ksort($phpGroups);
        foreach ($phpGroups as &$g) { sort($g); }
        unset($g);

        $this->renderAdmin('admin/editor', [
            'title'           => 'Editor',
            'page'            => null,
            'hasDraft'        => false,
            'state'           => null,
            'cruinnHtml'      => '',
            'cruinnCss'       => '',
            'menus'           => $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC'),
            'isZonePage'      => false,
            'zoneName'        => null,
            'isTemplatePage'  => false,
            'templateSlugName' => null,
            'templateId'      => null,
            'headerPageId'    => null,
            'footerPageId'    => null,
            'headerPages'     => $headerPages,
            'footerPages'     => $footerPages,
            'sitePages'       => $sitePages,
            'navTemplates'    => $navTemplates,
            'navMenus'        => $navMenus,
            'navCssFiles'     => $cssFiles,
            'navPhpGroups'    => $phpGroups,
            'headerZoneHtml'  => '',
            'headerZoneCss'   => '',
            'footerZoneHtml'  => '',
            'footerZoneCss'   => '',
            'templateZones'   => [],
            'templateCanvasPageId' => null,
            'templateCanvasHtml'   => '',
            'templateCanvasCss'    => '',
            'startInCodeView' => false,
            'htmlContent'     => null,
        ]);
    }

    /**
     * GET /admin/editor/{pageId}/edit
     * Render the full-screen Cruinn editor.
     */
    public function edit(string $pageId): void
    {
        Auth::requireRole('admin');
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT * FROM pages WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        $state    = $this->db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
        $hasDraft = !empty($state);

        if ($hasDraft) {
            $flat = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks
                  WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$pageId]
            );
        } else {
            $flat = $this->db->fetchAll(
                'SELECT * FROM cruinn_blocks
                  WHERE page_id = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$pageId]
            );
        }

        $renderMode = $page['render_mode'] ?? 'cruinn';

        // ── Auto-import: parse source HTML into typed blocks on first open ──
        if (in_array($renderMode, ['html', 'file'], true) && empty($flat)) {
            $importSvc  = new \Cruinn\Services\ImportService();
            $absPath    = null;
            if ($renderMode === 'file') {
                $filePath = $page['render_file'] ?? '';
                $resolved = CRUINN_PUBLIC . $filePath;
                if ($filePath !== '' && file_exists($resolved)) {
                    $absPath = $resolved;
                }
            }
            $importedBlocks = $importSvc->autoImport($page, $pageId, $absPath);
            if (!empty($importedBlocks)) {
                try {
                    $importSvc->persistImportedBlocks($importedBlocks, $pageId, $this->db);
                } catch (\Throwable $e) {
                    error_log('Import failed: ' . $e->getMessage());
                }

                $state    = $this->db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                $hasDraft = !empty($state);
                $flat     = $this->db->fetchAll(
                    'SELECT * FROM cruinn_draft_blocks
                      WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                    [$pageId]
                );
            }
        }

        // Extract doc-level metadata blocks from the flat list
        $docHtmlBlock     = null;
        $docHeadBlock     = null;
        $docBodyBlock     = null;
        $hasImportedBlocks = false;
        foreach ($flat as $row) {
            if ($row['block_type'] === 'doc-html')     { $docHtmlBlock  = $row; }
            elseif ($row['block_type'] === 'doc-head') { $docHeadBlock  = $row; }
            elseif ($row['block_type'] === 'doc-body') { $docBodyBlock  = $row; }
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            if (isset($cfg['_tag'])) { $hasImportedBlocks = true; }
        }

        $menus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');

        // Detect global zone pages and template canvas pages (slug starts with '_')
        $isZonePage       = str_starts_with($page['slug'] ?? '', '_');
        $zoneName         = $isZonePage ? ltrim($page['slug'], '_') : null;
        $isTemplatePage   = str_starts_with($page['slug'] ?? '', '_tpl_');
        $templateSlugName = $isTemplatePage ? substr($page['slug'], 5) : null;

        // For template canvas pages: look up which template owns this canvas
        $templateId = null;
        if ($isTemplatePage) {
            $tplRow = $this->db->fetch(
                'SELECT id FROM page_templates WHERE canvas_page_id = ? LIMIT 1',
                [$pageId]
            );
            $templateId = $tplRow ? (int) $tplRow['id'] : null;
        }

        // For body pages: load the zone pages' IDs and their published preview HTML/CSS
        $headerPageId   = null;
        $footerPageId   = null;
        $headerZoneHtml = '';
        $headerZoneCss  = '';
        $footerZoneHtml = '';
        $footerZoneCss  = '';

        // Template canvas info for regular content pages
        $templateZones       = [];
        $templateCanvasPageId = null;
        $templateCanvasHtml  = '';
        $templateCanvasCss   = '';

        if (!$isZonePage) {
            $hp = $this->db->fetch("SELECT id FROM pages WHERE slug = '_header' LIMIT 1");
            $fp = $this->db->fetch("SELECT id FROM pages WHERE slug = '_footer' LIMIT 1");
            $headerPageId = $hp ? (int) $hp['id'] : null;
            $footerPageId = $fp ? (int) $fp['id'] : null;

            $cruinnSvc = new \Cruinn\Services\CruinnRenderService();
            if ($headerPageId && $cruinnSvc->hasPublished($headerPageId)) {
                $headerZoneHtml = $cruinnSvc->buildHtml($headerPageId);
                $headerZoneCss  = $cruinnSvc->buildCss($headerPageId);
            }
            if ($footerPageId && $cruinnSvc->hasPublished($footerPageId)) {
                $footerZoneHtml = $cruinnSvc->buildHtml($footerPageId);
                $footerZoneCss  = $cruinnSvc->buildCss($footerPageId);
            }

            // For non-template-canvas pages: look up the template canvas and extract zones
            if (!$isTemplatePage) {
                $pageTemplateSlug = $page['template'] ?? 'default';
                if ($pageTemplateSlug && $pageTemplateSlug !== 'none') {
                    $tplRow = $this->db->fetch(
                        'SELECT id, canvas_page_id FROM page_templates WHERE slug = ? LIMIT 1',
                        [$pageTemplateSlug]
                    );
                    if ($tplRow && !empty($tplRow['canvas_page_id'])) {
                        $templateCanvasPageId = (int) $tplRow['canvas_page_id'];
                        if ($cruinnSvc->hasPublished($templateCanvasPageId)) {
                            $templateCanvasHtml = $cruinnSvc->buildHtml($templateCanvasPageId);
                            $templateCanvasCss  = $cruinnSvc->buildCss($templateCanvasPageId);
                            // Extract distinct zone names from root-level zone blocks
                            $zoneRows = $this->db->fetchAll(
                                "SELECT block_config FROM cruinn_blocks
                                  WHERE page_id = ? AND block_type = 'zone' AND parent_block_id IS NULL",
                                [$templateCanvasPageId]
                            );
                            foreach ($zoneRows as $zr) {
                                $cfg  = json_decode($zr['block_config'] ?? '{}', true) ?: [];
                                $zn   = $cfg['zone_name'] ?? 'main';
                                if (!in_array($zn, $templateZones, true)) {
                                    $templateZones[] = $zn;
                                }
                            }
                        }
                    }
                }
            }
        }

        // All editable header pages: canvas pages for templates with a header zone
        $headerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"header\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $hp0 = $this->db->fetch("SELECT id FROM pages WHERE slug = '_header' LIMIT 1");
        if ($hp0) {
            array_unshift($headerPages, [
                'id' => (int) $hp0['id'], 'title' => 'Header Zone Page',
                'slug' => '_header', 'template_name' => null,
            ]);
        }
        $footerPages = $this->db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"footer\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $fp0 = $this->db->fetch("SELECT id FROM pages WHERE slug = '_footer' LIMIT 1");
        if ($fp0) {
            array_unshift($footerPages, [
                'id' => (int) $fp0['id'], 'title' => 'Footer Zone Page',
                'slug' => '_footer', 'template_name' => null,
            ]);
        }

        // Content pages for the sidebar nav
        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages
             WHERE slug NOT LIKE '\\_\\_%'
             ORDER BY title ASC"
        );

        // Templates for sidebar nav
        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id
             FROM page_templates pt
             LEFT JOIN pages p ON p.id = pt.canvas_page_id
             WHERE pt.slug NOT LIKE '\\_\\_%'
             ORDER BY pt.sort_order, pt.name"
        );

        // Menus with their block layout page ids for sidebar nav
        // block_page_id requires migration 040 — fall back gracefully if column doesn't exist yet
        try {
            $navMenus = $this->db->fetchAll(
                'SELECT id, name, block_page_id FROM menus ORDER BY name ASC'
            );
        } catch (\Exception $e) {
            $navMenus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        // CSS files for sidebar nav
        $cssDir   = CRUINN_PUBLIC . '/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') as $f) {
            $cssFiles[] = basename($f);
        }
        sort($cssFiles);

        // PHP template groups for sidebar nav (same exclusions as template editor)
        $tplBase    = dirname(__DIR__, 2) . '/templates';
        $tplExclude = ['/admin/', '/platform/'];
        $tplIter    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplBase, \FilesystemIterator::SKIP_DOTS)
        );
        $phpGroups = [];
        foreach ($tplIter as $tplFile) {
            if ($tplFile->getExtension() !== 'php') continue;
            $rel  = str_replace('\\', '/', substr($tplFile->getPathname(), strlen($tplBase) + 1));
            $skip = false;
            foreach ($tplExclude as $ex) {
                if (str_contains('/' . $rel, $ex)) { $skip = true; break; }
            }
            if ($skip) continue;
            $parts = explode('/', $rel);
            $group = count($parts) > 1 ? $parts[0] : 'root';
            $phpGroups[$group][] = $rel;
        }
        ksort($phpGroups);
        foreach ($phpGroups as &$g) { sort($g); }
        unset($g);

        $this->renderAdmin('admin/editor', [
            'title'             => 'Editor — ' . $page['title'],
            'page'              => $page,
            'hasDraft'          => $hasDraft,
            'state'             => $state,
            'cruinnHtml'        => (new \Cruinn\Services\EditorRenderService())->buildCanvasHtml($flat, $this->db),
            'cruinnCss'         => (new \Cruinn\Services\EditorRenderService())->buildCanvasCss($flat),
            'menus'             => $menus,
            'isZonePage'        => $isZonePage,
            'zoneName'          => $zoneName,
            'isTemplatePage'    => $isTemplatePage,
            'templateSlugName'  => $templateSlugName,
            'templateId'        => $templateId,
            'headerPageId'      => $headerPageId,
            'footerPageId'      => $footerPageId,
            'headerPages'       => $headerPages,
            'footerPages'       => $footerPages,
            'sitePages'            => $sitePages,
            'navTemplates'         => $navTemplates,
            'navMenus'             => $navMenus,
            'navCssFiles'          => $cssFiles,
            'navPhpGroups'         => $phpGroups,
            'headerZoneHtml'    => $headerZoneHtml,
            'headerZoneCss'     => $headerZoneCss,
            'footerZoneHtml'      => $footerZoneHtml,
            'footerZoneCss'       => $footerZoneCss,
            'templateZones'       => $templateZones,
            'templateCanvasPageId' => $templateCanvasPageId,
            'templateCanvasHtml'  => $templateCanvasHtml,
            'templateCanvasCss'   => $templateCanvasCss,
            'startInCodeView'   => !$hasImportedBlocks && $renderMode === 'html',
            'htmlContent'       => !$hasImportedBlocks && $renderMode === 'html' ? ($page['body_html'] ?? '') : null,
            'isFileMode'        => $renderMode === 'file',
            'docHtmlBlock'      => $docHtmlBlock,
            'docHeadBlock'      => $docHeadBlock,
            'docBodyBlock'      => $docBodyBlock,
            'editorPageBase'    => null,
        ]);
    }

    /**
     * POST /admin/editor/{pageId}/action
     * Record a user edit action (full canvas serialisation).
     * Body: {"blocks": [{block_id, block_type, inner_html, css_props, block_config,
     *                     sort_order, parent_block_id}, ...]}
     */
    public function recordAction(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT id, render_mode FROM pages WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // Doc block types and PHP code blocks store raw content — skip sanitiseHtml.
        $unsanitisedTypes = ['doc-html', 'doc-head', 'doc-body', 'php-code'];

        $incomingRaw    = $body['blocks'] ?? [];
        $incomingBlocks = [];

        foreach ($incomingRaw as $b) {
            $blockId = preg_replace('/[^a-z0-9\-]/', '', (string) ($b['block_id'] ?? ''));
            if ($blockId === '') {
                continue;
            }
            $bType = preg_replace('/[^a-z0-9\-]/', '', (string) ($b['block_type'] ?? 'text'));
            $incomingBlocks[] = [
                'block_id'        => $blockId,
                'block_type'      => $bType,
                'inner_html'      => isset($b['inner_html'])
                    ? (in_array($bType, $unsanitisedTypes, true)
                        ? (string) $b['inner_html']
                        : $this->sanitiseHtml((string) $b['inner_html']))
                    : null,
                'css_props'       => !is_array($b['css_props'] ?? null)
                    ? null
                    // Cast to object so json_encode gives '{}' for empty rather than '[]'.
                    // This lets reconstructTree distinguish "explicitly empty" from "never set".
                    : json_encode((object) $b['css_props']),
                'block_config'    => is_array($b['block_config'] ?? null) ? json_encode($b['block_config']) : null,
                'sort_order'      => max(0, (int) ($b['sort_order'] ?? 0)),
                'parent_block_id' => !empty($b['parent_block_id'])
                    ? preg_replace('/[^a-z0-9\-]/', '', (string) $b['parent_block_id'])
                    : null,
            ];
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            // Upsert page state and increment edit_seq
            $state = $this->db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
            if ($state) {
                $newSeq = (int) $state['current_edit_seq'] + 1;
                $this->db->execute(
                    'UPDATE cruinn_page_state
                        SET current_edit_seq = ?, max_edit_seq = ?, last_edited_at = NOW()
                      WHERE page_id = ?',
                    [$newSeq, $newSeq, $pageId]
                );
            } else {
                $newSeq = 1;
                $this->db->execute(
                    'INSERT INTO cruinn_page_state (page_id, current_edit_seq, max_edit_seq, last_edited_at)
                     VALUES (?, 1, 1, NOW())',
                    [$pageId]
                );
            }

            // Clear the redo branch
            $this->db->execute(
                'DELETE FROM cruinn_draft_blocks WHERE page_id = ? AND edit_seq > ?',
                [$pageId, $newSeq]
            );

            // Load currently active blocks for this page
            $activeRows = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks WHERE page_id = ? AND is_active = 1',
                [$pageId]
            );
            $activeByBlockId = [];
            foreach ($activeRows as $row) {
                $activeByBlockId[$row['block_id']] = $row;
            }

            $incomingIds = array_flip(array_column($incomingBlocks, 'block_id'));

            // Insert/update incoming blocks
            foreach ($incomingBlocks as $b) {
                $prevId = null;
                if (isset($activeByBlockId[$b['block_id']])) {
                    $oldRow = $activeByBlockId[$b['block_id']];
                    $prevId = $oldRow['id'];
                    $this->db->execute(
                        'UPDATE cruinn_draft_blocks SET is_active = 0 WHERE id = ?',
                        [$prevId]
                    );
                }

                $this->db->execute(
                    'INSERT INTO cruinn_draft_blocks
                        (page_id, edit_seq, block_id, block_type, inner_html, css_props,
                         block_config, sort_order, parent_block_id, is_active, is_deletion, prev_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)',
                    [
                        $pageId, $newSeq,
                        $b['block_id'], $b['block_type'], $b['inner_html'],
                        $b['css_props'], $b['block_config'],
                        $b['sort_order'], $b['parent_block_id'], $prevId,
                    ]
                );
            }

            // Tombstone blocks that were in the active set but not in the incoming data.
            // Doc-level metadata blocks are never sent by the canvas serialiser — preserve them.
            $docBlockTypes = ['doc-html', 'doc-head', 'doc-body'];
            foreach ($activeByBlockId as $blockId => $oldRow) {
                if (isset($incomingIds[$blockId])) {
                    continue;
                }
                if (in_array($oldRow['block_type'], $docBlockTypes, true)) {
                    continue;
                }
                if ($oldRow['is_deletion']) {
                    continue; // already a tombstone
                }
                $this->db->execute(
                    'UPDATE cruinn_draft_blocks SET is_active = 0 WHERE id = ?',
                    [$oldRow['id']]
                );
                $this->db->execute(
                    'INSERT INTO cruinn_draft_blocks
                        (page_id, edit_seq, block_id, block_type, inner_html, css_props,
                         block_config, sort_order, parent_block_id, is_active, is_deletion, prev_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)',
                    [
                        $pageId, $newSeq,
                        $blockId, $oldRow['block_type'], $oldRow['inner_html'],
                        $oldRow['css_props'], $oldRow['block_config'],
                        $oldRow['sort_order'], $oldRow['parent_block_id'], $oldRow['id'],
                    ]
                );
            }

            // Enforce 50-action history cap
            $seqCount = (int) $this->db->fetchColumn(
                'SELECT COUNT(DISTINCT edit_seq) FROM cruinn_draft_blocks WHERE page_id = ?',
                [$pageId]
            );
            if ($seqCount > 50) {
                $oldest = (int) $this->db->fetchColumn(
                    'SELECT MIN(edit_seq) FROM cruinn_draft_blocks WHERE page_id = ?',
                    [$pageId]
                );
                $this->db->execute(
                    'DELETE FROM cruinn_draft_blocks WHERE page_id = ? AND edit_seq = ?',
                    [$pageId, $oldest]
                );
            }

            $pdo->commit();

            $updatedState = $this->db->fetch(
                'SELECT * FROM cruinn_page_state WHERE page_id = ?',
                [$pageId]
            );

            $this->json([
                'success'  => true,
                'edit_seq' => $newSeq,
                'can_undo' => (int) ($updatedState['current_edit_seq'] ?? 0) > 0,
                'can_redo' => (int) ($updatedState['current_edit_seq'] ?? 0)
                           <  (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('CruinnController::recordAction failed: ' . $e->getMessage());
            $this->json(['error' => 'Failed to record action'], 500);
        }
    }

    /**
     * POST /admin/editor/{pageId}/undo
     * Step one action backwards in the edit history.
     */
    public function undo(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $state = $this->db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
        if (!$state || (int) $state['current_edit_seq'] <= 0) {
            $this->json(['error' => 'Nothing to undo'], 400);
        }

        $currentSeq = (int) $state['current_edit_seq'];

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks
                  WHERE page_id = ? AND edit_seq = ? AND is_active = 1',
                [$pageId, $currentSeq]
            );

            foreach ($rows as $row) {
                $this->db->execute(
                    'UPDATE cruinn_draft_blocks SET is_active = 0 WHERE id = ?',
                    [$row['id']]
                );
                if ($row['prev_id'] !== null) {
                    $this->db->execute(
                        'UPDATE cruinn_draft_blocks SET is_active = 1 WHERE id = ?',
                        [$row['prev_id']]
                    );
                }
                // If prev_id IS NULL the block simply vanishes from the active set (it was new)
            }

            $newSeq = $currentSeq - 1;
            $this->db->execute(
                'UPDATE cruinn_page_state SET current_edit_seq = ? WHERE page_id = ?',
                [$newSeq, $pageId]
            );

            $pdo->commit();

            $activeBlocks = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks
                  WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$pageId]
            );

            $updatedState = $this->db->fetch(
                'SELECT * FROM cruinn_page_state WHERE page_id = ?',
                [$pageId]
            );

            $this->json([
                'success'  => true,
                'blocks'   => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('CruinnController::undo failed: ' . $e->getMessage());
            $this->json(['error' => 'Undo failed'], 500);
        }
    }

    /**
     * POST /admin/editor/{pageId}/redo
     * Step one action forwards in the edit history.
     */
    public function redo(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $state = $this->db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
        if (!$state || (int) $state['current_edit_seq'] >= (int) $state['max_edit_seq']) {
            $this->json(['error' => 'Nothing to redo'], 400);
        }

        $newSeq = (int) $state['current_edit_seq'] + 1;

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            // These rows were deactivated by the previous undo
            $rows = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks
                  WHERE page_id = ? AND edit_seq = ? AND is_active = 0',
                [$pageId, $newSeq]
            );

            foreach ($rows as $row) {
                $this->db->execute(
                    'UPDATE cruinn_draft_blocks SET is_active = 1 WHERE id = ?',
                    [$row['id']]
                );
                if ($row['prev_id'] !== null) {
                    $this->db->execute(
                        'UPDATE cruinn_draft_blocks SET is_active = 0 WHERE id = ?',
                        [$row['prev_id']]
                    );
                }
            }

            $this->db->execute(
                'UPDATE cruinn_page_state SET current_edit_seq = ? WHERE page_id = ?',
                [$newSeq, $pageId]
            );

            $pdo->commit();

            $activeBlocks = $this->db->fetchAll(
                'SELECT * FROM cruinn_draft_blocks
                  WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$pageId]
            );

            $updatedState = $this->db->fetch(
                'SELECT * FROM cruinn_page_state WHERE page_id = ?',
                [$pageId]
            );

            $this->json([
                'success'  => true,
                'blocks'   => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('CruinnController::redo failed: ' . $e->getMessage());
            $this->json(['error' => 'Redo failed'], 500);
        }
    }

    /**
     * POST /admin/editor/{pageId}/publish
     * Copy the current draft to cruinn_blocks (the published table).
     */
    public function publish(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT id, render_mode, render_file FROM pages WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
        }

        $renderMode = $page['render_mode'] ?? 'cruinn';

        // ── File / HTML mode with imported blocks ─────────────────────────
        // If a draft exists (created by import or subsequent edits),
        // reconstruct the canonical HTML from block records.
        if (in_array($renderMode, ['html', 'file'], true)) {
            $pageState = $this->db->fetch(
                'SELECT page_id FROM cruinn_page_state WHERE page_id = ?', [$pageId]
            );

            if ($pageState) {
                $flat = $this->db->fetchAll(
                    'SELECT * FROM cruinn_draft_blocks
                      WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                      ORDER BY sort_order ASC',
                    [$pageId]
                );

                $importSvc = new \Cruinn\Services\ImportService();

                $pdo = $this->db->pdo();
                $pdo->beginTransaction();
                try {
                    if ($renderMode === 'file') {
                        $filePath = $page['render_file'] ?? '';
                        if ($filePath !== '') {
                            if (str_starts_with($filePath, '@cms/')) {
                                $absPath = dirname(__DIR__, 2) . '/' . substr($filePath, 5);
                            } else {
                                $absPath = CRUINN_PUBLIC . $filePath;
                            }
                            file_put_contents($absPath, $importSvc->reconstructDocument($flat));
                        }
                        // File-mode: the file is the source of truth. Clear all block
                        // tables so the next editor open re-imports fresh from the file.
                        $this->db->execute('DELETE FROM cruinn_blocks WHERE page_id = ?', [$pageId]);
                    } else {
                        $this->db->execute(
                            "UPDATE pages SET body_html = ? WHERE id = ?",
                            [$importSvc->reconstructFragment($flat), $pageId]
                        );
                        // HTML mode: cruinn_blocks is the published state.
                        $this->db->execute('DELETE FROM cruinn_blocks WHERE page_id = ?', [$pageId]);
                        $this->db->execute(
                            'INSERT INTO cruinn_blocks
                                 (block_id, page_id, block_type, inner_html, css_props,
                                  block_config, sort_order, parent_block_id)
                             SELECT block_id, page_id, block_type, inner_html, css_props,
                                    block_config, sort_order, parent_block_id
                               FROM cruinn_draft_blocks
                              WHERE page_id = ? AND is_active = 1 AND is_deletion = 0',
                            [$pageId]
                        );
                    }
                    $this->db->execute('DELETE FROM cruinn_draft_blocks WHERE page_id = ?', [$pageId]);
                    $this->db->execute('DELETE FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                    $this->db->execute("UPDATE pages SET status = 'published' WHERE id = ?", [$pageId]);

                    $pdo->commit();

                    // Re-import from the just-written file so continued editing in
                    // the same session starts from a correctly seeded draft (with
                    // doc-html/doc-head/doc-body intact). Undo history is reset.
                    // Done after commit so it runs in its own transaction via persistImportedBlocks.
                    if ($renderMode === 'file' && isset($absPath) && $absPath !== '' && file_exists($absPath)) {
                        $reimportPage = $this->db->fetch('SELECT * FROM pages WHERE id = ? LIMIT 1', [$pageId]);
                        if ($reimportPage) {
                            $reimportBlocks = $importSvc->autoImport($reimportPage, $pageId, $absPath);
                            if (!empty($reimportBlocks)) {
                                $importSvc->persistImportedBlocks($reimportBlocks, $pageId, $this->db);
                            }
                        }
                    }

                    $this->json(['success' => true, 'reimported' => ($renderMode === 'file')]);
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    error_log('CruinnController::publish failed: ' . $e->getMessage());
                    $this->json(['error' => 'Publish failed'], 500);
                }
                return;
            }

            // No draft: plain html mode page (code-view only, no block import yet)
            if ($renderMode === 'html') {
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
                $html = isset($body['html']) ? (string) $body['html'] : null;
                if ($html !== null) {
                    $this->db->execute(
                        "UPDATE pages SET body_html = ?, status = 'published' WHERE id = ?",
                        [$html, $pageId]
                    );
                } else {
                    $this->db->execute("UPDATE pages SET status = 'published' WHERE id = ?", [$pageId]);
                }
                $this->json(['success' => true]);
                return;
            }

            // File mode, no draft: mark published only (file on disk is unchanged)
            $this->db->execute("UPDATE pages SET status = 'published' WHERE id = ?", [$pageId]);
            $this->json(['success' => true]);
            return;
        }

        // ── Standard Cruinn block mode ────────────────────────────────────
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            // Wipe existing published blocks for this page
            $this->db->execute('DELETE FROM cruinn_blocks WHERE page_id = ?', [$pageId]);

            // Copy active non-deleted draft blocks into published table
            $this->db->execute(
                'INSERT INTO cruinn_blocks
                    (block_id, page_id, block_type, inner_html, css_props, block_config,
                     sort_order, parent_block_id)
                 SELECT block_id, page_id, block_type, inner_html, css_props, block_config,
                        sort_order, parent_block_id
                   FROM cruinn_draft_blocks
                  WHERE page_id = ? AND is_active = 1 AND is_deletion = 0',
                [$pageId]
            );

            // Clean up draft
            $this->db->execute('DELETE FROM cruinn_draft_blocks WHERE page_id = ?', [$pageId]);
            $this->db->execute('DELETE FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
            $this->db->execute("UPDATE pages SET status = 'published' WHERE id = ?", [$pageId]);

            $pdo->commit();

            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('CruinnController::publish failed: ' . $e->getMessage());
            $this->json(['error' => 'Publish failed'], 500);
        }
    }

    /**
     * POST /admin/editor/{pageId}/doc-attrs
     * Update doc-level metadata blocks (doc-html, doc-head, doc-body) in the active draft.
     * These blocks are not serialised by the canvas JS and are saved separately via
     * the Document panel inputs.
     */
    public function saveDocAttrs(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $htmlAttrs = is_array($body['html_attrs'] ?? null) ? $body['html_attrs'] : null;
        $headHtml  = isset($body['head_html']) ? (string) $body['head_html'] : null;
        $bodyAttrs = is_array($body['body_attrs'] ?? null) ? $body['body_attrs'] : null;

        if ($htmlAttrs !== null) {
            $this->db->execute(
                "UPDATE cruinn_draft_blocks SET block_config = ?
                  WHERE page_id = ? AND block_type = 'doc-html' AND is_active = 1",
                [json_encode($htmlAttrs), $pageId]
            );
        }
        if ($headHtml !== null) {
            $this->db->execute(
                "UPDATE cruinn_draft_blocks SET inner_html = ?
                  WHERE page_id = ? AND block_type = 'doc-head' AND is_active = 1",
                [$headHtml, $pageId]
            );
        }
        if ($bodyAttrs !== null) {
            $this->db->execute(
                "UPDATE cruinn_draft_blocks SET block_config = ?
                  WHERE page_id = ? AND block_type = 'doc-body' AND is_active = 1",
                [json_encode($bodyAttrs), $pageId]
            );
        }

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/editor/{pageId}/discard
     * Delete the draft and revert to the published state.
     */
    public function discardDraft(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $this->db->execute('DELETE FROM cruinn_draft_blocks WHERE page_id = ?', [$pageId]);
        $this->db->execute('DELETE FROM cruinn_page_state WHERE page_id = ?', [$pageId]);

        $this->json(['success' => true, 'redirect' => '/admin/editor/' . $pageId . '/edit']);
    }

    /**
     * GET /admin/editor/php-include-preview?template=rel/path&var1=val...
     * Returns the rendered HTML for a php-include block canvas preview.
     */
    public function phpIncludePreview(): void
    {
        Auth::requireRole('admin');
        header('Content-Type: application/json');

        $raw = $_GET['template'] ?? '';
        if ($raw === '') {
            echo json_encode(['html' => '<p style="color:#9ca3af;font-size:0.8rem;padding:0.5rem">PHP Include — no template selected</p>']);
            exit;
        }

        $base    = realpath(dirname(__DIR__, 2) . '/templates');
        $exclude = ['/admin/', '/platform/'];

        if (str_contains($raw, '..') || str_contains($raw, "\0")) {
            echo json_encode(['html' => '']);
            exit;
        }
        $fullPath = realpath($base . '/' . $raw);
        if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            echo json_encode(['html' => '']);
            exit;
        }
        foreach ($exclude as $ex) {
            if (str_contains('/' . $raw, $ex)) {
                echo json_encode(['html' => '']);
                exit;
            }
        }

        // Build vars from query string (exclude 'template', internal keys)
        $vars = $_GET;
        unset($vars['template']);
        $vars['db'] = $this->db;

        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $fullPath;
            $html = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $html = '<div style="color:#b91c1c;font-size:0.8rem;padding:0.5rem;background:#fef2f2">'
                  . htmlspecialchars('Error: ' . $e->getMessage()) . '</div>';
        }

        echo json_encode(['html' => $html]);
        exit;
    }

    /**
     * GET /admin/editor/nav-menu-preview?menu_id=X
     * Returns the rendered inner HTML for a nav-menu block preview in the editor.
     */
    public function navMenuPreview(): void
    {
        Auth::requireRole('admin');
        header('Content-Type: application/json');

        $menuId = (int) ($_GET['menu_id'] ?? 0);
        if (!$menuId) {
            echo json_encode(['html' => '']);
            exit;
        }

        $items = $this->db->fetchAll(
            'SELECT mi.*, p.slug AS page_slug
             FROM menu_items mi
             LEFT JOIN pages p ON mi.page_id = p.id
             WHERE mi.menu_id = ? AND mi.is_active = 1
               AND (mi.parent_id IS NULL OR mi.parent_id = 0)
             ORDER BY mi.sort_order ASC',
            [$menuId]
        );

        if (empty($items)) {
            echo json_encode(['html' => '']);
            exit;
        }

        $html = '<ul class="nav-list">';
        foreach ($items as $mi) {
            $href = match ($mi['link_type'] ?? 'url') {
                'page'  => '/' . ($mi['page_slug'] ?? ''),
                'route' => $mi['route'] ?? '/',
                default => $mi['url'] ?? '#',
            };
            $html .= '<li><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                   . htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8')
                   . '</a></li>';
        }
        $html .= '</ul>';

        echo json_encode(['html' => $html]);
        exit;
    }

    /**
     * GET /admin/editor/zone/{zone}
     * Redirect to the Cruinn editor for the named global zone page (header/footer).
     */
    public function editZone(string $zone): void
    {
        Auth::requireRole('admin');

        if (!in_array($zone, ['header', 'footer'], true)) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Zone Not Found']);
            return;
        }

        $page = $this->db->fetch(
            'SELECT id FROM pages WHERE slug = ? LIMIT 1',
            ['_' . $zone]
        );

        if (!$page) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Zone page not found. Run migration 029.']);
            return;
        }

        header('Location: /admin/editor/' . (int) $page['id'] . '/edit');
        exit;
    }



    /**
     * Strip dangerous HTML from block inner_html.
     * Removes <script>, <style>, on* event attributes, and javascript: hrefs.
     */
    private function sanitiseHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        // Suppress warnings from malformed HTML; wrap to preserve encoding
        @$doc->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath   = new \DOMXPath($doc);
        $removal = [];

        // Remove <script> and <style> elements
        foreach ($xpath->query('//script | //style') as $node) {
            $removal[] = $node;
        }

        // Remove on* event attributes from all elements
        foreach ($xpath->query('//*[@*[starts-with(local-name(), "on")]]') as $el) {
            /** @var \DOMElement $el */
            $toRemove = [];
            foreach ($el->attributes as $attr) {
                if (stripos($attr->nodeName, 'on') === 0) {
                    $toRemove[] = $attr->nodeName;
                }
            }
            foreach ($toRemove as $attrName) {
                $el->removeAttribute($attrName);
            }
        }

        // Remove javascript: hrefs and srcs
        foreach ($xpath->query('//*[@href or @src or @action]') as $el) {
            foreach (['href', 'src', 'action'] as $attrName) {
                if ($el->hasAttribute($attrName)) {
                    $val = $el->getAttribute($attrName);
                    if (preg_match('/^\s*javascript\s*:/i', $val)) {
                        $el->setAttribute($attrName, '#');
                    }
                }
            }
        }

        foreach ($removal as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Extract just the body children back to HTML
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return $output;
    }

}
