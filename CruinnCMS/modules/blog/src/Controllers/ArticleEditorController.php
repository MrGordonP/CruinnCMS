<?php

namespace Cruinn\Module\Blog\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ArticleEditorController extends BaseController
{
    public function recordAction(string $id): void
    {
        Auth::requireAdmin();
        $articleId = (int) $id;
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $pdo = null;
        try {
            $incomingBlocks = $this->normaliseIncomingBlocks($body['blocks'] ?? []);

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

            foreach ($incomingBlocks as $block) {
                $prevId = null;
                if (isset($activeByBlockId[$block['block_id']])) {
                    $oldRow = $activeByBlockId[$block['block_id']];
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
                        $articleId,
                        $newSeq,
                        $block['block_id'],
                        $block['block_type'],
                        $block['inner_html'],
                        $block['css_props'],
                        $block['block_config'],
                        $block['sort_order'],
                        $block['parent_block_id'],
                        $prevId,
                    ]
                );
            }

            $docBlockTypes = ['doc-html', 'doc-head', 'doc-body'];
            foreach ($activeByBlockId as $blockId => $oldRow) {
                if (isset($incomingIds[$blockId])) {
                    continue;
                }
                if (in_array($oldRow['block_type'], $docBlockTypes, true)) {
                    continue;
                }
                if ((int) ($oldRow['is_deletion'] ?? 0) === 1) {
                    continue;
                }

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
                        $articleId,
                        $newSeq,
                        $blockId,
                        $oldRow['block_type'],
                        $oldRow['inner_html'],
                        $oldRow['css_props'],
                        $oldRow['block_config'],
                        $oldRow['sort_order'],
                        $oldRow['parent_block_id'],
                        $oldRow['id'],
                    ]
                );
            }

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
                'success' => true,
                'edit_seq' => $newSeq,
                'can_undo' => (int) ($updatedState['current_edit_seq'] ?? 0) > 0,
                'can_redo' => (int) ($updatedState['current_edit_seq'] ?? 0) < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ArticleEditorController::recordAction: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->json(['success' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function undo(string $id): void
    {
        Auth::requireAdmin();
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
                'success' => true,
                'blocks' => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ArticleEditorController::undo: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function redo(string $id): void
    {
        Auth::requireAdmin();
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
                'success' => true,
                'blocks' => $activeBlocks,
                'can_undo' => $newSeq > 0,
                'can_redo' => $newSeq < (int) ($updatedState['max_edit_seq'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ArticleEditorController::redo: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function publish(string $id): void
    {
        Auth::requireAdmin();
        $articleId = (int) $id;

        $state = $this->db->fetch('SELECT * FROM article_edit_state WHERE article_id = ?', [$articleId]);
        if (!$state) {
            $this->json(['success' => true]);
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ArticleEditorController::publish: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function discardDraft(string $id): void
    {
        Auth::requireAdmin();
        $articleId = (int) $id;

        $this->db->execute('DELETE FROM article_draft_blocks WHERE article_id = ?', [$articleId]);
        $this->db->execute('DELETE FROM article_edit_state WHERE article_id = ?', [$articleId]);

        $this->json([
            'success' => true,
            'redirect' => '/admin/blog/editor/' . $articleId . '/edit',
        ]);
    }

    private function normaliseIncomingBlocks(array $rawBlocks): array
    {
        $unsanitisedTypes = ['doc-html', 'doc-head', 'doc-body', 'php-code'];
        $incomingBlocks = [];

        foreach ($rawBlocks as $block) {
            $blockId = preg_replace('/[^a-z0-9\-]/', '', (string) ($block['block_id'] ?? ''));
            if ($blockId === '') {
                continue;
            }

            $blockType = preg_replace('/[^a-z0-9\-]/', '', (string) ($block['block_type'] ?? 'text'));
            $innerHtml = isset($block['inner_html']) ? (string) $block['inner_html'] : null;
            if ($innerHtml !== null && !in_array($blockType, $unsanitisedTypes, true)) {
                $innerHtml = $this->sanitiseHtml($innerHtml);
            }

            $incomingBlocks[] = [
                'block_id' => $blockId,
                'block_type' => $blockType,
                'inner_html' => $innerHtml,
                'css_props' => is_array($block['css_props'] ?? null) ? json_encode((object) $block['css_props']) : null,
                'block_config' => is_array($block['block_config'] ?? null) ? json_encode($block['block_config']) : null,
                'sort_order' => max(0, (int) ($block['sort_order'] ?? 0)),
                'parent_block_id' => !empty($block['parent_block_id'])
                    ? preg_replace('/[^a-z0-9\-]/', '', (string) $block['parent_block_id'])
                    : null,
            ];
        }

        return $incomingBlocks;
    }

    private function sanitiseHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('#<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+(href|src|action)\s*=\s*(["\'])\s*javascript\s*:[^\2]*\2/i', ' $1="#"', $html) ?? '';

        return $html;
    }
}
