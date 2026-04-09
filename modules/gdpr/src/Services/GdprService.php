<?php
/**
 * IGA Portal â€” GDPR Service
 *
 * Handles data export (Subject Access Requests), account deletion
 * (Right to Erasure), and consent record-keeping.
 *
 * All methods are no-ops when config('gdpr.enabled') is false.
 */

namespace IGA\Module\Gdpr\Services;

use IGA\App;
use IGA\Auth;
use IGA\Database;
use IGA\Modules\ModuleRegistry;

class GdprService
{
    /**
     * Check if GDPR features are enabled for this install.
     */
    public static function enabled(): bool
    {
        return (bool) App::config('gdpr.enabled', false);
    }

    // â”€â”€ Consent Tracking â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Record a consent action (granted or withdrawn).
     */
    public static function recordConsent(string $type, bool $granted, ?int $userId = null): void
    {
        if (!self::enabled()) {
            return;
        }

        $db = Database::getInstance();
        $db->insert('gdpr_consents', [
            'user_id'      => $userId ?? Auth::userId(),
            'consent_type' => $type,
            'granted'      => $granted ? 1 : 0,
            'ip_address'   => App::clientIp() ?: null,
            'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check if a user has active consent for a given type.
     * Returns the most recent consent record's granted status.
     */
    public static function hasConsent(string $type, ?int $userId = null): bool
    {
        if (!self::enabled()) {
            return true; // When GDPR is off, assume consent
        }

        $userId = $userId ?? Auth::userId();
        if (!$userId) {
            return false;
        }

        $db = Database::getInstance();
        $latest = $db->fetchColumn(
            'SELECT granted FROM gdpr_consents
             WHERE user_id = ? AND consent_type = ?
             ORDER BY created_at DESC LIMIT 1',
            [$userId, $type]
        );

        return $latest === '1' || $latest === 1;
    }

    // â”€â”€ Data Export (Subject Access Request) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Generate a full data export for a user as a structured array.
     * This collects all personal data held about the user.
     */
    public static function exportUserData(int $userId): array
    {
        $db = Database::getInstance();

        // Core user account
        $user = $db->fetch(
            'SELECT id, email, display_name, role, active, last_login, created_at
             FROM users WHERE id = ?',
            [$userId]
        );

        if (!$user) {
            return [];
        }

        // Member record
        $member = $db->fetch(
            'SELECT forenames, surnames, email, status, type, level, institute, notes, created_at
             FROM members WHERE user_id = ?',
            [$userId]
        );

        // Address
        $address = null;
        if ($member) {
            $memberId = $db->fetchColumn('SELECT id FROM members WHERE user_id = ?', [$userId]);
            $address = $db->fetch(
                'SELECT address1, address2, address3, address4, county, country, eircode, phone, mobile
                 FROM member_addresses WHERE member_id = ?',
                [$memberId]
            );
        }

        // OAuth linked accounts (strip tokens)
        $oauthAccounts = $db->fetchAll(
            'SELECT provider, email, display_name, created_at
             FROM user_oauth_accounts WHERE user_id = ?',
            [$userId]
        );

        // Event registrations
        $memberId = $member ? $db->fetchColumn('SELECT id FROM members WHERE user_id = ?', [$userId]) : null;
        $events = $memberId ? $db->fetchAll(
            'SELECT e.title, e.event_type, e.date_start, er.status, er.registered_at, er.amount_paid
             FROM event_registrations er
             JOIN events e ON e.id = er.event_id
             WHERE er.member_id = ?',
            [$memberId]
        ) : [];

        // Forum posts
        $forumPosts = ModuleRegistry::isActive('forum') ? $db->fetchAll(
            'SELECT fp.body, fp.created_at, ft.title as thread_title
             FROM forum_posts fp
             JOIN forum_threads ft ON ft.id = fp.thread_id
             WHERE fp.user_id = ?
             ORDER BY fp.created_at DESC',
            [$userId]
        ) : [];

        // Activity log entries about this user
        $activity = $db->fetchAll(
            'SELECT action, entity_type, ip_address, created_at
             FROM activity_log WHERE user_id = ?
             ORDER BY created_at DESC LIMIT 100',
            [$userId]
        );

        // Consent history
        $consents = $db->fetchAll(
            'SELECT consent_type, granted, ip_address, created_at
             FROM gdpr_consents WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );

        // Notification preferences
        $notifPrefs = $db->fetchAll(
            'SELECT channel, category, enabled
             FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        // Mailing list subscriptions
        $mailingLists = $db->fetchAll(
            'SELECT ml.name, mls.subscribed, mls.source, mls.subscribed_at, mls.unsubscribed_at
             FROM mailing_list_subscriptions mls
             JOIN mailing_lists ml ON ml.id = mls.list_id
             WHERE mls.user_id = ?
             ORDER BY mls.subscribed_at DESC',
            [$userId]
        );

        // Form submissions (data submitted through public forms)
        $formSubmissions = $db->fetchAll(
            'SELECT f.title AS form_title, fs.data, fs.status, fs.submitted_at
             FROM form_submissions fs
             JOIN forms f ON f.id = fs.form_id
             WHERE fs.user_id = ?
             ORDER BY fs.submitted_at DESC',
            [$userId]
        );
        // Decode the JSON data column for readable export
        foreach ($formSubmissions as &$sub) {
            $sub['data'] = json_decode($sub['data'], true) ?? $sub['data'];
        }
        unset($sub);

        return [
            'export_date' => date('Y-m-d H:i:s'),
            'account'     => $user,
            'member'      => $member,
            'address'     => $address,
            'oauth_accounts' => $oauthAccounts,
            'event_registrations' => $events,
            'forum_posts' => $forumPosts,
            'activity_log' => $activity,
            'consent_history' => $consents,
            'notification_preferences' => $notifPrefs,
            'mailing_list_subscriptions' => $mailingLists,
            'form_submissions' => $formSubmissions,
        ];
    }

    /**
     * Create a data export request (pending admin review / auto-processing).
     */
    public static function requestExport(int $userId): int
    {
        $db = Database::getInstance();
        return (int) $db->insert('gdpr_data_requests', [
            'user_id'      => $userId,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create an account deletion request.
     */
    public static function requestDeletion(int $userId): int
    {
        $db = Database::getInstance();
        return (int) $db->insert('gdpr_data_requests', [
            'user_id'      => $userId,
            'request_type' => 'deletion',
            'status'       => 'pending',
            'requested_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Process an account deletion â€” snapshot all data into a holding
     * table, then anonymise/remove from live tables.
     *
     * The snapshot is held for 30 days in case of accidental deletion
     * or disputes, then exported and purged.
     */
    public static function processAccountDeletion(int $userId, ?int $processedBy = null): void
    {
        $db = Database::getInstance();

        $db->transaction(function () use ($db, $userId, $processedBy) {
            // Snapshot all user data before we touch anything
            $snapshot = self::exportUserData($userId);

            if (!empty($snapshot)) {
                $now = date('Y-m-d H:i:s');
                $db->insert('deleted_accounts', [
                    'original_user_id' => $userId,
                    'account_data'     => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'deleted_at'       => $now,
                    'expires_at'       => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
            }

            $memberId = $db->fetchColumn('SELECT id FROM members WHERE user_id = ?', [$userId]);

            // Anonymise forum posts (keep content, remove authorship)
            if (ModuleRegistry::isActive('forum')) {
                $db->execute(
                    'UPDATE forum_posts SET user_id = NULL WHERE user_id = ?',
                    [$userId]
                );
            }

            // Anonymise event registrations (keep for event stats)
            if ($memberId) {
                $db->execute(
                    'UPDATE event_registrations SET member_id = NULL, guest_name = NULL, guest_email = NULL, dietary_notes = NULL, access_notes = NULL WHERE member_id = ?',
                    [$memberId]
                );
            }

            // Delete personal records
            if ($memberId) {
                $db->delete('member_addresses', 'member_id = ?', [$memberId]);
                $db->delete('member_subscriptions', 'member_id = ?', [$memberId]);
                $db->delete('member_surveys', 'member_id = ?', [$memberId]);
            }
            $db->delete('mailing_list_subscriptions', 'user_id = ?', [$userId]);
            $db->delete('members', 'user_id = ?', [$userId]);
            $db->delete('user_oauth_accounts', 'user_id = ?', [$userId]);
            $db->delete('notification_preferences', 'user_id = ?', [$userId]);
            $db->delete('notifications', 'user_id = ?', [$userId]);
            $db->delete('activity_log', 'user_id = ?', [$userId]);
            $db->delete('subject_subscriptions', 'user_id = ?', [$userId]);

            // Mark the deletion request as completed
            $db->execute(
                "UPDATE gdpr_data_requests SET status = 'completed', processed_at = NOW(), processed_by = ?
                 WHERE user_id = ? AND request_type = 'deletion' AND status = 'pending'",
                [$processedBy, $userId]
            );

            // Finally, deactivate and anonymise the user account
            $db->update('users', [
                'email'         => 'deleted_' . $userId . '@removed.local',
                'password_hash' => null,
                'display_name'  => 'Deleted User',
                'active'        => 0,
                'updated_at'    => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);
        });
    }

    // â”€â”€ Deleted account holding / purge â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get all held deleted accounts that have passed the 30-day window.
     */
    public static function expiredDeletedAccounts(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT id, original_user_id, account_data, deleted_at, expires_at
             FROM deleted_accounts
             WHERE purged_at IS NULL AND expires_at <= NOW()
             ORDER BY expires_at ASC'
        );
    }

    /**
     * Mark a deleted account record as purged (after export).
     */
    public static function markPurged(int $deletedAccountId): void
    {
        $db = Database::getInstance();
        $db->update('deleted_accounts', [
            'purged_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$deletedAccountId]);
    }

    /**
     * Purge all expired held accounts â€” delete the JSON data.
     * Returns the number of records purged.
     */
    public static function purgeExpired(): int
    {
        $expired = self::expiredDeletedAccounts();
        $db = Database::getInstance();
        $count = 0;

        foreach ($expired as $record) {
            $db->update('deleted_accounts', [
                'account_data' => json_encode(['purged' => true]),
                'purged_at'    => date('Y-m-d H:i:s'),
            ], 'id = ?', [$record['id']]);
            $count++;
        }

        return $count;
    }

    /**
     * Get pending data requests for admin review.
     */
    public static function pendingRequests(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT dr.*, u.email, u.display_name
             FROM gdpr_data_requests dr
             JOIN users u ON u.id = dr.user_id
             WHERE dr.status IN ('pending', 'processing')
             ORDER BY dr.requested_at ASC"
        );
    }
}
