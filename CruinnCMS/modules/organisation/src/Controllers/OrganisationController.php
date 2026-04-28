<?php
/**
 * CruinnCMS — Organisation Controller
 *
 * Restricted workspace for organisation members.
 * Features: dashboard, document management (upload/version/approve/archive),
 * discussion threads with replies, and IMAP inbox viewer (stubbed).
 */

namespace Cruinn\Module\Organisation\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\DashboardService;

class OrganisationController extends BaseController
{
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  DASHBOARD
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * Organisation dashboard â€” overview of recent documents, discussions, activity.
     * Uses configurable widget system when available.
     */
    public function dashboard(): void
    {
        $roleId = Auth::roleId();

        if ($roleId) {
            $dashService = new DashboardService();
            $widgets = $dashService->buildDashboard($roleId);

            if (!empty($widgets)) {
                $this->renderOrganisation('organisation/dashboard', [
                    'title'          => 'Organisation Workspace',
                    'dashboardTitle' => 'Organisation Workspace',
                    'widgets'        => $widgets,
                ]);
                return;
            }
        }

        // Legacy fallback
        $recentDocuments = $this->db->fetchAll(
            'SELECT d.*, u.display_name AS uploader_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             ORDER BY d.updated_at DESC
             LIMIT 5'
        );

        $activeDiscussions = $this->db->fetchAll(
            'SELECT d.*, u.display_name AS author_name
             FROM discussions d
             LEFT JOIN users u ON d.created_by = u.id
             ORDER BY d.pinned DESC, d.last_post_at DESC, d.created_at DESC
             LIMIT 5'
        );

        $stats = [
            'documents'   => $this->db->fetchColumn('SELECT COUNT(*) FROM documents'),
            'pending'     => $this->db->fetchColumn("SELECT COUNT(*) FROM documents WHERE status = 'submitted'"),
            'discussions' => $this->db->fetchColumn('SELECT COUNT(*) FROM discussions'),
            'posts'       => $this->db->fetchColumn('SELECT COUNT(*) FROM discussion_posts'),
        ];

        $this->renderOrganisation('organisation/dashboard', [
            'title'             => 'Organisation Workspace',
            'recentDocuments'   => $recentDocuments,
            'activeDiscussions' => $activeDiscussions,
            'stats'             => $stats,
        ]);
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  DISCUSSIONS â€” LIST / SHOW (with replies) / NEW / POST
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * List all discussion threads.
     */
    public function discussionList(): void
    {
        $category = $this->query('category', '');
        $search   = $this->query('q', '');
        $page     = max(1, (int) $this->query('page', 1));
        $perPage  = 20;

        $where  = [];
        $params = [];

        if ($category !== '') {
            $where[]  = 'd.category = ?';
            $params[] = $category;
        }
        if ($search !== '') {
            $where[]  = 'd.title LIKE ?';
            $params[] = "%{$search}%";
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM discussions d {$whereSQL}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset     = ($page - 1) * $perPage;

        $discussions = $this->db->fetchAll(
            "SELECT d.*, u.display_name AS author_name
             FROM discussions d
             LEFT JOIN users u ON d.created_by = u.id
             {$whereSQL}
             ORDER BY d.pinned DESC, d.last_post_at DESC, d.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Get distinct categories for filter
        $categories = $this->db->fetchAll(
            'SELECT DISTINCT category FROM discussions WHERE category IS NOT NULL AND category != "" ORDER BY category'
        );

        $this->renderOrganisation('organisation/discussions/index', [
            'title'       => 'Discussions',
            'discussions' => $discussions,
            'categories'  => array_column($categories, 'category'),
            'category'    => $category,
            'search'      => $search,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    /**
     * Show a single discussion thread with all posts.
     */
    public function discussionShow(int $id): void
    {
        $discussion = $this->db->fetch(
            'SELECT d.*, u.display_name AS author_name
             FROM discussions d
             LEFT JOIN users u ON d.created_by = u.id
             WHERE d.id = ?',
            [$id]
        );

        if (!$discussion) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $posts = $this->db->fetchAll(
            'SELECT p.*, u.display_name AS author_name
             FROM discussion_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.discussion_id = ?
             ORDER BY p.created_at ASC',
            [$id]
        );

        $this->renderOrganisation('organisation/discussions/show', [
            'title'      => $discussion['title'],
            'discussion' => $discussion,
            'posts'      => $posts,
        ]);
    }

    /**
     * Show the "new discussion" form.
     */
    public function discussionNew(): void
    {
        $categories = $this->db->fetchAll(
            'SELECT DISTINCT category FROM discussions WHERE category IS NOT NULL AND category != "" ORDER BY category'
        );

        $this->renderOrganisation('organisation/discussions/new', [
            'title'      => 'New Discussion',
            'discussion' => null,
            'categories' => array_column($categories, 'category'),
            'errors'     => [],
        ]);
    }

    /**
     * Create a new discussion thread (with optional first post).
     */
    public function discussionCreate(): void
    {
        $data = [
            'title'    => $this->input('title'),
            'category' => $this->input('category', ''),
            'body'     => $this->input('body', ''),
        ];

        $errors = $this->validateRequired(['title' => 'Title']);

        if ($errors) {
            $categories = $this->db->fetchAll(
                'SELECT DISTINCT category FROM discussions WHERE category IS NOT NULL AND category != "" ORDER BY category'
            );

            $this->renderOrganisation('organisation/discussions/new', [
                'title'      => 'New Discussion',
                'discussion' => $data,
                'categories' => array_column($categories, 'category'),
                'errors'     => $errors,
            ]);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $postCount = 0;
        $lastPostAt = null;

        // If a body was provided, we'll create the first post
        if (!empty($data['body'])) {
            $postCount  = 1;
            $lastPostAt = $now;
        }

        $discussionId = $this->db->insert('discussions', [
            'title'        => $data['title'],
            'category'     => $data['category'] ?: null,
            'created_by'   => Auth::userId(),
            'pinned'       => 0,
            'locked'       => 0,
            'post_count'   => $postCount,
            'last_post_at' => $lastPostAt,
        ]);

        // Create first post if body provided
        if (!empty($data['body'])) {
            $this->db->insert('discussion_posts', [
                'discussion_id' => $discussionId,
                'author_id'     => Auth::userId(),
                'body'          => $data['body'],
            ]);
        }

        $this->logActivity('create', 'discussion', (int) $discussionId, "New thread: {$data['title']}");
        Auth::flash('success', 'Discussion created.');
        $this->redirect("/organisation/discussions/{$discussionId}");
    }

    /**
     * Post a reply to a discussion thread.
     */
    public function discussionReply(int $id): void
    {
        $discussion = $this->db->fetch('SELECT * FROM discussions WHERE id = ?', [$id]);
        if (!$discussion) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if ($discussion['locked']) {
            Auth::flash('error', 'This discussion is locked.');
            $this->redirect("/organisation/discussions/{$id}");
            return;
        }

        $body = $this->input('body', '');
        if (empty($body)) {
            Auth::flash('error', 'Reply cannot be empty.');
            $this->redirect("/organisation/discussions/{$id}");
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->insert('discussion_posts', [
            'discussion_id' => $id,
            'author_id'     => Auth::userId(),
            'body'          => $body,
        ]);

        // Update thread stats
        $this->db->execute(
            'UPDATE discussions SET post_count = post_count + 1, last_post_at = ? WHERE id = ?',
            [$now, $id]
        );

        $this->logActivity('create', 'discussion_post', $id, "Reply in: {$discussion['title']}");
        Auth::flash('success', 'Reply posted.');
        $this->redirect("/organisation/discussions/{$id}#latest");
    }

    /**
     * Toggle pin status on a discussion.
     */
    public function discussionTogglePin(int $id): void
    {
        $discussion = $this->db->fetch('SELECT * FROM discussions WHERE id = ?', [$id]);
        if (!$discussion) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $newPinned = $discussion['pinned'] ? 0 : 1;
        $this->db->update('discussions', ['pinned' => $newPinned], 'id = ?', [$id]);

        $action = $newPinned ? 'Pinned' : 'Unpinned';
        $this->logActivity('update', 'discussion', $id, "{$action}: {$discussion['title']}");
        Auth::flash('success', "Discussion {$action}.");
        $this->redirect("/organisation/discussions/{$id}");
    }

    /**
     * Toggle lock status on a discussion.
     */
    public function discussionToggleLock(int $id): void
    {
        $discussion = $this->db->fetch('SELECT * FROM discussions WHERE id = ?', [$id]);
        if (!$discussion) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $newLocked = $discussion['locked'] ? 0 : 1;
        $this->db->update('discussions', ['locked' => $newLocked], 'id = ?', [$id]);

        $action = $newLocked ? 'Locked' : 'Unlocked';
        $this->logActivity('update', 'discussion', $id, "{$action}: {$discussion['title']}");
        Auth::flash('success', "Discussion {$action}.");
        $this->redirect("/organisation/discussions/{$id}");
    }

    /**
     * Delete a discussion and all posts.
     */
    public function discussionDelete(int $id): void
    {
        $discussion = $this->db->fetch('SELECT * FROM discussions WHERE id = ?', [$id]);
        if (!$discussion) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->db->delete('discussions', 'id = ?', [$id]); // CASCADE deletes posts
        $this->logActivity('delete', 'discussion', $id, "Deleted: {$discussion['title']}");
        Auth::flash('success', 'Discussion deleted.');
        $this->redirect('/organisation/discussions');
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  INBOX â€” IMAP STUB
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * Organisation inbox viewer (IMAP).
     * Currently stubbed â€” requires IMAP server to be configured.
     */
    public function inbox(): void
    {
        $imapConfig   = App::config('imap');
        $roundcubeUrl = App::config('roundcube_url');
        $emails       = [];
        $error        = null;

        // Only attempt IMAP connection if credentials are configured
        if (!empty($imapConfig['password']) && function_exists('imap_open')) {
            try {
                $mailbox = sprintf(
                    '{%s:%d/imap/ssl}%s',
                    $imapConfig['host'],
                    $imapConfig['port'],
                    $imapConfig['mailbox']
                );

                $connection = @imap_open($mailbox, $imapConfig['username'], $imapConfig['password']);

                if ($connection) {
                    $messageCount = imap_num_msg($connection);
                    $start = max(1, $messageCount - 24); // Last 25 emails

                    for ($i = $messageCount; $i >= $start; $i--) {
                        $header = imap_headerinfo($connection, $i);
                        $emails[] = [
                            'number'  => $i,
                            'from'    => $header->fromaddress ?? 'Unknown',
                            'subject' => $header->subject ?? '(No subject)',
                            'date'    => date('Y-m-d H:i', strtotime($header->date ?? 'now')),
                            'seen'    => (bool) ($header->Unseen ?? false) === false,
                        ];
                    }

                    imap_close($connection);
                }
            } catch (\Throwable $e) {
                $error = 'Could not connect to mail server.';
                if (App::config('site.debug')) {
                    $error .= ' ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Mail server is not configured. Use the Roundcube link below to access organisation email directly.';
        }

        $this->renderOrganisation('organisation/inbox', [
            'title'        => 'Organisation Inbox',
            'emails'       => $emails,
            'error'        => $error,
            'roundcubeUrl' => $roundcubeUrl,
        ]);
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  HELPERS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * Render using the organisation layout.
     */
    protected function renderOrganisation(string $view, array $data = []): void
    {
        $this->renderAdmin($view, $data);
    }

}
