<?php
/**
 * Cruinn CMS — Article Editor Controller
 *
 * Opens the full Cruinn editor for an article, backed by its own
 * article_edit_state / article_draft_blocks tables (mirrors of the
 * page editor's cruinn_page_state / cruinn_draft_blocks).
 *
 * The JS editor is reused verbatim — only apiBase changes so AJAX
 * calls land here instead of /admin/editor.
 *
 * Routes (registered in blog/module.php):
 *   GET  /admin/article-editor/{id}/edit    → edit()
 *   POST /admin/article-editor/{id}/action  → recordAction()
 *   POST /admin/article-editor/{id}/undo    → undo()
 *   POST /admin/article-editor/{id}/redo    → redo()
 *   POST /admin/article-editor/{id}/publish → publish()
 *   POST /admin/article-editor/{id}/discard → discardDraft()
 */

namespace Cruinn\Module\Blog\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\EditorRenderService;
use Cruinn\Services\ImportService;

class ArticleEditorController extends BaseController
{
    // ── Helpers ───────────────────────────────────────────────────

    private function loadArticle(string $id): array
    {
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$article) {
            http_response_code(404);
            $this->renderAdmin('errors/404', ['title' => 'Article Not Found']);
            exit;
        }
        return $article;
    }

    /**
     * Load the active draft blocks for an article.
     * Falls back to published article_blocks if no draft exists.
     * On first open with existing article_blocks, seeds a draft from them.
     */
    private function loadBlocks(int $articleId): array
    {
        $state = $this->db->fetch(
            'SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]
        );

        if ($state) {
            return $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
        }

        // No draft yet — seed from published article_blocks if any exist.
        $published = $this->db->fetchAll(
            'SELECT * FROM article_blocks
              WHERE article_id = ?
              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
            [$articleId]
        );

        if (!empty($published)) {
            $this->seedDraftFromPublished($articleId, $published);
            return $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
        }

        // Brand new article — try to parse imported body_html if present.
        $article = $this->db->fetch('SELECT body_html FROM articles WHERE id = ? LIMIT 1', [$articleId]);
        $bodyHtml = $article['body_html'] ?? '';
        if ($bodyHtml !== '') {
            $importSvc = new ImportService();
            $blocks    = $importSvc->parseFragment($bodyHtml, $articleId);
            if (!empty($blocks)) {
                $this->seedDraftFromImport($articleId, $blocks);
                return $this->db->fetchAll(
                    'SELECT * FROM article_draft_blocks
                      WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                      ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                    [$articleId]
                );
            }
        }

        return [];
    }

    private function seedDraftFromPublished(int $articleId, array $rows): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO article_edit_state (article_id, current_edit_seq, max_edit_seq, last_edited_at)
                 VALUES (?, 1, 1, NOW())
                 ON DUPLICATE KEY UPDATE current_edit_seq = 1, max_edit_seq = 1, last_edited_at = NOW()',
                [$articleId]
            );
            foreach ($rows as $b) {
                $this->db->execute(
                    'INSERT INTO article_draft_blocks
                         (article_id, edit_seq, block_id, block_type, inner_html,
                          css_props, block_config, sort_order, parent_block_id, is_active, is_deletion)
                     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                    [
                        $articleId, $b['block_id'], $b['block_type'],
                        $b['inner_html'], $b['css_props'] ?? null, $b['block_config'] ?? null,
                        $b['sort_order'], $b['parent_block_id'] ?? null,
                    ]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function seedDraftFromImport(int $articleId, array $blocks): void
    {
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
                    [
                        $articleId, $b['block_id'], $b['block_type'],
                        $b['inner_html'], $b['css_props'] ?? null, $b['block_config'] ?? null,
                        $b['sort_order'], $b['parent_block_id'] ?? null,
                    ]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Build the full sidebar nav data expected by the editor template.
     * Articles appear in a dedicated "Articles" group in the page nav list.
     */
    private function buildNavData(): array
    {
        $sitePages = $this->db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages_index
             WHERE slug NOT LIKE '\_\%'
             ORDER BY title ASC"
        );

        $headerPages = [];
        $hp0 = $this->db->fetch("SELECT id FROM pages_index WHERE slug = '_header' LIMIT 1");
        if ($hp0) {
            $headerPages[] = ['id' => (int) $hp0['id'], 'title' => 'Header Zone Page', 'slug' => '_header', 'template_name' => null];
        }
        $footerPages = [];
        $fp0 = $this->db->fetch("SELECT id FROM pages_index WHERE slug = '_footer' LIMIT 1");
        if ($fp0) {
            $footerPages[] = ['id' => (int) $fp0['id'], 'title' => 'Footer Zone Page', 'slug' => '_footer', 'template_name' => null];
        }

        try {
            $navMenus = $this->db->fetchAll('SELECT id, name, block_page_id FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $navMenus = $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        $navTemplates = $this->db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id
             FROM page_templates pt
             LEFT JOIN pages_index p ON p.id = pt.canvas_page_id
             WHERE pt.slug NOT LIKE '\\_\\_%'
             ORDER BY pt.sort_order, pt.name"
        );

        $cssDir   = CRUINN_PUBLIC . '/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') as $f) {
            $cssFiles[] = basename($f);
        }
        sort($cssFiles);

        $tplBase    = dirname(__DIR__, 4) . '/templates';
        $tplExclude = ['/admin/', '/platform/'];
        $tplIter    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplBase, \FilesystemIterator::SKIP_DOTS)
        );
        $phpGroups = [];
        foreach ($tplIter as $tplFile) {
            if ($tplFile->getExtension() !== 'php') { continue; }
            $rel  = str_replace('\\', '/', substr($tplFile->getPathname(), strlen($tplBase) + 1));
            $skip = false;
            foreach ($tplExclude as $ex) {
                if (str_contains('/' . $rel, $ex)) { $skip = true; break; }
            }
            if ($skip) { continue; }
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
        } catch (\Throwable) {}

        return compact(
            'sitePages', 'headerPages', 'footerPages',
            'navMenus', 'navTemplates', 'cssFiles', 'phpGroups',
            'headerZoneHtml', 'headerZoneCss', 'footerZoneHtml', 'footerZoneCss'
        );
    }

    // ── Actions ───────────────────────────────────────────────────

    /**
     * GET /admin/article-editor/{id}/edit
     */
    public function edit(string $id): void
    {
        Auth::requireRole('admin');
        $article   = $this->loadArticle($id);
        $articleId = (int) $article['id'];

        $flat  = $this->loadBlocks($articleId);
        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);

        $nav = $this->buildNavData();

        $this->renderAdmin('admin/editor', [
            'title'             => 'Editor — ' . $article['title'],
            // Editor needs a $page-shaped object for its title/back-link etc.
            'page'              => [
                'id'          => $articleId,
                'title'       => $article['title'],
                'slug'        => $article['slug'],
                'render_mode' => 'cruinn',
                'status'      => $article['status'],
                'template'    => 'none',
            ],
            'hasDraft'          => !empty($state) && (int) ($state['current_edit_seq'] ?? 0) > 1,
            'state'             => $state,
            'cruinnHtml'        => (new EditorRenderService())->buildCanvasHtml($flat, $this->db),
            'cruinnCss'         => (new EditorRenderService())->buildCanvasCss($flat),
            'menus'             => $this->db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC'),
            'contentSets'       => $this->db->fetchAll('SELECT id, name, slug, type, fields FROM content_sets ORDER BY name ASC'),
            'isZonePage'        => false,
            'zoneName'          => null,
            'isTemplatePage'    => false,
            'templateSlugName'  => null,
            'templateId'        => null,
            'headerPageId'      => null,
            'footerPageId'      => null,
            'headerPages'       => $nav['headerPages'],
            'footerPages'       => $nav['footerPages'],
            'sitePages'         => $nav['sitePages'],
            'navTemplates'      => $nav['navTemplates'],
            'navMenus'          => $nav['navMenus'],
            'navCssFiles'       => $nav['cssFiles'],
            'navPhpGroups'      => $nav['phpGroups'],
            'headerZoneHtml'    => $nav['headerZoneHtml'],
            'headerZoneCss'     => $nav['headerZoneCss'],
            'footerZoneHtml'    => $nav['footerZoneHtml'],
            'footerZoneCss'     => $nav['footerZoneCss'],
            'templateZones'     => [],
            'templateCanvasPageId' => null,
            'templateCanvasHtml'   => '',
            'templateCanvasCss'    => '',
            'startInCodeView'   => false,
            'htmlContent'       => null,
            'isFileMode'        => false,
            'docHtmlBlock'      => null,
            'docHeadBlock'      => null,
            'docBodyBlock'      => null,
            'editorPageBase'    => null,
            'editorBackHref'    => '/admin/articles/' . $articleId . '/edit',
            'editorBackLabel'   => 'Article Settings',
            'apiBase'           => '/admin/article-editor/' . $articleId,
        ]);
    }

    /**
     * POST /admin/article-editor/{id}/action
     */
    public function recordAction(string $id): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $id;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $unsanitisedTypes = ['doc-html', 'doc-head', 'doc-body', 'php-code'];
        $incomingBlocks   = [];

        foreach ($body['blocks'] ?? [] as $b) {
            $blockId = preg_replace('/[^a-z0-9\-]/', '', (string) ($b['block_id'] ?? ''));
            if ($blockId === '') { continue; }
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
            $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
            if ($state) {
                $newSeq = (int) $state['current_edit_seq'] + 1;
                $this->db->execute(
                    'UPDATE article_edit_state
                        SET current_edit_seq = ?, max_edit_seq = ?, last_edited_at = NOW()
                      WHERE article_id = ?',
                    [$newSeq, $newSeq, $articleId]
                );
            } else {
                $newSeq = 1;
                $this->db->execute(
                    'INSERT INTO article_edit_state (article_id, current_edit_seq, max_edit_seq, last_edited_at)
                     VALUES (?, 1, 1, NOW())',
                    [$articleId]
                );
            }

            $this->db->execute(
                'DELETE FROM article_draft_blocks WHERE article_id = ? AND edit_seq > ?',
                [$articleId, $newSeq]
            );

            $activeRows = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks WHERE article_id = ? AND is_active = 1',
                [$articleId]
            );
            $activeByBlockId = [];
            foreach ($activeRows as $row) {
                $activeByBlockId[$row['block_id']] = $row;
            }

            $incomingIds = array_flip(array_column($incomingBlocks, 'block_id'));

            foreach ($incomingBlocks as $b) {
                $prevId = null;
                if (isset($activeByBlockId[$b['block_id']])) {
                    $oldRow = $activeByBlockId[$b['block_id']];
                    $prevId = $oldRow['id'];
                    $this->db->execute(
                        'UPDATE article_draft_blocks SET is_active = 0 WHERE id = ?',
                        [$prevId]
                    );
                }
                $this->db->execute(
                    'INSERT INTO article_draft_blocks
                        (article_id, edit_seq, block_id, block_type, inner_html, css_props,
                         block_config, sort_order, parent_block_id, is_active, is_deletion, prev_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)',
                    [
                        $articleId, $newSeq,
                        $b['block_id'], $b['block_type'], $b['inner_html'],
                        $b['css_props'], $b['block_config'],
                        $b['sort_order'], $b['parent_block_id'], $prevId,
                    ]
                );
            }

            $docBlockTypes = ['doc-html', 'doc-head', 'doc-body'];
            foreach ($activeByBlockId as $blockId => $oldRow) {
                if (isset($incomingIds[$blockId])) { continue; }
                if (in_array($oldRow['block_type'], $docBlockTypes, true)) { continue; }
                if ($oldRow['is_deletion']) { continue; }
                $this->db->execute(
                    'UPDATE article_draft_blocks SET is_active = 0 WHERE id = ?',
                    [$oldRow['id']]
                );
                $this->db->execute(
                    'INSERT INTO article_draft_blocks
                        (article_id, edit_seq, block_id, block_type, inner_html, css_props,
                         block_config, sort_order, parent_block_id, is_active, is_deletion, prev_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)',
                    [
                        $articleId, $newSeq,
                        $blockId, $oldRow['block_type'], $oldRow['inner_html'],
                        $oldRow['css_props'], $oldRow['block_config'],
                        $oldRow['sort_order'], $oldRow['parent_block_id'], $oldRow['id'],
                    ]
                );
            }

            // Enforce 50-action history cap
            $seqCount = (int) $this->db->fetchColumn(
                'SELECT COUNT(DISTINCT edit_seq) FROM article_draft_blocks WHERE article_id = ?',
                [$articleId]
            );
            if ($seqCount > 50) {
                $oldest = (int) $this->db->fetchColumn(
                    'SELECT MIN(edit_seq) FROM article_draft_blocks WHERE article_id = ?',
                    [$articleId]
                );
                $this->db->execute(
                    'DELETE FROM article_draft_blocks WHERE article_id = ? AND edit_seq = ?',
                    [$articleId, $oldest]
                );
            }

            $pdo->commit();

            $updatedState = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
            $this->json([
                'success'  => true,
                'edit_seq' => $newSeq,
                'can_undo' => (int) ($updatedState['current_edit_seq'] ?? 0) > 0,
                'can_redo' => (int) ($updatedState['current_edit_seq'] ?? 0)
                           <  (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('ArticleEditorController::recordAction: ' . $e->getMessage());
            $this->json(['error' => 'Failed to record action'], 500);
        }
    }

    /**
     * POST /admin/article-editor/{id}/undo
     */
    public function undo(string $id): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $id;

        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
        if (!$state || (int) $state['current_edit_seq'] <= 0) {
            $this->json(['error' => 'Nothing to undo'], 400);
        }

        $currentSeq = (int) $state['current_edit_seq'];
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks WHERE article_id = ? AND edit_seq = ? AND is_active = 1',
                [$articleId, $currentSeq]
            );
            foreach ($rows as $row) {
                $this->db->execute('UPDATE article_draft_blocks SET is_active = 0 WHERE id = ?', [$row['id']]);
                if ($row['prev_id'] !== null) {
                    $this->db->execute('UPDATE article_draft_blocks SET is_active = 1 WHERE id = ?', [$row['prev_id']]);
                }
            }
            $newSeq = $currentSeq - 1;
            $this->db->execute(
                'UPDATE article_edit_state SET current_edit_seq = ? WHERE article_id = ?',
                [$newSeq, $articleId]
            );
            $pdo->commit();

            $activeBlocks = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
            $updatedState = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
            $this->json([
                'success'  => true,
                'blocks'   => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('ArticleEditorController::undo: ' . $e->getMessage());
            $this->json(['error' => 'Undo failed'], 500);
        }
    }

    /**
     * POST /admin/article-editor/{id}/redo
     */
    public function redo(string $id): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $id;

        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
        if (!$state || (int) $state['current_edit_seq'] >= (int) $state['max_edit_seq']) {
            $this->json(['error' => 'Nothing to redo'], 400);
        }

        $newSeq = (int) $state['current_edit_seq'] + 1;
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks WHERE article_id = ? AND edit_seq = ? AND is_active = 0',
                [$articleId, $newSeq]
            );
            foreach ($rows as $row) {
                $this->db->execute('UPDATE article_draft_blocks SET is_active = 1 WHERE id = ?', [$row['id']]);
                if ($row['prev_id'] !== null) {
                    $this->db->execute('UPDATE article_draft_blocks SET is_active = 0 WHERE id = ?', [$row['prev_id']]);
                }
            }
            $this->db->execute(
                'UPDATE article_edit_state SET current_edit_seq = ? WHERE article_id = ?',
                [$newSeq, $articleId]
            );
            $pdo->commit();

            $activeBlocks = $this->db->fetchAll(
                'SELECT * FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0
                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                [$articleId]
            );
            $updatedState = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
            $this->json([
                'success'  => true,
                'blocks'   => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('ArticleEditorController::redo: ' . $e->getMessage());
            $this->json(['error' => 'Redo failed'], 500);
        }
    }

    /**
     * POST /admin/article-editor/{id}/publish
     * Promotes draft to article_blocks (published state).
     */
    public function publish(string $id): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $id;

        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
        if (!$state) {
            $this->json(['success' => true]);
            return;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->execute('DELETE FROM article_blocks WHERE article_id = ?', [$articleId]);
            $this->db->execute(
                'INSERT INTO article_blocks
                    (block_id, article_id, block_type, inner_html, css_props, block_config, sort_order, parent_block_id)
                 SELECT block_id, article_id, block_type, inner_html, css_props, block_config, sort_order, parent_block_id
                   FROM article_draft_blocks
                  WHERE article_id = ? AND is_active = 1 AND is_deletion = 0',
                [$articleId]
            );
            $this->db->execute('DELETE FROM article_draft_blocks WHERE article_id = ?', [$articleId]);
            $this->db->execute('DELETE FROM article_edit_state WHERE article_id = ?', [$articleId]);

            $pdo->commit();
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('ArticleEditorController::publish: ' . $e->getMessage());
            $this->json(['error' => 'Publish failed'], 500);
        }
    }

    /**
     * POST /admin/article-editor/{id}/discard
     * Deletes the draft and reverts to published article_blocks.
     */
    public function discardDraft(string $id): void
    {
        Auth::requireRole('admin');
        $articleId = (int) $id;

        $this->db->execute('DELETE FROM article_draft_blocks WHERE article_id = ?', [$articleId]);
        $this->db->execute('DELETE FROM article_edit_state WHERE article_id = ?', [$articleId]);

        $this->json([
            'success'  => true,
            'redirect' => '/admin/article-editor/' . $articleId . '/edit',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function sanitiseHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $xpath   = new \DOMXPath($doc);
        $removal = [];
        foreach ($xpath->query('//script | //style') as $node) {
            $removal[] = $node;
        }
        foreach ($xpath->query('//*[@*[starts-with(local-name(), "on")]]') as $el) {
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
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) { return ''; }
        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return $output;
    }
}
