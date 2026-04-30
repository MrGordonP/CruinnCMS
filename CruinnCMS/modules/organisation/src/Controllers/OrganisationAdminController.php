<?php
/**
 * CruinnCMS — Organisation Admin Controller
 *
 * Admin management of:
 *   - Organisation profile (single-row identity)
 *   - Officers / committee positions
 *   - Meetings (scheduled, completed, cancelled)
 */

namespace Cruinn\Module\Organisation\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;

class OrganisationAdminController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════
    //  PROFILE
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/profile
     */
    public function profile(): void
    {
        Auth::requireRole('admin');

        $profile = $this->db->fetch('SELECT * FROM organisation_profile WHERE id = 1');

        $this->renderAdmin('admin/organisation/profile', [
            'title'       => 'Organisation Profile',
            'breadcrumbs' => [
                ['Dashboard', '/admin/dashboard'],
                ['Organisation Profile'],
            ],
            'profile'     => $profile ?? [],
        ]);
    }

    /**
     * POST /admin/organisation/profile
     */
    public function saveProfile(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $fields = [
            'name'            => trim($this->input('name', '')),
            'short_name'      => trim($this->input('short_name', '')) ?: null,
            'tagline'         => trim($this->input('tagline', '')) ?: null,
            'founded_year'    => $this->input('founded_year', '') !== '' ? (int) $this->input('founded_year') : null,
            'registration_no' => trim($this->input('registration_no', '')) ?: null,
            'address'         => trim($this->input('address', '')) ?: null,
            'email'           => trim($this->input('email', '')) ?: null,
            'phone'           => trim($this->input('phone', '')) ?: null,
            'website'         => trim($this->input('website', '')) ?: null,
            'bio'             => trim($this->input('bio', '')) ?: null,
        ];

        // Always UPDATE row 1 (seeded by migration)
        $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
        $this->db->execute(
            "UPDATE organisation_profile SET {$setClauses} WHERE id = 1",
            array_values($fields)
        );

        Auth::flash('success', 'Organisation profile saved.');
        $this->redirect('/admin/organisation/profile');
    }

    // ══════════════════════════════════════════════════════════════════
    //  OFFICERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/officers
     */
    public function officers(): void
    {
        Auth::requireRole('admin');

        $officers = $this->db->fetchAll(
            "SELECT o.*, u.display_name AS user_display_name
             FROM organisation_officers o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.sort_order, o.position"
        );

        $users = $this->db->fetchAll(
            'SELECT id, display_name, email FROM users WHERE active = 1 ORDER BY display_name'
        );

        // Mailbox integration — only when module is active
        $allMailboxes       = [];
        $mailboxGrantsByOfficer = [];
        if (\Cruinn\Modules\ModuleRegistry::isActive('mailbox')) {
            $allMailboxes = $this->db->fetchAll(
                'SELECT id, label FROM mailboxes WHERE enabled = 1 ORDER BY label'
            );
            $grants = $this->db->fetchAll(
                'SELECT ma.id AS grant_id, ma.officer_position_id, ma.mailbox_id, mb.label
                   FROM mailbox_access ma
                   JOIN mailboxes mb ON mb.id = ma.mailbox_id
                  WHERE ma.officer_position_id IS NOT NULL'
            );
            foreach ($grants as $g) {
                $mailboxGrantsByOfficer[(int)$g['officer_position_id']][] = $g;
            }
        }

        $this->renderAdmin('admin/organisation/officers', [
            'title'       => 'Officers',
            'breadcrumbs' => [
                ['Dashboard', '/admin/dashboard'],
                ['Organisation Admin', '/admin/organisation/profile'],
                ['Officers'],
            ],
            'officers'              => $officers,
            'users'                 => $users,
            'allMailboxes'          => $allMailboxes,
            'mailboxGrantsByOfficer' => $mailboxGrantsByOfficer,
        ]);
    }

    /**
     * POST /admin/organisation/officers
     */
    public function createOfficer(): void
    {
        Auth::requireRole('admin');

        $position = trim($this->input('position', ''));
        if ($position === '') {
            Auth::flash('error', 'Position title is required.');
            $this->redirect('/admin/organisation/officers');
        }

        $userId = $this->input('user_id', '') !== '' ? (int) $this->input('user_id') : null;

        $this->db->insert('organisation_officers', [
            'position'   => $position,
            'user_id'    => $userId,
            'name'       => trim($this->input('name', '')) ?: null,
            'email'      => trim($this->input('email', '')) ?: null,
            'bio'        => trim($this->input('bio', '')) ?: null,
            'sort_order' => (int) $this->input('sort_order', 0),
            'active'     => 1,
            'term_start' => $this->input('term_start', '') ?: null,
            'term_end'   => $this->input('term_end', '') ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', 'Officer \'' . $position . '\' added.');
        $this->redirect('/admin/organisation/officers');
    }

    /**
     * POST /admin/organisation/officers/{id}/update
     */
    public function updateOfficer(int $id): void
    {
        Auth::requireRole('admin');

        $officer = $this->db->fetch('SELECT id FROM organisation_officers WHERE id = ?', [$id]);
        if (!$officer) {
            Auth::flash('error', 'Officer record not found.');
            $this->redirect('/admin/organisation/officers');
        }

        $position = trim($this->input('position', ''));
        if ($position === '') {
            Auth::flash('error', 'Position title is required.');
            $this->redirect('/admin/organisation/officers');
        }

        $userId = $this->input('user_id', '') !== '' ? (int) $this->input('user_id') : null;

        $this->db->execute(
            'UPDATE organisation_officers
             SET position = ?, user_id = ?, name = ?, email = ?, bio = ?,
                 sort_order = ?, active = ?, term_start = ?, term_end = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $position,
                $userId,
                trim($this->input('name', '')) ?: null,
                trim($this->input('email', '')) ?: null,
                trim($this->input('bio', '')) ?: null,
                (int) $this->input('sort_order', 0),
                $this->input('active', '0') === '1' ? 1 : 0,
                $this->input('term_start', '') ?: null,
                $this->input('term_end', '') ?: null,
                $id,
            ]
        );

        Auth::flash('success', 'Officer updated.');
        $this->redirect('/admin/organisation/officers');
    }

    /**
     * POST /admin/organisation/officers/{id}/delete
     */
    public function deleteOfficer(int $id): void
    {
        Auth::requireRole('admin');

        $this->db->delete('organisation_officers', 'id = ?', [$id]);

        Auth::flash('success', 'Officer removed.');
        $this->redirect('/admin/organisation/officers');
    }

    /**
     * POST /admin/organisation/officers/{id}/mailbox/assign
     */
    public function assignMailbox(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $mailboxId = (int) $this->input('mailbox_id', 0);
        if (!$mailboxId) {
            Auth::flash('error', 'No mailbox selected.');
            $this->redirect('/admin/organisation/officers');
        }

        // Upsert — ignore if already granted
        $this->db->execute(
            'INSERT IGNORE INTO mailbox_access (mailbox_id, officer_position_id) VALUES (?, ?)',
            [$mailboxId, $id]
        );

        Auth::flash('success', 'Mailbox access granted.');
        $this->redirect('/admin/organisation/officers');
    }

    /**
     * POST /admin/organisation/officers/{id}/mailbox/{grant_id}/revoke
     */
    public function revokeMailbox(int $id, int $grant_id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $this->db->execute(
            'DELETE FROM mailbox_access WHERE id = ? AND officer_position_id = ?',
            [$grant_id, $id]
        );

        Auth::flash('success', 'Mailbox access revoked.');
        $this->redirect('/admin/organisation/officers');
    }

    // ══════════════════════════════════════════════════════════════════
    //  MEETINGS
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/meetings
     */
    public function meetings(): void
    {
        Auth::requireRole('admin');

        $status = $this->query('status', '');
        $year   = $this->query('year', '');

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]  = 'm.status = ?';
            $params[] = $status;
        }
        if ($year !== '') {
            $where[]  = 'YEAR(m.meeting_date) = ?';
            $params[] = (int) $year;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $meetings = $this->db->fetchAll(
            "SELECT m.*,
                    u.display_name AS created_by_name,
                    ad.title AS agenda_title,
                    md2.title AS minutes_title
             FROM organisation_meetings m
             LEFT JOIN users u ON m.created_by = u.id
             LEFT JOIN documents ad  ON m.agenda_doc_id  = ad.id
             LEFT JOIN documents md2 ON m.minutes_doc_id = md2.id
             {$whereSQL}
             ORDER BY m.meeting_date DESC, m.start_time DESC",
            $params
        );

        // Available years for filter
        $years = $this->db->fetchAll(
            'SELECT DISTINCT YEAR(meeting_date) AS y FROM organisation_meetings ORDER BY y DESC'
        );

        // Documents available to link as agenda/minutes
        $documents = $this->db->fetchAll(
            "SELECT id, title FROM documents WHERE status IN ('approved','submitted') ORDER BY title"
        );

        $this->renderAdmin('admin/organisation/meetings', [
            'title'       => 'Meetings',
            'breadcrumbs' => [
                ['Dashboard', '/admin/dashboard'],
                ['Organisation Admin', '/admin/organisation/profile'],
                ['Meetings'],
            ],
            'meetings'    => $meetings,
            'documents'   => $documents,
            'years'       => array_column($years, 'y'),
            'status'      => $status,
            'year'        => $year,
        ]);
    }

    /**
     * POST /admin/organisation/meetings
     */
    public function createMeeting(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $title = trim($this->input('title', ''));
        $date  = $this->input('meeting_date', '');

        if ($title === '' || $date === '') {
            Auth::flash('error', 'Title and date are required.');
            $this->redirect('/admin/organisation/meetings');
        }

        $this->db->insert('organisation_meetings', [
            'title'          => $title,
            'meeting_type'   => $this->input('meeting_type', 'committee'),
            'meeting_date'   => $date,
            'start_time'     => $this->input('start_time', '') ?: null,
            'location'       => trim($this->input('location', '')) ?: null,
            'description'    => trim($this->input('description', '')) ?: null,
            'agenda_doc_id'  => $this->input('agenda_doc_id', '') !== '' ? (int) $this->input('agenda_doc_id') : null,
            'minutes_doc_id' => $this->input('minutes_doc_id', '') !== '' ? (int) $this->input('minutes_doc_id') : null,
            'status'         => $this->input('status', 'scheduled'),
            'created_by'     => Auth::userId(),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', 'Meeting \'' . $title . '\' created.');
        $this->redirect('/admin/organisation/meetings');
    }

    /**
     * POST /admin/organisation/meetings/{id}/update
     */
    public function updateMeeting(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $meeting = $this->db->fetch('SELECT id FROM organisation_meetings WHERE id = ?', [$id]);
        if (!$meeting) {
            Auth::flash('error', 'Meeting not found.');
            $this->redirect('/admin/organisation/meetings');
        }

        $title = trim($this->input('title', ''));
        $date  = $this->input('meeting_date', '');

        if ($title === '' || $date === '') {
            Auth::flash('error', 'Title and date are required.');
            $this->redirect('/admin/organisation/meetings');
        }

        $this->db->execute(
            'UPDATE organisation_meetings
             SET title = ?, meeting_type = ?, meeting_date = ?, start_time = ?,
                 location = ?, description = ?, agenda_doc_id = ?, minutes_doc_id = ?,
                 status = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $title,
                $this->input('meeting_type', 'committee'),
                $date,
                $this->input('start_time', '') ?: null,
                trim($this->input('location', '')) ?: null,
                trim($this->input('description', '')) ?: null,
                $this->input('agenda_doc_id', '') !== '' ? (int) $this->input('agenda_doc_id') : null,
                $this->input('minutes_doc_id', '') !== '' ? (int) $this->input('minutes_doc_id') : null,
                $this->input('status', 'scheduled'),
                $id,
            ]
        );

        Auth::flash('success', 'Meeting updated.');
        $this->redirect('/admin/organisation/meetings');
    }

    /**
     * POST /admin/organisation/meetings/{id}/delete
     */
    public function deleteMeeting(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $this->db->delete('organisation_meetings', 'id = ?', [$id]);

        Auth::flash('success', 'Meeting deleted.');
        $this->redirect('/admin/organisation/meetings');
    }
}
