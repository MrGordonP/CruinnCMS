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

    // ── Admin: edit thread title ──────────────────────────────────

    public function editThreadTitle(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newTitle = trim((string)$this->input('title', ''));
        if (mb_strlen($newTitle) < 5) {
            Auth::flash('error', 'Title must be at least 5 characters.');
            $this->redirect('/forum/thread/' . (int)$id);
        }

        $this->db->execute(
            'UPDATE forum_threads SET title = ?, updated_at = NOW() WHERE id = ?',
            [htmlspecialchars($newTitle, ENT_QUOTES), (int)$id]
        );
        $this->logActivity('update', 'forum_thread', (int)$id, 'Admin edited title: ' . $newTitle);

        Auth::flash('success', 'Thread title updated.');
        $this->redirect('/forum/thread/' . (int)$id);
    }

    // ── Admin: edit any post ──────────────────────────────────────

    public function editPost(string $id): void
    {
        Auth::requireRole('admin');

        $post = $this->db->fetch(
            'SELECT p.*, t.title AS thread_title, t.category_id FROM forum_posts p
             JOIN forum_threads t ON t.id = p.thread_id WHERE p.id = ? LIMIT 1',
            [$postId]
        );

        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $this->renderAdmin('admin/forum/edit-post', [
            'title'       => 'Edit Post',
            'post'        => $post,
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Edit Post']],
        ]);
    }

    public function updatePost(string $id): void
    {
        Auth::requireRole('admin');

        $post = $this->db->fetch('SELECT id, thread_id FROM forum_posts WHERE id = ? LIMIT 1', [$postId]);
        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $bodyHtml = trim((string)$this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Post content cannot be empty.');
            $this->redirect('/admin/forum/post/' . (int)$id . '/edit');
        }

        $this->db->execute(
            'UPDATE forum_posts SET body_html = ?, edit_count = edit_count + 1, edited_at = NOW(), updated_at = NOW() WHERE id = ?',
            [sanitise_html($bodyHtml), (int)$id]
        );

        $this->logActivity('update', 'forum_post', (int)$id, 'Admin edited post #' . (int)$id);
        Auth::flash('success', 'Post updated.');
        $this->redirect('/forum/thread/' . (int)$post['thread_id'] . '#post-' . (int)$id);
    }

    // ── Admin: delete any post ────────────────────────────────────

    public function deletePost(string $id): void
    {
        Auth::requireRole('admin');

        $post = $this->db->fetch('SELECT id, thread_id FROM forum_posts WHERE id = ? LIMIT 1', [$postId]);
        if (!$post) {
            Auth::flash('error', 'Post not found.');
            $this->redirect('/admin/forum');
        }

        $this->db->execute(
            'UPDATE forum_posts SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ?',
            [(int)Auth::userId(), (int)$id]
        );

        $this->logActivity('delete', 'forum_post', (int)$id, 'Admin deleted post #' . (int)$id);
        Auth::flash('success', 'Post deleted.');
        $this->redirect('/forum/thread/' . (int)$post['thread_id']);
    }

    // ── Admin: move thread ────────────────────────────────────────

    public function moveThreadForm(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title, category_id FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $categories = $this->db->fetchAll(
            'SELECT id, title, parent_id FROM forum_categories WHERE is_active = 1 ORDER BY sort_order ASC, title ASC'
        );

        $this->renderAdmin('admin/forum/move-thread', [
            'title'       => 'Move Thread',
            'thread'      => $thread,
            'categories'  => $categories,
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Move Thread']],
        ]);
    }

    public function moveThread(string $id): void
    {
        Auth::requireRole('admin');

        $thread = $this->db->fetch('SELECT id, title FROM forum_threads WHERE id = ? LIMIT 1', [$id]);
        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/admin/forum');
        }

        $newCategoryId = (int)$this->input('category_id', 0);
        if ($newCategoryId < 1) {
            Auth::flash('error', 'Please select a category.');
            $this->redirect('/admin/forum/' . (int)$id . '/move');
        }

        $cat = $this->db->fetch('SELECT id FROM forum_categories WHERE id = ? AND is_active = 1 LIMIT 1', [$newCategoryId]);
        if (!$cat) {
            Auth::flash('error', 'Category not found.');
            $this->redirect('/admin/forum/' . (int)$id . '/move');
        }

        $this->db->execute(
            'UPDATE forum_threads SET category_id = ?, updated_at = NOW() WHERE id = ?',
            [$newCategoryId, (int)$id]
        );

        $this->logActivity('update', 'forum_thread', (int)$thread['id'], 'Moved thread: ' . $thread['title']);
        Auth::flash('success', 'Thread moved.');
        $this->redirect('/forum/thread/' . (int)$id);
    }

    // ── Admin: post reports ───────────────────────────────────────

    public function listReports(): void
    {
        Auth::requireRole('admin');

        $status = (string)$this->query('status', 'open');

        $where = $status === 'all' ? '' : 'WHERE r.status = ?';
        $params = $status === 'all' ? [] : [$status];

        $reports = $this->db->fetchAll(
            "SELECT r.*, u.display_name AS reporter_name, ru.display_name AS reviewer_name,
                    p.body_html, p.thread_id, t.title AS thread_title
             FROM forum_post_reports r
             JOIN users u ON u.id = r.reporter_id
             LEFT JOIN users ru ON ru.id = r.reviewed_by
             JOIN forum_posts p ON p.id = r.post_id
             JOIN forum_threads t ON t.id = p.thread_id
             {$where}
             ORDER BY r.created_at DESC
             LIMIT 200",
            $params
        );

        $this->renderAdmin('admin/forum/reports', [
            'title'       => 'Post Reports',
            'reports'     => $reports,
            'status'      => $status,
            'breadcrumbs' => [['Admin', '/admin'], ['Forum', '/admin/forum'], ['Post Reports']],
        ]);
    }

    public function reviewReport(string $id): void
    {
        Auth::requireRole('admin');

        $report = $this->db->fetch('SELECT id FROM forum_post_reports WHERE id = ? LIMIT 1', [$reportId]);
        if (!$report) {
            Auth::flash('error', 'Report not found.');
            $this->redirect('/admin/forum/reports');
        }

        $newStatus = (string)$this->input('status', 'reviewed');
        if (!in_array($newStatus, ['reviewed', 'dismissed'], true)) {
            $newStatus = 'reviewed';
        }

        $this->db->execute(
            'UPDATE forum_post_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$newStatus, (int)Auth::userId(), (int)$id]
        );

        $this->logActivity('update', 'forum_post_report', (int)$id, 'Report marked: ' . $newStatus);
        Auth::flash('success', 'Report marked as ' . $newStatus . '.');
        $this->redirect('/admin/forum/reports');
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
