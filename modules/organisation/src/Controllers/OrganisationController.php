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
use Cruinn\Database;
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
    //  DOCUMENTS â€” LIST / SHOW / UPLOAD / NEW VERSION / APPROVE / ARCHIVE
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * List all documents with optional filters.
     */
    public function documentList(): void
    {
        $category = $this->query('category', '');
        $status   = $this->query('status', '');
        $search   = $this->query('q', '');
        $page     = max(1, (int) $this->query('page', 1));
        $perPage  = 20;

        $where  = [];
        $params = [];

        if ($category !== '') {
            $where[]  = 'd.category = ?';
            $params[] = $category;
        }
        if ($status !== '') {
            $where[]  = 'd.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[]  = '(d.title LIKE ? OR d.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM documents d {$whereSQL}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset     = ($page - 1) * $perPage;

        $documents = $this->db->fetchAll(
            "SELECT d.*, u.display_name AS uploader_name,
                    a.display_name AS approver_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             LEFT JOIN users a ON d.approved_by = a.id
             {$whereSQL}
             ORDER BY d.updated_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $this->renderOrganisation('organisation/documents/index', [
            'title'      => 'Documents',
            'documents'  => $documents,
            'category'   => $category,
            'status'     => $status,
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    /**
     * Show a single document with version history.
     */
    public function documentShow(int $id): void
    {
        $document = $this->db->fetch(
            'SELECT d.*, u.display_name AS uploader_name,
                    a.display_name AS approver_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             LEFT JOIN users a ON d.approved_by = a.id
             WHERE d.id = ?',
            [$id]
        );

        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $versions = $this->db->fetchAll(
            'SELECT v.*, u.display_name AS uploader_name
             FROM document_versions v
             LEFT JOIN users u ON v.uploaded_by = u.id
             WHERE v.document_id = ?
             ORDER BY v.version_num DESC',
            [$id]
        );

        $this->renderOrganisation('organisation/documents/show', [
            'title'    => $document['title'],
            'document' => $document,
            'versions' => $versions,
        ]);
    }

    /**
     * Show the upload form for a new document.
     */
    public function documentNew(): void
    {
        $this->renderOrganisation('organisation/documents/upload', [
            'title'    => 'Upload Document',
            'document' => null,
            'errors'   => [],
        ]);
    }

    /**
     * Handle new document upload.
     */
    public function documentCreate(): void
    {
        $data = [
            'title'       => $this->input('title'),
            'description' => $this->input('description', ''),
            'category'    => $this->input('category', 'other'),
            'status'      => $this->input('status', 'draft'),
        ];

        $errors = $this->validateRequired(['title' => 'Title']);

        // Validate file
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors['file'] = 'A file is required.';
        }

        if ($errors) {
            $this->renderOrganisation('organisation/documents/upload', [
                'title'    => 'Upload Document',
                'document' => $data,
                'errors'   => $errors,
            ]);
            return;
        }

        $file = $_FILES['file'];
        $uploadResult = $this->handleDocumentUpload($file);

        if (!$uploadResult['success']) {
            $errors['file'] = $uploadResult['error'];
            $this->renderOrganisation('organisation/documents/upload', [
                'title'    => 'Upload Document',
                'document' => $data,
                'errors'   => $errors,
            ]);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $docId = $this->db->insert('documents', [
            'title'       => $data['title'],
            'description' => $data['description'],
            'category'    => $data['category'],
            'file_path'   => $uploadResult['path'],
            'file_size'   => $file['size'],
            'file_type'   => $ext,
            'uploaded_by' => Auth::userId(),
            'status'      => $data['status'],
            'version'     => 1,
        ]);

        // Also record as version 1
        $this->db->insert('document_versions', [
            'document_id' => $docId,
            'version_num' => 1,
            'file_path'   => $uploadResult['path'],
            'file_size'   => $file['size'],
            'uploaded_by' => Auth::userId(),
            'notes'       => 'Initial upload',
        ]);

        $this->logActivity('create', 'document', (int) $docId, "Uploaded: {$data['title']}");
        Auth::flash('success', 'Document uploaded successfully.');
        $this->redirect("/organisation/documents/{$docId}");
    }

    /**
     * Upload a new version of an existing document.
     */
    public function documentNewVersion(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'A file is required for the new version.');
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $file = $_FILES['file'];
        $uploadResult = $this->handleDocumentUpload($file);

        if (!$uploadResult['success']) {
            Auth::flash('error', $uploadResult['error']);
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $newVersion = (int) $document['version'] + 1;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $notes = $this->input('notes', '');

        // Update main document record
        $this->db->update('documents', [
            'file_path' => $uploadResult['path'],
            'file_size' => $file['size'],
            'file_type' => $ext,
            'version'   => $newVersion,
            'status'    => 'draft', // New version resets to draft
        ], 'id = ?', [$id]);

        // Record version
        $this->db->insert('document_versions', [
            'document_id' => $id,
            'version_num' => $newVersion,
            'file_path'   => $uploadResult['path'],
            'file_size'   => $file['size'],
            'uploaded_by' => Auth::userId(),
            'notes'       => $notes,
        ]);

        $this->logActivity('update', 'document', $id, "New version {$newVersion}: {$document['title']}");
        Auth::flash('success', "Version {$newVersion} uploaded successfully.");
        $this->redirect("/organisation/documents/{$id}");
    }

    /**
     * Submit a document for approval.
     */
    public function documentSubmit(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if ($document['status'] !== 'draft') {
            Auth::flash('error', 'Only draft documents can be submitted for approval.');
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $this->db->update('documents', ['status' => 'submitted'], 'id = ?', [$id]);
        $this->logActivity('update', 'document', $id, "Submitted for approval: {$document['title']}");
        Auth::flash('success', 'Document submitted for approval.');
        $this->redirect("/organisation/documents/{$id}");
    }

    /**
     * Approve a document (admin/organisation lead action).
     */
    public function documentApprove(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if ($document['status'] !== 'submitted') {
            Auth::flash('error', 'Only submitted documents can be approved.');
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $this->db->update('documents', [
            'status'      => 'approved',
            'approved_by' => Auth::userId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('approve', 'document', $id, "Approved: {$document['title']}");
        Auth::flash('success', 'Document approved.');
        $this->redirect("/organisation/documents/{$id}");
    }

    /**
     * Archive a document.
     */
    public function documentArchive(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->db->update('documents', ['status' => 'archived'], 'id = ?', [$id]);
        $this->logActivity('update', 'document', $id, "Archived: {$document['title']}");
        Auth::flash('success', 'Document archived.');
        $this->redirect("/organisation/documents/{$id}");
    }

    /**
     * Download a document file.
     */
    public function documentDownload(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $filePath = $this->publicPath($document['file_path']);
        if (!file_exists($filePath)) {
            Auth::flash('error', 'File not found on disk.');
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $filename = pathinfo($document['file_path'], PATHINFO_BASENAME);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    /**
     * Download a specific version of a document.
     */
    public function documentDownloadVersion(int $id, int $versionId): void
    {
        $version = $this->db->fetch(
            'SELECT * FROM document_versions WHERE id = ? AND document_id = ?',
            [$versionId, $id]
        );
        if (!$version) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $filePath = $this->publicPath($version['file_path']);
        if (!file_exists($filePath)) {
            Auth::flash('error', 'Version file not found on disk.');
            $this->redirect("/organisation/documents/{$id}");
            return;
        }

        $filename = pathinfo($version['file_path'], PATHINFO_BASENAME);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    /**
     * Delete a document and all versions.
     */
    public function documentDelete(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        // Get all version file paths for cleanup
        $versions = $this->db->fetchAll(
            'SELECT file_path FROM document_versions WHERE document_id = ?',
            [$id]
        );

        // Delete DB records (cascades to versions)
        $this->db->delete('documents', 'id = ?', [$id]);

        // Clean up files
        $allPaths = array_column($versions, 'file_path');
        $allPaths[] = $document['file_path'];
        foreach (array_unique($allPaths) as $path) {
            $fullPath = $this->publicPath($path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->logActivity('delete', 'document', $id, "Deleted: {$document['title']}");
        Auth::flash('success', 'Document deleted.');
        $this->redirect('/organisation/documents');
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
        $this->template->setLayout('organisation/layout');
        echo $this->template->render($view, $data);
    }

    /**
     * Handle a document file upload.
     * Returns ['success' => bool, 'path' => string, 'error' => string].
     */
    private function handleDocumentUpload(array $file): array
    {
        $config = App::config('uploads');

        // Validate size
        if ($file['size'] > $config['max_size']) {
            $maxMB = $config['max_size'] / (1024 * 1024);
            return ['success' => false, 'error' => "File too large. Maximum size: {$maxMB}MB"];
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $config['allowed'])) {
            return ['success' => false, 'error' => "File type not allowed: {$ext}"];
        }

        // Generate safe filename
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        // Organise into documents/year/month
        $subdir    = 'documents/' . date('Y/m');
        $uploadDir = dirname(__DIR__, 5) . '/public/uploads/' . $subdir;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        return [
            'success' => true,
            'path'    => '/uploads/' . $subdir . '/' . $filename,
        ];
    }

    /**
     * Format file size for display.
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Resolve an uploads-relative path (e.g. /uploads/foo.pdf) to CRUINN_ROOT/public.
     */
    private function publicPath(string $relativePath): string
    {
        return dirname(__DIR__, 5) . '/public' . $relativePath;
    }
}
