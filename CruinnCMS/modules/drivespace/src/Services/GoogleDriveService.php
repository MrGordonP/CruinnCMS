<?php
/**
 * CruinnCMS — Google Drive Service
 *
 * Authenticates via a service account JSON key (RS256 JWT → OAuth2 access token).
 * No Composer dependencies — uses openssl_sign() and file_get_contents() only.
 *
 * Usage:
 *   $gdrive = new GoogleDriveService();
 *   if ($gdrive->isConfigured()) {
 *       $files = $gdrive->listFolder();
 *   }
 */

namespace Cruinn\Module\Drivespace\Services;

use Cruinn\Database;

class GoogleDriveService
{
    private const API_BASE   = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD_BASE = 'https://www.googleapis.com/upload/drive/v3';
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    private const SCOPE      = 'https://www.googleapis.com/auth/drive';
    private const TOKEN_TTL  = 3600; // seconds Google access tokens are valid

    private Database $db;
    private ?array   $serviceAccount = null;
    private ?string  $rootFolderId = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Public API ──────────────────────────────────────────────

    /**
     * Returns true when a service account JSON has been configured.
     */
    public function isConfigured(): bool
    {
        return $this->loadServiceAccount() !== null;
    }

    /**
     * Static convenience — check without instantiation (for use in templates).
     */
    public static function isConfiguredStatic(): bool
    {
        return (new self())->isConfigured();
    }

    /**
     * List contents of a Google Drive folder.
     * Pass null to use the configured root folder.
     * Returns ['folders' => [...], 'files' => [...]] or throws on error.
     */
    public function listFolder(?string $folderId = null): array
    {
        $configuredRoot = $this->db->fetchColumn(
            "SELECT `value` FROM settings WHERE `key` = 'gdrive.root_folder_id'"
        ) ?: null;
        $sharedDriveId  = $this->getSharedDriveId();

        $token  = $this->getAccessToken();
        $fields = urlencode('files(id,name,mimeType,size,modifiedTime,iconLink,webViewLink,parents)');

        // Determine effective folder
        $atVirtualRoot = false;
        if ($folderId === null || $folderId === 'root') {
            if ($configuredRoot) {
                $folderId = $configuredRoot;
            } elseif ($sharedDriveId) {
                $folderId = $sharedDriveId;
            } else {
                // No root configured — show all folders/files shared with this service account
                $atVirtualRoot = true;
            }
        }

        if ($atVirtualRoot) {
            $q   = urlencode("sharedWithMe = true and trashed = false");
            $url = self::API_BASE . "/files?q={$q}&fields={$fields}&orderBy=folder,name&pageSize=200"
                 . "&supportsAllDrives=true&includeItemsFromAllDrives=true";
            $effectiveFolderId = 'root';
        } elseif ($sharedDriveId && ($folderId === $sharedDriveId)) {
            $q   = urlencode("trashed = false");
            $url = self::API_BASE . "/files?corpora=drive&driveId={$sharedDriveId}&q={$q}&fields={$fields}"
                 . "&orderBy=folder,name&pageSize=200&supportsAllDrives=true&includeItemsFromAllDrives=true";
            $effectiveFolderId = $sharedDriveId;
        } else {
            $q   = urlencode("'{$folderId}' in parents and trashed = false");
            $url = self::API_BASE . "/files?q={$q}&fields={$fields}&orderBy=folder,name&pageSize=200"
                 . ($sharedDriveId ? "&supportsAllDrives=true&includeItemsFromAllDrives=true" : '');
            $effectiveFolderId = $folderId;
        }

        $response = $this->apiFetch($url, $token);
        $items    = $response['files'] ?? [];

        $folders = [];
        $files   = [];
        foreach ($items as $item) {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder') {
                $folders[] = $item;
            } else {
                $files[] = $item;
            }
        }

        return ['folders' => $folders, 'files' => $files, 'folderId' => $effectiveFolderId];
    }

    /**
     * Get metadata for a single file.
     */
    public function getFile(string $fileId): array
    {
        $token  = $this->getAccessToken();
        $fields = urlencode('id,name,mimeType,size,modifiedTime,description,webViewLink,parents');
        $extra  = $this->getSharedDriveId() ? '&supportsAllDrives=true' : '';
        return $this->apiFetch(self::API_BASE . "/files/{$fileId}?fields={$fields}{$extra}", $token);
    }

    /**
     * Get a short-lived download URL for a file.
     * For Google Docs/Sheets/Slides, exports as PDF.
     * For binary files, returns the download URL for proxy-stream.
     */
    public function downloadUrl(string $fileId, string $mimeType = ''): string
    {
        // Google Workspace types need export
        $exportMime = $this->exportMimeType($mimeType);
        if ($exportMime) {
            return self::API_BASE . "/files/{$fileId}/export?mimeType=" . urlencode($exportMime);
        }
        return self::API_BASE . "/files/{$fileId}?alt=media";
    }

    /**
     * Upload a local file to Google Drive.
     * Returns the created Drive file metadata array.
     */
    public function uploadFile(string $localPath, string $name, string $mimeType, ?string $parentFolderId = null): array
    {
        $token = $this->getAccessToken();

        $parentId = $parentFolderId ?? $this->getRootFolderId();

        // Build multipart body
        $boundary = '---CruinnBoundary' . bin2hex(random_bytes(8));
        $metadata = json_encode([
            'name'    => $name,
            'parents' => [$parentId],
        ]);

        $fileContent = file_get_contents($localPath);
        if ($fileContent === false) {
            throw new \RuntimeException('Could not read local file for upload: ' . $localPath);
        }

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . $fileContent . "\r\n"
            . "--{$boundary}--";

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  =>
                    "Authorization: Bearer {$token}\r\n"
                    . "Content-Type: multipart/related; boundary={$boundary}\r\n"
                    . "Content-Length: " . strlen($body) . "\r\n",
                'content' => $body,
            ],
        ]);

        $uploadExtra = $this->getSharedDriveId() ? '&supportsAllDrives=true' : '';
        $raw = file_get_contents(self::UPLOAD_BASE . '/files?uploadType=multipart' . $uploadExtra, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Google Drive upload failed.');
        }

        $data = json_decode($raw, true);
        if (isset($data['error'])) {
            throw new \RuntimeException('Drive upload error: ' . ($data['error']['message'] ?? $raw));
        }

        return $data;
    }

    /**
     * Import a Drive file to a local temp path.
     * Returns ['tmpPath' => ..., 'name' => ..., 'mimeType' => ..., 'size' => ...].
     */
    public function importFile(string $fileId): array
    {
        $token    = $this->getAccessToken();
        $meta     = $this->getFile($fileId);
        $mimeType = $meta['mimeType'] ?? '';
        $name     = $meta['name'] ?? $fileId;

        $url = $this->downloadUrl($fileId, $mimeType);

        // Resolve export mime for naming
        $exportMime = $this->exportMimeType($mimeType);
        if ($exportMime) {
            $ext  = match($exportMime) {
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                default => 'bin',
            };
            $name = pathinfo($name, PATHINFO_FILENAME) . '.' . $ext;
            $mimeType = $exportMime;
        }

        $tmpPath = sys_get_temp_dir() . '/gdrive_import_' . bin2hex(random_bytes(6));

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\n",
            ],
        ]);

        $bytes = file_put_contents($tmpPath, file_get_contents($url, false, $ctx));
        if ($bytes === false) {
            throw new \RuntimeException('Failed to download Drive file for import.');
        }

        return [
            'tmpPath'  => $tmpPath,
            'name'     => $name,
            'mimeType' => $mimeType,
            'size'     => $bytes,
        ];
    }

    /**
     * Get the minimum role slug required for Drive write operations.
     * Stored in settings as gdrive.write_role, default 'council'.
     */
    public function getWriteRole(): string
    {
        return $this->db->fetchColumn(
            "SELECT `value` FROM settings WHERE `key` = 'gdrive.write_role'"
        ) ?: 'council';
    }

    /**
     * Proxy-stream a file to the output buffer.
     * Call before any output is sent.
     */
    public function streamFile(string $fileId, string $mimeType = ''): void
    {
        $token = $this->getAccessToken();
        $url   = $this->downloadUrl($fileId, $mimeType);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\n",
            ],
        ]);

        $handle = fopen($url, 'r', false, $ctx);
        if (!$handle) {
            throw new \RuntimeException('Failed to open file stream from Google Drive.');
        }
        fpassthru($handle);
        fclose($handle);
    }

    /**
     * Return the configured root folder ID.
     * If a Shared Drive ID is set and no explicit folder is configured, returns the Shared Drive ID.
     * Falls back to 'root' (service account My Drive) otherwise.
     */
    public function getRootFolderId(): string
    {
        if ($this->rootFolderId === null) {
            $configured = $this->db->fetchColumn(
                "SELECT `value` FROM settings WHERE `key` = 'gdrive.root_folder_id'"
            );
            if ($configured) {
                $this->rootFolderId = $configured;
            } elseif ($sharedDriveId = $this->getSharedDriveId()) {
                $this->rootFolderId = $sharedDriveId;
            } else {
                $this->rootFolderId = 'root';
            }
        }
        return $this->rootFolderId;
    }

    /**
     * Return the configured Shared Drive (Team Drive) ID, or null if not set.
     */
    public function getSharedDriveId(): ?string
    {
        $val = $this->db->fetchColumn(
            "SELECT `value` FROM settings WHERE `key` = 'gdrive.shared_drive_id'"
        );
        return $val ?: null;
    }

    // ── Auth ────────────────────────────────────────────────────

    /**
     * Return a valid OAuth2 access token, refreshing from the DB cache or
     * issuing a new one via JWT grant.
     */
    public function getAccessToken(): string
    {
        // Try cached token
        $expires = (int) ($this->db->fetchColumn(
            "SELECT `value` FROM settings WHERE `key` = 'gdrive.token_expires_at'"
        ) ?? 0);

        if ($expires > time() + 60) {
            $token = $this->db->fetchColumn(
                "SELECT `value` FROM settings WHERE `key` = 'gdrive.access_token'"
            );
            if ($token) {
                return $token;
            }
        }

        // Issue a new token via JWT
        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $sa = $this->loadServiceAccount();
        if (!$sa) {
            throw new \RuntimeException('Google Drive service account is not configured.');
        }

        $jwt = $this->buildJwt($sa);

        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
            ],
        ]);

        $raw = file_get_contents(self::TOKEN_URL, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Failed to contact Google token endpoint.');
        }

        $data = json_decode($raw, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Google token response: ' . ($data['error_description'] ?? $raw));
        }

        $token   = $data['access_token'];
        $expires = time() + (int) ($data['expires_in'] ?? self::TOKEN_TTL);

        // Cache in settings
        $this->db->execute(
            "UPDATE settings SET `value` = ? WHERE `key` = 'gdrive.access_token'",
            [$token]
        );
        $this->db->execute(
            "UPDATE settings SET `value` = ? WHERE `key` = 'gdrive.token_expires_at'",
            [$expires]
        );

        return $token;
    }

    private function buildJwt(array $sa): string
    {
        $now = time();

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signing = $header . '.' . $payload;

        $privateKey = openssl_pkey_get_private($sa['private_key']);
        if (!$privateKey) {
            throw new \RuntimeException('Failed to load service account private key.');
        }

        openssl_sign($signing, $signature, $privateKey, 'SHA256');

        return $signing . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function loadServiceAccount(): ?array
    {
        if ($this->serviceAccount === null) {
            $json = $this->db->fetchColumn(
                "SELECT `value` FROM settings WHERE `key` = 'gdrive.service_account_json'"
            );
            if (!$json) {
                return null;
            }
            $decoded = json_decode($json, true);
            if (!isset($decoded['client_email'], $decoded['private_key'])) {
                return null;
            }
            $this->serviceAccount = $decoded;
        }
        return $this->serviceAccount;
    }

    private function apiFetch(string $url, string $token): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
            ],
        ]);

        $raw = file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException("Google Drive API request failed: {$url}");
        }

        $data = json_decode($raw, true);
        if (isset($data['error'])) {
            throw new \RuntimeException('Google Drive API error: ' . ($data['error']['message'] ?? $raw));
        }

        return $data;
    }

    private function exportMimeType(string $mimeType): ?string
    {
        return match($mimeType) {
            'application/vnd.google-apps.document'     => 'application/pdf',
            'application/vnd.google-apps.spreadsheet'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.google-apps.presentation' => 'application/pdf',
            default                                    => null,
        };
    }

    /**
     * Format bytes to human-readable string (same sig as DocumentService::formatSize).
     */
    public static function formatSize(?int $bytes): string
    {
        if ($bytes === null) return '—';
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576,    1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024,       1) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Return a simple icon character for a Google MIME type.
     */
    public static function fileIcon(string $mimeType): string
    {
        return match(true) {
            str_contains($mimeType, 'folder')       => '📁',
            str_contains($mimeType, 'document')     => '📝',
            str_contains($mimeType, 'spreadsheet')  => '📊',
            str_contains($mimeType, 'presentation') => '📽️',
            str_contains($mimeType, 'pdf')           => '📄',
            str_contains($mimeType, 'image')         => '🖼️',
            str_contains($mimeType, 'video')         => '🎬',
            str_contains($mimeType, 'audio')         => '🎵',
            str_contains($mimeType, 'zip')           => '📦',
            default                                  => '📎',
        };
    }
}
