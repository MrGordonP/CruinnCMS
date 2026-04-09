<?php

namespace Cruinn\Module\Forum\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ForumAdminController extends BaseController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $search = trim((string)$this->query('q', ''));
        $categoryId = (int)$this->query('category_id', 0);
        $status = (string)$this->query('status', 'all');

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where[] = 't.category_id = ?';
            $params[] = $categoryId;
        }

        if ($status === 'locked') {
            $where[] = 't.is_locked = 1';
        } elseif ($status === 'open') {
            $where[] = 't.is_locked = 0';
        } elseif ($status === 'pinned') {
            $where[] = 't.is_pinned = 1';
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $threads = $this->db->fetchAll(
            'SELECT t.*, c.title AS category_title, c.slug AS category_slug, u.display_name AS author_name
             FROM forum_threads t
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = t.user_id
             ' . $whereSql . '
             ORDER BY t.is_pinned DESC, t.last_post_at DESC
             LIMIT 300',
            $params
        );

        $categories = $this->db->fetchAll(
            'SELECT id, title FROM forum_categories ORDER BY sort_order ASC, title ASC'
        );

        $this->renderAdmin('admin/forum/index', [
            'title' => 'Forum Moderation',
            'threads' => $threads,
            'categories' => $categories,
            'filters' => [
                'q' => $search,
                'category_id' => $categoryId,
                'status' => $status,
            ],
            'breadcrumbs' => [['Admin', '/admin'], ['Forum Moderation']],
        ]);
    }

    public function togglePin(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title, is_pinned FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newValue = (int)$thread['is_pinned'] === 1 ? 0 : 1;

        $this->db->update('forum_threads', [
            'is_pinned' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity(
            'update',
            'forum_thread',
            (int)$thread['id'],
            ($newValue === 1 ? 'Pinned: ' : 'Unpinned: ') . $thread['title']
        );

        Auth::flash('success', $newValue === 1 ? 'Thread pinned.' : 'Thread unpinned.');
        $this->redirect($this->forumReturnUrl());
    }

    public function toggleLock(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title, is_locked FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newValue = (int)$thread['is_locked'] === 1 ? 0 : 1;

        $this->db->update('forum_threads', [
            'is_locked' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity(
            'update',
            'forum_thread',
            (int)$thread['id'],
            ($newValue === 1 ? 'Locked: ' : 'Unlocked: ') . $thread['title']
        );

        Auth::flash('success', $newValue === 1 ? 'Thread locked.' : 'Thread unlocked.');
        $this->redirect($this->forumReturnUrl());
    }

    public function deleteThread(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $this->db->transaction(function () use ($id): void {
            $this->db->delete('forum_threads', 'id = ?', [$id]);
        });

        $this->logActivity('delete', 'forum_thread', (int)$thread['id'], 'Deleted: ' . $thread['title']);

        Auth::flash('success', 'Thread deleted.');
        $this->redirect('/admin/forum');
    }

    private function forumReturnUrl(): string
    {
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $path = (string)parse_url($referer, PHP_URL_PATH);
        $query = (string)parse_url($referer, PHP_URL_QUERY);

        if ($path === '/admin/forum') {
            return $query !== '' ? '/admin/forum?' . $query : '/admin/forum';
        }

        return '/admin/forum';
    }
}
