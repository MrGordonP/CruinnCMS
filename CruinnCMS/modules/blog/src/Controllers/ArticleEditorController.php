]633;E;sed -n '1,25p' /workspaces/CruinnCMS/CruinnCMS/modules/blog/src/Controllers/ArticleEditorController.php;2f1e49f8-31c9-4209-b2af-b0ee48c0b0ff]633;C<?php
/**
 * Cruinn CMS — Article Editor AJAX Controller
 *
 * Pure AJAX back-end for the shared Cruinn editor when editing articles.
 * The editor UI is opened by CruinnController::editArticle(), which sets
 * data-api-base to /admin/article-editor/{id} so all editor JS calls land here.
 *
 * Routes (registered in blog/module.php):
 *   POST /admin/article-editor/{id}/action  → recordAction()
 *   POST /admin/article-editor/{id}/undo    → undo()
 *   POST /admin/article-editor/{id}/redo    → redo()
 *   POST /admin/article-editor/{id}/publish → publish()
 *   POST /admin/article-editor/{id}/discard → discardDraft()
 */

namespace Cruinn\Module\Blog\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ArticleEditorController extends BaseController
{
    // ── AJAX Handlers ─────────────────────────────────────────────

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

        $pdo = null;
        try {
            $pdo = $this->db->pdo();
            $pdo->beginTransaction();
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
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('ArticleEditorController::recordAction: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->json(['success' => false, 'error' => $e->getMessage()], 200);
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
