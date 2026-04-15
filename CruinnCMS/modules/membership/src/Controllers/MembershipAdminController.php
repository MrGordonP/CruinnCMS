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

        $members = $this->membership->listMembers($filters);

        $this->renderAdmin('admin/membership/members/index', [
            'title'       => 'Membership',
            'members'     => $members,
            'plans'       => $this->membership->allPlans(),
            'filters'     => $filters,
            'statusCount' => $this->membership->countByStatus(),
            'breadcrumbs' => [['Admin', '/admin'], ['Membership']],
        ]);
    }

    public function showMember(int $id): void
    {
        Auth::requireRole('admin');

        $member = $this->membership->findById($id);
        if (!$member) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->renderAdmin('admin/membership/members/show', [
            'title'         => trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')),
            'member'        => $member,
            'plans'         => $this->membership->allPlans(true),
            'subscriptions' => $this->membership->subscriptionsForMember($id),
            'payments'      => $this->membership->paymentsForMember($id),
            'breadcrumbs'   => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Member']],
        ]);
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
        $this->redirect('/admin/membership/members/' . $memberId);
    }

    public function editMember(int $id): void
    {
        Auth::requireRole('admin');

        $member = $this->membership->findById($id);
        if (!$member) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->renderAdmin('admin/membership/members/form', [
            'title'       => 'Edit Member',
            'member'      => $member,
            'plans'       => $this->membership->allPlans(true),
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Edit Member']],
        ]);
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
            $this->renderAdmin('admin/membership/members/form', [
                'title'       => 'Edit Member',
                'member'      => array_merge($existing, $data),
                'plans'       => $this->membership->allPlans(true),
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Membership', '/admin/membership'], ['Edit Member']],
            ]);
            return;
        }

        $this->membership->updateMember($id, $data);
        $this->logActivity('update', 'member', $id, 'Membership record updated.');
        Auth::flash('success', 'Member updated.');
        $this->redirect('/admin/membership/members/' . $id);
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
        $this->redirect('/admin/membership/members/' . $id);
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
            $this->redirect('/admin/membership/members/' . $memberId);
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
            $this->redirect('/admin/membership/members/' . $memberId);
        }

        $this->redirect('/admin/membership');
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

    private function memberPayload(): array
    {
        return [
            'user_id'           => $this->input('user_id', ''),
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
