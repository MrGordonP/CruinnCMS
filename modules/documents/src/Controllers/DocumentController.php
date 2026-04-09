<?php
/**
 * CruinnCMS — Document Controller
 *
 * Organisation document library: upload, versioning, approval workflow, download.
 */

namespace Cruinn\Module\Documents\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class DocumentController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════
    //  LIST
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /documents — List all documents with optional filters.
     */
    public function index(): void
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

        $this->renderAdmin('admin/documents/index', [
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

    // ══════════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /documents/{id} — Show a single document with version history.
     */
    public function show(int $id): void
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

        $this->renderAdmin('admin/documents/show', [
            'title'    => $document['title'],
            'document' => $document,
            'versions' => $versions,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  UPLOAD
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /documents/new — Show the upload form.
     */
    public function uploadForm(): void
    {
        $this->renderAdmin('admin/documents/upload', [
            'title'    => 'Upload Document',
            'document' => null,
            'errors'   => [],
        ]);
    }

    /**
     * POST /documents — Handle new document upload.
     */
    public function upload(): void
    {
        $data = [
            'title'       => $this->input('title'),
            'description' => $this->input('description', ''),
            'category'    => $this->input('category', 'other'),
            'status'      => $this->input('status', 'draft'),
        ];

        $errors = $this->validateRequired(['title' => 'Title']);

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors['file'] = 'A file is required.';
        }

        if ($errors) {
            $this->renderAdmin('admin/documents/upload', [
                'title'    => 'Upload Document',
                'document' => $data,
                'errors'   => $errors,
            ]);
            return;
        }

        $file         = $_FILES['file'];
        $uploadResult = $this->handleDocumentUpload($file);

        if (!$uploadResult['success']) {
            $errors['file'] = $uploadResult['error'];
            $this->renderAdmin('admin/documents/upload', [
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
        $this->redirect("/documents/{$docId}");
    }

    // ══════════════════════════════════════════════════════════════════
    //  VERSIONING
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /documents/{id}/version — Upload a new version.
     */
    public function newVersion(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'A file is required for the new version.');
            $this->redirect("/documents/{$id}");
            return;
        }

        $file         = $_FILES['file'];
        $uploadResult = $this->handleDocumentUpload($file);

        if (!$uploadResult['success']) {
            Auth::flash('error', $uploadResult['error']);
            $this->redirect("/documents/{$id}");
            return;
        }

        $newVersion = (int) $document['version'] + 1;
        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $this->db->update('documents', [
            'file_path' => $uploadResult['path'],
            'file_size' => $file['size'],
            'file_type' => $ext,
            'version'   => $newVersion,
            'status'    => 'draft',
        ], 'id = ?', [$id]);

        $this->db->insert('document_versions', [
            'document_id' => $id,
            'version_num' => $newVersion,
            'file_path'   => $uploadResult['path'],
            'file_size'   => $file['size'],
            'uploaded_by' => Auth::userId(),
            'notes'       => $this->input('notes', ''),
        ]);

        $this->logActivity('update', 'document', $id, "New version {$newVersion}: {$document['title']}");
        Auth::flash('success', "Version {$newVersion} uploaded successfully.");
        $this->redirect("/documents/{$id}");
    }

    // ══════════════════════════════════════════════════════════════════
    //  STATUS WORKFLOW
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /documents/{id}/submit — Submit for approval.
     */
    public function submit(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if ($document['status'] !== 'draft') {
            Auth::flash('error', 'Only draft documents can be submitted for approval.');
            $this->redirect("/documents/{$id}");
            return;
        }

        $this->db->update('documents', ['status' => 'submitted'], 'id = ?', [$id]);
        $this->logActivity('update', 'document', $id, "Submitted for approval: {$document['title']}");
        Auth::flash('success', 'Document submitted for approval.');
        $this->redirect("/documents/{$id}");
    }

    /**
     * POST /documents/{id}/approve — Approve a submitted document.
     */
    public function approve(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        if ($document['status'] !== 'submitted') {
            Auth::flash('error', 'Only submitted documents can be approved.');
            $this->redirect("/documents/{$id}");
            return;
        }

        $this->db->update('documents', [
            'status'      => 'approved',
            'approved_by' => Auth::userId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('approve', 'document', $id, "Approved: {$document['title']}");
        Auth::flash('success', 'Document approved.');
        $this->redirect("/documents/{$id}");
    }

    /**
     * POST /documents/{id}/archive — Archive a document.
     */
    public function archive(int $id): void
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
        $this->redirect("/documents/{$id}");
    }

    // ══════════════════════════════════════════════════════════════════
    //  DOWNLOAD
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /documents/{id}/download — Download the current version.
     */
    public function download(int $id): void
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
            $this->redirect("/documents/{$id}");
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
     * GET /documents/{id}/versions/{versionId}/download — Download a specific version.
     */
    public function downloadVersion(int $id, int $versionId): void
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
            $this->redirect("/documents/{$id}");
            return;
        }

        $filename = pathinfo($version['file_path'], PATHINFO_BASENAME);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // ══════════════════════════════════════════════════════════════════
    //  DELETE
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /documents/{id}/delete — Delete a document and all versions.
     */
    public function delete(int $id): void
    {
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$document) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $versions = $this->db->fetchAll(
            'SELECT file_path FROM document_versions WHERE document_id = ?',
            [$id]
        );

        $this->db->delete('documents', 'id = ?', [$id]);

        $allPaths   = array_column($versions, 'file_path');
        $allPaths[] = $document['file_path'];
        foreach (array_unique($allPaths) as $path) {
            $fullPath = $this->publicPath($path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->logActivity('delete', 'document', $id, "Deleted: {$document['title']}");
        Auth::flash('success', 'Document deleted.');
        $this->redirect('/documents');
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Handle a document file upload.
     * Returns ['success' => bool, 'path' => string, 'error' => string].
     */
    private function handleDocumentUpload(array $file): array
    {
        $config = App::config('uploads');

        if ($file['size'] > $config['max_size']) {
            $maxMB = $config['max_size'] / (1024 * 1024);
            return ['success' => false, 'error' => "File too large. Maximum size: {$maxMB}MB"];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $config['allowed'])) {
            return ['success' => false, 'error' => "File type not allowed: {$ext}"];
        }

        $filename  = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $subdir    = 'documents/' . date('Y/m');
        $uploadDir = dirname(__DIR__, 5) . '/public/uploads/' . $subdir;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        return [
            'success' => true,
            'path'    => '/uploads/' . $subdir . '/' . $filename,
        ];
    }

    /**
     * Resolve an uploads-relative path to CRUINN_ROOT/public.
     */
    private function publicPath(string $relativePath): string
    {
        return dirname(__DIR__, 5) . '/public' . $relativePath;
    }

    /**
     * Format a file size for display.
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
}
