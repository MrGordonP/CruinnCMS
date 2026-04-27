<?php

namespace Cruinn\Module\Membership\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Membership\Services\MembershipService;

class MembershipAdminController extends BaseController
{
    private MembershipService $membership;

    public function __construct()
    {
        parent::__construct();
        $this->membership = new MembershipService();
    }

    public function indexMembers(): void
    {
        Auth::requireRole('admin');

        $filters = [
            'status'  => $this->query('status', ''),
            'plan_id' => $this->query('plan_id', ''),
            'q'       => trim((string) $this->query('q', '')),
        ];

        $memberId      = (int) $this->query('member', 0);
        $members       = $this->membership->listMembers($filters);
        $member        = $memberId ? $this->membership->findById($memberId) : null;
        $subscriptions = $member ? $this->membership->subscriptionsForMember($memberId) : [];
        $payments      = $member ? $this->membership->paymentsForMember($memberId) : [];

        $linkedUser = null;
        if ($member && !empty($member['user_id'])) {
            $linkedUser = $this->db->fetch(
                'SELECT id, display_name, email FROM users WHERE id = ?',
                [(int) $member['user_id']]
            );
        }

        $this->renderAdmin('admin/membership/members/index', [
            'title'         => 'Membership',
            'members'       => $members,
            'plans'         => $this->membership->allPlans(true),
            'filters'       => $filters,
            'statusCount'   => $this->membership->countByStatus(),
            'member'        => $member,
            'memberId'      => $memberId,
            'subscriptions' => $subscriptions,
            'payments'      => $payments,
            'linkedUser'    => $linkedUser,
            'errors'        => [],
            'breadcrumbs'   => [['Admin', '/admin'], ['Membership']],
        ]);
    }

    public function showMember(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/admin/membership?member=' . $id);
    }

    public function newMember(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/membership/members/form', [
            'title'       => 'New Member',
            'member'      => null,
            'plans'       => $this->membership->allPlans(true),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['New Member']],
        ]);
    }

    public function createMember(): void
    {
        Auth::requireRole('admin');

        $data = $this->memberPayload();
        $errors = $this->validateMemberPayload($data, null);

        if ($errors) {
            $this->renderAdmin('admin/membership/members/form', [
                'title'       => 'New Member',
                'member'      => $data,
                'plans'       => $this->membership->allPlans(true),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['New Member']],
            ]);
            return;
        }

        $memberId = $this->membership->createMember($data);
        $this->logActivity('create', 'member', $memberId, 'Membership record created.');
        Auth::flash('success', 'Member created.');
        $this->redirect('/admin/membership?member=' . $memberId);
    }

    public function editMember(int $id): void
    {
        Auth::requireRole('admin');
        $this->redirect('/admin/membership?member=' . $id);
    }

    public function updateMember(int $id): void
    {
        Auth::requireRole('admin');

        $existing = $this->membership->findById($id);
        if (!$existing) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = $this->memberPayload();
        $errors = $this->validateMemberPayload($data, $id);

        if ($errors) {
            $filters = ['status' => '', 'plan_id' => '', 'q' => ''];
            $this->renderAdmin('admin/membership/members/index', [
                'title'         => 'Membership',
                'members'       => $this->membership->listMembers($filters),
                'plans'         => $this->membership->allPlans(true),
                'filters'       => $filters,
                'statusCount'   => $this->membership->countByStatus(),
                'member'        => array_merge($existing, $data),
                'memberId'      => $id,
                'subscriptions' => $this->membership->subscriptionsForMember($id),
                'payments'      => $this->membership->paymentsForMember($id),
                'errors'        => $errors,
                'breadcrumbs'   => [['Admin', '/admin'], ['Membership']],
            ]);
            return;
        }

        $this->membership->updateMember($id, $data);
        $this->logActivity('update', 'member', $id, 'Membership record updated.');
        Auth::flash('success', 'Member updated.');
        $this->redirect('/admin/membership?member=' . $id);
    }

    public function createSubscription(int $id): void
    {
        Auth::requireRole('admin');

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership');
        }

        $data = [
            'plan_id'           => $this->input('plan_id', ''),
            'period_label'      => $this->input('period_label', ''),
            'period_start'      => $this->input('period_start', ''),
            'period_end'        => $this->input('period_end', ''),
            'amount'            => (float) $this->input('amount', 0),
            'currency'          => strtoupper((string) $this->input('currency', 'EUR')),
            'status'            => $this->input('status', 'pending'),
            'due_date'          => $this->input('due_date', ''),
            'paid_at'           => $this->input('paid_at', ''),
            'payment_reference' => $this->input('payment_reference', ''),
            'notes'             => $this->input('notes', ''),
        ];

        if ($data['period_label'] === '' || $data['period_start'] === '' || $data['period_end'] === '') {
            Auth::flash('error', 'Period label/start/end are required to create a subscription.');
            $this->redirect('/admin/membership/members/' . $id);
        }

        $subId = $this->membership->createSubscription($id, $data);
        $this->logActivity('create', 'membership_subscription', $subId, 'Subscription created for member #' . $id . '.');
        Auth::flash('success', 'Subscription created.');
        $this->redirect('/admin/membership?member=' . $id);
    }

    public function updateSubscriptionStatus(int $id): void
    {
        Auth::requireRole('admin');

        $status = (string) $this->input('status', 'pending');
        $allowed = ['pending', 'paid', 'overdue', 'waived', 'refunded', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            Auth::flash('error', 'Invalid subscription status.');
            $this->redirect('/admin/membership');
        }

        $this->membership->updateSubscriptionStatus($id, $status);
        $this->logActivity('update', 'membership_subscription', $id, 'Subscription status set to ' . $status . '.');
        Auth::flash('success', 'Subscription status updated.');

        $memberId = (int) $this->input('member_id', 0);
        if ($memberId > 0) {
            $this->redirect('/admin/membership?member=' . $memberId);
        }

        $this->redirect('/admin/membership');
    }

    public function recordPayment(int $id): void
    {
        Auth::requireRole('admin');

        $data = [
            'amount'    => (float) $this->input('amount', 0),
            'currency'  => strtoupper((string) $this->input('currency', 'EUR')),
            'method'    => $this->input('method', ''),
            'reference' => $this->input('reference', ''),
            'status'    => (string) $this->input('status', 'completed'),
            'paid_at'   => (string) $this->input('paid_at', date('Y-m-d H:i:s')),
            'notes'     => $this->input('notes', ''),
        ];

        if ($data['amount'] <= 0) {
            Auth::flash('error', 'Payment amount must be greater than zero.');
            $this->redirect('/admin/membership');
        }

        try {
            $paymentId = $this->membership->recordPayment($id, $data);
            $this->logActivity('create', 'membership_payment', $paymentId, 'Payment recorded for subscription #' . $id . '.');
            Auth::flash('success', 'Payment recorded.');
        } catch (\Throwable $e) {
            Auth::flash('error', 'Failed to record payment: ' . $e->getMessage());
        }

        $memberId = (int) $this->input('member_id', 0);
        if ($memberId > 0) {
            $this->redirect('/admin/membership?member=' . $memberId);
        }

        $this->redirect('/admin/membership');
    }

    public function importForm(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/membership/members/import', [
            'title'       => 'Import Members',
            'plans'       => $this->membership->allPlans(true),
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Import']],
        ]);
    }

    /**
     * Step 1: Validate upload, store temp file, parse headers, render mapping UI.
     */
    public function processImport(): void
    {
        Auth::requireRole('admin');

        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'No file uploaded or upload error.');
            $this->redirect('/admin/membership/import');
        }

        $nameOk = preg_match('/\.csv$/i', (string) $_FILES['csv_file']['name']) === 1;
        $mimeOk = in_array($_FILES['csv_file']['type'], ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true);
        if (!$mimeOk && !$nameOk) {
            Auth::flash('error', 'Uploaded file must be a CSV.');
            $this->redirect('/admin/membership/import');
        }

        // Persist to a session-scoped temp file so the confirm step can re-read it
        $tmpKey  = bin2hex(random_bytes(16));
        $tmpDest = sys_get_temp_dir() . '/cruinn_import_' . $tmpKey . '.csv';
        if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpDest)) {
            Auth::flash('error', 'Could not save uploaded file.');
            $this->redirect('/admin/membership/import');
        }

        $handle = fopen($tmpDest, 'r');
        if ($handle === false) {
            Auth::flash('error', 'Could not read uploaded file.');
            $this->redirect('/admin/membership/import');
        }

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '');
        if (!$rawHeaders) {
            fclose($handle);
            unlink($tmpDest);
            Auth::flash('error', 'CSV file appears to be empty.');
            $this->redirect('/admin/membership/import');
        }

        $csvHeaders = array_map(fn($h) => trim((string) $h), $rawHeaders);

        // Read up to 3 preview rows
        $preview = [];
        while (count($preview) < 3 && ($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) === count($csvHeaders)) {
                $preview[] = array_combine($csvHeaders, $row);
            }
        }
        // Count total data rows
        $totalRows = count($preview);
        while (fgetcsv($handle, 0, ',', '"', '') !== false) {
            $totalRows++;
        }
        fclose($handle);

        if ($totalRows === 0) {
            unlink($tmpDest);
            Auth::flash('error', 'CSV file contains no data rows.');
            $this->redirect('/admin/membership/import');
        }

        $onDuplicate = (string) $this->input('on_duplicate', 'skip');
        if (!in_array($onDuplicate, ['skip', 'update'], true)) {
            $onDuplicate = 'skip';
        }
        $defaultStatus = (string) $this->input('default_status', 'applicant');
        $allowedStatuses = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
        if (!in_array($defaultStatus, $allowedStatuses, true)) {
            $defaultStatus = 'applicant';
        }

        // Auto-detect mapping: if a CSV header matches a known canonical name, pre-select it
        $systemFields = [
            'forenames'       => 'Forenames *',
            'surnames'        => 'Surnames *',
            'email'           => 'Email *',
            'phone'           => 'Phone',
            'organisation'    => 'Organisation',
            'membership_number' => 'Membership number',
            'membership_year' => 'Membership year',
            'status'          => 'Status',
            'plan'            => 'Plan',
            'joined_at'       => 'Joined date',
            'lapsed_at'       => 'Lapsed date',
            'notes'           => 'Notes',
            'address_line_1'  => 'Address line 1',
            'address_line_2'  => 'Address line 2',
            'city'            => 'City / Town',
            'county'          => 'County / State',
            'postcode'        => 'Postcode',
            'country'         => 'Country',
        ];

        // Aliases used by importMembers for auto-detect pre-fill
        $fieldAliases = [
            'forenames'       => ['forenames', 'first_name', 'firstname', 'first name'],
            'surnames'        => ['surnames', 'surname', 'last_name', 'lastname', 'last name'],
            'email'           => ['email', 'email_address'],
            'phone'           => ['phone', 'telephone', 'mobile'],
            'organisation'    => ['organisation', 'organization', 'org', 'company'],
            'membership_number' => ['membership_number', 'membership_no', 'member_number', 'member_no', 'number'],
            'membership_year' => ['membership_year', 'year'],
            'status'          => ['status'],
            'plan'            => ['plan', 'plan_id', 'plan_slug', 'plan_name'],
            'joined_at'       => ['joined_at', 'joined', 'join_date'],
            'lapsed_at'       => ['lapsed_at', 'lapsed'],
            'notes'           => ['notes', 'note', 'comments'],
            'address_line_1'  => ['address_line_1', 'line_1', 'address1', 'address'],
            'address_line_2'  => ['address_line_2', 'line_2', 'address2'],
            'city'            => ['city', 'town'],
            'county'          => ['county', 'state', 'region', 'province'],
            'postcode'        => ['postcode', 'postal_code', 'zip'],
            'country'         => ['country'],
        ];

        $autoMapping = [];
        foreach ($systemFields as $fieldKey => $_) {
            $autoMapping[$fieldKey] = '';
            $aliases = $fieldAliases[$fieldKey] ?? [$fieldKey];
            foreach ($csvHeaders as $csvHeader) {
                if (in_array(strtolower($csvHeader), $aliases, true)) {
                    $autoMapping[$fieldKey] = $csvHeader;
                    break;
                }
            }
        }

        $_SESSION['cruinn_import'] = [
            'tmp'            => $tmpDest,
            'on_duplicate'   => $onDuplicate,
            'default_status' => $defaultStatus,
        ];

        $this->renderAdmin('admin/membership/members/import_map', [
            'title'          => 'Import Members — Map Columns',
            'csvHeaders'     => $csvHeaders,
            'systemFields'   => $systemFields,
            'autoMapping'    => $autoMapping,
            'preview'        => $preview,
            'totalRows'      => $totalRows,
            'onDuplicate'    => $onDuplicate,
            'defaultStatus'  => $defaultStatus,
            'breadcrumbs'    => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Import', '/admin/membership/import'], ['Map Columns']],
        ]);
    }

    /**
     * Step 2: Apply column mapping, run import, show result.
     */
    public function confirmImport(): void
    {
        Auth::requireRole('admin');

        $importMeta = $_SESSION['cruinn_import'] ?? null;
        if (!$importMeta || !isset($importMeta['tmp']) || !file_exists($importMeta['tmp'])) {
            Auth::flash('error', 'Import session expired. Please upload the file again.');
            $this->redirect('/admin/membership/import');
        }

        unset($_SESSION['cruinn_import']);

        $tmpPath       = $importMeta['tmp'];
        $onDuplicate   = $importMeta['on_duplicate'];
        $defaultStatus = $importMeta['default_status'];

        // Read the mapping from POST: map[system_field] = csv_column_name (or '' to ignore)
        $rawMapping = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : [];
        $mapping    = []; // canonical_field => csv_column_name
        foreach ($rawMapping as $field => $csvCol) {
            $csvCol = trim((string) $csvCol);
            if ($csvCol !== '') {
                $mapping[$field] = $csvCol;
            }
        }

        $handle = fopen($tmpPath, 'r');
        if ($handle === false) {
            unlink($tmpPath);
            Auth::flash('error', 'Could not reopen import file.');
            $this->redirect('/admin/membership/import');
        }

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '');
        $csvHeaders = $rawHeaders ? array_map(fn($h) => trim((string) $h), $rawHeaders) : [];

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($csvHeaders)) {
                continue;
            }
            $csvRow    = array_combine($csvHeaders, $row);
            $canonical = [];
            foreach ($mapping as $field => $csvCol) {
                $canonical[$field] = $csvRow[$csvCol] ?? '';
            }
            $rows[] = $canonical;
        }
        fclose($handle);
        unlink($tmpPath);

        if (empty($rows)) {
            Auth::flash('error', 'No data rows found after applying mapping.');
            $this->redirect('/admin/membership/import');
        }

        $result = $this->membership->importMembers($rows, $onDuplicate, $defaultStatus);

        $this->logActivity('import', 'member', null,
            sprintf('CSV import: %d created, %d updated, %d skipped, %d errors.',
                $result['created'], $result['updated'], $result['skipped'], count($result['errors']))
        );

        $this->renderAdmin('admin/membership/members/import', [
            'title'       => 'Import Members',
            'plans'       => $this->membership->allPlans(true),
            'result'      => $result,
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Import']],
        ]);
    }

    public function listPlans(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/membership/plans/index', [
            'title'       => 'Membership Plans',
            'plans'       => $this->membership->allPlans(),
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans']],
        ]);
    }

    public function newPlan(): void
    {
        Auth::requireRole('admin');

        $this->renderAdmin('admin/membership/plans/form', [
            'title'       => 'New Membership Plan',
            'plan'        => null,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['New Plan']],
        ]);
    }

    public function createPlan(): void
    {
        Auth::requireRole('admin');

        $data = $this->planPayload();
        $errors = $this->validatePlanPayload($data, null);

        if ($errors) {
            $this->renderAdmin('admin/membership/plans/form', [
                'title'       => 'New Membership Plan',
                'plan'        => $data,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['New Plan']],
            ]);
            return;
        }

        $planId = $this->membership->createPlan($data);
        $this->logActivity('create', 'membership_plan', $planId, 'Membership plan created.');
        Auth::flash('success', 'Plan created.');
        $this->redirect('/admin/membership/plans');
    }

    public function editPlan(int $id): void
    {
        Auth::requireRole('admin');

        $plan = $this->membership->findPlan($id);
        if (!$plan) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->renderAdmin('admin/membership/plans/form', [
            'title'       => 'Edit Membership Plan',
            'plan'        => $plan,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['Edit Plan']],
        ]);
    }

    public function updatePlan(int $id): void
    {
        Auth::requireRole('admin');

        $plan = $this->membership->findPlan($id);
        if (!$plan) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = $this->planPayload();
        $errors = $this->validatePlanPayload($data, $id);

        if ($errors) {
            $this->renderAdmin('admin/membership/plans/form', [
                'title'       => 'Edit Membership Plan',
                'plan'        => array_merge($plan, $data),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['Edit Plan']],
            ]);
            return;
        }

        $this->membership->updatePlan($id, $data);
        $this->logActivity('update', 'membership_plan', $id, 'Membership plan updated.');
        Auth::flash('success', 'Plan updated.');
        $this->redirect('/admin/membership/plans');
    }

    public function searchMembers(): void
    {
        Auth::requireRole('admin');
        $q = trim((string) $this->query('q', ''));
        if (strlen($q) < 2) {
            $this->json([]);
        }
        $like = '%' . $q . '%';
        $rows = $this->db->fetchAll(
            'SELECT id, forenames, surnames, email, membership_number, status
             FROM members
             WHERE forenames LIKE ? OR surnames LIKE ? OR email LIKE ? OR membership_number LIKE ?
             ORDER BY surnames ASC, forenames ASC LIMIT 10',
            [$like, $like, $like, $like]
        );
        $this->json(array_values(array_map(fn($r) => [
            'id'                => (int) $r['id'],
            'display_name'      => trim($r['forenames'] . ' ' . $r['surnames']),
            'email'             => $r['email'],
            'membership_number' => $r['membership_number'] ?? '',
            'status'            => $r['status'],
        ], $rows)));
    }

    public function linkUser(int $id): void
    {
        Auth::requireRole('admin');

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership');
            return;
        }

        $q = trim((string) $this->input('user_search', ''));
        if ($q === '') {
            Auth::flash('error', 'Enter a user email or display name.');
            $this->redirect('/admin/membership?member=' . $id);
            return;
        }

        $user = $this->db->fetch(
            'SELECT id, display_name, email FROM users WHERE email = ? OR display_name = ? LIMIT 1',
            [strtolower($q), $q]
        );
        if (!$user) {
            Auth::flash('error', 'No user found matching "' . htmlspecialchars($q, ENT_QUOTES) . '".');
            $this->redirect('/admin/membership?member=' . $id);
            return;
        }

        $conflict = $this->db->fetch(
            'SELECT id FROM members WHERE user_id = ? AND id != ?',
            [(int) $user['id'], $id]
        );
        if ($conflict) {
            Auth::flash('error', 'That user is already linked to a different member record.');
            $this->redirect('/admin/membership?member=' . $id);
            return;
        }

        $this->membership->linkUser($id, (int) $user['id']);
        $this->logActivity('update', 'member', $id, 'Linked to user #' . $user['id'] . ' (' . $user['email'] . ').');
        Auth::flash('success', 'User account linked to member.');
        $this->redirect('/admin/membership?member=' . $id);
    }

    public function unlinkUser(int $id): void
    {
        Auth::requireRole('admin');

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership');
            return;
        }

        $this->membership->unlinkUser($id);
        $this->logActivity('update', 'member', $id, 'User account unlinked.');
        Auth::flash('success', 'User account unlinked.');
        $this->redirect('/admin/membership?member=' . $id);
    }

    private function memberPayload(): array
    {
        return [
            'user_id'           => isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (string)$_POST['user_id'] : '',
            'membership_number' => $this->input('membership_number', ''),
            'forenames'         => $this->input('forenames', ''),
            'surnames'          => $this->input('surnames', ''),
            'email'             => $this->input('email', ''),
            'phone'             => $this->input('phone', ''),
            'organisation'      => $this->input('organisation', ''),
            'status'            => $this->input('status', 'applicant'),
            'plan_id'           => $this->input('plan_id', ''),
            'joined_at'         => $this->input('joined_at', ''),
            'lapsed_at'         => $this->input('lapsed_at', ''),
            'notes'             => $this->input('notes', ''),
        ];
    }

    private function validateMemberPayload(array $data, ?int $memberId): array
    {
        $errors = [];

        if ($data['forenames'] === '') {
            $errors['forenames'] = 'Forenames are required.';
        }
        if ($data['surnames'] === '') {
            $errors['surnames'] = 'Surnames are required.';
        }
        if ($data['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        $allowed = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
        if (!in_array($data['status'], $allowed, true)) {
            $errors['status'] = 'Invalid status selected.';
        }

        $existing = $this->membership->findByEmail(strtolower($data['email']));
        if ($existing && (int) $existing['id'] !== (int) $memberId) {
            $errors['email'] = 'A member with this email already exists.';
        }

        return $errors;
    }

    private function planPayload(): array
    {
        return [
            'slug'           => $this->sanitiseSlug((string) $this->input('slug', '')),
            'name'           => $this->input('name', ''),
            'description'    => $this->input('description', ''),
            'billing_period' => $this->input('billing_period', 'annual'),
            'price'          => (float) $this->input('price', 0),
            'currency'       => strtoupper((string) $this->input('currency', 'EUR')),
            'is_active'      => $this->input('is_active') ? 1 : 0,
        ];
    }

    private function validatePlanPayload(array $data, ?int $planId): array
    {
        $errors = [];

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        }
        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($data['price'] < 0) {
            $errors['price'] = 'Price cannot be negative.';
        }

        $allowedPeriods = ['annual', 'monthly', 'quarterly', 'lifetime', 'custom'];
        if (!in_array($data['billing_period'], $allowedPeriods, true)) {
            $errors['billing_period'] = 'Invalid billing period selected.';
        }

        $allowedCurrency = preg_match('/^[A-Z]{3}$/', $data['currency']) === 1;
        if (!$allowedCurrency) {
            $errors['currency'] = 'Currency must be a 3-letter code (e.g. EUR).';
        }

        $sql = 'SELECT id FROM membership_plans WHERE slug = ?';
        $params = [$data['slug']];
        if ($planId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $planId;
        }
        $existing = $this->db->fetch($sql, $params);
        if ($existing) {
            $errors['slug'] = 'A plan with this slug already exists.';
        }

        return $errors;
    }
}
