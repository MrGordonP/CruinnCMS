<?php
/**
 * CruinnCMS â€” Drivespace Controller
 *
 * Google-Drive-style file management: folder tree, file upload/compose,
 * document parsing, version history, sharing, and publish workflow.
 */

namespace Cruinn\Module\Drivespace\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;
use Cruinn\Module\Drivespace\Services\DocumentService;
use Cruinn\Services\NotificationService;

class FileManagerController extends BaseController
{
    private DocumentService $docService;

    public function __construct()
    {
        parent::__construct();
        $this->docService = new DocumentService();
    }

    // â”€â”€ File Browser â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /drivespace â€” Main file browser (folder tree + file grid).
     */
    public function index(): void
    {
        $folderId = $this->query('folder') ? (int) $this->query('folder') : null;
        $search = $this->query('q', '');
        $status = $this->query('status', '');
        $type = $this->query('type', '');

        // Build breadcrumb trail
        $breadcrumb = $this->buildBreadcrumb($folderId);

        // Get current folder info
        $currentFolder = null;
        if ($folderId) {
            $currentFolder = $this->db->fetch(
                'SELECT * FROM folders WHERE id = ?',
                [$folderId]
            );
            if (!$currentFolder || !$this->canAccessFolder($currentFolder)) {
                Auth::flash('error', 'Folder not found or access denied.');
                $this->redirect('/drivespace');
            }
        }

        // Get subfolders
        $subfolders = $this->getAccessibleFolders($folderId);

        // Get files in current folder
        $where = ['1=1'];
        $params = [];

        if ($folderId) {
            $where[] = 'f.folder_id = ?';
            $params[] = $folderId;
        } else {
            $where[] = 'f.folder_id IS NULL';
        }

        if ($search) {
            $where[] = '(f.title LIKE ? OR f.description LIKE ? OR f.original_name LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($status) {
            $where[] = 'f.status = ?';
            $params[] = $status;
        }

        if ($type) {
            $where[] = 'f.file_ext = ?';
            $params[] = $type;
        }

        $whereClause = implode(' AND ', $where);

        $files = $this->db->fetchAll(
            "SELECT f.*, u.display_name as owner_name, s.title as subject_title
             FROM files f
             LEFT JOIN users u ON f.owner_id = u.id
             LEFT JOIN subjects s ON f.subject_id = s.id
             WHERE {$whereClause}
             ORDER BY f.updated_at DESC",
            $params
        );

        // Filter files by access permissions
        $files = array_filter($files, fn($file) => $this->canAccessFile($file, $currentFolder));

        // Get folder tree for sidebar
        $folderTree = $this->buildFolderTree();

        // Get subjects for filter dropdown
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title',
            ['active']
        );

        $this->renderAdmin('admin/files/index', [
            'title' => 'Drivespace',
            'files' => array_values($files),
            'subfolders' => $subfolders,
            'currentFolder' => $currentFolder,
            'folderTree' => $folderTree,
            'breadcrumb' => $breadcrumb,
            'subjects' => $subjects,
            'search' => $search,
            'status' => $status,
            'type' => $type,
        ]);
    }

    /**
     * GET /drivespace/search â€” Global file search (AJAX).
     */
    public function search(): void
    {
        $q = $this->query('q', '');
        if (strlen($q) < 2) {
            $this->json(['files' => []]);
        }

        $term = '%' . $q . '%';
        $files = $this->db->fetchAll(
            "SELECT f.*, u.display_name as owner_name, fo.name as folder_name
             FROM files f
             LEFT JOIN users u ON f.owner_id = u.id
             LEFT JOIN folders fo ON f.folder_id = fo.id
             WHERE f.title LIKE ? OR f.description LIKE ? OR f.original_name LIKE ?
             ORDER BY f.updated_at DESC
             LIMIT 20",
            [$term, $term, $term]
        );

        $this->json(['files' => $files]);
    }

    // â”€â”€ File Upload â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /drivespace/upload â€” Upload form.
     */
    public function uploadForm(): void
    {
        $folderId = $this->query('folder') ? (int) $this->query('folder') : null;

        $folders = $this->getAccessibleFolders(null, true);
        $subjects = $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title',
            ['active']
        );

        $this->renderAdmin('admin/files/upload', [
            'title' => 'Upload File',
            'folders' => $folders,
            'subjects' => $subjects,
            'currentFolderId' => $folderId,
        ]);
    }

    /**
     * POST /drivespace/upload â€” Handle file upload + auto-parse.
     */
    public function upload(): void
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'No file was uploaded or upload failed.');
            $this->redirect('/drivespace/upload');
        }

        $file = $_FILES['file'];
        $title = $this->input('title') ?: pathinfo($file['name'], PATHINFO_FILENAME);
        $description = $this->input('description', '');
        $folderId = $this->input('folder_id') ? (int) $this->input('folder_id') : null;
        $subjectId = $this->input('subject_id') ? (int) $this->input('subject_id') : null;

        // Validate folder access
        if ($folderId) {
            $folder = $this->db->fetch('SELECT * FROM folders WHERE id = ?', [$folderId]);
            if (!$folder || !$this->canWriteFolder($folder)) {
                Auth::flash('error', 'Access denied to target folder.');
                $this->redirect('/drivespace/upload');
            }
        }

        // Validate file
        $result = $this->handleFileUpload($file);
        if (!$result['success']) {
            Auth::flash('error', $result['error']);
            $this->redirect('/drivespace/upload');
        }

        // Auto-parse if parseable format
        $parsedContent = null;
        $metadata = null;
        $ext = $result['ext'];

        if (DocumentService::isParseable($ext)) {
            $fullPath = dirname(__DIR__) . '/public' . $result['path'];
            $parsed = $this->docService->parseFile($fullPath, $ext);
            $parsedContent = $parsed['html'];
            $metadata = $parsed['metadata'];
        }

        // Insert file record
        $fileId = $this->db->insert('files', [
            'folder_id' => $folderId,
            'title' => $title,
            'description' => $description,
            'file_path' => $result['path'],
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $result['real_mime'],
            'file_ext' => $ext,
            'owner_id' => Auth::userId(),
            'subject_id' => $subjectId,
            'status' => 'draft',
            'version' => 1,
            'content_type' => 'upload',
            'parsed_content' => $parsedContent,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Save initial version
        $this->db->insert('file_versions', [
            'file_id' => $fileId,
            'version_num' => 1,
            'file_path' => $result['path'],
            'file_size' => $file['size'],
            'parsed_content' => $parsedContent,
            'notes' => 'Initial upload',
            'created_by' => Auth::userId(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'file', (int) $fileId, "Uploaded: {$file['name']}");

        Auth::flash('success', 'File uploaded successfully.' . ($parsedContent ? ' Content has been parsed.' : ''));
        $this->redirect('/drivespace/' . $fileId);
    }

    // â”€â”€ File Detail â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /drivespace/{id} â€” View file detail, parsed content, versions, sharing.
     */
    public function show(int $id): void
    {
        $file = $this->db->fetch(
            "SELECT f.*, u.display_name as owner_name, s.title as subject_title,
                    fo.name as folder_name, fo.id as folder_id_resolved
             FROM files f
             LEFT JOIN users u ON f.owner_id = u.id
             LEFT JOIN subjects s ON f.subject_id = s.id
             LEFT JOIN folders fo ON f.folder_id = fo.id
             WHERE f.id = ?",
            [$id]
        );

        if (!$file) {
            Auth::flash('error', 'File not found.');
            $this->redirect('/drivespace');
        }

        // Check access
        $folder = $file['folder_id'] ? $this->db->fetch('SELECT * FROM folders WHERE id = ?', [$file['folder_id']]) : null;
        if (!$this->canAccessFile($file, $folder)) {
            Auth::flash('error', 'Access denied.');
            $this->redirect('/drivespace');
        }

        // Version history
        $versions = $this->db->fetchAll(
            "SELECT fv.*, u.display_name as creator_name
             FROM file_versions fv
             LEFT JOIN users u ON fv.created_by = u.id
             WHERE fv.file_id = ?
             ORDER BY fv.version_num DESC",
            [$id]
        );

        // Sharing info
        $shares = $this->db->fetchAll(
            "SELECT fs.*,
                    CASE fs.target_type
                        WHEN 'user' THEN (SELECT display_name FROM users WHERE id = fs.target_id)
                        WHEN 'role' THEN (SELECT name FROM roles WHERE id = fs.target_id)
                    END as target_name
             FROM file_shares fs
             WHERE fs.resource_type = 'file' AND fs.resource_id = ?
             ORDER BY fs.created_at DESC",
            [$id]
        );

        // Publication history
        $publications = $this->db->fetchAll(
            "SELECT fp.*, u.display_name as publisher_name
             FROM file_publications fp
             LEFT JOIN users u ON fp.published_by = u.id
             WHERE fp.file_id = ?
             ORDER BY fp.published_at DESC",
            [$id]
        );

        // Available roles and users for sharing
        $roles = $this->db->fetchAll('SELECT id, name FROM roles ORDER BY name');
        $users = $this->db->fetchAll('SELECT id, display_name FROM users WHERE status = ? ORDER BY display_name', ['active']);

        // Subjects for metadata editing
        $subjects = $this->db->fetchAll('SELECT id, title FROM subjects WHERE status = ? ORDER BY title', ['active']);

        // All folders for move operation
        $folders = $this->getAccessibleFolders(null, true);

        $canEdit = $this->canEditFile($file, $folder);

        $this->renderAdmin('admin/files/show', [
            'title' => $file['title'],
            'file' => $file,
            'versions' => $versions,
            'shares' => $shares,
            'publications' => $publications,
            'roles' => $roles,
            'users' => $users,
            'subjects' => $subjects,
            'folders' => $folders,
            'canEdit' => $canEdit,
        ]);
    }

    // â”€â”€ File Actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /drivespace/{id}/download â€” Download the file.
     */
    public function download(int $id): void
    {
        $file = $this->db->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$file) {
            Auth::flash('error', 'File not found.');
            $this->redirect('/drivespace');
        }

        if (!$file['file_path']) {
            // Composed document â€” export as HTML
            $path = $this->docService->exportToHtml($file['parsed_content'] ?? '', $file['title']);
            $fullPath = dirname(__DIR__) . '/public' . $path;
            $name = pathinfo($path, PATHINFO_BASENAME);
        } else {
            $fullPath = dirname(__DIR__) . '/public' . $file['file_path'];
            $name = $file['original_name'] ?: basename($file['file_path']);
        }

        if (!file_exists($fullPath)) {
            Auth::flash('error', 'File not found on disk.');
            $this->redirect('/drivespace/' . $id);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    /**
     * POST /drivespace/{id}/version â€” Upload a new version of an existing file.
     */
    public function newVersion(int $id): void
    {
        $file = $this->db->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$file) {
            Auth::flash('error', 'File not found.');
            $this->redirect('/drivespace');
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'No file uploaded.');
            $this->redirect('/drivespace/' . $id);
        }

        $uploadedFile = $_FILES['file'];
        $result = $this->handleFileUpload($uploadedFile);
        if (!$result['success']) {
            Auth::flash('error', $result['error']);
            $this->redirect('/drivespace/' . $id);
        }

        $newVersion = (int) $file['version'] + 1;

        // Parse new version if parseable
        $parsedContent = null;
        if (DocumentService::isParseable($result['ext'])) {
            $fullPath = dirname(__DIR__) . '/public' . $result['path'];
            $parsed = $this->docService->parseFile($fullPath, $result['ext']);
            $parsedContent = $parsed['html'];
        }

        // Update file record
        $this->db->update('files', [
            'file_path' => $result['path'],
            'original_name' => $uploadedFile['name'],
            'file_size' => $uploadedFile['size'],
            'mime_type' => $uploadedFile['type'],
            'file_ext' => $result['ext'],
            'version' => $newVersion,
            'parsed_content' => $parsedContent ?? $file['parsed_content'],
            'status' => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Save version
        $this->db->insert('file_versions', [
            'file_id' => $id,
            'version_num' => $newVersion,
            'file_path' => $result['path'],
            'file_size' => $uploadedFile['size'],
            'parsed_content' => $parsedContent,
            'notes' => $this->input('notes', ''),
            'created_by' => Auth::userId(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('update', 'file', $id, "New version v{$newVersion}: {$uploadedFile['name']}");

        Auth::flash('success', "Version {$newVersion} uploaded successfully.");
        $this->redirect('/drivespace/' . $id);
    }


    /**
     * POST /drivespace/{id}/delete â€” Delete a file and its versions.
     */
    public function delete(int $id): void
    {
        $file = $this->db->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$file) {
            Auth::flash('error', 'File not found.');
            $this->redirect('/drivespace');
        }

        $folder = $file['folder_id'] ? $this->db->fetch('SELECT * FROM folders WHERE id = ?', [$file['folder_id']]) : null;
        if (!$this->canEditFile($file, $folder) && Auth::role() !== 'admin') {
            Auth::flash('error', 'Access denied.');
            $this->redirect('/drivespace/' . $id);
        }

        // Delete physical files (all versions)
        $versions = $this->db->fetchAll('SELECT file_path FROM file_versions WHERE file_id = ?', [$id]);
        foreach ($versions as $v) {
            if ($v['file_path']) {
                $path = dirname(__DIR__) . '/public' . $v['file_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        // Delete main file if different
        if ($file['file_path']) {
            $mainPath = dirname(__DIR__) . '/public' . $file['file_path'];
            if (file_exists($mainPath)) {
                unlink($mainPath);
            }
        }

        // CASCADE handles file_versions, file_shares, file_publications
        $this->db->delete('files', 'id = ?', [$id]);

        $this->logActivity('delete', 'file', $id, "Deleted: {$file['title']}");

        Auth::flash('success', 'File deleted.');
        $this->redirect($file['folder_id'] ? '/drivespace?folder=' . $file['folder_id'] : '/drivespace');
    }

    // â”€â”€ Sharing â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /drivespace/{id}/share â€” Add a share permission.
     */
    public function share(int $id): void
    {
        $file = $this->db->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$file) {
            Auth::flash('error', 'File not found.');
            $this->redirect('/drivespace');
        }

        $targetType = $this->input('target_type');
        $targetId = (int) $this->input('target_id');
        $permission = $this->input('permission', 'view');

        if (!in_array($targetType, ['user', 'role'], true) || !$targetId) {
            Auth::flash('error', 'Invalid share target.');
            $this->redirect('/drivespace/' . $id);
        }

        if (!in_array($permission, ['view', 'edit', 'manage'], true)) {
            $permission = 'view';
        }

        // Insert or update
        $existing = $this->db->fetch(
            'SELECT id FROM file_shares WHERE resource_type = ? AND resource_id = ? AND target_type = ? AND target_id = ?',
            ['file', $id, $targetType, $targetId]
        );

        if ($existing) {
            $this->db->update('file_shares', ['permission' => $permission], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('file_shares', [
                'resource_type' => 'file',
                'resource_id' => $id,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'permission' => $permission,
                'shared_by' => Auth::userId(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Notify shared user
        if ($targetType === 'user') {
            $notificationService = new NotificationService();
            $notificationService->notifyUser(
                $targetId,
                'council',
                'File shared with you: ' . $file['title'],
                null,
                '/drivespace/' . $id
            );
        }

        $this->logActivity('share', 'file', $id, "Shared with {$targetType}:{$targetId} ({$permission})");

        Auth::flash('success', 'File shared successfully.');
        $this->redirect('/drivespace/' . $id);
    }

    /**
     * POST /drivespace/{id}/unshare â€” Remove a share permission.
     */
    public function unshare(int $id): void
    {
        $shareId = (int) $this->input('share_id');
        $this->db->delete('file_shares', 'id = ? AND resource_id = ?', [$shareId, $id]);

        Auth::flash('success', 'Share removed.');
        $this->redirect('/drivespace/' . $id);
    }
    // â”€â”€ Folder Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /drivespace/folders â€” Create a new folder.
     */
    public function createFolder(): void
    {
        $name = $this->input('name');
        if (!$name) {
            Auth::flash('error', 'Folder name is required.');
            $this->redirect('/drivespace');
        }

        $parentId = $this->input('parent_id') ? (int) $this->input('parent_id') : null;
        $visibility = $this->input('visibility', 'private');
        $subjectId = $this->input('subject_id') ? (int) $this->input('subject_id') : null;
        $description = $this->input('description', '');

        if (!in_array($visibility, ['private', 'role', 'members', 'public'], true)) {
            $visibility = 'private';
        }

        $allowedRoles = null;
        if ($visibility === 'role') {
            $roleIds = $this->input('allowed_roles');
            if (is_array($roleIds)) {
                $allowedRoles = json_encode(array_map('intval', $roleIds));
            }
        }

        $slug = $this->sanitiseSlug($name);

        $folderId = $this->db->insert('folders', [
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'owner_id' => Auth::userId(),
            'subject_id' => $subjectId,
            'visibility' => $visibility,
            'allowed_roles' => $allowedRoles,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'folder', (int) $folderId, "Created folder: {$name}");

        Auth::flash('success', "Folder '{$name}' created.");
        $this->redirect($parentId ? '/drivespace?folder=' . $parentId : '/drivespace');
    }

    /**
     * POST /drivespace/folders/{id}/update â€” Update folder settings.
     */
    public function updateFolder(int $id): void
    {
        $folder = $this->db->fetch('SELECT * FROM folders WHERE id = ?', [$id]);
        if (!$folder) {
            Auth::flash('error', 'Folder not found.');
            $this->redirect('/drivespace');
        }

        if ($folder['owner_id'] !== Auth::userId() && Auth::role() !== 'admin') {
            Auth::flash('error', 'Access denied.');
            $this->redirect('/drivespace?folder=' . $id);
        }

        $name = $this->input('name', $folder['name']);
        $visibility = $this->input('visibility', $folder['visibility']);
        $description = $this->input('description', $folder['description']);
        $subjectId = $this->input('subject_id') !== null ? ((int) $this->input('subject_id') ?: null) : $folder['subject_id'];

        $allowedRoles = $folder['allowed_roles'];
        if ($visibility === 'role') {
            $roleIds = $this->input('allowed_roles');
            if (is_array($roleIds)) {
                $allowedRoles = json_encode(array_map('intval', $roleIds));
            }
        }

        $this->db->update('folders', [
            'name' => $name,
            'slug' => $this->sanitiseSlug($name),
            'description' => $description,
            'subject_id' => $subjectId,
            'visibility' => $visibility,
            'allowed_roles' => $allowedRoles,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Auth::flash('success', 'Folder updated.');
        $this->redirect('/drivespace?folder=' . $id);
    }

    /**
     * POST /drivespace/folders/{id}/delete â€” Delete a folder (moves contents to parent).
     */
    public function deleteFolder(int $id): void
    {
        $folder = $this->db->fetch('SELECT * FROM folders WHERE id = ?', [$id]);
        if (!$folder) {
            Auth::flash('error', 'Folder not found.');
            $this->redirect('/drivespace');
        }

        if ($folder['owner_id'] !== Auth::userId() && Auth::role() !== 'admin') {
            Auth::flash('error', 'Access denied.');
            $this->redirect('/drivespace');
        }

        // Move files to parent folder (or root)
        $this->db->update('files', [
            'folder_id' => $folder['parent_id'],
        ], 'folder_id = ?', [$id]);

        // Move subfolders to parent
        $this->db->update('folders', [
            'parent_id' => $folder['parent_id'],
        ], 'parent_id = ?', [$id]);

        $this->db->delete('folders', 'id = ?', [$id]);

        $this->logActivity('delete', 'folder', $id, "Deleted folder: {$folder['name']}");

        Auth::flash('success', "Folder '{$folder['name']}' deleted. Contents moved to parent.");
        $this->redirect($folder['parent_id'] ? '/drivespace?folder=' . $folder['parent_id'] : '/drivespace');
    }

    // â”€â”€ Access Control Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Check if the current user can access a folder.
     */
    private function canAccessFolder(array $folder): bool
    {
        // Admin can access everything
        if (Auth::role() === 'admin') {
            return true;
        }

        // Owner always has access
        if ($folder['owner_id'] === Auth::userId()) {
            return true;
        }

        return match ($folder['visibility']) {
            'public' => true,
            'members' => Auth::check(),
            'role' => $this->userHasRoleAccess($folder),
            'private' => $this->userHasShareAccess('folder', (int) $folder['id']),
            default => false,
        };
    }

    private function canWriteFolder(?array $folder): bool
    {
        if (!$folder) {
            return true; // Root level â€” logged-in users can add
        }

        if (Auth::role() === 'admin' || $folder['owner_id'] === Auth::userId()) {
            return true;
        }

        // Check share permissions
        $share = $this->db->fetch(
            "SELECT permission FROM file_shares
             WHERE resource_type = 'folder' AND resource_id = ?
             AND ((target_type = 'user' AND target_id = ?) OR (target_type = 'role' AND target_id = ?))
             ORDER BY FIELD(permission, 'manage', 'edit', 'view') LIMIT 1",
            [$folder['id'], Auth::userId(), Auth::roleId()]
        );

        return $share && in_array($share['permission'], ['edit', 'manage'], true);
    }

    private function canAccessFile(array $file, ?array $folder): bool
    {
        if (Auth::role() === 'admin') {
            return true;
        }
        if ($file['owner_id'] === Auth::userId()) {
            return true;
        }

        // Check folder-level access
        if ($folder && $this->canAccessFolder($folder)) {
            return true;
        }

        // Check file-level shares
        return $this->userHasShareAccess('file', (int) $file['id']);
    }

    private function canEditFile(array $file, ?array $folder): bool
    {
        if (Auth::role() === 'admin') {
            return true;
        }
        if ($file['owner_id'] === Auth::userId()) {
            return true;
        }

        // Check share permissions
        $share = $this->db->fetch(
            "SELECT permission FROM file_shares
             WHERE resource_type = 'file' AND resource_id = ?
             AND ((target_type = 'user' AND target_id = ?) OR (target_type = 'role' AND target_id = ?))
             ORDER BY FIELD(permission, 'manage', 'edit', 'view') LIMIT 1",
            [$file['id'], Auth::userId(), Auth::roleId()]
        );

        if ($share && in_array($share['permission'], ['edit', 'manage'], true)) {
            return true;
        }

        // Check folder write access
        return $folder && $this->canWriteFolder($folder);
    }

    private function userHasRoleAccess(array $folder): bool
    {
        if (!$folder['allowed_roles']) {
            return false;
        }
        $roleIds = json_decode($folder['allowed_roles'], true);
        return is_array($roleIds) && in_array(Auth::roleId(), $roleIds, false);
    }

    private function userHasShareAccess(string $type, int $resourceId): bool
    {
        $share = $this->db->fetch(
            "SELECT id FROM file_shares
             WHERE resource_type = ? AND resource_id = ?
             AND ((target_type = 'user' AND target_id = ?) OR (target_type = 'role' AND target_id = ?))",
            [$type, $resourceId, Auth::userId(), Auth::roleId()]
        );
        return (bool) $share;
    }

    // â”€â”€ Folder Tree Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Build a hierarchical folder tree for the sidebar.
     */
    private function buildFolderTree(?int $parentId = null): array
    {
        $folders = $this->db->fetchAll(
            'SELECT f.*, u.display_name as owner_name,
                    (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count
             FROM folders f
             LEFT JOIN users u ON f.owner_id = u.id
             WHERE f.parent_id ' . ($parentId ? '= ?' : 'IS NULL') . '
             ORDER BY f.sort_order, f.name',
            $parentId ? [$parentId] : []
        );

        $tree = [];
        foreach ($folders as $folder) {
            if ($this->canAccessFolder($folder)) {
                $folder['children'] = $this->buildFolderTree((int) $folder['id']);
                $tree[] = $folder;
            }
        }
        return $tree;
    }

    /**
     * Get accessible folders at a given level (or all, flattened).
     */
    private function getAccessibleFolders(?int $parentId, bool $flatten = false): array
    {
        if ($flatten) {
            return $this->flattenFolderTree($this->buildFolderTree(), 0);
        }

        $folders = $this->db->fetchAll(
            'SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count
             FROM folders f
             WHERE f.parent_id ' . ($parentId ? '= ?' : 'IS NULL') . '
             ORDER BY f.sort_order, f.name',
            $parentId ? [$parentId] : []
        );

        return array_filter($folders, fn($f) => $this->canAccessFolder($f));
    }

    /**
     * Flatten a folder tree with indentation depth.
     */
    private function flattenFolderTree(array $tree, int $depth): array
    {
        $flat = [];
        foreach ($tree as $folder) {
            $folder['depth'] = $depth;
            $children = $folder['children'] ?? [];
            unset($folder['children']);
            $flat[] = $folder;
            if ($children) {
                $flat = array_merge($flat, $this->flattenFolderTree($children, $depth + 1));
            }
        }
        return $flat;
    }

    /**
     * Build breadcrumb trail from root to current folder.
     */
    private function buildBreadcrumb(?int $folderId): array
    {
        $crumbs = [['name' => 'Files', 'url' => '/drivespace', 'id' => null]];

        $current = $folderId;
        $trail = [];
        while ($current) {
            $folder = $this->db->fetch('SELECT id, name, parent_id FROM folders WHERE id = ?', [$current]);
            if (!$folder) {
                break;
            }
            array_unshift($trail, [
                'name' => $folder['name'],
                'url' => '/drivespace?folder=' . $folder['id'],
                'id' => (int) $folder['id'],
            ]);
            $current = $folder['parent_id'] ? (int) $folder['parent_id'] : null;
        }

        return array_merge($crumbs, $trail);
    }

    // â”€â”€ File Upload Helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Handle the physical file upload.
     */
    private function handleFileUpload(array $file): array
    {
        $config = App::config('uploads');
        $maxSize = $config['max_size'] ?? 10 * 1024 * 1024;

        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File exceeds maximum size of ' . DocumentService::formatSize($maxSize) . '.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = DocumentService::allAllowedExtensions();
        if (!in_array($ext, $allowed, true)) {
            return ['success' => false, 'error' => 'File type .' . $ext . ' is not allowed.'];
        }

        // Verify the real MIME type against the declared extension.
        // This catches file-extension spoofing (e.g. malicious.php renamed to doc.pdf).
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);
        $allowedMimes = DocumentService::allowedMimeTypes();
        if (!in_array($realMime, $allowedMimes, true)) {
            return ['success' => false, 'error' => 'File content type (' . $realMime . ') is not permitted.'];
        }
        // Also confirm the detected MIME is consistent with the claimed extension.
        $expectedMimes = DocumentService::mimesForExtension($ext);
        if (!empty($expectedMimes) && !in_array($realMime, $expectedMimes, true)) {
            return ['success' => false, 'error' => 'File content does not match its extension (.'. $ext . ').'];
        }

        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $subdir = 'documents/' . date('Y/m');
        $uploadDir = dirname(__DIR__) . '/public/uploads/' . $subdir;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        return [
            'success'   => true,
            'path'      => '/uploads/' . $subdir . '/' . $filename,
            'ext'       => $ext,
            'real_mime' => $realMime,
        ];
    }
}
