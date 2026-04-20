<?php
/**
 * CruinnCMS — Drivespace Admin Controller
 *
 * Quota management: view per-user storage usage and set quota limits.
 */

namespace Cruinn\Module\Drivespace\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;

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
}
