<?php
/**
 * CruinnCMS — Drivespace Admin Controller
 *
 * Quota management and Google Drive service account configuration.
 */

namespace Cruinn\Module\Drivespace\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;
use Cruinn\Module\Drivespace\Services\GoogleDriveService;

class FileManagerAdminController extends BaseController
{
    /**
     * GET /admin/drivespace — List all users with quota usage.
     */
    public function index(): void
    {
        Auth::requireRole('admin');

        $users = $this->db->fetchAll(
            "SELECT id, display_name, email,
                    COALESCE(drivespace_quota_bytes, 524288000)  AS quota_bytes,
                    COALESCE(drivespace_used_bytes,  0)          AS used_bytes
             FROM users
             ORDER BY used_bytes DESC"
        );

        $this->renderAdmin('admin/quota', [
            'title'       => 'Drivespace — Quota Management',
            'breadcrumbs' => [
                ['Dashboard', '/admin/dashboard'],
                ['Drivespace Admin'],
            ],
            'users'       => $users,
        ]);
    }

    /**
     * POST /admin/drivespace/{id}/quota — Update a user's quota limit.
     */
    public function setQuota(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $user = $this->db->fetch('SELECT id FROM users WHERE id = ?', [$id]);
        if (!$user) {
            Auth::flash('error', 'User not found.');
            $this->redirect('/admin/drivespace');
        }

        $mb    = max(0, (int) $this->input('quota_mb', 500));
        $bytes = $mb * 1048576;

        $this->db->execute(
            'UPDATE users SET drivespace_quota_bytes = ? WHERE id = ?',
            [$bytes, $id]
        );

        Auth::flash('success', 'Quota updated.');
        $this->redirect('/admin/drivespace');
    }

    // ── Google Drive configuration ──────────────────────────────

    /**
     * GET /admin/drivespace/gdrive — Google Drive settings page.
     */
    public function gdriveSettings(): void
    {
        Auth::requireRole('admin');

        $gdrive = new GoogleDriveService();

        $json        = $this->db->fetchColumn("SELECT `value` FROM settings WHERE `key` = 'gdrive.service_account_json'");
        $rootFolder  = $this->db->fetchColumn("SELECT `value` FROM settings WHERE `key` = 'gdrive.root_folder_id'");
        $sharedDrive = $gdrive->getSharedDriveId();
        $writeRole   = $gdrive->getWriteRole();
        $configured  = $gdrive->isConfigured();

        $this->renderAdmin('admin/gdrive-settings', [
            'title'           => 'Drivespace — Google Drive',
            'breadcrumbs'     => [
                ['Dashboard',       '/admin/dashboard'],
                ['Drivespace Admin', '/admin/drivespace'],
                ['Google Drive'],
            ],
            'configured'      => $configured,
            'hasJson'         => !empty($json),
            'rootFolderId'    => $rootFolder ?? '',
            'sharedDriveId'   => $sharedDrive ?? '',
            'writeRole'       => $writeRole,
        ]);
    }

    /**
     * POST /admin/drivespace/gdrive — Save service account JSON + root folder.
     */
    public function gdriveSettingsSave(): void
    {
        Auth::requireRole('admin');

        $rootFolder = trim($this->input('root_folder_id', ''));

        // Only update JSON if a new file was uploaded
        if (!empty($_FILES['service_account']['tmp_name'])) {
            $raw = file_get_contents($_FILES['service_account']['tmp_name']);
            $decoded = json_decode($raw, true);
            if (!$decoded || !isset($decoded['client_email'], $decoded['private_key'])) {
                Auth::flash('error', 'Invalid service account JSON file.');
                $this->redirect('/admin/drivespace/gdrive');
            }
            $this->upsertSetting('gdrive.service_account_json', $raw);
            $this->upsertSetting('gdrive.token_expires_at', '0');
            $this->upsertSetting('gdrive.access_token', null);
        }

        $this->upsertSetting('gdrive.root_folder_id', $rootFolder ?: null);

        $sharedDrive = trim($this->input('shared_drive_id', ''));
        $this->upsertSetting('gdrive.shared_drive_id', $sharedDrive ?: null);

        $allowed   = ['public', 'member', 'editor', 'council', 'admin'];
        $writeRole = $this->input('write_role', 'council');
        if (!in_array($writeRole, $allowed, true)) { $writeRole = 'council'; }
        $this->upsertSetting('gdrive.write_role', $writeRole);

        Auth::flash('success', 'Google Drive settings saved.');
        $this->redirect('/admin/drivespace/gdrive');
    }

    /**
     * Upsert a settings row — insert if missing, update if present.
     */
    private function upsertSetting(string $key, ?string $value): void
    {
        $this->db->execute(
            "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, 'drivespace')"
            . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );
    }

    /**
     * POST /admin/drivespace/gdrive/test — Test the service account connection.
     * Returns JSON.
     */
    public function gdriveTest(): void
    {
        Auth::requireRole('admin');

        $gdrive = new GoogleDriveService();
        try {
            if (!$gdrive->isConfigured()) {
                $this->json(['success' => false, 'error' => 'No service account configured.']);
                return;
            }
            $result = $gdrive->listFolder();
            $count  = count($result['folders']) + count($result['files']);
            $this->json(['success' => true, 'message' => "Connected. {$count} item(s) in root folder."]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/drivespace/gdrive/clear — Remove service account credentials.
     */
    public function gdriveClear(): void
    {
        Auth::requireRole('admin');

        $this->db->execute("UPDATE settings SET `value` = NULL WHERE `key` = 'gdrive.service_account_json'");
        $this->db->execute("UPDATE settings SET `value` = NULL WHERE `key` = 'gdrive.access_token'");
        $this->db->execute("UPDATE settings SET `value` = '0'  WHERE `key` = 'gdrive.token_expires_at'");
        $this->db->execute("UPDATE settings SET `value` = NULL WHERE `key` = 'gdrive.root_folder_id'");

        Auth::flash('success', 'Google Drive credentials removed.');
        $this->redirect('/admin/drivespace/gdrive');
    }
}
