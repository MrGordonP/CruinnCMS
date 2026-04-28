<?php
/**
 * CruinnCMS — Drivespace Google Drive Controller
 *
 * Browse, download proxy, upload, and import for the instance Google Drive.
 * Authenticates via the service account — no per-user OAuth needed.
 * Write operations restricted to the role configured in gdrive.write_role.
 */

namespace Cruinn\Module\Drivespace\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Drivespace\Services\DocumentService;
use Cruinn\Module\Drivespace\Services\GoogleDriveService;

class GoogleDriveController extends BaseController
{
    private GoogleDriveService $gdrive;

    public function __construct()
    {
        parent::__construct();
        $this->gdrive = new GoogleDriveService();
    }

    // ── Browse ──────────────────────────────────────────────────

    /**
     * GET /drivespace/gdrive[?folder={id}]
     */
    public function index(): void
    {
        Auth::requireRole('member');

        if (!$this->gdrive->isConfigured()) {
            Auth::flash('error', 'Google Drive integration is not configured.');
            $this->redirect('/drivespace');
        }

        $folderId = $this->query('folder') ?: null;

        try {
            $listing = $this->gdrive->listFolder($folderId);
        } catch (\Throwable $e) {
            Auth::flash('error', 'Google Drive error: ' . $e->getMessage());
            $this->redirect('/drivespace');
        }

        $canWrite = Auth::hasRole($this->gdrive->getWriteRole());

        $this->renderAdmin('admin/files/gdrive', [
            'title'        => 'Drivespace — Google Drive',
            'listing'      => $listing,
            'folderId'     => $listing['folderId'],
            'rootFolderId' => $this->gdrive->getRootFolderId(),
            'canWrite'     => $canWrite,
        ]);
    }

    // ── Download proxy ──────────────────────────────────────────

    /**
     * GET /drivespace/gdrive/{id}/download
     */
    public function download(string $id): void
    {
        Auth::requireRole('member');

        if (!$this->gdrive->isConfigured()) {
            http_response_code(503);
            exit('Google Drive not configured.');
        }

        try {
            $file     = $this->gdrive->getFile($id);
            $mimeType = $file['mimeType'] ?? '';
            $name     = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $file['name'] ?? $id);

            if (str_contains($mimeType, 'document') || str_contains($mimeType, 'presentation')) {
                $name .= '.pdf';
                header('Content-Type: application/pdf');
            } elseif (str_contains($mimeType, 'spreadsheet')) {
                $name .= '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            } else {
                header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
            }

            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: private, no-store');

            $this->gdrive->streamFile($id, $mimeType);
        } catch (\Throwable $e) {
            http_response_code(502);
            exit('Download failed: ' . htmlspecialchars($e->getMessage()));
        }
    }

    // ── Fragment (AJAX pane) ────────────────────────────────────

    /**
     * GET /drivespace/gdrive/fragment?folder={id}
     * Returns JSON {html, folderId, rootFolderId} for embedding in the split-pane center panel.
     */
    public function fragment(): void
    {
        Auth::requireRole('member');

        if (!$this->gdrive->isConfigured()) {
            $this->json(['error' => 'not_configured']);
            return;
        }

        $folderId = $this->query('folder') ?: null;

        try {
            $listing      = $this->gdrive->listFolder($folderId);
            $rootFolderId = $this->gdrive->getRootFolderId();
            $canWrite     = Auth::hasRole($this->gdrive->getWriteRole());
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()]);
            return;
        }

        // Render table HTML inline — no template engine needed for a fragment
        ob_start();
        $folders = $listing['folders'] ?? [];
        $files   = $listing['files']   ?? [];
        ?>
        <?php if (empty($folders) && empty($files)): ?>
            <div class="fm-empty"><p>This folder is empty or not accessible.</p></div>
        <?php else: ?>
            <?php if (!empty($folders)): ?>
            <p class="fm-section-title">Folders</p>
            <table class="fm-items-table">
                <thead><tr>
                    <th class="fm-col-icon"></th>
                    <th>Name</th>
                    <th>Modified</th>
                </tr></thead>
                <tbody>
                <?php foreach ($folders as $f): ?>
                <tr class="gdrive-folder-row" data-folder-id="<?= htmlspecialchars($f['id'], ENT_QUOTES) ?>">
                    <td class="fm-col-icon">📁</td>
                    <td class="fm-col-name"><?= htmlspecialchars($f['name'], ENT_QUOTES) ?></td>
                    <td class="fm-col-meta"><?= htmlspecialchars(substr($f['modifiedTime'] ?? '', 0, 10), ENT_QUOTES) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php if (!empty($files)): ?>
            <p class="fm-section-title" <?= !empty($folders) ? 'style="margin-top:1rem"' : '' ?>>
                Files <span style="font-weight:400;color:#bbb">(<?= count($files) ?>)</span>
            </p>
            <table class="fm-items-table">
                <thead><tr>
                    <th class="fm-col-icon"></th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($files as $f):
                    $size = isset($f['size']) ? \Cruinn\Module\Drivespace\Services\GoogleDriveService::formatSize((int)$f['size']) : '—';
                    $icon = \Cruinn\Module\Drivespace\Services\GoogleDriveService::fileIcon($f['mimeType'] ?? '');
                    $fid  = htmlspecialchars($f['id'], ENT_QUOTES);
                    $fname = htmlspecialchars($f['name'], ENT_QUOTES);
                ?>
                <tr>
                    <td class="fm-col-icon"><?= $icon ?></td>
                    <td class="fm-col-name">
                        <?= $fname ?>
                        <?php if (!empty($f['webViewLink'])): ?>
                            <a href="<?= htmlspecialchars($f['webViewLink'], ENT_QUOTES) ?>" target="_blank" rel="noopener"
                               style="margin-left:0.4rem;font-size:0.75rem;color:#888" title="Open in Drive">↗</a>
                        <?php endif; ?>
                    </td>
                    <td class="fm-col-meta"><?= htmlspecialchars($size, ENT_QUOTES) ?></td>
                    <td class="fm-col-meta"><?= htmlspecialchars(substr($f['modifiedTime'] ?? '', 0, 10), ENT_QUOTES) ?></td>
                    <td class="fm-col-actions" style="white-space:nowrap">
                        <a href="/drivespace/gdrive/<?= $fid ?>/download" class="btn btn-sm btn-outline">⬇</a>
                        <?php if ($canWrite): ?>
                        <button type="button" class="btn btn-sm btn-outline gdrive-import-btn"
                                data-file-id="<?= $fid ?>" data-file-name="<?= $fname ?>"
                                title="Import to local Drivespace">⬇ Import</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();

        $this->json([
            'html'         => $html,
            'folderId'     => $listing['folderId'],
            'rootFolderId' => $rootFolderId,
            'canWrite'     => $canWrite,
        ]);
    }

    // ── Upload to Drive ─────────────────────────────────────────

    /**
     * POST /drivespace/gdrive/upload
     * Upload a file from the user's browser directly to Google Drive.
     */
    public function upload(): void
    {
        Auth::requireRole($this->gdrive->getWriteRole());

        if (!$this->gdrive->isConfigured()) {
            $this->json(['success' => false, 'error' => 'Google Drive not configured.']);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'No file received.']);
            return;
        }

        $file     = $_FILES['file'];
        $folderId = $this->input('folder_id') ?: null;

        // MIME validation — reuse DocumentService allowed types
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);
        $allowed  = DocumentService::allowedMimeTypes();
        if (!in_array($realMime, $allowed, true)) {
            $this->json(['success' => false, 'error' => 'File type not permitted: ' . $realMime]);
            return;
        }

        try {
            $result = $this->gdrive->uploadFile(
                $file['tmp_name'],
                $file['name'],
                $realMime,
                $folderId
            );
            $this->json(['success' => true, 'file' => $result]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Import Drive → Local ────────────────────────────────────

    /**
     * POST /drivespace/gdrive/{id}/import
     * Download a file from Drive and save it into local Drivespace.
     */
    public function import(string $id): void
    {
        Auth::requireRole($this->gdrive->getWriteRole());

        if (!$this->gdrive->isConfigured()) {
            $this->json(['success' => false, 'error' => 'Google Drive not configured.']);
            return;
        }

        $localFolderId = $this->input('folder_id') ? (int) $this->input('folder_id') : null;
        $userId        = Auth::userId();

        try {
            $imported = $this->gdrive->importFile($id);

            $tmpPath  = $imported['tmpPath'];
            $name     = $imported['name'];
            $mimeType = $imported['mimeType'];
            $size     = $imported['size'];
            $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Save to local uploads directory
            $subdir    = 'documents/' . date('Y/m');
            $uploadDir = dirname(__DIR__) . '/public/uploads/' . $subdir;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = date('Ymd-His') . '-gdrive-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = $uploadDir . '/' . $filename;

            if (!rename($tmpPath, $dest)) {
                @unlink($tmpPath);
                throw new \RuntimeException('Failed to save imported file to local storage.');
            }

            $filePath = '/uploads/' . $subdir . '/' . $filename;

            // Parse content if supported
            $parsedContent = null;
            $metadata      = null;
            $docService    = new DocumentService();
            if (DocumentService::isParseable($ext)) {
                $parsed        = $docService->parseFile($dest, $ext);
                $parsedContent = $parsed['html'];
                $metadata      = $parsed['metadata'];
            }

            // Insert into local files table
            $fileId = $this->db->insert('files', [
                'folder_id'      => $localFolderId,
                'title'          => pathinfo($name, PATHINFO_FILENAME),
                'description'    => 'Imported from Google Drive',
                'file_path'      => $filePath,
                'original_name'  => $name,
                'file_size'      => $size,
                'mime_type'      => $mimeType,
                'file_ext'       => $ext,
                'owner_id'       => $userId,
                'status'         => 'draft',
                'version'        => 1,
                'content_type'   => 'upload',
                'parsed_content' => $parsedContent,
                'metadata'       => $metadata ? json_encode($metadata) : null,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            // Update quota
            $this->db->execute(
                'UPDATE users SET drivespace_used_bytes = drivespace_used_bytes + ? WHERE id = ?',
                [$size, $userId]
            );

            $this->db->insert('file_versions', [
                'file_id'        => $fileId,
                'version_num'    => 1,
                'file_path'      => $filePath,
                'file_size'      => $size,
                'parsed_content' => $parsedContent,
                'notes'          => 'Imported from Google Drive',
                'created_by'     => $userId,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            $this->json(['success' => true, 'file_id' => $fileId, 'title' => pathinfo($name, PATHINFO_FILENAME)]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
