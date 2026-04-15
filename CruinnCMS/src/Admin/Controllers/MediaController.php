<?php
/**
 * CruinnCMS — Media Controller
 *
 * Handles file uploads and media listing for the admin panel.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\App;

class MediaController extends \Cruinn\Controllers\BaseController
{
    /**
     * Resolve the storage slug for the current context.
     * Platform editor → '__platform__' or the instance slug from session.
     * Instance admin  → basename of the active instance directory.
     */
    private function storageSlug(): string
    {
        $editorInstance = $_SESSION['_platform_editor_instance'] ?? null;
        if ($editorInstance !== null && $editorInstance !== '') {
            return basename($editorInstance);
        }
        $dir = App::instanceDir();
        return $dir ? basename($dir) : '__default__';
    }

    /**
     * POST /admin/upload — Handle file upload.
     * Returns JSON with the uploaded file URL.
     */
    public function uploadFile(): void
    {
        if (empty($_FILES['file'])) {
            $this->json(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];
        $config = App::config('uploads');

        // Validate size
        if ($file['size'] > $config['max_size']) {
            $maxMB = $config['max_size'] / (1024 * 1024);
            $this->json(['error' => "File too large. Maximum size: {$maxMB}MB"], 400);
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $config['allowed'])) {
            $this->json(['error' => 'File type not allowed: ' . $ext], 400);
        }

        // Generate safe filename: YYYYMMDD-HHMMSS-random.ext
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        // Route to the right sub-folder based on file type
        $isImage = in_array($ext, $config['image_types']);
        $isDoc   = in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip']);
        $typeDir = $isImage ? 'media' : ($isDoc ? 'documents' : 'media');

        // Instance-scoped storage path
        $slug      = $this->storageSlug();
        $subdir    = date('Y/m');
        $uploadDir = CRUINN_PUBLIC . '/storage/' . $slug . '/' . $typeDir . '/' . $subdir;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->json(['error' => 'Failed to save file'], 500);
        }

        // Resize images if they're too large
        if ($isImage) {
            $this->resizeImage($destination, 1920); // Max 1920px wide
        }

        $url = '/storage/' . $slug . '/' . $typeDir . '/' . $subdir . '/' . $filename;
        $this->logActivity('upload', 'file', null, htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'));

        $this->json([
            'success'  => true,
            'url'      => $url,
            'filename' => $filename,
            'original' => $file['name'],
        ]);
    }

    /**
     * GET /admin/media — List uploaded media files as JSON.
     * Scans the instance-scoped uploads directory and returns file metadata.
     *
     * With no params:    lists subfolders + images in the media root.
     * ?folder=/storage/… lists subfolders + images in that folder (non-recursive).
     * ?q=term            recursive search across all folders (existing behaviour).
     */
    public function listMedia(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $publicRoot = CRUINN_PUBLIC;
        $slug       = $this->storageSlug();
        $mediaRoot  = $publicRoot . '/storage/' . $slug . '/media';
        $config     = App::config('uploads');
        $imageExts  = $config['image_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $search     = $this->query('q', '');
        $folderParam = trim($this->query('folder', ''));

        // ── Search mode: recursive, flat results ─────────────────────────────
        if ($search !== '') {
            $scanDirs = [
                $mediaRoot,
                $publicRoot . '/uploads',
            ];
            $files = [];
            $seen  = [];
            foreach ($scanDirs as $scanDir) {
                if (!is_dir($scanDir)) continue;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($scanDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if (!$file->isFile()) continue;
                    $ext = strtolower($file->getExtension());
                    if (!in_array($ext, $imageExts)) continue;
                    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($publicRoot)));
                    if (isset($seen[$relativePath])) continue;
                    $seen[$relativePath] = true;
                    $name = $file->getFilename();
                    if (stripos($name, $search) === false) continue;
                    $files[] = ['url' => $relativePath, 'name' => $name, 'size' => $file->getSize(), 'modified' => $file->getMTime()];
                }
            }
            usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
            $this->json(['files' => $files, 'folders' => [], 'current' => '', 'parent' => null]);
            return;
        }

        // ── Folder browse mode: non-recursive ────────────────────────────────
        // Resolve the directory to scan.
        if ($folderParam !== '') {
            // Prevent path traversal: must start with /storage/{slug}/media
            $allowedPrefix = '/storage/' . $slug . '/media';
            if (!str_starts_with($folderParam, $allowedPrefix)) {
                $this->json(['error' => 'Invalid folder'], 400);
                return;
            }
            $absDir = realpath($publicRoot . $folderParam);
            $absRoot = realpath($mediaRoot);
            if ($absDir === false || $absRoot === false || !str_starts_with($absDir, $absRoot)) {
                $this->json(['error' => 'Invalid folder'], 400);
                return;
            }
            $currentAbs = $absDir;
            $currentRel = str_replace('\\', '/', substr($currentAbs, strlen($publicRoot)));
            // Parent: one level up, but not above media root
            $parentAbs = dirname($currentAbs);
            $parent = ($parentAbs !== $absRoot && str_starts_with($parentAbs, $absRoot))
                ? str_replace('\\', '/', substr($parentAbs, strlen($publicRoot)))
                : $allowedPrefix; // at a year folder — parent is the root
            if ($currentAbs === $absRoot) {
                $parent = null; // already at root
            }
        } else {
            $currentAbs = realpath($mediaRoot);
            $currentRel = str_replace('\\', '/', substr($currentAbs, strlen($publicRoot)));
            $parent = null;
        }

        if (!$currentAbs || !is_dir($currentAbs)) {
            $this->json(['files' => [], 'folders' => [], 'current' => '', 'parent' => null]);
            return;
        }

        $folders = [];
        $files   = [];

        foreach (new \DirectoryIterator($currentAbs) as $entry) {
            if ($entry->isDot()) continue;
            $entryRel = $currentRel . '/' . $entry->getFilename();
            if ($entry->isDir()) {
                $folders[] = ['name' => $entry->getFilename(), 'path' => $entryRel];
            } elseif ($entry->isFile()) {
                $ext = strtolower($entry->getExtension());
                if (!in_array($ext, $imageExts)) continue;
                $files[] = ['url' => $entryRel, 'name' => $entry->getFilename(), 'size' => $entry->getSize(), 'modified' => $entry->getMTime()];
            }
        }

        sort($folders);
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);

        $this->json(['files' => $files, 'folders' => $folders, 'current' => $currentRel, 'parent' => $parent]);
    }

    /**
     * POST /admin/media/folder — Create a new subfolder.
     * Body: folder (current folder path), name (new folder name)
     */
    public function createFolder(): void
    {
        \Cruinn\CSRF::validate();
        $publicRoot  = CRUINN_PUBLIC;
        $slug        = $this->storageSlug();
        $mediaRoot   = realpath($publicRoot . '/storage/' . $slug . '/media');
        $folderParam = trim($this->input('folder', ''));
        $name        = trim($this->input('name', ''));

        if ($name === '' || preg_match('/[^a-zA-Z0-9_\-]/', $name)) {
            $this->json(['error' => 'Invalid folder name (letters, numbers, - _ only)'], 400);
            return;
        }

        $parentAbs = $folderParam !== ''
            ? realpath($publicRoot . $folderParam)
            : $mediaRoot;

        if (!$parentAbs || !str_starts_with($parentAbs, $mediaRoot)) {
            $this->json(['error' => 'Invalid parent folder'], 400);
            return;
        }

        $newDir = $parentAbs . DIRECTORY_SEPARATOR . $name;
        if (is_dir($newDir)) {
            $this->json(['error' => 'Folder already exists'], 409);
            return;
        }
        if (!mkdir($newDir, 0755)) {
            $this->json(['error' => 'Failed to create folder'], 500);
            return;
        }
        $this->json(['success' => true]);
    }

    /**
     * POST /admin/media/delete — Delete an empty folder.
     * Body: folder (path to delete)
     */
    public function deleteFolder(): void
    {
        \Cruinn\CSRF::validate();
        $publicRoot = CRUINN_PUBLIC;
        $slug       = $this->storageSlug();
        $mediaRoot  = realpath($publicRoot . '/storage/' . $slug . '/media');
        $folderParam = trim($this->input('folder', ''));

        if ($folderParam === '') {
            $this->json(['error' => 'No folder specified'], 400);
            return;
        }

        $absDir = realpath($publicRoot . $folderParam);
        if (!$absDir || !str_starts_with($absDir, $mediaRoot) || $absDir === $mediaRoot) {
            $this->json(['error' => 'Invalid folder'], 400);
            return;
        }

        // Only delete if empty
        $contents = array_diff(scandir($absDir), ['.', '..']);
        if (!empty($contents)) {
            $this->json(['error' => 'Folder is not empty'], 409);
            return;
        }

        if (!rmdir($absDir)) {
            $this->json(['error' => 'Failed to delete folder'], 500);
            return;
        }
        $this->json(['success' => true]);
    }

    /**
     * POST /admin/media/delete-file — Delete a single media file.
     * Body: file (web path to file, e.g. /storage/iga/media/2026/04/filename.jpg)
     */
    public function deleteFile(): void
    {
        \Cruinn\CSRF::validate();
        $publicRoot = CRUINN_PUBLIC;
        $slug       = $this->storageSlug();
        $mediaRoot  = realpath($publicRoot . '/storage/' . $slug . '/media');
        $fileParam  = trim($this->input('file', ''));

        if ($fileParam === '') {
            $this->json(['error' => 'No file specified'], 400);
            return;
        }

        $absFile = realpath($publicRoot . $fileParam);
        if (!$absFile || !str_starts_with($absFile, $mediaRoot) || !is_file($absFile)) {
            $this->json(['error' => 'Invalid file'], 400);
            return;
        }

        if (!unlink($absFile)) {
            $this->json(['error' => 'Failed to delete file'], 500);
            return;
        }
        $this->json(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function resizeImage(string $path, int $maxWidth): void
    {
        $info = getimagesize($path);
        if ($info === false || $info[0] <= $maxWidth) {
            return; // Not an image or already small enough
        }

        $srcWidth  = $info[0];
        $srcHeight = $info[1];
        $ratio     = $maxWidth / $srcWidth;
        $newWidth  = $maxWidth;
        $newHeight = (int) ($srcHeight * $ratio);

        $src = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => null,
        };

        if ($src === null) {
            return;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/GIF/WebP
        if (in_array($info['mime'], ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($dst, $path, 85),
            'image/png'  => imagepng($dst, $path, 6),
            'image/gif'  => imagegif($dst, $path),
            'image/webp' => imagewebp($dst, $path, 85),
        };

        imagedestroy($src);
        imagedestroy($dst);
    }
}
