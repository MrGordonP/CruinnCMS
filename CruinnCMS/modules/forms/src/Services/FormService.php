<?php
/**
 * CruinnCMS â€” Form Service
 *
 * CRUD for dynamic forms, field management, submission handling,
 * validation, approval workflows, CSV export, and membership
 * application post-processing.
 */

namespace Cruinn\Module\Forms\Services;

use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Mailer;

class FormService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // â”€â”€ Form CRUD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get all forms with submission counts.
     */
    public function allForms(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'f.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['form_type'])) {
            $where[] = 'f.form_type = ?';
            $params[] = $filters['form_type'];
        }

        $sql = 'SELECT f.*,
                    COUNT(fs.id) AS submission_count,
                    SUM(CASE WHEN fs.status = "pending" THEN 1 ELSE 0 END) AS pending_count
                FROM forms f
                LEFT JOIN form_submissions fs ON fs.form_id = f.id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY f.id ORDER BY f.created_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get a single form by ID or slug.
     */
    public function getForm(int|string $idOrSlug): array|false
    {
        $col = is_numeric($idOrSlug) ? 'id' : 'slug';
        return $this->db->fetch("SELECT * FROM forms WHERE {$col} = ?", [$idOrSlug]);
    }

    /**
     * Get a form with its active fields.
     */
    public function getFormWithFields(int|string $idOrSlug): array|false
    {
        $form = $this->getForm($idOrSlug);
        if (!$form) return false;

        $form['fields'] = $this->getFields($form['id']);
        $form['settings'] = json_decode($form['settings'] ?? '{}', true) ?: [];
        return $form;
    }

    /**
     * Create a new form.
     */
    public function createForm(array $data): string
    {
        return $this->db->insert('forms', [
            'title'       => $data['title'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? '',
            'form_type'   => $data['form_type'] ?? 'general',
            'status'      => $data['status'] ?? 'draft',
            'settings'    => json_encode($data['settings'] ?? []),
            'created_by'  => Auth::userId(),
        ]);
    }

    /**
     * Update a form.
     */
    public function updateForm(int $id, array $data): void
    {
        $update = [
            'title'       => $data['title'],
            'description' => $data['description'] ?? '',
            'form_type'   => $data['form_type'] ?? 'general',
            'status'      => $data['status'] ?? 'draft',
        ];

        if (isset($data['settings'])) {
            $update['settings'] = json_encode($data['settings']);
        }

        $this->db->update('forms', $update, 'id = ?', [$id]);
    }

    /**
     * Delete a form and all associated data (cascades via FK).
     */
    public function deleteForm(int $id): void
    {
        $this->db->delete('forms', 'id = ?', [$id]);
    }

    // â”€â”€ Field Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get all fields for a form.
     */
    public function getFields(int $formId): array
    {
        $fields = $this->db->fetchAll(
            'SELECT * FROM form_fields WHERE form_id = ? AND is_active = 1 ORDER BY sort_order ASC',
            [$formId]
        );

        foreach ($fields as &$f) {
            $f['options'] = json_decode($f['options'] ?? 'null', true);
            $f['validation'] = json_decode($f['validation'] ?? '{}', true) ?: [];
        }
        unset($f);

        return $fields;
    }

    /**
     * Add a field to a form.
     */
    public function addField(int $formId, array $data): string
    {
        $maxOrder = (int) $this->db->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), 0) FROM form_fields WHERE form_id = ?',
            [$formId]
        );

        return $this->db->insert('form_fields', [
            'form_id'     => $formId,
            'field_type'  => $data['field_type'] ?? 'text',
            'label'       => $data['label'],
            'name'        => $data['name'],
            'placeholder' => $data['placeholder'] ?? null,
            'help_text'   => $data['help_text'] ?? null,
            'options'     => !empty($data['options']) ? json_encode($data['options']) : null,
            'validation'  => !empty($data['validation']) ? json_encode($data['validation']) : '{}',
            'sort_order'  => $data['sort_order'] ?? ($maxOrder + 1),
        ]);
    }

    /**
     * Update a field.
     */
    public function updateField(int $fieldId, array $data): void
    {
        $update = [];
        foreach (['field_type', 'label', 'name', 'placeholder', 'help_text', 'sort_order'] as $col) {
            if (array_key_exists($col, $data)) {
                $update[$col] = $data[$col];
            }
        }
        if (array_key_exists('options', $data)) {
            $update['options'] = $data['options'] ? json_encode($data['options']) : null;
        }
        if (array_key_exists('validation', $data)) {
            $update['validation'] = json_encode($data['validation'] ?? []);
        }

        if ($update) {
            $this->db->update('form_fields', $update, 'id = ?', [$fieldId]);
        }
    }

    /**
     * Deactivate a field (soft delete â€” preserves submission data integrity).
     */
    public function deactivateField(int $fieldId): void
    {
        $this->db->update('form_fields', ['is_active' => 0], 'id = ?', [$fieldId]);
    }

    /**
     * Reorder fields.
     *
     * @param array $fieldOrders [{id, sort_order}, ...]
     */
    public function reorderFields(int $formId, array $fieldOrders): void
    {
        foreach ($fieldOrders as $entry) {
            $this->db->update('form_fields',
                ['sort_order' => (int) $entry['sort_order']],
                'id = ? AND form_id = ?',
                [(int) $entry['id'], $formId]
            );
        }
    }

    // â”€â”€ Submission Handling â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Validate and store a form submission.
     *
     * @return array ['success' => bool, 'errors' => [...], 'submission_id' => int|null]
     */
    public function submitForm(int $formId, array $postData, ?int $userId = null): array
    {
        $form = $this->getFormWithFields($formId);
        if (!$form) {
            return ['success' => false, 'errors' => ['Form not found.']];
        }

        if ($form['status'] !== 'published') {
            return ['success' => false, 'errors' => ['This form is not currently accepting submissions.']];
        }

        $settings = $form['settings'];

        // Check max submissions
        if (!empty($settings['max_submissions'])) {
            $count = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM form_submissions WHERE form_id = ?',
                [$formId]
            );
            if ($count >= (int) $settings['max_submissions']) {
                return ['success' => false, 'errors' => ['This form has reached its maximum number of submissions.']];
            }
        }

        // Validate fields
        $errors = $this->validateSubmission($form['fields'], $postData);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        // Collect clean data
        $cleanData = [];
        foreach ($form['fields'] as $field) {
            if (in_array($field['field_type'], ['heading', 'paragraph'])) continue;

            $value = $postData[$field['name']] ?? null;

            if ($field['field_type'] === 'checkbox') {
                $value = !empty($value) ? true : false;
            } elseif ($field['field_type'] === 'checkbox_group') {
                $value = is_array($value) ? $value : [];
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            $cleanData[$field['name']] = $value;
        }

        // Determine initial status
        $status = !empty($settings['require_approval']) ? 'pending' : 'approved';

        $submissionId = $this->db->insert('form_submissions', [
            'form_id'      => $formId,
            'user_id'      => $userId,
            'data'         => json_encode($cleanData),
            'status'       => $status,
            'ip_address'   => \Cruinn\App::clientIp() ?: null,
        ]);

        // Send notification email
        if (!empty($settings['notification_email'])) {
            $this->sendSubmissionNotification($form, $cleanData, (int) $submissionId);
        }

        return ['success' => true, 'errors' => [], 'submission_id' => (int) $submissionId];
    }

    /**
     * Validate a submission against field rules.
     *
     * @return string[] Error messages, empty if valid.
     */
    public function validateSubmission(array $fields, array $data): array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (in_array($field['field_type'], ['heading', 'paragraph', 'hidden'])) continue;

            $name = $field['name'];
            $value = $data[$name] ?? null;
            $rules = $field['validation'] ?? [];
            $label = $field['label'];

            // Required check
            if (!empty($rules['required'])) {
                if ($field['field_type'] === 'checkbox' && empty($value)) {
                    $errors[] = "{$label} must be accepted.";
                    continue;
                }
                if ($field['field_type'] === 'checkbox_group' && (empty($value) || !is_array($value) || count($value) === 0)) {
                    $errors[] = "{$label} requires at least one selection.";
                    continue;
                }
                if (is_string($value) && trim($value) === '') {
                    $errors[] = "{$label} is required.";
                    continue;
                }
                if ($value === null) {
                    $errors[] = "{$label} is required.";
                    continue;
                }
            }

            if ($value === null || $value === '' || (is_array($value) && empty($value))) continue;

            // Type-specific validation
            if ($field['field_type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label} must be a valid email address.";
            }

            if ($field['field_type'] === 'number') {
                if (!is_numeric($value)) {
                    $errors[] = "{$label} must be a number.";
                } else {
                    if (isset($rules['min']) && (float) $value < (float) $rules['min']) {
                        $errors[] = "{$label} must be at least {$rules['min']}.";
                    }
                    if (isset($rules['max']) && (float) $value > (float) $rules['max']) {
                        $errors[] = "{$label} must be no more than {$rules['max']}.";
                    }
                }
            }

            if (is_string($value)) {
                if (!empty($rules['min_length']) && mb_strlen($value) < (int) $rules['min_length']) {
                    $errors[] = "{$label} must be at least {$rules['min_length']} characters.";
                }
                if (!empty($rules['max_length']) && mb_strlen($value) > (int) $rules['max_length']) {
                    $errors[] = "{$label} must be no more than {$rules['max_length']} characters.";
                }
                if (!empty($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $value)) {
                    $errors[] = "{$label} format is invalid.";
                }
            }

            // Validate select/radio against allowed options
            if (in_array($field['field_type'], ['select', 'radio']) && !empty($field['options'])) {
                $allowed = array_column($field['options'], 'value');
                if (!in_array($value, $allowed, true)) {
                    $errors[] = "{$label}: invalid selection.";
                }
            }

            if ($field['field_type'] === 'checkbox_group' && is_array($value) && !empty($field['options'])) {
                $allowed = array_column($field['options'], 'value');
                foreach ($value as $v) {
                    if (!in_array($v, $allowed, true)) {
                        $errors[] = "{$label}: invalid option \"{$v}\".";
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    // â”€â”€ Submission Retrieval â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get submissions for a form with optional filters.
     */
    public function getSubmissions(int $formId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['fs.form_id = ?'];
        $params = [$formId];

        if (!empty($filters['status'])) {
            $where[] = 'fs.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'fs.data LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql = 'SELECT fs.*, u.display_name AS user_name, u.email AS user_email,
                       r.display_name AS reviewer_name
                FROM form_submissions fs
                LEFT JOIN users u ON fs.user_id = u.id
                LEFT JOIN users r ON fs.reviewer_id = r.id
                WHERE ' . implode(' AND ', $where) .
               ' ORDER BY fs.submitted_at DESC
                LIMIT ? OFFSET ?';

        $params[] = $limit;
        $params[] = $offset;

        $submissions = $this->db->fetchAll($sql, $params);

        foreach ($submissions as &$s) {
            $s['data'] = json_decode($s['data'], true) ?: [];
        }
        unset($s);

        return $submissions;
    }

    /**
     * Count submissions for a form with optional status filter.
     */
    public function countSubmissions(int $formId, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) FROM form_submissions WHERE form_id = ?';
        $params = [$formId];

        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Get a single submission.
     */
    public function getSubmission(int $submissionId): array|false
    {
        $s = $this->db->fetch(
            'SELECT fs.*, u.display_name AS user_name, u.email AS user_email,
                    r.display_name AS reviewer_name
             FROM form_submissions fs
             LEFT JOIN users u ON fs.user_id = u.id
             LEFT JOIN users r ON fs.reviewer_id = r.id
             WHERE fs.id = ?',
            [$submissionId]
        );

        if ($s) {
            $s['data'] = json_decode($s['data'], true) ?: [];
        }

        return $s ?: false;
    }

    // â”€â”€ Approval Workflow â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Approve a submission. For membership applications, triggers post-processing.
     */
    public function approveSubmission(int $submissionId, ?string $notes = null): bool
    {
        $submission = $this->getSubmission($submissionId);
        if (!$submission || $submission['status'] !== 'pending') return false;

        $this->db->update('form_submissions', [
            'status'         => 'approved',
            'reviewer_id'    => Auth::userId(),
            'reviewed_at'    => date('Y-m-d H:i:s'),
            'reviewer_notes' => $notes,
        ], 'id = ?', [$submissionId]);

        // Post-processing for membership applications
        $form = $this->getForm($submission['form_id']);
        if ($form && $form['form_type'] === 'membership_application') {
            $this->processMembershipApplication($submission);
        }

        return true;
    }

    /**
     * Reject a submission.
     */
    public function rejectSubmission(int $submissionId, ?string $notes = null): bool
    {
        $submission = $this->getSubmission($submissionId);
        if (!$submission || $submission['status'] !== 'pending') return false;

        $this->db->update('form_submissions', [
            'status'         => 'rejected',
            'reviewer_id'    => Auth::userId(),
            'reviewed_at'    => date('Y-m-d H:i:s'),
            'reviewer_notes' => $notes,
        ], 'id = ?', [$submissionId]);

        return true;
    }

    // â”€â”€ CSV Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Export form submissions as CSV.
     */
    public function exportCsv(int $formId): string
    {
        $form = $this->getFormWithFields($formId);
        if (!$form) return '';

        $fields = array_filter($form['fields'], fn($f) => !in_array($f['field_type'], ['heading', 'paragraph']));
        $submissions = $this->getSubmissions($formId, [], 10000, 0);

        $output = fopen('php://temp', 'r+');

        // Header row
        $headers = ['Submitted', 'Status', 'User'];
        foreach ($fields as $f) {
            $headers[] = $f['label'];
        }
        $headers[] = 'Reviewer Notes';
        fputcsv($output, $headers);

        // Data rows
        foreach ($submissions as $s) {
            $row = [
                $s['submitted_at'],
                $s['status'],
                $s['user_name'] ?? ($s['user_email'] ?? 'Guest'),
            ];
            foreach ($fields as $f) {
                $val = $s['data'][$f['name']] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                if (is_bool($val)) $val = $val ? 'Yes' : 'No';
                $row[] = $val;
            }
            $row[] = $s['reviewer_notes'] ?? '';
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    // â”€â”€ Membership Application Processing â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Process an approved membership application submission.
     * Creates/updates member, address, subscription, and survey records.
     */
    private function processMembershipApplication(array $submission): void
    {
        $data = $submission['data'];
        $email = $data['email'] ?? '';
        $forenames = $data['first_name'] ?? '';
        $surnames = $data['surname'] ?? '';

        if (!$email || !$forenames || !$surnames) return;

        // Determine membership fee from type
        $typeFees = [
            'O' => 25.00, 'S' => 10.00, 'SM' => 100.00,
            'L' => 0, 'HO' => 0, 'H&L' => 0,
        ];
        $memberType = $data['membership_type'] ?? 'O';
        $fee = $typeFees[$memberType] ?? 25.00;

        $this->db->transaction(function () use ($data, $email, $forenames, $surnames, $memberType, $fee, $submission) {
            // Find or create member
            $member = $this->db->fetch('SELECT * FROM members WHERE email = ?', [$email]);

            if ($member) {
                // Update existing member
                $this->db->update('members', [
                    'type'   => $memberType,
                    'level'  => $data['geologist_level'] ?? null,
                    'status' => 'active',
                ], 'id = ?', [$member['id']]);
                $memberId = $member['id'];
            } else {
                // Create new member
                $memberId = $this->db->insert('members', [
                    'user_id'    => $submission['user_id'],
                    'forenames'  => $forenames,
                    'surnames'   => $surnames,
                    'email'      => $email,
                    'type'       => $memberType,
                    'level'      => $data['geologist_level'] ?? null,
                    'status'     => 'active',
                ]);
            }

            // Upsert address (county)
            if (!empty($data['county'])) {
                $addr = $this->db->fetch('SELECT id FROM member_addresses WHERE member_id = ?', [$memberId]);
                if ($addr) {
                    $this->db->update('member_addresses', [
                        'county'  => $data['county'],
                        'country' => 'Ireland',
                    ], 'id = ?', [$addr['id']]);
                } else {
                    $this->db->insert('member_addresses', [
                        'member_id' => $memberId,
                        'county'    => $data['county'],
                        'country'   => 'Ireland',
                    ]);
                }
            }

            // Create subscription record
            if ($fee > 0) {
                $this->db->insert('member_subscriptions', [
                    'member_id'    => $memberId,
                    'amount'       => $fee,
                    'currency'     => 'EUR',
                    'period'       => date('Y'),
                    'category'     => $memberType,
                    'level'        => $data['geologist_level'] ?? null,
                    'method'       => $data['payment_method'] ?? null,
                    'reference'    => $data['payment_reference'] ?? null,
                    'status'       => ($data['payment_reference'] ?? '') ? 'paid' : 'pending',
                    'payment_date' => $data['payment_date'] ?? null,
                ]);
            }

            // Create survey record (interests)
            $eventInterest = $data['event_interest'] ?? [];
            if (is_array($eventInterest)) $eventInterest = implode(', ', $eventInterest);

            $this->db->insert('member_surveys', [
                'member_id'              => $memberId,
                'level'                  => $data['geologist_level'] ?? null,
                'event_interest'         => $eventInterest ?: null,
                'field_interest'         => $data['lecture_interest'] ?? null,
                'excursion_field_interest' => $data['excursion_interest'] ?? null,
            ]);

            // Record GDPR consent
            if (!empty($data['gdpr_consent'])) {
                // Check if gdpr_consents table exists (from migration 014)
                try {
                    $userId = $submission['user_id'] ?? null;
                    if ($userId) {
                        $this->db->insert('gdpr_consents', [
                            'user_id'      => $userId,
                            'consent_type' => 'membership_communications',
                            'granted'      => 1,
                            'ip_address'   => $submission['ip_address'] ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    // GDPR table may not exist yet â€” non-fatal
                    error_log('FormService: GDPR consent insert failed: ' . $e->getMessage());
                }
            }

            // Mark submission as processed
            $this->db->update('form_submissions', [
                'status' => 'processed',
            ], 'id = ?', [$submission['id']]);
        });
    }

    // â”€â”€ Notification Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Send notification email when a form receives a submission.
     */
    private function sendSubmissionNotification(array $form, array $data, int $submissionId): void
    {
        $settings = is_string($form['settings']) ? json_decode($form['settings'], true) : ($form['settings'] ?? []);
        $to = $settings['notification_email'] ?? '';
        if (!$to) return;

        $dataRows = '';
        foreach ($data as $key => $val) {
            if (is_array($val)) $val = implode(', ', $val);
            if (is_bool($val)) $val = $val ? 'Yes' : 'No';
            $key = htmlspecialchars(ucwords(str_replace('_', ' ', $key)), ENT_QUOTES, 'UTF-8');
            $val = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
            $dataRows .= "<tr><td style=\"padding:4px 8px;font-weight:bold;\">{$key}</td><td style=\"padding:4px 8px;\">{$val}</td></tr>";
        }

        $html = "<h2>New submission: {$form['title']}</h2>"
              . "<p>Submission #{$submissionId} received at " . date('Y-m-d H:i') . ".</p>"
              . "<table border=\"1\" cellpadding=\"4\" cellspacing=\"0\" style=\"border-collapse:collapse;\">{$dataRows}</table>"
              . "<p><a href=\"/admin/forms/{$form['id']}/submissions/{$submissionId}\">View in admin</a></p>";

        $text = "New submission: {$form['title']}\n"
              . "Submission #{$submissionId} at " . date('Y-m-d H:i') . "\n\n";
        foreach ($data as $key => $val) {
            if (is_array($val)) $val = implode(', ', $val);
            if (is_bool($val)) $val = $val ? 'Yes' : 'No';
            $text .= ucwords(str_replace('_', ' ', $key)) . ': ' . $val . "\n";
        }

        Mailer::send($to, "Form Submission: {$form['title']}", $html, $text);
    }
}
