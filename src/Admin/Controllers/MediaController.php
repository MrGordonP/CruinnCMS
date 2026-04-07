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

        // Organise into year/month subdirectories
        $subdir   = date('Y/m');
        $uploadDir = dirname(__DIR__, 3) . '/public/storage/' . $typeDir . '/' . $subdir;
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

        $url = '/storage/' . $typeDir . '/' . $subdir . '/' . $filename;
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
     * Scans the uploads directory and returns file metadata.
     */
    public function listMedia(): void
    {
        // Scan both storage/media (new) and uploads/ (legacy) for backward compat
        $publicRoot = dirname(__DIR__, 3) . '/public';
        $scanDirs = [
            $publicRoot . '/storage/media',
            $publicRoot . '/uploads',  // legacy — keep until all instances migrated
        ];
        $search = $this->query('q', '');
        $files = [];
        $config = App::config('uploads');
        $imageExts = $config['image_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];

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
                $name = $file->getFilename();

                // Filter by search term
                if ($search !== '' && stripos($name, $search) === false) continue;

                $files[] = [
                    'url'      => $relativePath,
                    'name'     => $name,
                    'size'     => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        // Sort newest first
        usort($files, function ($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        $this->json(['files' => $files]);
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
