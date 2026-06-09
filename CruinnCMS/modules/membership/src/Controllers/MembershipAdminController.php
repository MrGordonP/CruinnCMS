<?php

namespace Cruinn\Module\Membership\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
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

    public function hub(): void
    {
        Auth::requireAdmin();

        $members = $this->membership->countByStatus();

        $plansCount = 0;
        $subscriptionsCount = 0;
        $paymentsCount = 0;
        $formsCount = 0;
        $responsesCount = 0;
        $pendingResponsesCount = 0;
        $latestForm = null;

        try {
            $plansCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM membership_plans');
        } catch (\Throwable) {
            $plansCount = 0;
        }

        try {
            $subscriptionsCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM membership_subscriptions');
        } catch (\Throwable) {
            $subscriptionsCount = 0;
        }

        try {
            $paymentsCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM payments WHERE subscription_id IS NOT NULL');
        } catch (\Throwable) {
            $paymentsCount = 0;
        }

        try {
            $formsCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM forms');
            $responsesCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM form_submissions');
            $pendingResponsesCount = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM form_submissions WHERE status = 'pending'");
            $latestForm = $this->db->fetch('SELECT id, title FROM forms ORDER BY updated_at DESC, id DESC LIMIT 1') ?: null;
        } catch (\Throwable) {
            $formsCount = 0;
            $responsesCount = 0;
            $pendingResponsesCount = 0;
            $latestForm = null;
        }

        $this->renderAdmin('admin/membership/hub', [
            'title'                 => 'Membership Hub',
            'members'               => $members,
            'plansCount'            => $plansCount,
            'subscriptionsCount'    => $subscriptionsCount,
            'paymentsCount'         => $paymentsCount,
            'formsCount'            => $formsCount,
            'responsesCount'        => $responsesCount,
            'pendingResponsesCount' => $pendingResponsesCount,
            'latestForm'            => $latestForm,
            'breadcrumbs'           => [['Admin', '/admin'], ['Membership']],
        ]);
    }

    public function indexMembers(): void
    {
        Auth::requireAdmin();

        $allowedStatuses = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
        $allowedSorts    = ['surnames', 'forenames', 'email', 'membership_number', 'organisation', 'status', 'plan_name', 'verification_status'];

        $filters = [
            'q'             => trim((string) $this->query('q', '')),
            'status_filter' => in_array($this->query('status_filter', ''), $allowedStatuses, true) ? $this->query('status_filter', '') : '',
            'org_filter'    => trim((string) $this->query('org_filter', '')),
            'sort'          => in_array($this->query('sort', ''), $allowedSorts, true) ? $this->query('sort', '') : '',
            'dir'           => strtolower($this->query('dir', '')) === 'desc' ? 'desc' : 'asc',
        ];

        $category = (string) $this->query('category', 'all');
        $allowedCategories = ['all', 'group', 'plan'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'all';
        }
        $categoryId = (int) $this->query('category_id', 0);

        $memberId      = (int) $this->query('member', 0);
        $plans         = $this->membership->allPlans(true);
        $memberTree    = $this->buildPlanTree($plans);
        $members       = $this->membersForCategory($filters, $category, $categoryId);
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

        // Bulk fetch subscriptions for all listed members (powers expandable rows)
        $subsByMember = [];
        if (!empty($members)) {
            $memberIds = array_map(static fn(array $m): int => (int) $m['id'], $members);
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $bulkSubs = $this->db->fetchAll(
                "SELECT s.id, s.member_id, s.period_start, s.period_end,
                        s.amount, s.currency, s.verification_status, p.name AS plan_name
                 FROM membership_subscriptions s
                 LEFT JOIN membership_plans p ON p.id = s.plan_id
                 WHERE s.member_id IN ($placeholders)
                 ORDER BY s.period_start DESC",
                $memberIds
            );
            foreach ($bulkSubs as $bs) {
                $subsByMember[(int) $bs['member_id']][] = $bs;
            }
        }

        // Distinct organisations for sidebar filter
        $distinctOrgs = array_values(array_filter(array_column(
            $this->db->fetchAll('SELECT DISTINCT organisation FROM members WHERE organisation IS NOT NULL AND organisation != \'\'  ORDER BY organisation ASC'),
            'organisation'
        )));

        $this->renderAdmin('admin/membership/members/index', [
            'title'         => 'Membership',
            'members'       => $members,
            'plans'         => $plans,
            'memberTree'    => $memberTree,
            'category'      => $category,
            'categoryId'    => $categoryId,
            'filters'       => $filters,
            'allowedStatuses' => $allowedStatuses,
            'distinctOrgs'  => $distinctOrgs,
            'statusCount'   => $this->membership->countByStatus(),
            'member'        => $member,
            'memberId'      => $memberId,
            'subscriptions' => $subscriptions,
            'payments'      => $payments,
            'linkedUser'    => $linkedUser,
            'subsByMember'  => $subsByMember,
            'errors'        => [],
            'breadcrumbs'   => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members']],
        ]);
    }

    public function showMember(int $id): void
    {
        Auth::requireAdmin();

        $member = $this->membership->findById($id);
        if (!$member) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $subscriptions = $this->membership->subscriptionsForMember($id);
        $payments      = $this->membership->paymentsForMember($id);
        $plans         = $this->membership->allPlans(true);

        $linkedUser = null;
        if (!empty($member['user_id'])) {
            $linkedUser = $this->db->fetch(
                'SELECT id, display_name, email FROM users WHERE id = ?',
                [(int) $member['user_id']]
            );
        }

        $address = $this->db->fetch(
            'SELECT * FROM member_addresses WHERE member_id = ?',
            [$id]
        );

        $this->renderAdmin('admin/membership/members/show', [
            'title'         => trim((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? '')),
            'member'        => $member,
            'subscriptions' => $subscriptions,
            'payments'      => $payments,
            'plans'         => $plans,
            'linkedUser'    => $linkedUser,
            'address'       => $address,
            'breadcrumbs'   => [
                ['Admin', '/admin'],
                ['Membership', '/admin/membership'],
                ['Members', '/admin/membership/members'],
                [trim((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? ''))],
            ],
        ]);
    }

    public function newMember(): void
    {
        Auth::requireAdmin();

        $this->renderAdmin('admin/membership/members/form', [
            'title'       => 'New Member',
            'member'      => null,
            'plans'       => $this->membership->allPlans(true),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members', '/admin/membership/members'], ['New Member']],
        ]);
    }

    public function createMember(): void
    {
        Auth::requireAdmin();

        $data = $this->memberPayload();
        $errors = $this->validateMemberPayload($data, null);

        if ($errors) {
            $this->renderAdmin('admin/membership/members/form', [
                'title'       => 'New Member',
                'member'      => $data,
                'plans'       => $this->membership->allPlans(true),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members', '/admin/membership/members'], ['New Member']],
            ]);
            return;
        }

        $memberId = $this->membership->createMember($data);
        $this->logActivity('create', 'member', $memberId, 'Membership record created.');
        Auth::flash('success', 'Member created.');
        $this->redirect('/admin/membership/members?member=' . $memberId);
    }

    public function editMember(int $id): void
    {
        Auth::requireAdmin();
        $this->redirect('/admin/membership/members?member=' . $id);
    }

    public function updateMember(int $id): void
    {
        Auth::requireAdmin();

        $existing = $this->membership->findById($id);
        if (!$existing) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = $this->memberPayload();
        $errors = $this->validateMemberPayload($data, $id);

        if ($errors) {
            $filters = ['q' => ''];
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
                'breadcrumbs'   => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members']],
            ]);
            return;
        }

        $this->membership->updateMember($id, $data);
        $this->logActivity('update', 'member', $id, 'Membership record updated.');
        Auth::flash('success', 'Member updated.');
        $this->redirect('/admin/membership/members?member=' . $id);
    }

    public function createSubscription(int $id): void
    {
        Auth::requireAdmin();

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership/members');
        }

        $data = [
            'plan_id'             => $this->input('plan_id', ''),
            'period_start'        => $this->input('period_start', ''),
            'period_end'          => $this->input('period_end', ''),
            'member_type'         => $this->input('member_type', 'new'),
            'geologist_level'     => $this->input('geologist_level', ''),
            'institution'         => $this->input('institution', ''),
            'position'            => $this->input('position', ''),
            'student_level'       => $this->input('student_level', ''),
            'amount'              => (float) $this->input('amount', 0),
            'currency'            => strtoupper((string) $this->input('currency', 'EUR')),
            'payment_method'      => $this->input('payment_method', 'bank_transfer'),
            'transaction_id'      => $this->input('transaction_id', ''),
            'verification_status' => $this->input('verification_status', 'unverified'),
            'notes'               => $this->input('notes', ''),
        ];

        if ($data['period_start'] === '' || $data['period_end'] === '') {
            Auth::flash('error', 'Period start and end dates are required to create a subscription.');
            $this->redirect('/admin/membership/members?member=' . $id);
        }

        $subId = $this->membership->createSubscription($id, $data);
        $this->logActivity('create', 'membership_subscription', $subId, 'Subscription created for member #' . $id . '.');
        Auth::flash('success', 'Subscription created.');
        $this->redirect('/admin/membership/members?member=' . $id);
    }

    public function updateSubscriptionStatus(int $id): void
    {
        Auth::requireAdmin();

        $status = (string) $this->input('status', 'unverified');
        $allowed = ['unverified', 'verified', 'disputed', 'waived'];
        if (!in_array($status, $allowed, true)) {
            Auth::flash('error', 'Invalid verification status.');
            $this->redirect('/admin/membership/members');
        }

        $this->membership->updateVerificationStatus($id, $status);
        $this->logActivity('update', 'membership_subscription', $id, 'Subscription verification status set to ' . $status . '.');
        Auth::flash('success', 'Subscription status updated.');

        $memberId = (int) $this->input('member_id', 0);
        if ($memberId > 0) {
            $this->redirect('/admin/membership/members?member=' . $memberId);
        }

        $this->redirect('/admin/membership/members');
    }

    public function recordPayment(int $id): void
    {
        Auth::requireAdmin();

        $data = [
            'transaction_id' => $this->input('transaction_id', ''),
            'gateway'        => $this->input('gateway', ''),
            'amount'         => (float) $this->input('amount', 0),
            'currency'       => strtoupper((string) $this->input('currency', 'EUR')),
            'paid_at'        => (string) $this->input('paid_at', date('Y-m-d H:i:s')),
            'notes'          => $this->input('notes', ''),
        ];

        if ($data['amount'] <= 0) {
            Auth::flash('error', 'Payment amount must be greater than zero.');
            $this->redirect('/admin/membership/members');
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
            $this->redirect('/admin/membership/members?member=' . $memberId);
        }

        $this->redirect('/admin/membership/members');
    }

    public function importForm(): void
    {
        Auth::requireAdmin();

        $this->renderAdmin('admin/membership/members/import', [
            'title'       => 'Import Members',
            'plans'       => $this->membership->allPlans(true),
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members', '/admin/membership/members'], ['Import']],
        ]);
    }

    /**
     * Step 1: Validate upload, store temp file, parse headers, render mapping UI.
     */
    public function processImport(): void
    {
        Auth::requireAdmin();

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
            'breadcrumbs'    => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members', '/admin/membership/members'], ['Import', '/admin/membership/import'], ['Map Columns']],
        ]);
    }

    /**
     * Step 2: Apply column mapping, run import, show result.
     */
    public function confirmImport(): void
    {
        Auth::requireAdmin();

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
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Members', '/admin/membership/members'], ['Import']],
        ]);
    }

    public function listPlans(): void
    {
        Auth::requireAdmin();

        $plans = $this->membership->allPlans();
        $groupPlans = array_values(array_filter(
            $plans,
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));

        $selectedPlanId = (int) $this->query('plan', 0);
        if ($selectedPlanId <= 0 && !empty($plans)) {
            $selectedPlanId = (int) $plans[0]['id'];
        }

        $selectedPlan = null;
        foreach ($plans as $plan) {
            if ((int) $plan['id'] === $selectedPlanId) {
                $selectedPlan = $plan;
                break;
            }
        }

        $subCountByPlan = [];
        foreach ($this->db->fetchAll('SELECT plan_id, COUNT(*) AS c FROM membership_subscriptions WHERE plan_id IS NOT NULL GROUP BY plan_id') as $row) {
            $subCountByPlan[(int) $row['plan_id']] = (int) $row['c'];
        }

        $selectedSubCount = $selectedPlan ? ($subCountByPlan[(int) $selectedPlan['id']] ?? 0) : 0;

        // Inline form state
        $inlineMode   = null;
        $inlinePlan   = null;
        $inlineErrors = [];
        $inlineAction = (string) $this->query('action', '');
        $inlineEditId = (int) $this->query('edit', 0);
        $subjects     = $this->activeSubjects();
        $inlineGroupPlans = $groupPlans;

        if ($inlineEditId > 0) {
            $found = $this->membership->findPlan($inlineEditId);
            if ($found) {
                $inlineMode = $this->isStructuralGroupPlan($found) ? 'group' : 'tier';
                $inlinePlan = $found;
                $inlineGroupPlans = array_values(array_filter(
                    $groupPlans,
                    static fn(array $p): bool => (int) ($p['id'] ?? 0) !== $inlineEditId
                ));
            }
        } elseif ($inlineAction === 'new-group') {
            $inlineMode = 'group';
            $inlinePlan = [
                'is_plan_group' => 1, 'is_group' => 1, 'is_active' => 1,
                'max_members' => 2, 'parent_plan_id' => 0,
                'billing_period' => 'annual', 'currency' => 'EUR',
            ];
        } elseif ($inlineAction === 'new-tier') {
            $prefillParent = (int) $this->query('parent_id', 0);
            $prefillBilling = 'annual';
            if ($prefillParent > 0) {
                $parent = $this->db->fetch('SELECT billing_period FROM membership_plans WHERE id = ?', [$prefillParent]);
                if ($parent && !empty($parent['billing_period'])) {
                    $prefillBilling = (string) $parent['billing_period'];
                }
            }
            $inlineMode = 'tier';
            $inlinePlan = [
                'is_plan_group' => 0, 'is_group' => 0, 'is_active' => 1,
                'max_members' => 0, 'parent_plan_id' => $prefillParent,
                'billing_period' => $prefillBilling, 'currency' => 'EUR',
            ];
        }

        $this->renderAdmin('admin/membership/plans/index', [
            'title'            => 'Membership Plans',
            'plans'            => $plans,
            'groupPlans'       => $groupPlans,
            'selectedPlanId'   => $selectedPlanId,
            'selectedPlan'     => $selectedPlan,
            'subCountByPlan'   => $subCountByPlan,
            'selectedSubCount' => $selectedSubCount,
            'inlineMode'       => $inlineMode,
            'inlinePlan'       => $inlinePlan,
            'inlineErrors'     => $inlineErrors,
            'inlineGroupPlans' => $inlineGroupPlans,
            'inlineSubjects'   => $subjects,
            'breadcrumbs'      => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans']],
        ]);
    }

    public function newPlan(): void
    {
        $this->newGroup();
    }

    public function newGroup(): void
    {
        Auth::requireAdmin();

        $groupPlans = array_values(array_filter(
            $this->membership->allPlans(),
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $subjects = $this->activeSubjects();

        $this->renderAdmin('admin/membership/plans/form', [
            'title'       => 'New Membership Group',
            'plan'        => [
                'is_plan_group' => 1,
                'is_group' => 1,
                'is_active' => 1,
                'max_members' => 2,
                'parent_plan_id' => 0,
                'billing_period' => 'annual',
                'currency' => 'EUR',
            ],
            'mode'        => 'group',
            'groupPlans'  => $groupPlans,
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['New Group']],
        ]);
    }

    public function newTier(): void
    {
        Auth::requireAdmin();

        $groupPlans = array_values(array_filter(
            $this->membership->allPlans(),
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $subjects = $this->activeSubjects();
        $prefillParent = (int) $this->query('parent_id', 0);
        $prefillBillingPeriod = 'annual';
        if ($prefillParent > 0) {
            $parent = $this->db->fetch('SELECT billing_period FROM membership_plans WHERE id = ?', [$prefillParent]);
            if ($parent && !empty($parent['billing_period'])) {
                $prefillBillingPeriod = (string) $parent['billing_period'];
            }
        }

        $this->renderAdmin('admin/membership/plans/form', [
            'title'       => 'New Membership Tier',
            'plan'        => [
                'is_plan_group' => 0,
                'is_group' => 0,
                'is_active' => 1,
                'max_members' => 0,
                'parent_plan_id' => $prefillParent,
                'billing_period' => $prefillBillingPeriod,
                'currency' => 'EUR',
            ],
            'mode'        => 'tier',
            'groupPlans'  => $groupPlans,
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['New Tier']],
        ]);
    }

    public function createPlan(): void
    {
        Auth::requireAdmin();

        try {
            $data = $this->planPayload();
            $errors = $this->validatePlanPayload($data, null);
        } catch (\Throwable $e) {
            $data = $this->planPayloadFromPost();
            $errors = ['general' => 'Failed to process plan submission: ' . $e->getMessage()];
        }
        $groupPlans = array_values(array_filter(
            $this->membership->allPlans(),
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $subjects = $this->activeSubjects();

        if ($errors) {
            $this->renderInlinePlansIndex($data, !empty($data['is_plan_group']) ? 'group' : 'tier', null, $groupPlans, $subjects, $errors);
            return;
        }

        try {
            $planId = $this->membership->createPlan($data);
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to save plan: ' . $e->getMessage();
            $this->renderInlinePlansIndex($data, !empty($data['is_plan_group']) ? 'group' : 'tier', null, $groupPlans, $subjects, $errors);
            return;
        }
        $this->logActivity('create', 'membership_plan', $planId, 'Membership plan created.');
        Auth::flash('success', 'Plan created.');
        $this->redirect('/admin/membership/plans?plan=' . $planId);
    }

    private function planPayloadFromPost(): array
    {
        $mode = strtolower((string) ($_POST['mode'] ?? ''));
        $isPlanGroup = !empty($_POST['is_plan_group']) ? 1 : 0;
        if ($mode === 'group') {
            $isPlanGroup = 1;
        } elseif ($mode === 'tier') {
            $isPlanGroup = 0;
        }

        return [
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'billing_period' => trim((string) ($_POST['billing_period'] ?? 'annual')),
            'price' => isset($_POST['price']) ? (float) $_POST['price'] : 0.0,
            'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'EUR'))),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'is_group' => !empty($_POST['is_group']) ? 1 : 0,
            'is_plan_group' => $isPlanGroup,
            'max_members' => isset($_POST['max_members']) ? (int) $_POST['max_members'] : 0,
            'parent_plan_id' => isset($_POST['parent_plan_id']) ? (int) $_POST['parent_plan_id'] : 0,
            'subject_id' => isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0,
            'promo_type' => trim((string) ($_POST['promo_type'] ?? '')),
            'promo_value' => trim((string) ($_POST['promo_value'] ?? '')),
            'promo_starts_at' => trim((string) ($_POST['promo_starts_at'] ?? '')),
            'promo_ends_at' => trim((string) ($_POST['promo_ends_at'] ?? '')),
        ];
    }

    public function editPlan(int $id): void
    {
        Auth::requireAdmin();

        $plan = $this->membership->findPlan($id);
        if (!$plan) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $groupPlans = array_values(array_filter(
            $this->membership->allPlans(),
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $groupPlans = array_values(array_filter(
            $groupPlans,
            static fn(array $p): bool => (int) ($p['id'] ?? 0) !== $id
        ));
        $subjects = $this->activeSubjects();

        $this->renderAdmin('admin/membership/plans/form', [
            'title'       => 'Edit Membership Plan',
            'plan'        => $plan,
            'groupPlans'  => $groupPlans,
            'subjects'    => $subjects,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans', '/admin/membership/plans'], ['Edit Plan']],
        ]);
    }

    public function updatePlan(int $id): void
    {
        Auth::requireAdmin();

        $plan = $this->membership->findPlan($id);
        if (!$plan) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $data = $this->planPayload();
        $errors = $this->validatePlanPayload($data, $id);
        $groupPlans = array_values(array_filter(
            $this->membership->allPlans(),
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $groupPlans = array_values(array_filter(
            $groupPlans,
            static fn(array $p): bool => (int) ($p['id'] ?? 0) !== $id
        ));
        $subjects = $this->activeSubjects();

        if ($errors) {
            $this->renderInlinePlansIndex(array_merge($plan, $data), $this->isStructuralGroupPlan($plan) ? 'group' : 'tier', $id, $groupPlans, $subjects, $errors);
            return;
        }

        try {
            $this->membership->updatePlan($id, $data);
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update plan: ' . $e->getMessage();
            $this->renderInlinePlansIndex(array_merge($plan, $data), $this->isStructuralGroupPlan($plan) ? 'group' : 'tier', $id, $groupPlans, $subjects, $errors);
            return;
        }
        $this->logActivity('update', 'membership_plan', $id, 'Membership plan updated.');
        Auth::flash('success', 'Plan updated.');
        $this->redirect('/admin/membership/plans?plan=' . $id);
    }

    private function renderInlinePlansIndex(array $inlinePlan, string $inlineMode, ?int $inlineEditId, array $inlineGroupPlans, array $inlineSubjects, array $inlineErrors): void
    {
        $plans = $this->membership->allPlans();
        $groupPlans = array_values(array_filter(
            $plans,
            fn(array $p): bool => $this->isStructuralGroupPlan($p)
        ));
        $selectedPlanId = $inlineEditId ?? (int) ($inlinePlan['id'] ?? 0);
        if ($selectedPlanId <= 0 && !empty($plans)) {
            $selectedPlanId = (int) $plans[0]['id'];
        }
        $selectedPlan = null;
        foreach ($plans as $p) {
            if ((int) $p['id'] === $selectedPlanId) { $selectedPlan = $p; break; }
        }
        $subCountByPlan = [];
        foreach ($this->db->fetchAll('SELECT plan_id, COUNT(*) AS c FROM membership_subscriptions WHERE plan_id IS NOT NULL GROUP BY plan_id') as $row) {
            $subCountByPlan[(int) $row['plan_id']] = (int) $row['c'];
        }
        $this->renderAdmin('admin/membership/plans/index', [
            'title'            => 'Membership Plans',
            'plans'            => $plans,
            'groupPlans'       => $groupPlans,
            'selectedPlanId'   => $selectedPlanId,
            'selectedPlan'     => $selectedPlan,
            'subCountByPlan'   => $subCountByPlan,
            'selectedSubCount' => $selectedPlan ? ($subCountByPlan[(int) $selectedPlan['id']] ?? 0) : 0,
            'inlineMode'       => $inlineMode,
            'inlinePlan'       => $inlinePlan,
            'inlineErrors'     => $inlineErrors,
            'inlineGroupPlans' => $inlineGroupPlans,
            'inlineSubjects'   => $inlineSubjects,
            'breadcrumbs'      => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Plans']],
        ]);
    }

    public function bulkPlans(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $action  = (string) ($_POST['bulk_action'] ?? '');
        $rawIds  = $_POST['plan_ids'] ?? [];
        $planIds = array_map('intval', is_array($rawIds) ? $rawIds : []);
        $planIds = array_filter($planIds, static fn(int $id): bool => $id > 0);

        if (empty($planIds) || !in_array($action, ['set_active', 'set_inactive', 'delete'], true)) {
            Auth::flash('error', 'No plans selected or invalid action.');
            $this->redirect('/admin/membership/plans');
            return;
        }

        $affected = 0;
        if ($action === 'set_active' || $action === 'set_inactive') {
            $isActive = $action === 'set_active' ? 1 : 0;
            foreach ($planIds as $id) {
                $this->db->execute('UPDATE membership_plans SET is_active = ? WHERE id = ?', [$isActive, $id]);
                $affected++;
            }
            $label = $isActive ? 'activated' : 'deactivated';
            Auth::flash('success', $affected . ' plan(s) ' . $label . '.');
        } elseif ($action === 'delete') {
            foreach ($planIds as $id) {
                $inUse = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM membership_subscriptions WHERE plan_id = ?', [$id])['c'] ?? 0);
                if ($inUse > 0) {
                    Auth::flash('error', 'Plan #' . $id . ' has active subscriptions and cannot be deleted.');
                    $this->redirect('/admin/membership/plans');
                    return;
                }
            }
            foreach ($planIds as $id) {
                $this->db->execute('DELETE FROM membership_plans WHERE id = ?', [$id]);
                $affected++;
            }
            Auth::flash('success', $affected . ' plan(s) deleted.');
        }

        $this->redirect('/admin/membership/plans');
    }

    public function indexSubscriptions(): void
    {
        Auth::requireAdmin();

        $yearFilter   = (string) $this->query('year', '');
        $planFilter   = (int) $this->query('plan', 0);
        $statusFilter = (string) $this->query('status', '');
        $memberSearch = trim((string) $this->query('q', ''));
        $sort         = (string) $this->query('sort', 'period_start');
        $dir          = strtoupper((string) $this->query('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $selectedId   = (int) $this->query('sub', 0);

        $allowedSorts = [
            'period_start' => 's.period_start',
            'member'       => 'm.surnames',
            'plan'         => 'p.name',
            'amount'       => 's.amount',
            'verification' => 's.verification_status',
        ];
        $orderBy = isset($allowedSorts[$sort]) ? $allowedSorts[$sort] : 's.period_start';

        $where = ['1=1'];
        $params = [];

        if ($yearFilter !== '') {
            $where[] = 'YEAR(s.period_start) = ?';
            $params[] = (int) $yearFilter;
        }
        if ($planFilter > 0) {
            $where[] = 's.plan_id = ?';
            $params[] = $planFilter;
        }
        $allowedStatuses = ['unverified', 'verified', 'disputed', 'waived'];
        if (in_array($statusFilter, $allowedStatuses, true)) {
            $where[] = 's.verification_status = ?';
            $params[] = $statusFilter;
        }
        if ($memberSearch !== '') {
            $where[] = '(m.forenames LIKE ? OR m.surnames LIKE ? OR m.email LIKE ? OR m.membership_number LIKE ?)';
            $like = '%' . $memberSearch . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereClause = implode(' AND ', $where);
        $subscriptions = $this->db->fetchAll(
            "SELECT s.id, s.member_id, s.plan_id, s.period_start, s.period_end,
                    s.amount, s.currency, s.payment_method, s.transaction_id,
                    s.payment_id, s.verification_status, s.verified_at, s.notes,
                    m.forenames, m.surnames, m.membership_number,
                    p.name AS plan_name,
                    py.transaction_id AS payment_transaction_id,
                    py.amount AS payment_amount, py.paid_at AS payment_paid_at,
                    py.gateway AS payment_gateway
             FROM membership_subscriptions s
             LEFT JOIN members m ON m.id = s.member_id
             LEFT JOIN membership_plans p ON p.id = s.plan_id
             LEFT JOIN payments py ON py.id = s.payment_id
             WHERE $whereClause
             ORDER BY $orderBy $dir, s.id DESC
             LIMIT 500",
            $params
        );

        $selectedSub = null;
        if ($selectedId > 0) {
            foreach ($subscriptions as $sub) {
                if ((int) $sub['id'] === $selectedId) { $selectedSub = $sub; break; }
            }
            if (!$selectedSub) {
                $selectedSub = $this->db->fetch(
                    "SELECT s.id, s.member_id, s.plan_id, s.period_start, s.period_end,
                            s.amount, s.currency, s.payment_method, s.transaction_id,
                            s.payment_id, s.verification_status, s.verified_at, s.notes,
                            m.forenames, m.surnames, m.membership_number,
                            p.name AS plan_name,
                            py.transaction_id AS payment_transaction_id,
                            py.amount AS payment_amount, py.paid_at AS payment_paid_at,
                            py.gateway AS payment_gateway
                     FROM membership_subscriptions s
                     LEFT JOIN members m ON m.id = s.member_id
                     LEFT JOIN membership_plans p ON p.id = s.plan_id
                     LEFT JOIN payments py ON py.id = s.payment_id
                     WHERE s.id = ?",
                    [$selectedId]
                );
            }
        }

        // Unmatched payments for the link-payment panel
        $unmatchedPayments = [];
        if ($selectedSub) {
            $unmatchedPayments = $this->db->fetchAll(
                "SELECT id, transaction_id, amount, currency, gateway, paid_at
                 FROM payments
                 WHERE subscription_id IS NULL OR subscription_id = ?
                 ORDER BY paid_at DESC
                 LIMIT 50",
                [(int) $selectedSub['id']]
            );
        }

        // Filter options
        $availableYears = $this->db->fetchAll(
            'SELECT DISTINCT YEAR(period_start) AS y FROM membership_subscriptions ORDER BY y DESC'
        );
        $plans = $this->membership->allPlans();

        $this->renderAdmin('admin/membership/subscriptions/index', [
            'title'             => 'Subscriptions',
            'subscriptions'     => $subscriptions,
            'selectedId'        => $selectedId,
            'selectedSub'       => $selectedSub,
            'unmatchedPayments' => $unmatchedPayments,
            'availableYears'    => array_column($availableYears, 'y'),
            'plans'             => $plans,
            'filters'           => compact('yearFilter', 'planFilter', 'statusFilter', 'memberSearch', 'sort', 'dir'),
            'allowedStatuses'   => $allowedStatuses,
            'breadcrumbs'       => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Subscriptions']],
        ]);
    }

    public function linkPayment(int $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $paymentId = (int) $this->input('payment_id', 0);
        $sub = $this->db->fetch('SELECT id, member_id FROM membership_subscriptions WHERE id = ?', [$id]);
        if (!$sub) {
            Auth::flash('error', 'Subscription not found.');
            $this->redirect('/admin/membership/subscriptions');
            return;
        }

        if ($paymentId > 0) {
            $payment = $this->db->fetch('SELECT id FROM payments WHERE id = ?', [$paymentId]);
            if (!$payment) {
                Auth::flash('error', 'Payment not found.');
                $this->redirect('/admin/membership/subscriptions?sub=' . $id);
                return;
            }
            $this->db->update('membership_subscriptions', ['payment_id' => $paymentId], 'id = ?', [$id]);
            $this->db->update('payments', ['subscription_id' => $id], 'id = ?', [$paymentId]);
            $this->logActivity('update', 'membership_subscription', $id, 'Payment #' . $paymentId . ' linked to subscription.');
            Auth::flash('success', 'Payment linked.');
        } else {
            $this->db->update('membership_subscriptions', ['payment_id' => null], 'id = ?', [$id]);
            $this->logActivity('update', 'membership_subscription', $id, 'Payment unlinked from subscription.');
            Auth::flash('success', 'Payment unlinked.');
        }

        $this->redirect('/admin/membership/subscriptions?sub=' . $id);
    }

    public function verifySubscription(int $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $status = (string) $this->input('verification_status', 'verified');
        $allowed = ['unverified', 'verified', 'disputed', 'waived'];
        if (!in_array($status, $allowed, true)) {
            Auth::flash('error', 'Invalid verification status.');
            $this->redirect('/admin/membership/subscriptions');
            return;
        }

        $sub = $this->db->fetch('SELECT id FROM membership_subscriptions WHERE id = ?', [$id]);
        if (!$sub) {
            Auth::flash('error', 'Subscription not found.');
            $this->redirect('/admin/membership/subscriptions');
            return;
        }

        $payload = ['verification_status' => $status];
        if ($status === 'verified') {
            $userId = Auth::userId();
            $payload['verified_by'] = $userId > 0 ? $userId : null;
            $payload['verified_at'] = date('Y-m-d H:i:s');
        }
        $this->db->update('membership_subscriptions', $payload, 'id = ?', [$id]);
        $this->logActivity('update', 'membership_subscription', $id, 'Subscription verification status set to ' . $status . '.');
        Auth::flash('success', 'Subscription marked as ' . $status . '.');
        $this->redirect('/admin/membership/subscriptions?sub=' . $id);
    }

    public function formsWorkspace(): void
    {
        Auth::requireAdmin();

        $subjectIds = $this->membershipAssociatedSubjectIds();
        $forms = [];
        if (!empty($subjectIds)) {
            $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
            $params = array_merge($subjectIds, $subjectIds);
            $forms = $this->db->fetchAll(
                "SELECT f.id, f.title, f.slug, f.status, f.subject_id, s.title AS subject_title,
                        COUNT(fs.id) AS submission_count,
                        SUM(CASE WHEN fs.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
                 FROM forms f
                 LEFT JOIN subjects s ON s.id = f.subject_id
                 LEFT JOIN form_submissions fs ON fs.form_id = f.id
                 WHERE f.subject_id IN ($placeholders)
                    OR EXISTS (
                        SELECT 1
                        FROM subject_content sc
                        WHERE sc.item_type = 'form'
                          AND sc.item_id = f.id
                          AND sc.subject_id IN ($placeholders)
                    )
                 GROUP BY f.id
                 ORDER BY f.title ASC",
                $params
            );
        }

        $selectedFormId = (int) $this->query('form', 0);
        if ($selectedFormId <= 0 && !empty($forms)) {
            $selectedFormId = (int) $forms[0]['id'];
        }

        $selectedForm = null;
        foreach ($forms as $form) {
            if ((int) $form['id'] === $selectedFormId) {
                $selectedForm = $form;
                break;
            }
        }

        $statusFilter = (string) $this->query('status', '');
        $search = trim((string) $this->query('search', ''));
        $submissions = [];
        if ($selectedFormId > 0) {
            $where = ['fs.form_id = ?'];
            $params = [$selectedFormId];
            if ($statusFilter !== '') {
                $where[] = 'fs.status = ?';
                $params[] = $statusFilter;
            }
            if ($search !== '') {
                $where[] = 'fs.data LIKE ?';
                $params[] = '%' . $search . '%';
            }
            $submissions = $this->db->fetchAll(
                'SELECT fs.id, fs.form_id, fs.user_id, fs.status, fs.submitted_at, fs.data,
                        u.display_name AS user_name, u.email AS user_email
                 FROM form_submissions fs
                 LEFT JOIN users u ON u.id = fs.user_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY fs.submitted_at DESC
                 LIMIT 200',
                $params
            );
            foreach ($submissions as &$submission) {
                $submission['data'] = json_decode((string) $submission['data'], true) ?: [];
            }
            unset($submission);
        }

        $selectedSubmissionId = (int) $this->query('submission', 0);
        if ($selectedSubmissionId <= 0 && !empty($submissions)) {
            $selectedSubmissionId = (int) $submissions[0]['id'];
        }

        $selectedSubmission = null;
        foreach ($submissions as $submission) {
            if ((int) $submission['id'] === $selectedSubmissionId) {
                $selectedSubmission = $submission;
                break;
            }
        }

        $this->renderAdmin('admin/membership/forms/index', [
            'title' => 'Membership Forms and Responses',
            'forms' => $forms,
            'subjectIds' => $subjectIds,
            'selectedFormId' => $selectedFormId,
            'selectedForm' => $selectedForm,
            'submissions' => $submissions,
            'selectedSubmissionId' => $selectedSubmissionId,
            'selectedSubmission' => $selectedSubmission,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Forms and Responses']],
        ]);
    }

    public function searchMembers(): void
    {
        Auth::requireAdmin();
        $q = trim((string) $this->query('q', ''));
        if (strlen($q) < 2) {
            $this->json([]);
        }
        $like = '%' . $q . '%';
        $rows = $this->db->fetchAll(
            'SELECT id, forenames, surnames, email, membership_number
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
        ], $rows)));
    }

    public function linkUser(int $id): void
    {
        Auth::requireAdmin();

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership/members');
            return;
        }

        $q = trim((string) $this->input('user_search', ''));
        if ($q === '') {
            Auth::flash('error', 'Enter a user email or display name.');
            $this->redirect('/admin/membership/members?member=' . $id);
            return;
        }

        $user = $this->db->fetch(
            'SELECT id, display_name, email FROM users WHERE email = ? OR display_name = ? LIMIT 1',
            [strtolower($q), $q]
        );
        if (!$user) {
            Auth::flash('error', 'No user found matching "' . htmlspecialchars($q, ENT_QUOTES) . '".');
            $this->redirect('/admin/membership/members?member=' . $id);
            return;
        }

        $conflict = $this->db->fetch(
            'SELECT id FROM members WHERE user_id = ? AND id != ?',
            [(int) $user['id'], $id]
        );
        if ($conflict) {
            Auth::flash('error', 'That user is already linked to a different member record.');
            $this->redirect('/admin/membership/members?member=' . $id);
            return;
        }

        $this->membership->linkUser($id, (int) $user['id']);
        $this->logActivity('update', 'member', $id, 'Linked to user #' . $user['id'] . ' (' . $user['email'] . ').');
        Auth::flash('success', 'User account linked to member.');
        $this->redirect('/admin/membership/members?member=' . $id);
    }

    public function unlinkUser(int $id): void
    {
        Auth::requireAdmin();

        $member = $this->membership->findById($id);
        if (!$member) {
            Auth::flash('error', 'Member not found.');
            $this->redirect('/admin/membership/members');
            return;
        }

        $this->membership->unlinkUser($id);
        $this->logActivity('update', 'member', $id, 'User account unlinked.');
        Auth::flash('success', 'User account unlinked.');
        $this->redirect('/admin/membership/members?member=' . $id);
    }

    private function memberPayload(): array
    {
        return [
            'user_id'           => isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (string)$_POST['user_id'] : '',
            'membership_number' => $this->input('membership_number', ''),
            'forenames'         => $this->input('forenames', ''),
            'surnames'          => $this->input('surnames', ''),
            'email'             => $this->input('email', ''),
            'organisation'      => $this->input('organisation', ''),
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

        $existing = $this->membership->findByEmail(strtolower($data['email']));
        if ($existing && (int) $existing['id'] !== (int) $memberId) {
            $errors['email'] = 'A member with this email already exists.';
        }

        return $errors;
    }

    private function planPayload(): array
    {
        $mode = strtolower((string) $this->input('mode', ''));
        $isGroup = $this->input('is_group') ? 1 : 0;
        $isPlanGroup = $this->input('is_plan_group') ? 1 : 0;
        if ($mode === 'group') {
            $isPlanGroup = 1;
        }
        if ($mode === 'tier') {
            $isPlanGroup = 0;
        }

        $billingPeriod = (string) $this->input('billing_period', 'annual');
        $price = (float) $this->input('price', 0);
        $currency = strtoupper((string) $this->input('currency', 'EUR'));
        $parentPlanId = (int) $this->input('parent_plan_id', 0);

        // Groups define billing period, but amount remains on child plans/tiers.
        if ($isPlanGroup === 1) {
            $price = 0.0;
            $currency = 'EUR';
            $parentPlanId = 0;
        }

        $promoType = strtolower((string) $this->input('promo_type', ''));
        $promoValue = $this->input('promo_value', '');
        $promoStartsAt = trim((string) $this->input('promo_starts_at', ''));
        $promoEndsAt = trim((string) $this->input('promo_ends_at', ''));
        if ($isPlanGroup === 1) {
            $promoType = '';
            $promoValue = '';
            $promoStartsAt = '';
            $promoEndsAt = '';
        }

        if ($isPlanGroup === 0 && $parentPlanId > 0) {
            $parent = $this->db->fetch('SELECT billing_period FROM membership_plans WHERE id = ?', [$parentPlanId]);
            if ($parent && !empty($parent['billing_period'])) {
                $billingPeriod = (string) $parent['billing_period'];
            }
        }

        return [
            'slug'           => $this->sanitiseSlug((string) $this->input('slug', '')),
            'name'           => $this->input('name', ''),
            'description'    => $this->input('description', ''),
            'billing_period' => $billingPeriod,
            'price'          => $price,
            'currency'       => $currency,
            'is_active'      => $this->input('is_active') ? 1 : 0,
            'is_group'       => $isGroup,
            'is_plan_group'  => $isPlanGroup,
            'max_members'    => (int) $this->input('max_members', 0),
            'parent_plan_id' => $parentPlanId,
            'subject_id'     => (int) $this->input('subject_id', 0),
            'promo_type'     => $promoType,
            'promo_value'    => $promoValue,
            'promo_starts_at'=> $promoStartsAt,
            'promo_ends_at'  => $promoEndsAt,
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

        if (!empty($data['parent_plan_id']) && !empty($data['is_plan_group'])) {
            $errors['parent_plan_id'] = 'A group plan cannot be assigned to a parent group.';
        }

        if (!empty($data['parent_plan_id']) && $planId !== null && (int) $data['parent_plan_id'] === (int) $planId) {
            $errors['parent_plan_id'] = 'A plan cannot be its own parent.';
        }

        if ((int) ($data['max_members'] ?? 0) < 0) {
            $errors['max_members'] = 'Max members cannot be negative.';
        }

        if (!empty($data['is_group']) && (int) ($data['max_members'] ?? 0) === 1) {
            $errors['max_members'] = 'Use 0 for no limit, or 2+ for a capped group.';
        }

        if (empty($data['is_plan_group']) && (int) $data['price'] <= 0) {
            $errors['price'] = 'Plans/tiers must have a billable amount greater than zero.';
        }

        if ($planId === null && empty($data['is_plan_group']) && empty($data['parent_plan_id'])) {
            $errors['parent_plan_id'] = 'Create a group first, then assign new plans as tiers under a parent group.';
        }

        $allowedPeriods = ['annual', 'monthly', 'quarterly', 'lifetime', 'custom'];
        if (!in_array($data['billing_period'], $allowedPeriods, true)) {
            $errors['billing_period'] = 'Invalid billing period selected.';
        }

        $allowedCurrency = preg_match('/^[A-Z]{3}$/', $data['currency']) === 1;
        if (!$allowedCurrency) {
            $errors['currency'] = 'Currency must be a 3-letter code (e.g. EUR).';
        }

        $promoType = (string) ($data['promo_type'] ?? '');
        $promoValueRaw = $data['promo_value'] ?? '';
        $promoValue = $promoValueRaw === '' ? null : (float) $promoValueRaw;
        if ($promoType !== '' && !in_array($promoType, ['percent', 'fixed'], true)) {
            $errors['promo_type'] = 'Promotion type must be percent or fixed.';
        }
        if ($promoType !== '' && ($promoValue === null || $promoValue <= 0)) {
            $errors['promo_value'] = 'Promotion value must be greater than zero.';
        }
        if ($promoType === 'percent' && $promoValue !== null && $promoValue > 100) {
            $errors['promo_value'] = 'Percent promotion cannot exceed 100.';
        }

        $promoStartsAt = !empty($data['promo_starts_at']) ? strtotime((string) $data['promo_starts_at']) : false;
        $promoEndsAt = !empty($data['promo_ends_at']) ? strtotime((string) $data['promo_ends_at']) : false;
        if (!empty($data['promo_starts_at']) && $promoStartsAt === false) {
            $errors['promo_starts_at'] = 'Promotion start date/time is invalid.';
        }
        if (!empty($data['promo_ends_at']) && $promoEndsAt === false) {
            $errors['promo_ends_at'] = 'Promotion end date/time is invalid.';
        }
        if ($promoStartsAt !== false && $promoEndsAt !== false && $promoEndsAt < $promoStartsAt) {
            $errors['promo_ends_at'] = 'Promotion end must be after promotion start.';
        }

        if (!empty($data['is_plan_group'])) {
            if ($promoType !== '') {
                $errors['promo_type'] = 'Promotions apply to billable plans/tiers, not groups.';
            }
            if (!empty($data['promo_starts_at']) || !empty($data['promo_ends_at'])) {
                $errors['promo_type'] = 'Promotions apply to billable plans/tiers, not groups.';
            }
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

        if (!empty($data['parent_plan_id'])) {
            $parent = $this->db->fetch('SELECT id, is_plan_group, is_group, parent_plan_id, price FROM membership_plans WHERE id = ?', [(int) $data['parent_plan_id']]);
            if (!$parent) {
                $errors['parent_plan_id'] = 'Selected parent group does not exist.';
            } elseif (!$this->isStructuralGroupPlan($parent)) {
                $errors['parent_plan_id'] = 'Parent plan must be marked as a group.';
            }
        }

        if (!empty($data['subject_id'])) {
            $subject = $this->db->fetch('SELECT id FROM subjects WHERE id = ?', [(int) $data['subject_id']]);
            if (!$subject) {
                $errors['subject_id'] = 'Selected subject does not exist.';
            }
        }

        if ($promoType === '') {
            $data['promo_value'] = null;
            $data['promo_starts_at'] = null;
            $data['promo_ends_at'] = null;
        }

        return $errors;
    }

    private function activeSubjects(): array
    {
        return $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );
    }

    private function buildPlanTree(array $plans): array
    {
        $groups = [];
        $plansByParent = [];
        $standalone = [];

        foreach ($plans as $plan) {
            $isGroup = $this->isStructuralGroupPlan($plan);
            $parentId = (int) ($plan['parent_plan_id'] ?? 0);

            if ($isGroup) {
                $groups[(int) $plan['id']] = $plan;
            } elseif ($parentId > 0) {
                $plansByParent[$parentId][] = $plan;
            } else {
                $standalone[] = $plan;
            }
        }

        return [
            'groups' => $groups,
            'plansByParent' => $plansByParent,
            'standalone' => $standalone,
        ];
    }

    private function membersForCategory(array $filters, string $category, int $categoryId): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(m.forenames LIKE ? OR m.surnames LIKE ? OR m.email LIKE ? OR m.membership_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        if (!empty($filters['status_filter'])) {
            $where[] = 'm.status = ?';
            $params[] = $filters['status_filter'];
        }

        if (!empty($filters['org_filter'])) {
            $where[] = 'm.organisation = ?';
            $params[] = $filters['org_filter'];
        }

        if ($category === 'plan' && $categoryId > 0) {
            $where[] = 's.plan_id = ?';
            $params[] = $categoryId;
        }

        if ($category === 'group' && $categoryId > 0) {
            $where[] = '(s.plan_id = ? OR s.plan_id IN (SELECT id FROM membership_plans WHERE parent_plan_id = ?))';
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        $sql = 'SELECT m.*,
                    s.id                 AS latest_sub_id,
                    s.period_start       AS latest_period_start,
                    s.period_end         AS latest_period_end,
                    s.verification_status,
                    p.name               AS plan_name,
                    gp.name              AS group_name
                FROM members m
                LEFT JOIN membership_subscriptions s
                    ON s.id = (
                        SELECT id FROM membership_subscriptions
                        WHERE member_id = m.id
                        ORDER BY period_end DESC, id DESC
                        LIMIT 1
                    )
                LEFT JOIN membership_plans p ON p.id = s.plan_id
                LEFT JOIN membership_plans gp ON gp.id = p.parent_plan_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Sort
        $sortCol = !empty($filters['sort']) ? $filters['sort'] : 'surnames';
        $sortDir = !empty($filters['dir']) && $filters['dir'] === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'surnames'            => 'm.surnames',
            'forenames'           => 'm.forenames',
            'email'               => 'm.email',
            'membership_number'   => 'm.membership_number',
            'organisation'        => 'm.organisation',
            'status'              => 'm.status',
            'plan_name'           => 'p.name',
            'verification_status' => 's.verification_status',
        ];
        $orderExpr = $sortMap[$sortCol] ?? 'm.surnames';
        $sql .= " ORDER BY {$orderExpr} {$sortDir}, m.forenames ASC, m.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function bulkMembers(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $action = (string) $this->input('bulk_action', '');
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['member_ids'] ?? []))));

        if (empty($ids)) {
            Auth::flash('error', 'No members selected.');
            $this->redirect('/admin/membership/members');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        switch ($action) {
            case 'set_status':
                $newStatus = (string) $this->input('bulk_status', '');
                $allowed = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
                if (!in_array($newStatus, $allowed, true)) {
                    Auth::flash('error', 'Invalid status.');
                    $this->redirect('/admin/membership/members');
                }
                $this->db->execute(
                    "UPDATE members SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})",
                    array_merge([$newStatus], $ids)
                );
                Auth::flash('success', count($ids) . ' member(s) set to ' . $newStatus . '.');
                break;

            case 'archive':
                $this->db->execute(
                    "UPDATE members SET status = 'archived', updated_at = NOW() WHERE id IN ({$placeholders})",
                    $ids
                );
                Auth::flash('success', count($ids) . ' member(s) archived.');
                break;

            case 'delete':
                // Only delete members who have no subscription history
                $this->db->execute(
                    "DELETE FROM members WHERE id IN ({$placeholders})
                     AND id NOT IN (SELECT DISTINCT member_id FROM membership_subscriptions)",
                    $ids
                );
                Auth::flash('success', 'Members without subscriptions deleted. Members with subscription history were not deleted.');
                break;

            default:
                Auth::flash('error', 'Unknown bulk action.');
        }

        $this->redirect('/admin/membership/members');
    }

    private function membershipAssociatedSubjectIds(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT subject_id FROM (
                SELECT subject_id FROM membership_plans WHERE subject_id IS NOT NULL
                UNION
                SELECT subject_id FROM membership_subscriptions WHERE subject_id IS NOT NULL
            ) t
            ORDER BY subject_id ASC'
        );

        return array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['subject_id'] ?? 0),
            $rows
        )));
    }

    private function isStructuralGroupPlan(array $plan): bool
    {
        if (array_key_exists('is_plan_group', $plan)) {
            return (int) ($plan['is_plan_group'] ?? 0) === 1;
        }

        $isGroupFlag = (int) ($plan['is_group'] ?? 0) === 1;
        $hasParent = (int) ($plan['parent_plan_id'] ?? 0) > 0;
        $price = (float) ($plan['price'] ?? 0);

        return $isGroupFlag && !$hasParent && $price <= 0;
    }
}
