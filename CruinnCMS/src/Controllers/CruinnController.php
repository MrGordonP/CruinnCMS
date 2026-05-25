<?php
/**
 * Cruinn CMS — Page Editor Controller
 *
 * Handles the full-screen page editor, draft/publish/discard flow, and the
 * server-side undo/redo history backed by pages_draft.
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
    private function loadBlogProfiles(): array
    {
        try {
            return $this->db->fetchAll('SELECT id, name, slug FROM blog_profiles ORDER BY name ASC');
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadEventProfiles(): array
    {
        try {
            return $this->db->fetchAll('SELECT id, name, slug FROM event_profiles ORDER BY name ASC');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Accept either instance admin auth or platform auth for editor AJAX.
     * Platform editor routes share the same controller methods.
     */
    private function requireEditorAuth(): void
    {
        // DB swap only applies to platform-context requests (/cms/editor/...).
        // Admin-context requests (/admin/editor/...) always use the instance DB
        // via Auth::requireAdmin(), even if a platform session is also active.
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isPlatformContext = str_starts_with(parse_url($uri, PHP_URL_PATH) ?? $uri, '/cms/');

        if ($isPlatformContext) {
            $editorInstance = $_SESSION['_platform_editor_instance'] ?? null;
            if ($editorInstance !== null && PlatformAuth::check()) {
                // Swap the DB singleton to the correct DB (platform or instance)
                // based on which instance the platform editor is targeting.
                \Cruinn\Database::resetInstance();
                $this->db = \Cruinn\Database::getInstance();
                return;
            }
        }
        Auth::requireAdmin();
    }

    // ── Public actions ─────────────────────────────────────────────

    /**
     * Resolve the block storage key for a page.
     * Post-migration 012, template canvas blocks are stored with template_id, not page_id.
     * Returns ['col' => 'page_id'|'template_id', 'val' => int].
     */
    private function resolveBlockKey(int $pageId): array
    {
        // Platform editor pages are stored by page_id only — page_templates doesn't
        // exist in the platform DB and template-zone block storage is never used there.
        if (($_SESSION['_platform_editor_instance'] ?? null) === '__platform__') {
            return ['col' => 'page_id', 'val' => $pageId];
        }

        $tplId = (int) ($this->db->fetchColumn(
            'SELECT id FROM page_templates WHERE canvas_page_id = ? LIMIT 1', [$pageId]
        ) ?: 0);
        return $tplId > 0
            ? ['col' => 'template_id', 'val' => $tplId]
            : ['col' => 'page_id',     'val' => $pageId];
    }

    /**
     * GET /admin/editor
     * Redirect to the editor for the first accessible Cruinn content page.
     * Falls back to the site-builder pages list if no pages exist yet.
     */
    public function openEditor(): void
    {
        Auth::requireAdmin();

        // Build nav data — identical to edit(), but with no page loaded
        $headerPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'header'
             ORDER BY title ASC"
        );
        $footerPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'footer'
             ORDER BY title ASC"
        );
        $sidebarPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'sidebar'
             ORDER BY title ASC"
        );

        // Content pages only (exclude zone canvases and template layouts)
        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages_index
             WHERE canvas_type = 'content'
             ORDER BY title ASC"
        );

        // Template layout canvases (template-shell pages not used as template canvases)
        $templateLayoutPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'template-shell'
               AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
             ORDER BY title ASC"
        );

        // Template definitions for sidebar nav
        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id, p.title AS canvas_title
             FROM page_templates pt
             LEFT JOIN pages_index p ON p.id = pt.canvas_page_id
             ORDER BY pt.sort_order, pt.name"
        );
        try {
            $navMenus = $this->db->fetchAll('SELECT id, name, block_page_id FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $navMenus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        $cssDir   = CRUINN_PUBLIC . '/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') ?: [] as $f) {
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

        $typographyPageId = (int) ($this->db->fetchColumn("SELECT id FROM pages_index WHERE slug = '_typography' LIMIT 1") ?: 0);

        try {
            $openEditorContentSets = $this->db->fetchAll('SELECT id, name, slug, fields FROM content_sets ORDER BY name ASC');
        } catch (\Exception $e) {
            $openEditorContentSets = [];
        }

        try {
            $moduleWidgets = \Cruinn\Modules\ModuleRegistry::widgetCatalog();
        } catch (\Throwable $e) {
            $moduleWidgets = [];
        }
        $blogProfiles = $this->loadBlogProfiles();
        $eventProfiles = $this->loadEventProfiles();

        $allTemplates = $this->db->fetchAll(
            'SELECT id, slug, name FROM page_templates WHERE template_type = ? ORDER BY sort_order, name',
            ['page']
        );
        foreach ($allTemplates as &$tpl) {
            $tpl['zones'] = $this->getTemplateZonesByTemplateId((int) ($tpl['id'] ?? 0));
        }
        unset($tpl);

        $this->renderAdmin('admin/editor', [
            'title'           => 'Editor',
            'page'            => null,
            'hasDraft'        => false,
            'state'           => null,
            'cruinnHtml'      => '',
            'cruinnCss'       => '',
            'menus'           => $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC'),
            'contentSets'     => $openEditorContentSets,
            'moduleWidgets'   => $moduleWidgets,
            'blogProfiles'    => $blogProfiles,
            'eventProfiles'   => $eventProfiles,
            'templates'       => $allTemplates,
            'isZonePage'      => false,
            'zoneName'        => null,
            'isTemplatePage'  => false,
            'templateSlugName' => null,
            'templateId'      => null,
            'headerPageId'    => null,
            'footerPageId'    => null,
            'headerPages'     => $headerPages,
            'footerPages'     => $footerPages,
            'sidebarPages'    => $sidebarPages,
            'sitePages'       => $sitePages,
            'templateLayoutPages' => $templateLayoutPages,
            'navTemplates'    => $navTemplates,
            'navMenus'        => $navMenus,
            'navCssFiles'     => $cssFiles,
            'navPhpGroups'    => $phpGroups,
            'typographyPageId' => $typographyPageId ?: null,
            'isThemePage'     => false,
            'themeVars'       => [],
            'headerZoneHtml'  => '',
            'headerZoneCss'   => '',
            'footerZoneHtml'  => '',
            'footerZoneCss'   => '',
            'contextCanvases' => [],
            'templateZones'   => [],
            'templateCanvasPageId' => null,
            'templateCanvasHtml'   => '',
            'templateCanvasCss'    => '',
            'sidebarContextHtml'   => '',
            'sidebarContextCss'    => '',
            'sidebarContextPageId' => null,
            'sidebarContextLabel'  => '',
            'startInCodeView' => false,
            'htmlContent'     => null,
            'editorPageBase'  => null,
            'apiBase'         => '/admin/editor',
            'fromPageId'      => 0,
            'fromPageTitle'   => '',
        ]);
    }

    /**
     * GET /admin/editor/{pageId}/edit
     * Render the full-screen Cruinn editor.
     */
    public function edit(string $pageId): void
    {
        Auth::requireAdmin();
        $pageId = (int) $pageId;

        // Back-navigation: ?from={pageId} passes the originating page when jumping
        // to a context zone canvas (header, footer, etc.) from the editor.
        $fromPageId    = 0;
        $fromPageTitle = '';
        if (!empty($_GET['from'])) {
            $fromId = (int) $_GET['from'];
            if ($fromId > 0) {
                $fromRow = $this->db->fetch('SELECT id, title FROM pages_index WHERE id = ? LIMIT 1', [$fromId]);
                if ($fromRow) {
                    $fromPageId    = (int) $fromRow['id'];
                    $fromPageTitle = $fromRow['title'];
                }
            }
        }

        $page = $this->db->fetch('SELECT * FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Page Not Found']);
            return;
        }

        // Early template detection: after migration 012, template blocks are stored
        // with template_id rather than page_id, so block queries must use the right column.
        $earlyCanvasType     = $page['canvas_type'] ?? null;
        $earlyTemplateId = (int) ($this->db->fetchColumn(
            'SELECT id FROM page_templates WHERE canvas_page_id = ? LIMIT 1', [$pageId]
        ) ?: 0);
        $earlyIsTemplatePage = $earlyTemplateId > 0;

        // For file/html render modes, edit_seq=1 is the auto-import baseline written
        // on every fresh open — not a user edit. Only seq>=2 means the user has made
        // actual changes worth showing the "unsaved draft" banner for.
        $renderMode  = $page['render_mode'] ?? 'block';
        // Template-page blocks are keyed by template_id (post-migration 012), not page_id.
        $blockWhere  = ($earlyIsTemplatePage && $earlyTemplateId > 0)
            ? ['col' => 'template_id', 'val' => $earlyTemplateId]
            : ['col' => 'page_id',     'val' => $pageId];
        $anyDraftRows = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM pages_draft WHERE {$blockWhere['col']} = ?", [$blockWhere['val']]
        ) > 0;
        if (in_array($renderMode, ['file', 'html'], true)) {
            $maxDraftSeq = (int) $this->db->fetchColumn(
                "SELECT COALESCE(MAX(edit_seq), 0) FROM pages_draft WHERE {$blockWhere['col']} = ?", [$blockWhere['val']]
            );
            $hasDraft = $maxDraftSeq > 1;
        } else {
            $hasDraft    = $anyDraftRows;
            $maxDraftSeq = $anyDraftRows
                ? (int) $this->db->fetchColumn("SELECT MAX(edit_seq) FROM pages_draft WHERE {$blockWhere['col']} = ?", [$blockWhere['val']])
                : 0;
        }

        if ($anyDraftRows) {
            $flat = $this->db->fetchAll(
                "SELECT * FROM pages_draft
                  WHERE {$blockWhere['col']} = ? AND edit_seq = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC",
                [$blockWhere['val'], $maxDraftSeq]
            );
        } else {
            $flat = $this->db->fetchAll(
                "SELECT * FROM pages
                  WHERE {$blockWhere['col']} = ?
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC",
                [$blockWhere['val']]
            );
        }

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

                // Auto-import always writes edit_seq=1 — not a user edit, hasDraft stays false.
                $maxDraftSeq = 1;
                $flat = $this->db->fetchAll(
                    'SELECT * FROM pages_draft
                      WHERE page_id = ? AND edit_seq = 1
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

        try {
            $contentSets = $this->db->fetchAll('SELECT id, name, slug, fields FROM content_sets ORDER BY name ASC');
        } catch (\Exception $e) {
            $contentSets = [];
        }

        try {
            $contentTemplates = $this->db->fetchAll(
                "SELECT id, name, slug FROM page_templates WHERE template_type = 'content' ORDER BY name ASC"
            );
        } catch (\Exception $e) {
            $contentTemplates = [];
        }

        try {
            $moduleWidgets = \Cruinn\Modules\ModuleRegistry::widgetCatalog();
        } catch (\Throwable $e) {
            $moduleWidgets = [];
        }
        $blogProfiles = $this->loadBlogProfiles();
        $eventProfiles = $this->loadEventProfiles();

        // Detect zone and template-shell canvas pages via canvas_type column.
        // Fall back to slug prefix convention for instances that haven't run migration 011 yet.
        $canvasType       = $page['canvas_type'] ?? null;
        $isZonePage       = ($canvasType === 'zone') || ($canvasType === null && str_starts_with($page['slug'] ?? '', '_') && !str_starts_with($page['slug'] ?? '', '_tpl_'));
        $zoneName         = $isZonePage ? ($page['zone_name'] ?? ltrim($page['slug'], '_')) : null;
        $templateRow      = $this->db->fetch(
            'SELECT id, slug, context_source, settings, layout_page_id FROM page_templates WHERE canvas_page_id = ? LIMIT 1',
            [$pageId]
        );
        $isTemplatePage   = $templateRow !== false && $templateRow !== null;
        $isTemplateLayoutPage = ($canvasType === 'template-shell') && !$isTemplatePage;
        $templateSlugName = $isTemplatePage ? ($templateRow['slug'] ?? null) : null;

        // For template canvas pages: look up which template owns this canvas
        $templateId      = null;
        $templateZonesDef = [];
        $contextFields   = [];   // [{key, label, type}] for content template binding
        $templateLayoutSettings = [];  // Layout settings for template pages
        $templateLayoutPageId = null;
        $templateZoneAssignments = [];
        $templatePreviewHtml = '';
        $templatePreviewCss = '';
        $cruinnSvc = new \Cruinn\Services\CruinnRenderService();
        if ($isTemplatePage) {
            $tplRow = $templateRow;
            $templateId       = $tplRow ? (int) $tplRow['id'] : null;
            $templateLayoutPageId = !empty($tplRow['layout_page_id']) ? (int) $tplRow['layout_page_id'] : null;
            if ($templateId && $templateLayoutPageId) {
                $this->syncTemplateZoneBlocks($templateId, $this->getLayoutZones($templateLayoutPageId));
            }
            if ($templateId) {
                $templateZoneAssignments = $this->getTemplateZoneAssignments($templateId);
                $templateZonesDef = array_values(array_map(
                    fn(array $zone): string => (string) ($zone['zone_name'] ?? ''),
                    $this->getTemplateDisplayZones($templateId, (int) $templateLayoutPageId)
                ));
                $templateZonesDef = array_values(array_filter($templateZonesDef, fn(string $z): bool => $z !== ''));

                $preview = $cruinnSvc->buildWithTemplate($templateId, 'main');
                $templatePreviewHtml = $this->stripPreviewEditorAttrs($preview['html']);
                $templatePreviewCss = $preview['css'];
            }

            // Template layout settings for .site-body-wrap
            if ($tplRow && !empty($tplRow['settings'])) {
                $settings = json_decode($tplRow['settings'], true) ?: [];
                $templateLayoutSettings = $settings['body_layout'] ?? [];
            }

            // Resolve context fields from context_source
            if ($tplRow && !empty($tplRow['context_source'])) {
                $contextFields = $this->resolveContextFields($tplRow['context_source']);
            }
        }

        // Template canvas info for regular content pages
        $contextCanvases     = [];   // [{zone, pageId, label, html, css, position}]
        $headerPageId        = null;
        $footerPageId        = null;
        $headerZoneHtml      = '';
        $headerZoneCss       = '';
        $footerZoneHtml      = '';
        $footerZoneCss       = '';
        $templateZones       = $templateZonesDef;
        $templateCanvasPageId = null;
        $templateCanvasHtml  = '';
        $templateCanvasCss   = '';
        $zoneSuggestions     = $this->db->fetchColumn("SELECT value FROM settings WHERE `key` = 'editor.zone_suggestions' LIMIT 1") ?: 'main,header,footer,sidebar';

        if (!$isZonePage || $isTemplatePage) {
            // For non-template-canvas pages: look up the template canvas and extract zones
            if (!$isTemplatePage) {
                $pageTemplateSlug = $page['template'] ?? 'default';
                if ($pageTemplateSlug && $pageTemplateSlug !== 'none') {
                    $tplRow = $this->db->fetch(
                        'SELECT id, slug, name, canvas_page_id, layout_page_id, zone_canvases, settings FROM page_templates WHERE slug = ? LIMIT 1',
                        [$pageTemplateSlug]
                    );
                    if ($tplRow) {
                        // templateCanvasHtml: use template_id path (blocks migrated from canvas_page_id in 012)
                        $templateCanvasPageId = !empty($tplRow['canvas_page_id']) ? (int) $tplRow['canvas_page_id'] : null;
                        $tplIdForCanvas = (int) $tplRow['id'];
                        if ($tplIdForCanvas > 0 && $cruinnSvc->hasPublishedTemplate($tplIdForCanvas)) {
                            $tplResult          = $cruinnSvc->buildWithTemplate($tplIdForCanvas, 'main');
                            $templateCanvasHtml = $tplResult['html'];
                            $templateCanvasCss  = $tplResult['css'];
                        }

                        // Extract zone names from template's zone blocks for editor UI
                        foreach ($this->getTemplateDisplayZones((int) $tplRow['id'], (int) ($tplRow['layout_page_id'] ?? 0)) as $zoneDef) {
                            $templateZones[] = $zoneDef['zone_name'];
                        }
                    }

                    // Build context canvases - extract zone blocks from the template tree
                    if ($tplRow) {
                        $pageZone = $page['page_zone'] ?? 'main';
                        $tplId = (int) $tplRow['id'];

                        // Fetch template zone blocks
                        $zoneBlocks = $this->getTemplateDisplayZones($tplId, (int) ($tplRow['layout_page_id'] ?? 0));
                        $assignmentBlocks = $this->getTemplateZoneAssignments($tplId);

                        // Page-level zone overrides
                        $zoneOverrides = json_decode($page['zone_overrides'] ?? '{}', true) ?: [];

                        // Backward compat: zone_canvases fallback (until migration 020 applied)
                        $legacyZoneCanvases = json_decode($tplRow['zone_canvases'] ?? '{}', true) ?: [];

                        // Find main zone index for position calculation
                        $mainIdx = 0;
                        foreach ($zoneBlocks as $idx => $zb) {
                            if (($zb['zone_name'] ?? '') === $pageZone) {
                                $mainIdx = $idx;
                                break;
                            }
                        }

                        foreach ($zoneBlocks as $idx => $zoneBlock) {
                            $zone = $zoneBlock['zone_name'] ?? null;
                            $cfg = $assignmentBlocks[$zone] ?? [];

                            if (!$zone || $zone === $pageZone) { continue; }

                            // Resolve canvas page ID: page override → zone block → legacy zone_canvases → global zone canvas
                            $canvasPageId = null;

                            if (!empty($zoneOverrides[$zone])) {
                                $canvasPageId = (int) $zoneOverrides[$zone];
                            } elseif (!empty($cfg['canvas_page_id'])) {
                                $canvasPageId = (int) $cfg['canvas_page_id'];
                            } elseif (!empty($legacyZoneCanvases[$zone])) {
                                // Backward compat: read from zone_canvases JSON until migration 020 runs
                                $canvasPageId = (int) $legacyZoneCanvases[$zone];
                            }

                            // Render the zone canvas content
                            $ctxHtml = '';
                            $ctxCss  = '';
                            if ($canvasPageId > 0 && $cruinnSvc->hasPublished($canvasPageId)) {
                                $ctxHtml = $cruinnSvc->buildHtml($canvasPageId);
                                $ctxCss  = $cruinnSvc->buildCss($canvasPageId);
                            }

                            $contextCanvases[] = [
                                'zone'     => $zone,
                                'pageId'   => $canvasPageId,
                                'label'    => ucfirst($zone),
                                'html'     => $ctxHtml,
                                'css'      => $ctxCss,
                                'position' => $zone === 'sidebar' ? 'right' : ($idx < $mainIdx ? 'before' : 'after'),
                            ];
                        }
                    }
                }
            }
        }

        // Backward-compat: populate header/footer vars from contextCanvases
        foreach ($contextCanvases as $cc) {
            if ($cc['zone'] === 'header') {
                $headerPageId   = $cc['pageId'];
                $headerZoneHtml = $cc['html'];
                $headerZoneCss  = $cc['css'];
            } elseif ($cc['zone'] === 'footer') {
                $footerPageId   = $cc['pageId'];
                $footerZoneHtml = $cc['html'];
                $footerZoneCss  = $cc['css'];
            }
        }

        // All editable zone canvas pages for sidebar nav and toolbar shortcuts
        $headerPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'header'
             ORDER BY title ASC"
        );
        $footerPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'footer'
             ORDER BY title ASC"
        );
        $sidebarPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'zone' AND zone_name = 'sidebar'
             ORDER BY title ASC"
        );

        // Content pages only (exclude zone canvases and template layouts)
        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages_index
             WHERE canvas_type = 'content'
             ORDER BY title ASC"
        );

        // Template layout canvases (template-shell pages not used as template canvases)
        $templateLayoutPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'template-shell'
               AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
             ORDER BY title ASC"
        );

        // Template definitions for sidebar nav
        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id, p.title AS canvas_title
             FROM page_templates pt
             LEFT JOIN pages_index p ON p.id = pt.canvas_page_id
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

        $allTemplates = $this->db->fetchAll(
            'SELECT id, slug, name FROM page_templates WHERE template_type = ? ORDER BY sort_order, name',
            ['page']
        );
        foreach ($allTemplates as &$tpl) {
            $tpl['zones'] = $this->getTemplateZonesByTemplateId((int) ($tpl['id'] ?? 0));
        }
        unset($tpl);

        $typographyPageId = (int) ($this->db->fetchColumn("SELECT id FROM pages_index WHERE slug = '_typography' LIMIT 1") ?: 0);
        $isThemePage   = ($page['slug'] ?? '') === '_typography';
        $editingTheme  = null;
        $themeVars     = [];
        // Scan themes directory for available theme files
        $themeFiles = [];
        $themesDir  = CRUINN_PUBLIC . '/css/themes';
        if (is_dir($themesDir)) {
            foreach (glob($themesDir . '/*.css') ?: [] as $_tf) {
                $themeFiles[] = basename($_tf, '.css');
            }
            sort($themeFiles);
        }
        if ($isThemePage) {
            // Allow ?theme= to override which file is being edited
            $requestedTheme = $_GET['theme'] ?? null;
            $editingTheme   = ($requestedTheme && preg_match('/^[a-z0-9_-]+$/i', $requestedTheme))
                ? $requestedTheme
                : \Cruinn\Admin\Controllers\ThemeController::activeTheme();
            $themeFile = \Cruinn\Admin\Controllers\ThemeController::themeFilePath($editingTheme);
            if (file_exists($themeFile)) {
                $themeVars = \Cruinn\Admin\Controllers\ThemeController::parseVariables(file_get_contents($themeFile));
            }
        }

        // Fetch available zone canvas pages for canvas assignment dropdown (template pages only)
        $availableZoneCanvases = [];
        if ($isTemplatePage) {
            $availableZoneCanvases = $this->db->fetchAll(
                "SELECT id, title, zone_name FROM pages_index
                 WHERE canvas_type = 'zone'
                 ORDER BY zone_name, title ASC"
            );
        }

        $this->renderAdmin('admin/editor', [
            'title'             => 'Editor — ' . $page['title'],
            'page'              => $page,
            'hasDraft'          => $hasDraft,
            'state'             => null,
            'cruinnHtml'        => (new \Cruinn\Services\EditorRenderService())->buildCanvasHtml($flat, $this->db),
            'cruinnCss'         => (new \Cruinn\Services\EditorRenderService())->buildCanvasCss($flat),
            'menus'             => $menus,
            'contentSets'       => $contentSets,
            'contentTemplates'  => $contentTemplates,
            'moduleWidgets'     => $moduleWidgets,
            'blogProfiles'      => $blogProfiles,
            'eventProfiles'     => $eventProfiles,
            'templates'         => $allTemplates,
            'isZonePage'        => $isZonePage,
            'zoneName'          => $zoneName,
            'isTemplatePage'    => $isTemplatePage,
            'isTemplateLayoutPage' => $isTemplateLayoutPage,
            'templateSlugName'  => $templateSlugName,
            'templateId'        => $templateId,
            'templateLayoutPageId' => $templateLayoutPageId,
            'templateZoneAssignments' => $templateZoneAssignments,
            'templatePreviewHtml' => $templatePreviewHtml,
            'templatePreviewCss' => $templatePreviewCss,
            'availableZoneCanvases' => $availableZoneCanvases,
            'headerPageId'      => $headerPageId,
            'footerPageId'      => $footerPageId,
            'headerPages'       => $headerPages,
            'footerPages'       => $footerPages,
            'sidebarPages'      => $sidebarPages,
            'sitePages'            => $sitePages,
            'templateLayoutPages'  => $templateLayoutPages,
            'navTemplates'         => $navTemplates,
            'navMenus'             => $navMenus,
            'navCssFiles'          => $cssFiles,
            'navPhpGroups'         => $phpGroups,
            'navArticles'          => (function() { try { return \Cruinn\Database::getInstance()->fetchAll('SELECT id, title, slug FROM articles ORDER BY updated_at DESC LIMIT 100'); } catch (\Throwable $e) { return []; } })(),
            'typographyPageId'     => $typographyPageId ?: null,
            'isThemePage'          => $isThemePage,
            'editingTheme'         => $editingTheme,
            'themeVars'            => $themeVars,
            'themeFiles'           => $themeFiles,
            'headerZoneHtml'    => $headerZoneHtml,
            'headerZoneCss'     => $headerZoneCss,
            'footerZoneHtml'      => $footerZoneHtml,
            'footerZoneCss'       => $footerZoneCss,
            'contextCanvases'     => $contextCanvases,
            'zoneSuggestions'      => $zoneSuggestions,
            'templateZones'       => $templateZones,
            'templateCanvasPageId' => $templateCanvasPageId,
            'templateCanvasHtml'  => $templateCanvasHtml,
            'templateCanvasCss'   => $templateCanvasCss,
            'sidebarContextHtml'  => '',
            'sidebarContextCss'   => '',
            'sidebarContextPageId'=> null,
            'sidebarContextLabel' => '',
            'contextFields'       => $contextFields,
            'templateLayoutSettings' => $templateLayoutSettings,
            'startInCodeView'   => !$hasImportedBlocks && $renderMode === 'html',
            'htmlContent'       => !$hasImportedBlocks && $renderMode === 'html' ? ($page['body_html'] ?? '') : null,
            'isFileMode'        => $renderMode === 'file',
            'docHtmlBlock'      => $docHtmlBlock,
            'docHeadBlock'      => $docHeadBlock,
            'docBodyBlock'      => $docBodyBlock,
            'editorPageBase'    => null,
            'apiBase'           => '/admin/editor',
            'fromPageId'        => $fromPageId,
            'fromPageTitle'     => $fromPageTitle,
        ]);
    }

    /**
     * GET /admin/editor/article/{id}/edit
     * Open the shared Cruinn editor for a blog article.
     * All editor JS AJAX calls are routed to /admin/article-editor/{id}/* by apiBase.
     */
    public function editArticle(string $id): void
    {
        Auth::requireAdmin();
        $articleId = (int) $id;

        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ? LIMIT 1', [$articleId]);
        if (!$article) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Article Not Found']);
            return;
        }

        // ── Load blocks (draft → published → seeded from body_html) ──
        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
        $hasDraft = !empty($state) && (int) ($state['current_edit_seq'] ?? 0) > 0;

        if ($state) {
            $flat = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
        } else {
            $published = $this->db->fetchAll(
                'SELECT * FROM article_blocks WHERE article_id = ? ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
            if (!empty($published)) {
                // Seed draft from published blocks
                $pdo = $this->db->pdo();
                $pdo->beginTransaction();
                try {
                    $this->db->execute(
                        'INSERT INTO article_edit_state (article_id, current_edit_seq, max_edit_seq, last_edited_at)
                         VALUES (?, 1, 1, NOW())
                         ON DUPLICATE KEY UPDATE current_edit_seq = 1, max_edit_seq = 1, last_edited_at = NOW()',
                        [$articleId]
                    );
                    foreach ($published as $b) {
                        $this->db->execute(
                            'INSERT INTO article_draft_blocks
                                 (article_id, edit_seq, block_id, block_type, inner_html,
                                  css_props, block_config, sort_order, parent_block_id, is_active, is_deletion)
                             VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                            [$articleId, $b['block_id'], $b['block_type'], $b['inner_html'],
                             $b['css_props'] ?? null, $b['block_config'] ?? null,
                             $b['sort_order'], $b['parent_block_id'] ?? null]
                        );
                    }
                    $pdo->commit();
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                $flat = $this->db->fetchAll(
                    'SELECT * FROM article_draft_blocks
                      WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                    [$articleId]
                );
            } else {
                // Seed from body_html import if present
                $bodyHtml = $article['body_html'] ?? '';
                if ($bodyHtml !== '') {
                    $importSvc = new \Cruinn\Services\ImportService();
                    $blocks    = $importSvc->parseFragment($bodyHtml, $articleId);
                    if (!empty($blocks)) {
                        $pdo = $this->db->pdo();
                        $pdo->beginTransaction();
                        try {
                            $this->db->execute(
                                'INSERT INTO article_edit_state (article_id, current_edit_seq, max_edit_seq, last_edited_at)
                                 VALUES (?, 1, 1, NOW())
                                 ON DUPLICATE KEY UPDATE current_edit_seq = 1, max_edit_seq = 1, last_edited_at = NOW()',
                                [$articleId]
                            );
                            foreach ($blocks as $b) {
                                $this->db->execute(
                                    'INSERT INTO article_draft_blocks
                                         (article_id, edit_seq, block_id, block_type, inner_html,
                                          css_props, block_config, sort_order, parent_block_id, is_active, is_deletion)
                                     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                                    [$articleId, $b['block_id'], $b['block_type'], $b['inner_html'],
                                     $b['css_props'] ?? null, $b['block_config'] ?? null,
                                     $b['sort_order'], $b['parent_block_id'] ?? null]
                                );
                            }
                            $pdo->commit();
                        } catch (\Throwable $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                        $flat = $this->db->fetchAll(
                            'SELECT * FROM article_draft_blocks
                              WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                            [$articleId]
                        );
                    } else {
                        $flat = [];
                    }
                } else {
                    $flat = [];
                }
            }
        }

        // ── Build shared nav payload ──────────────────────────────
           $headerPages = $this->db->fetchAll(
              "SELECT p.id, p.title, p.slug, pt.name AS template_name
               FROM page_templates pt
               JOIN pages_index p ON p.id = pt.canvas_page_id
               WHERE EXISTS (
                 SELECT 1
                 FROM pages z
                 WHERE z.block_type = 'zone'
                   AND z.parent_block_id IS NULL
                   AND (
                        (pt.layout_page_id IS NOT NULL AND pt.layout_page_id > 0 AND z.page_id = pt.layout_page_id)
                        OR ((pt.layout_page_id IS NULL OR pt.layout_page_id = 0) AND z.template_id = pt.id)
                   )
                   AND JSON_UNQUOTE(JSON_EXTRACT(z.block_config, '$.zone_name')) = 'header'
               )
               ORDER BY pt.sort_order, pt.name"
           );
        $hp0 = $this->db->fetch("SELECT id FROM pages_index WHERE slug = '_header' LIMIT 1");
        if ($hp0) {
            array_unshift($headerPages, ['id' => (int) $hp0['id'], 'title' => 'Header Zone Page', 'slug' => '_header', 'template_name' => null]);
        }
           $footerPages = $this->db->fetchAll(
              "SELECT p.id, p.title, p.slug, pt.name AS template_name
               FROM page_templates pt
               JOIN pages_index p ON p.id = pt.canvas_page_id
               WHERE EXISTS (
                 SELECT 1
                 FROM pages z
                 WHERE z.block_type = 'zone'
                   AND z.parent_block_id IS NULL
                   AND (
                        (pt.layout_page_id IS NOT NULL AND pt.layout_page_id > 0 AND z.page_id = pt.layout_page_id)
                        OR ((pt.layout_page_id IS NULL OR pt.layout_page_id = 0) AND z.template_id = pt.id)
                   )
                   AND JSON_UNQUOTE(JSON_EXTRACT(z.block_config, '$.zone_name')) = 'footer'
               )
               ORDER BY pt.sort_order, pt.name"
           );
        $fp0 = $this->db->fetch("SELECT id FROM pages_index WHERE slug = '_footer' LIMIT 1");
        if ($fp0) {
            array_unshift($footerPages, ['id' => (int) $fp0['id'], 'title' => 'Footer Zone Page', 'slug' => '_footer', 'template_name' => null]);
        }

        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages_index WHERE canvas_type = 'content' ORDER BY title ASC"
        );

        // Template layout canvases (template-shell pages not used as template canvases)
        $templateLayoutPages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index
             WHERE canvas_type = 'template-shell'
               AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
             ORDER BY title ASC"
        );

        // Template definitions
        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id
             FROM page_templates pt
             LEFT JOIN pages_index p ON p.id = pt.canvas_page_id
             ORDER BY pt.sort_order, pt.name"
        );

        try {
            $navMenus = $this->db->fetchAll('SELECT id, name, block_page_id FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $navMenus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        $cssDir   = CRUINN_PUBLIC . '/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') as $f) { $cssFiles[] = basename($f); }
        sort($cssFiles);

        $tplBase    = dirname(__DIR__, 2) . '/templates';
        $tplExclude = ['/admin/', '/platform/'];
        $tplIter    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplBase, \FilesystemIterator::SKIP_DOTS)
        );
        $phpGroups = [];
        foreach ($tplIter as $tplFile) {
            if ($tplFile->getExtension() !== 'php') continue;
            $rel = str_replace('\\', '/', substr($tplFile->getPathname(), strlen($tplBase) + 1));
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

        $headerZoneHtml = '';
        $headerZoneCss  = '';
        $footerZoneHtml = '';
        $footerZoneCss  = '';
        try {
            $cruinnSvc = new \Cruinn\Services\CruinnRenderService();
            $headerPageId = $hp0 ? (int) $hp0['id'] : null;
            $footerPageId = $fp0 ? (int) $fp0['id'] : null;
            if ($headerPageId && $cruinnSvc->hasPublished($headerPageId)) {
                $headerZoneHtml = $cruinnSvc->buildHtml($headerPageId);
                $headerZoneCss  = $cruinnSvc->buildCss($headerPageId);
            }
            if ($footerPageId && $cruinnSvc->hasPublished($footerPageId)) {
                $footerZoneHtml = $cruinnSvc->buildHtml($footerPageId);
                $footerZoneCss  = $cruinnSvc->buildCss($footerPageId);
            }
        } catch (\Throwable $e) {
            error_log('CruinnController::editArticle zone render failed: ' . $e->getMessage());
        }

        try {
            $menus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $menus = [];
        }
        try {
            $contentSets = $this->db->fetchAll('SELECT id, name, slug, fields FROM content_sets ORDER BY name ASC');
        } catch (\Exception $e) {
            $contentSets = [];
        }
        try {
            $contentTemplates = $this->db->fetchAll("SELECT id, name, slug FROM page_templates WHERE template_type = 'content' ORDER BY name ASC");
        } catch (\Exception $e) {
            $contentTemplates = [];
        }
        try {
            $moduleWidgets = \Cruinn\Modules\ModuleRegistry::widgetCatalog();
        } catch (\Throwable $e) {
            $moduleWidgets = [];
        }
        $blogProfiles = $this->loadBlogProfiles();
        $eventProfiles = $this->loadEventProfiles();
        try {
            $navArticles = $this->db->fetchAll('SELECT id, title, slug FROM articles ORDER BY updated_at DESC LIMIT 100');
        } catch (\Throwable $e) {
            $navArticles = [];
        }

        $allTemplates     = $this->db->fetchAll('SELECT id, slug, name FROM page_templates WHERE template_type = ? ORDER BY sort_order, name', ['page']);
        foreach ($allTemplates as &$tpl) {
            $tpl['zones'] = $this->getTemplateZonesByTemplateId((int) ($tpl['id'] ?? 0));
        }
        unset($tpl);
        $typographyPageId = (int) ($this->db->fetchColumn("SELECT id FROM pages_index WHERE slug = '_typography' LIMIT 1") ?: 0);

        $this->renderAdmin('admin/editor', [
            'title'               => 'Editor — ' . $article['title'],
            'page'                => [
                'id'          => $articleId,
                'title'       => $article['title'],
                'slug'        => $article['slug'],
                'render_mode' => 'cruinn',
                'status'      => $article['status'],
                'template'    => 'none',
                '_is_article' => true,
            ],
            'hasDraft'            => $hasDraft,
            'state'               => $state,
            'cruinnHtml'          => (new \Cruinn\Services\EditorRenderService())->buildCanvasHtml($flat, $this->db),
            'cruinnCss'           => (new \Cruinn\Services\EditorRenderService())->buildCanvasCss($flat),
            'menus'               => $menus,
            'contentSets'         => $contentSets,
            'contentTemplates'    => $contentTemplates,
            'moduleWidgets'       => $moduleWidgets,
            'blogProfiles'        => $blogProfiles,
            'eventProfiles'       => $eventProfiles,
            'templates'           => $allTemplates,
            'isZonePage'          => false,
            'zoneName'            => null,
            'isTemplatePage'      => false,
            'templateSlugName'    => null,
            'templateId'          => null,
            'headerPageId'        => null,
            'footerPageId'        => null,
            'headerPages'         => $headerPages,
            'footerPages'         => $footerPages,
            'sitePages'           => $sitePages,
            'templateLayoutPages' => $templateLayoutPages,
            'navTemplates'        => $navTemplates,
            'navMenus'            => $navMenus,
            'navCssFiles'         => $cssFiles,
            'navPhpGroups'        => $phpGroups,
            'navArticles'         => $navArticles,
            'typographyPageId'    => $typographyPageId ?: null,
            'isThemePage'         => false,
            'editingTheme'        => null,
            'themeVars'           => [],
            'themeFiles'          => [],
            'headerZoneHtml'      => $headerZoneHtml,
            'headerZoneCss'       => $headerZoneCss,
            'footerZoneHtml'      => $footerZoneHtml,
            'footerZoneCss'       => $footerZoneCss,
            'contextCanvases'     => [],
            'templateZones'       => [],
            'templateCanvasPageId'=> null,
            'templateCanvasHtml'  => '',
            'templateCanvasCss'   => '',
            'sidebarContextHtml'  => '',
            'sidebarContextCss'   => '',
            'sidebarContextPageId'=> null,
            'sidebarContextLabel' => '',
            'contextFields'       => [],
            'templateLayoutSettings' => [],
            'startInCodeView'     => false,
            'htmlContent'         => null,
            'isFileMode'          => false,
            'docHtmlBlock'        => null,
            'docHeadBlock'        => null,
            'docBodyBlock'        => null,
            'editorPageBase'      => null,
            'apiBase'             => '/admin/article-editor',
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

        $page = $this->db->fetch('SELECT id, render_mode FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
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
                'css_props_tablet' => !is_array($b['css_props_tablet'] ?? null)
                    ? null
                    : json_encode((object) $b['css_props_tablet']),
                'css_props_mobile' => !is_array($b['css_props_mobile'] ?? null)
                    ? null
                    : json_encode((object) $b['css_props_mobile']),
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
            $bk = $this->resolveBlockKey($pageId);

            // Determine next sequence number
            $currentSeq = (int) $this->db->fetchColumn(
                "SELECT MAX(edit_seq) FROM pages_draft WHERE {$bk['col']} = ?",
                [$bk['val']]
            );
            $newSeq = $currentSeq + 1;

            // Insert all incoming blocks as a full snapshot at newSeq
            foreach ($incomingBlocks as $b) {
                $this->db->execute(
                    "INSERT INTO pages_draft
                        ({$bk['col']}, edit_seq, block_id, block_type, inner_html, css_props,
                         css_props_tablet, css_props_mobile,
                         block_config, sort_order, parent_block_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $bk['val'], $newSeq,
                        $b['block_id'], $b['block_type'], $b['inner_html'],
                        $b['css_props'], $b['css_props_tablet'], $b['css_props_mobile'],
                        $b['block_config'],
                        $b['sort_order'], $b['parent_block_id'],
                    ]
                );
            }

            // Enforce 50-action history cap: prune oldest seq if exceeded
            $seqCount = (int) $this->db->fetchColumn(
                "SELECT COUNT(DISTINCT edit_seq) FROM pages_draft WHERE {$bk['col']} = ?",
                [$bk['val']]
            );
            if ($seqCount > 50) {
                $oldest = (int) $this->db->fetchColumn(
                    "SELECT MIN(edit_seq) FROM pages_draft WHERE {$bk['col']} = ?",
                    [$bk['val']]
                );
                $this->db->execute(
                    "DELETE FROM pages_draft WHERE {$bk['col']} = ? AND edit_seq = ?",
                    [$bk['val'], $oldest]
                );
            }

            $pdo->commit();

            $this->json([
                'success'  => true,
                'edit_seq' => $newSeq,
                'can_undo' => true,
                'can_redo' => false,
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('CruinnController::recordAction failed: ' . $e->getMessage());
            $this->json(['error' => 'Failed to record action: ' . $e->getMessage()], 500);
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

        $bk = $this->resolveBlockKey($pageId);
        $maxSeq = (int) $this->db->fetchColumn(
            "SELECT MAX(edit_seq) FROM pages_draft WHERE {$bk['col']} = ?", [$bk['val']]
        );
        if ($maxSeq < 1) {
            $this->json(['error' => 'Nothing to undo'], 400);
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            // Destructive undo: delete the most recent snapshot
            $this->db->execute(
                "DELETE FROM pages_draft WHERE {$bk['col']} = ? AND edit_seq = ?",
                [$bk['val'], $maxSeq]
            );

            $pdo->commit();

            $newMax = (int) $this->db->fetchColumn(
                "SELECT MAX(edit_seq) FROM pages_draft WHERE {$bk['col']} = ?",
                [$bk['val']]
            );

            if ($newMax > 0) {
                // Return the previous snapshot
                $activeBlocks = $this->db->fetchAll(
                    "SELECT * FROM pages_draft
                      WHERE {$bk['col']} = ? AND edit_seq = ?
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC",
                    [$bk['val'], $newMax]
                );
            } else {
                // No draft remains: return published blocks
                $activeBlocks = $this->db->fetchAll(
                    "SELECT * FROM pages
                      WHERE {$bk['col']} = ?
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC",
                    [$bk['val']]
                );
            }

            $this->json([
                'success'  => true,
                'blocks'   => $activeBlocks,
                'can_undo' => $newMax >= 1,
                'can_redo' => false,
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
        $this->json(['error' => 'Redo is not supported'], 400);
    }

    /**
     * POST /admin/editor/{pageId}/publish
     * Copy the current draft to pages (the published table).
     */
    public function publish(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT id, render_mode, render_file FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
        }

        $renderMode = $page['render_mode'] ?? 'block';

        // ── File / HTML mode with imported blocks ─────────────────────────
        // If a draft exists (created by import or subsequent edits),
        // reconstruct the canonical HTML from block records.
        if (in_array($renderMode, ['html', 'file'], true)) {
            $hasDraft = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM pages_draft WHERE page_id = ?', [$pageId]
            ) > 0;

            if ($hasDraft) {
                $maxSeq = (int) $this->db->fetchColumn(
                    'SELECT MAX(edit_seq) FROM pages_draft WHERE page_id = ?', [$pageId]
                );
                $flat = $this->db->fetchAll(
                    'SELECT * FROM pages_draft
                      WHERE page_id = ? AND edit_seq = ?
                      ORDER BY sort_order ASC',
                    [$pageId, $maxSeq]
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
                        $this->db->execute('DELETE FROM pages WHERE page_id = ?', [$pageId]);
                    } else {
                        $this->db->execute(
                            "UPDATE pages_index SET body_html = ? WHERE id = ?",
                            [$importSvc->reconstructFragment($flat), $pageId]
                        );
                        // HTML mode: pages is the published state.
                        $this->db->execute('DELETE FROM pages WHERE page_id = ?', [$pageId]);
                        $this->publishDraftSnapshotToPages('page_id', $pageId, $maxSeq);
                    }
                    $this->db->execute('DELETE FROM pages_draft WHERE page_id = ?', [$pageId]);
                    $this->db->execute("UPDATE pages_index SET status = 'published' WHERE id = ?", [$pageId]);

                    $pdo->commit();

                    // Re-import from the just-written file so continued editing in
                    // the same session starts from a correctly seeded draft (with
                    // doc-html/doc-head/doc-body intact). Undo history is reset.
                    // Done after commit so it runs in its own transaction via persistImportedBlocks.
                    if ($renderMode === 'file' && isset($absPath) && $absPath !== '' && file_exists($absPath)) {
                        $reimportPage = $this->db->fetch('SELECT * FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
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
                        "UPDATE pages_index SET body_html = ?, status = 'published' WHERE id = ?",
                        [$html, $pageId]
                    );
                } else {
                    $this->db->execute("UPDATE pages_index SET status = 'published' WHERE id = ?", [$pageId]);
                }
                $this->json(['success' => true]);
                return;
            }

            // File mode, no draft: mark published only (file on disk is unchanged)
            $this->db->execute("UPDATE pages_index SET status = 'published' WHERE id = ?", [$pageId]);
            $this->json(['success' => true]);
            return;
        }

        // ── Standard block mode ────────────────────────────────────────────
        $bk = $this->resolveBlockKey($pageId);
        $hasDraftBlock = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM pages_draft WHERE {$bk['col']} = ?", [$bk['val']]
        ) > 0;
        if (!$hasDraftBlock) {
            // No draft: only succeed if a published snapshot already exists.
            $hasPublishedBlock = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM pages WHERE {$bk['col']} = ?",
                [$bk['val']]
            ) > 0;
            if (!$hasPublishedBlock) {
                $this->json(['error' => 'No saved blocks to publish.'], 400);
            }

            $this->db->execute("UPDATE pages_index SET status = 'published' WHERE id = ?", [$pageId]);
            $this->json(['success' => true]);
            return;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            // Wipe existing published blocks for this page
            $this->db->execute("DELETE FROM pages WHERE {$bk['col']} = ?", [$bk['val']]);

            $maxSeq = (int) $this->db->fetchColumn(
                "SELECT MAX(edit_seq) FROM pages_draft WHERE {$bk['col']} = ?",
                [$bk['val']]
            );
            $this->publishDraftSnapshotToPages($bk['col'], $bk['val'], $maxSeq);

            // Clean up draft
            $this->db->execute("DELETE FROM pages_draft WHERE {$bk['col']} = ?", [$bk['val']]);
            $this->db->execute("UPDATE pages_index SET status = 'published' WHERE id = ?", [$pageId]);

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
            $maxSeq = (int) $this->db->fetchColumn(
                'SELECT MAX(edit_seq) FROM pages_draft WHERE page_id = ?', [$pageId]
            );
            $this->db->execute(
                "UPDATE pages_draft SET block_config = ?
                  WHERE page_id = ? AND block_type = 'doc-html' AND edit_seq = ?",
                [json_encode($htmlAttrs), $pageId, $maxSeq]
            );
        }
        if ($headHtml !== null) {
            $maxSeq = $maxSeq ?? (int) $this->db->fetchColumn(
                'SELECT MAX(edit_seq) FROM pages_draft WHERE page_id = ?', [$pageId]
            );
            $this->db->execute(
                "UPDATE pages_draft SET inner_html = ?
                  WHERE page_id = ? AND block_type = 'doc-head' AND edit_seq = ?",
                [$headHtml, $pageId, $maxSeq]
            );
        }
        if ($bodyAttrs !== null) {
            $maxSeq = $maxSeq ?? (int) $this->db->fetchColumn(
                'SELECT MAX(edit_seq) FROM pages_draft WHERE page_id = ?', [$pageId]
            );
            $this->db->execute(
                "UPDATE pages_draft SET block_config = ?
                  WHERE page_id = ? AND block_type = 'doc-body' AND edit_seq = ?",
                [json_encode($bodyAttrs), $pageId, $maxSeq]
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

        $bk = $this->resolveBlockKey($pageId);
        $this->db->execute("DELETE FROM pages_draft WHERE {$bk['col']} = ?", [$bk['val']]);

        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/')) {
            $redirect = '/cms/editor?instance=__platform__&page=' . $pageId;
        } else {
            $redirect = '/admin/editor/' . $pageId . '/edit';
        }
        $this->json(['success' => true, 'redirect' => $redirect]);
    }

    /**
     * POST /admin/editor/{pageId}/reload-source
     * Clear draft state and rebuild draft from the page's source where possible.
     */
    public function reloadFromSource(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT * FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
        }

        $bk = $this->resolveBlockKey($pageId);
        $this->db->execute("DELETE FROM pages_draft WHERE {$bk['col']} = ?", [$bk['val']]);

        $renderMode = $page['render_mode'] ?? 'block';
        if (in_array($renderMode, ['html', 'file'], true)) {
            $importSvc = new \Cruinn\Services\ImportService();
            $absPath = null;

            if ($renderMode === 'file') {
                $filePath = $page['render_file'] ?? '';
                if ($filePath !== '') {
                    if (str_starts_with($filePath, '@cms/')) {
                        $absPath = dirname(__DIR__, 2) . '/' . substr($filePath, 5);
                    } else {
                        $absPath = CRUINN_PUBLIC . $filePath;
                    }
                }
            }

            $blocks = $importSvc->autoImport($page, $pageId, $absPath);
            if (!empty($blocks)) {
                $importSvc->persistImportedBlocks($blocks, $pageId, $this->db);
            }
        }

        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/')) {
            $redirect = '/cms/editor?instance=__platform__&page=' . $pageId;
        } else {
            $redirect = '/admin/editor/' . $pageId . '/edit';
        }
        $this->json(['success' => true, 'redirect' => $redirect]);
    }

    /**
     * GET /admin/editor/php-include-preview?template=rel/path&var1=val...
     * Returns the rendered HTML for a php-include block canvas preview.
     */
    public function phpIncludePreview(): void
    {
        Auth::requireAdmin();
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
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $menuId = (int) ($_GET['menu_id'] ?? 0);
        if (!$menuId) {
            echo json_encode(['html' => '']);
            exit;
        }

        $items = $this->db->fetchAll(
            'SELECT mi.*, p.slug AS page_slug
             FROM menu_items mi
             LEFT JOIN pages_index p ON mi.page_id = p.id
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
     * GET /admin/editor/canvas-preview?page_id=X
     * Returns rendered html/css for a published canvas page.
     */
    public function canvasPreview(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $pageId = (int) ($_GET['page_id'] ?? 0);
        if ($pageId <= 0) {
            echo json_encode(['html' => '', 'css' => '']);
            exit;
        }

        $page = $this->db->fetch(
            'SELECT id FROM pages_index WHERE id = ? AND canvas_type = ? LIMIT 1',
            [$pageId, 'zone']
        );
        if (!$page) {
            echo json_encode(['html' => '', 'css' => '']);
            exit;
        }

        try {
            $cruinnSvc = new \Cruinn\Services\CruinnRenderService();
            if (!$cruinnSvc->hasPublished($pageId)) {
                echo json_encode(['html' => '', 'css' => '']);
                exit;
            }
            echo json_encode([
                'html' => $cruinnSvc->buildHtml($pageId),
                'css' => $cruinnSvc->buildCss($pageId),
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Canvas preview failed']);
            exit;
        }
    }

    /**
     * POST /admin/editor/{pageId}/zone-canvas/new
     * Create a new zone canvas page for the current template canvas and
     * immediately assign it to the requested zone.
     */
    public function createZoneCanvas(string $pageId): void
    {
        $this->requireEditorAuth();
        header('Content-Type: application/json');

        $pageId = (int) $pageId;
        if ($pageId <= 0) {
            $this->json(['error' => 'Invalid page id'], 400);
        }

        $templateRow = $this->db->fetch(
            'SELECT id, name FROM page_templates WHERE canvas_page_id = ? LIMIT 1',
            [$pageId]
        );
        if (!$templateRow) {
            $this->json(['error' => 'Not a template canvas page'], 400);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $zoneName = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($body['zone_name'] ?? '')));
        if ($zoneName === '' || !preg_match('/^[a-z0-9_\-]+$/', $zoneName)) {
            $this->json(['error' => 'Invalid zone name'], 400);
        }

        $templateId = (int) $templateRow['id'];
        $templateName = (string) ($templateRow['name'] ?? 'Template');

        $baseSlug = '_zone_tpl_' . $templateId . '_' . $zoneName;
        $slug = $baseSlug;
        $i = 2;
        while ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM pages_index WHERE slug = ?', [$slug]) > 0) {
            $slug = $baseSlug . '_' . $i;
            $i++;
        }

        $zoneLabel = ucwords(str_replace(['-', '_'], ' ', $zoneName));
        $title = $zoneLabel . ' — ' . $templateName;

        $this->db->execute(
            "INSERT INTO pages_index (title, slug, status, template, editor_mode, canvas_type, zone_name)
             VALUES (?, ?, 'published', 'none', 'freeform', 'zone', ?)",
            [$title, $slug, $zoneName]
        );
        $newCanvasId = (int) $this->db->pdo()->lastInsertId();

        $this->updateTemplateZoneAssignments($templateId, [$zoneName => $newCanvasId]);

        $this->json([
            'success' => true,
            'canvas' => [
                'id' => $newCanvasId,
                'title' => $title,
                'zone_name' => $zoneName,
            ],
        ]);
    }

    /**
     * GET /admin/editor/zone/{zone}
     * Redirect to the Cruinn editor for the named global zone page (header/footer).
     */
    public function editZone(string $zone): void
    {
        Auth::requireAdmin();

        if (!in_array($zone, ['header', 'footer'], true)) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Zone Not Found']);
            return;
        }

        $page = $this->db->fetch(
            'SELECT id FROM pages_index WHERE slug = ? LIMIT 1',
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

    /**
     * GET /admin/editor/db-tables
     * Returns all table names in the instance DB as JSON.
     */
    public function dbTables(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        try {
            $svc    = new \Cruinn\Services\QueryBuilderService($this->db);
            $tables = $svc->getTables();
            echo json_encode(['tables' => $tables]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET /admin/editor/db-columns?tables[]=t1&tables[]=t2
     * Returns column names for the requested tables as JSON.
     */
    public function dbColumns(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $tables = array_values(array_filter((array) ($_GET['tables'] ?? []), fn($t) => is_string($t) && $t !== ''));
        if (empty($tables)) {
            echo json_encode(['columns' => (object) []]);
            exit;
        }
        // Reject obviously invalid table names before hitting DB
        foreach ($tables as $t) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid table name: ' . $t]);
                exit;
            }
        }
        try {
            $svc  = new \Cruinn\Services\QueryBuilderService($this->db);
            $cols = $svc->getColumns($tables);
            echo json_encode(['columns' => $cols]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET /admin/editor/db-preview?table=X&column=Y
     * Returns up to 8 distinct non-null values for a column as JSON.
     */
    public function dbPreview(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $table  = trim($_GET['table']  ?? '');
        $column = trim($_GET['column'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid table or column name']);
            exit;
        }
        try {
            $svc    = new \Cruinn\Services\QueryBuilderService($this->db);
            $values = $svc->getPreviewValues($table, $column);
            echo json_encode(['values' => $values]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Resolve context fields for a content template from its context_source string.
     *
     * Format 'content_set:{slug}' — reads fields JSON from content_sets.
     * Each field entry is expected to be {key, label, type} or {name, label, type}.
     * Returns a normalised array of [{key, label, type}].
     */
    private function resolveContextFields(string $contextSource): array
    {
        if (str_starts_with($contextSource, 'content_set:')) {
            $slug = substr($contextSource, strlen('content_set:'));
            $set  = $this->db->fetch(
                'SELECT type, query_config FROM content_sets WHERE slug = ? LIMIT 1',
                [$slug]
            );
            if (!$set) { return []; }

            if (($set['type'] ?? 'manual') === 'query') {
                // Run query with LIMIT 1 and derive fields from actual result columns
                try {
                    $svc  = new \Cruinn\Services\QueryBuilderService($this->db);
                    $qcfg = json_decode($set['query_config'] ?? '{}', true) ?: [];
                    $qcfg['limit'] = 1;
                    $rows = $svc->run($qcfg);
                    if (empty($rows)) { return []; }
                    $out = [];
                    foreach (array_keys($rows[0]) as $col) {
                        $out[] = ['key' => $col, 'label' => $col, 'type' => 'text'];
                    }
                    return $out;
                } catch (\Throwable $e) {
                    return [];
                }
            }

            // Manual set — derive fields from first row's data keys
            $row = $this->db->fetch(
                'SELECT data FROM content_set_rows
                  WHERE set_id = (SELECT id FROM content_sets WHERE slug = ? LIMIT 1)
                  ORDER BY sort_order ASC, id ASC LIMIT 1',
                [$slug]
            );
            if (!$row) { return []; }
            $data = json_decode($row['data'] ?? '{}', true);
            if (!is_array($data)) { return []; }
            $out = [];
            foreach (array_keys($data) as $col) {
                $out[] = ['key' => $col, 'label' => $col, 'type' => 'text'];
            }
            return $out;
        }

        $builtIn = [
            'blog.post' => [
                ['key' => 'title',         'label' => 'Post Title',       'type' => 'text'],
                ['key' => 'body_html',      'label' => 'Post Body',        'type' => 'html'],
                ['key' => 'excerpt',        'label' => 'Excerpt',          'type' => 'text'],
                ['key' => 'featured_image', 'label' => 'Featured Image',   'type' => 'image'],
                ['key' => 'author_name',    'label' => 'Author Name',      'type' => 'text'],
                ['key' => 'published_at',   'label' => 'Published Date',   'type' => 'date'],
                ['key' => 'subject_title',  'label' => 'Subject',          'type' => 'text'],
                ['key' => 'slug',           'label' => 'Post Slug',        'type' => 'text'],
            ],
            'blog.list' => [
                ['key' => 'articles',   'label' => 'Articles (list)',  'type' => 'collection'],
                ['key' => 'page',       'label' => 'Current Page',     'type' => 'number'],
                ['key' => 'totalPages', 'label' => 'Total Pages',      'type' => 'number'],
            ],
            'events.detail' => [
                ['key' => 'title',              'label' => 'Event Title',         'type' => 'text'],
                ['key' => 'description',        'label' => 'Description',         'type' => 'html'],
                ['key' => 'location',           'label' => 'Location',            'type' => 'text'],
                ['key' => 'date_start',         'label' => 'Start Date',          'type' => 'date'],
                ['key' => 'date_end',           'label' => 'End Date',            'type' => 'date'],
                ['key' => 'event_type',         'label' => 'Event Type',          'type' => 'text'],
                ['key' => 'price',              'label' => 'Price',               'type' => 'number'],
                ['key' => 'currency',           'label' => 'Currency',            'type' => 'text'],
                ['key' => 'capacity',           'label' => 'Capacity',            'type' => 'number'],
                ['key' => 'registrationCount',  'label' => 'Registrations',       'type' => 'number'],
                ['key' => 'spotsRemaining',     'label' => 'Spots Remaining',     'type' => 'number'],
                ['key' => 'slug',               'label' => 'Event Slug',          'type' => 'text'],
            ],
            'events.list' => [
                ['key' => 'events',      'label' => 'Events (list)',      'type' => 'collection'],
                ['key' => 'page',        'label' => 'Current Page',       'type' => 'number'],
                ['key' => 'totalPages',  'label' => 'Total Pages',        'type' => 'number'],
                ['key' => 'filter',      'label' => 'Current Filter',     'type' => 'text'],
            ],
        ];

        return $builtIn[$contextSource] ?? [];
    }

    private function getLayoutZones(int $layoutPageId): array
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
        $layoutZones = $this->getLayoutZones($layoutPageId);
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

    private function syncTemplateZoneBlocks(int $templateId, array $layoutZones): void
    {
        if ($templateId <= 0) {
            return;
        }

        $existing = $this->getTemplateZoneAssignments($templateId);

        $layoutZoneNames = [];
        foreach ($layoutZones as $zone) {
            $zoneName = $zone['zone_name'] ?? null;
            if (is_string($zoneName) && $zoneName !== '') {
                $layoutZoneNames[$zoneName] = true;
            }
        }

        // Remove zones that no longer exist in layout.
        foreach ($existing as $zoneName => $cfg) {
            if (!isset($layoutZoneNames[$zoneName])) {
                $this->db->execute(
                    'DELETE FROM pages WHERE template_id = ? AND block_id = ?',
                    [$templateId, $cfg['block_id']]
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

            if (isset($existing[$zoneName])) {
                $existingCfg = $existing[$zoneName];
                $mergedCfg = $layoutCfg;
                if (isset($existingCfg['canvas_page_id'])) {
                    $mergedCfg['canvas_page_id'] = (int) $existingCfg['canvas_page_id'];
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
                        $existingCfg['block_id'],
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

    private function updateTemplateZoneAssignments(int $templateId, array $assignments): void
    {
        if ($templateId <= 0) {
            return;
        }

        $maxSeq = (int) ($this->db->fetchColumn(
            'SELECT MAX(edit_seq) FROM pages_draft WHERE template_id = ?',
            [$templateId]
        ) ?: 0);

        // Draft-first model: if no template draft exists yet, seed seq 1 from
        // current published rows so metadata edits stay in draft until publish.
        if ($maxSeq <= 0) {
            $this->db->execute(
                'INSERT INTO pages_draft
                    (page_id, template_id, edit_seq, block_id, block_type, inner_html, css_props,
                     css_props_tablet, css_props_mobile, block_config, sort_order, parent_block_id)
                 SELECT page_id, template_id, 1, block_id, block_type, inner_html, css_props,
                        css_props_tablet, css_props_mobile, block_config, sort_order, parent_block_id
                   FROM pages
                  WHERE template_id = ?',
                [$templateId]
            );
            $maxSeq = 1;
        }

        $rows = $this->db->fetchAll(
            "SELECT block_id, block_config, sort_order
               FROM pages_draft
              WHERE template_id = ? AND edit_seq = ?
                AND block_type = 'zone' AND parent_block_id IS NULL
              ORDER BY sort_order ASC",
            [$templateId, $maxSeq]
        );

        $existing = [];
        foreach ($rows as $row) {
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            $zoneName = $cfg['zone_name'] ?? null;
            if (!$zoneName || !preg_match('/^[a-z0-9_-]+$/', (string) $zoneName)) {
                continue;
            }
            $existing[(string) $zoneName] = $cfg + [
                'block_id' => $row['block_id'],
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        foreach ($assignments as $zoneName => $canvasPageId) {
            if (!isset($existing[$zoneName])) {
                continue;
            }

            $cfg = $existing[$zoneName];
            unset($cfg['block_id'], $cfg['sort_order']);

            $canvasId = (int) $canvasPageId;
            if ($canvasId > 0) {
                $cfg['canvas_page_id'] = $canvasId;
            } else {
                unset($cfg['canvas_page_id']);
            }

            $this->db->execute(
                'UPDATE pages_draft SET block_config = ? WHERE template_id = ? AND edit_seq = ? AND block_id = ?',
                [json_encode($cfg), $templateId, $maxSeq, $existing[$zoneName]['block_id']]
            );
        }
    }

    private function stripPreviewEditorAttrs(string $html): string
    {
        $html = preg_replace('/\sdata-block(?:="[^"]*")?/', '', $html) ?? $html;
        $html = preg_replace('/\sdata-block-type="[^"]*"/', '', $html) ?? $html;
        return $html;
    }

    /**
     * Copy one draft snapshot into published pages.
     * If draft block_ids collide with existing published block_ids, remap only
     * the conflicting ids and rewrite parent_block_id references accordingly.
     */
    private function publishDraftSnapshotToPages(string $keyCol, int $keyVal, int $editSeq): void
    {
        if (!in_array($keyCol, ['page_id', 'template_id'], true) || $keyVal <= 0 || $editSeq <= 0) {
            return;
        }

        $rows = $this->db->fetchAll(
            "SELECT block_id, page_id, template_id, block_type, inner_html, css_props,
                    css_props_tablet, css_props_mobile, block_config, sort_order, parent_block_id
               FROM pages_draft
              WHERE {$keyCol} = ? AND edit_seq = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC",
            [$keyVal, $editSeq]
        );

        if (empty($rows)) {
            return;
        }

        $incomingIds = [];
        foreach ($rows as $row) {
            $bid = (string) ($row['block_id'] ?? '');
            if ($bid !== '') {
                $incomingIds[$bid] = true;
            }
        }

        $existingIds = [];
        $incomingList = array_keys($incomingIds);
        if (!empty($incomingList)) {
            $placeholders = implode(',', array_fill(0, count($incomingList), '?'));
            $existingRows = $this->db->fetchAll(
                "SELECT block_id FROM pages WHERE block_id IN ({$placeholders})",
                $incomingList
            );
            foreach ($existingRows as $existingRow) {
                $eid = (string) ($existingRow['block_id'] ?? '');
                if ($eid !== '') {
                    $existingIds[$eid] = true;
                }
            }
        }

        $idMap = [];
        $reservedIds = $incomingIds + $existingIds;
        foreach ($incomingList as $oldId) {
            if (isset($existingIds[$oldId])) {
                $idMap[$oldId] = $this->generateUniquePublishedBlockId($reservedIds);
            }
        }

        foreach ($rows as $row) {
            $oldBlockId = (string) ($row['block_id'] ?? '');
            $newBlockId = $idMap[$oldBlockId] ?? $oldBlockId;

            $parentBlockId = $row['parent_block_id'] ?? null;
            if (is_string($parentBlockId) && isset($idMap[$parentBlockId])) {
                $parentBlockId = $idMap[$parentBlockId];
            }

            $this->db->execute(
                'INSERT INTO pages
                    (block_id, page_id, template_id, block_type, inner_html, css_props,
                     css_props_tablet, css_props_mobile,
                     block_config, sort_order, parent_block_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $newBlockId,
                    $row['page_id'] !== null ? (int) $row['page_id'] : null,
                    $row['template_id'] !== null ? (int) $row['template_id'] : null,
                    (string) ($row['block_type'] ?? 'text'),
                    $row['inner_html'] ?? null,
                    $row['css_props'] ?? null,
                    $row['css_props_tablet'] ?? null,
                    $row['css_props_mobile'] ?? null,
                    $row['block_config'] ?? null,
                    (int) ($row['sort_order'] ?? 0),
                    $parentBlockId,
                ]
            );
        }
    }

    private function generateUniquePublishedBlockId(array &$reservedIds): string
    {
        do {
            $candidate = 'b-' . substr(bin2hex(random_bytes(8)), 0, 8);
            if (isset($reservedIds[$candidate])) {
                continue;
            }

            $exists = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM pages WHERE block_id = ?', [$candidate]) > 0;
            if ($exists) {
                continue;
            }

            $reservedIds[$candidate] = true;
            return $candidate;
        } while (true);
    }

    /**
     * POST /admin/editor/{pageId}/metadata
     * Save page metadata (template, zone) or template layout settings.
     */
    public function saveMetadata(string $pageId): void
    {
        $this->requireEditorAuth();
        $pageId = (int) $pageId;

        $page = $this->db->fetch('SELECT id, slug, template FROM pages_index WHERE id = ? LIMIT 1', [$pageId]);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $templateRow = $this->db->fetch(
            'SELECT id FROM page_templates WHERE canvas_page_id = ? LIMIT 1',
            [$pageId]
        );
        $isTemplatePage = $templateRow !== false && $templateRow !== null;

        if ($isTemplatePage) {
            $templateId = (int) $templateRow['id'];

            if (array_key_exists('layout_page_id', $body)) {
                $layoutPageId = (int) ($body['layout_page_id'] ?? 0);
                if ($layoutPageId > 0) {
                    $layoutPage = $this->db->fetch(
                        "SELECT id FROM pages_index
                         WHERE id = ? AND canvas_type = 'template-shell'
                           AND id NOT IN (SELECT canvas_page_id FROM page_templates WHERE canvas_page_id IS NOT NULL)
                         LIMIT 1",
                        [$layoutPageId]
                    );
                    if (!$layoutPage) {
                        $this->json(['error' => 'Invalid template layout'], 400);
                    }
                } else {
                    $layoutPageId = null;
                }

                $fields = ['layout_page_id' => $layoutPageId];
                if ($layoutPageId !== null) {
                    $layoutZones = $this->getLayoutZones($layoutPageId);
                    $this->syncTemplateZoneBlocks($templateId, $layoutZones);
                } else {
                    // Empty layout selection must prune all persisted template zones.
                    $this->syncTemplateZoneBlocks($templateId, []);
                }

                $this->db->update('page_templates', $fields, 'id = ?', [$templateId]);

                // Layout swaps invalidate prior template draft snapshots.
                $this->db->execute('DELETE FROM pages_draft WHERE template_id = ?', [$templateId]);
            }

            if (isset($body['zone_assignments']) && is_array($body['zone_assignments'])) {
                $this->updateTemplateZoneAssignments($templateId, $body['zone_assignments']);
            }

            $this->json(['success' => true]);
            return;
        }

        // Regular page metadata (template + zone)
        if (isset($body['template'])) {
            $template = preg_replace('/[^a-z0-9_\-]/', '', $body['template']);
            $this->db->execute('UPDATE pages_index SET template = ? WHERE id = ?', [$template, $pageId]);
        }
        if (isset($body['page_zone'])) {
            $zone = preg_replace('/[^a-z0-9_\-]/', '', $body['page_zone']);
            $this->db->execute('UPDATE pages_index SET page_zone = ? WHERE id = ?', [$zone, $pageId]);
        }

        $this->json(['success' => true]);
    }

}
