<?php
/**
 * CruinnCMS â€” GDPR Controller
 *
 * Handles privacy policy, cookie policy, cookie consent,
 * data export requests, and account deletion.
 * All routes are no-ops / 404 when gdpr.enabled is false.
 */

namespace Cruinn\Module\Gdpr\Controllers;

use Cruinn\Auth;
use Cruinn\App;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Gdpr\Services\GdprService;

class GdprController extends BaseController
{
    // â”€â”€ Public Pages â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /privacy â€” Privacy policy page.
     */
    public function privacyPolicy(): void
    {
        $this->render('public/privacy', [
            'title'     => 'Privacy Policy',
            'org_name'  => App::config('gdpr.org_name', App::config('site.name')),
            'contact'   => App::config('gdpr.contact_email', App::config('mail.from_email')),
            'dpo_email' => App::config('gdpr.dpo_email', ''),
        ]);
    }

    /**
     * GET /cookies â€” Cookie policy page.
     */
    public function cookiePolicy(): void
    {
        $this->render('public/cookies', [
            'title'    => 'Cookie Policy',
            'org_name' => App::config('gdpr.org_name', App::config('site.name')),
        ]);
    }

    // â”€â”€ Cookie Consent (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /gdpr/consent â€” Record cookie consent via AJAX.
     */
    public function recordConsent(): void
    {
        $type    = $this->input('type', 'cookies');
        $granted = (bool) $this->input('granted', true);

        // Validate type
        $allowedTypes = ['cookies', 'privacy_policy', 'data_processing'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->json(['error' => 'Invalid consent type'], 400);
        }

        GdprService::recordConsent($type, $granted);

        $this->json(['ok' => true]);
    }

    // â”€â”€ Data Export (SAR) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /members/data-export â€” Request or immediately download data.
     */
    public function requestExport(): void
    {
        Auth::requireLogin();
        $userId = Auth::userId();

        // Generate export immediately (small dataset per user)
        $data = GdprService::exportUserData($userId);

        // Log the request
        GdprService::requestExport($userId);
        $this->logActivity('gdpr_export', 'user', $userId, 'Data export downloaded');

        // Mark it completed straight away
        $this->db->execute(
            "UPDATE gdpr_data_requests SET status = 'completed', processed_at = NOW()
             WHERE user_id = ? AND request_type = 'export' AND status = 'pending'",
            [$userId]
        );

        // Send as JSON download
        $filename = 'my-data-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // â”€â”€ Account Deletion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /members/delete-account â€” Confirmation page.
     */
    public function showDeleteAccount(): void
    {
        Auth::requireLogin();

        $this->render('public/members/delete-account', [
            'title' => 'Delete My Account',
        ]);
    }

    /**
     * POST /members/delete-account â€” Process account deletion.
     */
    public function deleteAccount(): void
    {
        Auth::requireLogin();
        $userId = Auth::userId();

        // Require the user to type "DELETE" to confirm
        $confirmation = strtoupper(trim($this->input('confirmation', '')));
        if ($confirmation !== 'DELETE') {
            Auth::flash('error', 'Please type DELETE to confirm account deletion.');
            $this->redirect('/members/delete-account');
        }

        $this->logActivity('gdpr_deletion', 'user', $userId, 'Account deletion requested by user');

        // Process deletion immediately
        GdprService::processAccountDeletion($userId);

        // Log out
        Auth::logout();

        Auth::flash('success', 'Your account has been deleted and your personal data has been removed.');
        $this->redirect('/');
    }
}
