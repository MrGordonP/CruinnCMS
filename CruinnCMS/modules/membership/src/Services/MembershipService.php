<?php

namespace Cruinn\Module\Membership\Services;

use Cruinn\Database;

class MembershipService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allPlans(bool $activeOnly = false): array
    {
        if ($activeOnly) {
            return $this->db->fetchAll(
                'SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY name ASC'
            );
        }

        return $this->db->fetchAll(
            'SELECT * FROM membership_plans ORDER BY is_active DESC, name ASC'
        );
    }

    public function findPlan(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM membership_plans WHERE id = ?', [$id]);
        return $row ?: null;
    }

    public function createPlan(array $data): int
    {
        return (int) $this->db->insert('membership_plans', [
            'slug'           => $data['slug'],
            'name'           => $data['name'],
            'description'    => $data['description'] ?: null,
            'billing_period' => $data['billing_period'],
            'price'          => $data['price'],
            'currency'       => strtoupper($data['currency'] ?: 'EUR'),
            'is_active'      => !empty($data['is_active']) ? 1 : 0,
        ]);
    }

    public function updatePlan(int $id, array $data): void
    {
        $this->db->update('membership_plans', [
            'slug'           => $data['slug'],
            'name'           => $data['name'],
            'description'    => $data['description'] ?: null,
            'billing_period' => $data['billing_period'],
            'price'          => $data['price'],
            'currency'       => strtoupper($data['currency'] ?: 'EUR'),
            'is_active'      => !empty($data['is_active']) ? 1 : 0,
        ], 'id = ?', [$id]);
    }

    public function listMembers(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'm.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['plan_id'])) {
            $where[] = 'm.plan_id = ?';
            $params[] = (int) $filters['plan_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(m.forenames LIKE ? OR m.surnames LIKE ? OR m.email LIKE ? OR m.membership_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT m.*, p.name AS plan_name
                FROM members m
                LEFT JOIN membership_plans p ON p.id = m.plan_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY m.updated_at DESC, m.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function countByStatus(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS cnt FROM members GROUP BY status'
        );

        $out = [];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['cnt'];
        }

        return $out;
    }

    public static function dashboardSummary(array $settings): array
    {
        $db = Database::getInstance();
        $limit = max(1, (int) ($settings['limit'] ?? 5));

        $statusRows = $db->fetchAll(
            'SELECT status, COUNT(*) AS cnt FROM members GROUP BY status'
        );

        $statusCounts = [];
        foreach ($statusRows as $row) {
            $statusCounts[$row['status']] = (int) $row['cnt'];
        }

        $recentMembers = $db->fetchAll(
            'SELECT id, forenames, surnames, status, membership_number, updated_at
             FROM members
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . $limit
        );

        $dueSoon = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM membership_subscriptions WHERE status = 'due'"
        );

        $paidCurrent = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM membership_subscriptions WHERE status = 'paid' AND period_end >= CURDATE()"
        );

        return [
            'total_members' => (int) $db->fetchColumn('SELECT COUNT(*) FROM members'),
            'active_members' => (int) ($statusCounts['active'] ?? 0),
            'pending_members' => (int) ($statusCounts['pending'] ?? 0),
            'lapsed_members' => (int) ($statusCounts['lapsed'] ?? 0),
            'due_subscriptions' => $dueSoon,
            'paid_subscriptions' => $paidCurrent,
            'recent_members' => $recentMembers,
        ];
    }

    public function findById(int $id): ?array
    {
        $member = $this->db->fetch(
            'SELECT m.*, p.name AS plan_name
             FROM members m
             LEFT JOIN membership_plans p ON p.id = m.plan_id
             WHERE m.id = ?',
            [$id]
        );

        return $member ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->db->fetch('SELECT * FROM members WHERE email = ?', [$email]);
        return $row ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM members WHERE user_id = ?', [$userId]);
        return $row ?: null;
    }

    public function hasActiveMembership(int $memberId): bool
    {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM membership_subscriptions WHERE member_id = ? AND status = 'paid' AND period_end >= CURDATE()",
            [$memberId]
        );

        return (int) $count > 0;
    }

    public function currentSubscription(int $memberId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM membership_subscriptions
             WHERE member_id = ?
             ORDER BY period_end DESC, id DESC
             LIMIT 1',
            [$memberId]
        );

        return $row ?: null;
    }

    public function subscriptionsForMember(int $memberId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, p.name AS plan_name
             FROM membership_subscriptions s
             LEFT JOIN membership_plans p ON p.id = s.plan_id
             WHERE s.member_id = ?
             ORDER BY s.period_start DESC, s.id DESC',
            [$memberId]
        );
    }

    public function paymentsForMember(int $memberId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM membership_payments WHERE member_id = ? ORDER BY paid_at DESC, id DESC',
            [$memberId]
        );
    }

    public function createMember(array $data): int
    {
        return (int) $this->db->insert('members', [
            'user_id'           => $data['user_id'] !== '' ? (int) $data['user_id'] : null,
            'membership_number' => $data['membership_number'] ?: null,
            'forenames'         => $data['forenames'],
            'surnames'          => $data['surnames'],
            'email'             => strtolower($data['email']),
            'phone'             => $data['phone'] ?: null,
            'organisation'      => $data['organisation'] ?: null,
            'status'            => $data['status'],
            'plan_id'           => $data['plan_id'] !== '' ? (int) $data['plan_id'] : null,
            'joined_at'         => $data['joined_at'] ?: null,
            'lapsed_at'         => $data['lapsed_at'] ?: null,
            'notes'             => $data['notes'] ?: null,
        ]);
    }

    public function updateMember(int $id, array $data): void
    {
        $this->db->update('members', [
            'user_id'           => $data['user_id'] !== '' ? (int) $data['user_id'] : null,
            'membership_number' => $data['membership_number'] ?: null,
            'forenames'         => $data['forenames'],
            'surnames'          => $data['surnames'],
            'email'             => strtolower($data['email']),
            'phone'             => $data['phone'] ?: null,
            'organisation'      => $data['organisation'] ?: null,
            'status'            => $data['status'],
            'plan_id'           => $data['plan_id'] !== '' ? (int) $data['plan_id'] : null,
            'joined_at'         => $data['joined_at'] ?: null,
            'lapsed_at'         => $data['lapsed_at'] ?: null,
            'notes'             => $data['notes'] ?: null,
        ], 'id = ?', [$id]);
    }

    public function setStatus(int $memberId, string $status): void
    {
        $payload = ['status' => $status];
        if ($status === 'lapsed') {
            $payload['lapsed_at'] = date('Y-m-d H:i:s');
        }
        $this->db->update('members', $payload, 'id = ?', [$memberId]);
    }

    public function createSubscription(int $memberId, array $data): int
    {
        return (int) $this->db->insert('membership_subscriptions', [
            'member_id'          => $memberId,
            'plan_id'            => $data['plan_id'] !== '' ? (int) $data['plan_id'] : null,
            'period_label'       => $data['period_label'],
            'period_start'       => $data['period_start'],
            'period_end'         => $data['period_end'],
            'amount'             => $data['amount'],
            'currency'           => strtoupper($data['currency'] ?: 'EUR'),
            'status'             => $data['status'],
            'due_date'           => $data['due_date'] ?: null,
            'paid_at'            => $data['paid_at'] ?: null,
            'payment_reference'  => $data['payment_reference'] ?: null,
            'notes'              => $data['notes'] ?: null,
        ]);
    }

    public function updateSubscriptionStatus(int $subscriptionId, string $status): void
    {
        $payload = ['status' => $status];
        if ($status === 'paid') {
            $payload['paid_at'] = date('Y-m-d H:i:s');
        }
        $this->db->update('membership_subscriptions', $payload, 'id = ?', [$subscriptionId]);
    }

    public function recordPayment(int $subscriptionId, array $data): int
    {
        $subscription = $this->db->fetch(
            'SELECT id, member_id FROM membership_subscriptions WHERE id = ?',
            [$subscriptionId]
        );

        if (!$subscription) {
            throw new \RuntimeException('Subscription not found.');
        }

        $paymentId = (int) $this->db->insert('membership_payments', [
            'subscription_id' => $subscriptionId,
            'member_id'       => (int) $subscription['member_id'],
            'amount'          => $data['amount'],
            'currency'        => strtoupper($data['currency'] ?: 'EUR'),
            'method'          => $data['method'] ?: null,
            'reference'       => $data['reference'] ?: null,
            'status'          => $data['status'],
            'paid_at'         => $data['paid_at'],
            'notes'           => $data['notes'] ?: null,
        ]);

        if ($data['status'] === 'completed') {
            $this->db->update('membership_subscriptions', [
                'status'      => 'paid',
                'paid_at'     => $data['paid_at'],
                'payment_reference' => $data['reference'] ?: null,
            ], 'id = ?', [$subscriptionId]);

            $this->db->update('members', [
                'status' => 'active',
                'lapsed_at' => null,
            ], 'id = ?', [(int) $subscription['member_id']]);
        }

        return $paymentId;
    }

    public function markPaid(int $subscriptionId, ?string $reference = null): void
    {
        $this->db->update('membership_subscriptions', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_reference' => $reference,
        ], 'id = ?', [$subscriptionId]);
    }

    /**
     * Import an array of normalised rows (keyed by lowercased CSV header names).
     *
     * Recognised column names (case-insensitive in the CSV header):
     *   forenames, surnames, email, phone, organisation, membership_number,
     *   status, plan_id, plan, joined_at, lapsed_at, notes, membership_year,
     *   address_line_1 / line_1, address_line_2 / line_2, city, county, postcode, country
     *
     * @param array  $rows         Rows from the CSV (each row is an associative array keyed by header)
     * @param string $onDuplicate  'skip' | 'update' — what to do when email already exists
     * @param string $defaultStatus  Status to apply when CSV row has no status column
     * @return array{created:int, updated:int, skipped:int, errors:array}
     */
    public function importMembers(array $rows, string $onDuplicate = 'skip', string $defaultStatus = 'applicant'): array
    {
        $allowedStatuses = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];

        // Build a plan slug→id map for resolving plan names
        $plansBySlug = [];
        $plansByName = [];
        foreach ($this->allPlans() as $plan) {
            $plansBySlug[strtolower($plan['slug'])] = (int) $plan['id'];
            $plansByName[strtolower($plan['name'])] = (int) $plan['id'];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $lineNo => $row) {
            $line = $lineNo + 2; // 1-based + header row

            // Map flexible column names to canonical names
            $get = static function (array $row, string ...$keys): string {
                foreach ($keys as $key) {
                    if (isset($row[$key]) && trim($row[$key]) !== '') {
                        return trim($row[$key]);
                    }
                }
                return '';
            };

            $forenames = $get($row, 'forenames', 'first_name', 'firstname', 'first name');
            $surnames  = $get($row, 'surnames', 'surname', 'last_name', 'lastname', 'last name');
            $email     = strtolower($get($row, 'email', 'email_address'));

            if ($forenames === '') {
                $errors[] = "Row {$line}: forenames are required.";
                continue;
            }
            if ($surnames === '') {
                $errors[] = "Row {$line}: surnames are required.";
                continue;
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$line}: valid email is required (got: " . htmlspecialchars($email) . ").";
                continue;
            }

            // Resolve plan
            $planRaw = $get($row, 'plan', 'plan_id', 'plan_slug', 'plan_name');
            $planId  = null;
            if ($planRaw !== '') {
                if (ctype_digit($planRaw)) {
                    $planId = (int) $planRaw;
                } else {
                    $planId = $plansBySlug[strtolower($planRaw)]
                           ?? $plansByName[strtolower($planRaw)]
                           ?? null;
                }
            }

            // Resolve status
            $statusRaw = strtolower($get($row, 'status'));
            $status    = in_array($statusRaw, $allowedStatuses, true) ? $statusRaw : $defaultStatus;

            // Dates
            $joinedAt = $get($row, 'joined_at', 'joined', 'join_date');
            $joinedAt = $joinedAt !== '' ? $joinedAt : null;

            $lapsedAt = $get($row, 'lapsed_at', 'lapsed');
            $lapsedAt = $lapsedAt !== '' ? $lapsedAt : null;

            $membershipNumber = $get($row, 'membership_number', 'membership_no', 'member_number', 'member_no', 'number');
            $membershipYear   = $get($row, 'membership_year', 'year');
            $phone            = $get($row, 'phone', 'telephone', 'mobile');
            $organisation     = $get($row, 'organisation', 'organization', 'org', 'company');
            $notes            = $get($row, 'notes', 'note', 'comments');

            // Address fields
            $addrLine1  = $get($row, 'address_line_1', 'line_1', 'address1', 'address');
            $addrLine2  = $get($row, 'address_line_2', 'line_2', 'address2');
            $addrCity   = $get($row, 'city', 'town');
            $addrCounty = $get($row, 'county', 'state', 'region', 'province');
            $addrPost   = $get($row, 'postcode', 'postal_code', 'zip');
            $addrCountry = $get($row, 'country');

            $hasAddress = ($addrLine1 !== '' || $addrCity !== '' || $addrPost !== '');

            $existing = $this->findByEmail($email);

            if ($existing) {
                if ($onDuplicate === 'skip') {
                    $skipped++;
                    continue;
                }

                // Update
                $payload = [
                    'forenames'         => $forenames,
                    'surnames'          => $surnames,
                    'phone'             => $phone ?: null,
                    'organisation'      => $organisation ?: null,
                    'status'            => $status,
                    'plan_id'           => $planId,
                    'joined_at'         => $joinedAt,
                    'lapsed_at'         => $lapsedAt,
                    'notes'             => $notes ?: null,
                ];

                if ($membershipNumber !== '') {
                    $payload['membership_number'] = $membershipNumber;
                }
                if ($membershipYear !== '') {
                    $payload['membership_year'] = (int) $membershipYear;
                }

                $this->db->update('members', $payload, 'id = ?', [(int) $existing['id']]);

                if ($hasAddress) {
                    $this->upsertAddress((int) $existing['id'], $addrLine1, $addrLine2, $addrCity, $addrCounty, $addrPost, $addrCountry);
                }

                $updated++;
            } else {
                // Create
                $memberPayload = [
                    'forenames'         => $forenames,
                    'surnames'          => $surnames,
                    'email'             => $email,
                    'phone'             => $phone ?: null,
                    'organisation'      => $organisation ?: null,
                    'status'            => $status,
                    'plan_id'           => $planId,
                    'joined_at'         => $joinedAt,
                    'lapsed_at'         => $lapsedAt,
                    'notes'             => $notes ?: null,
                    'membership_number' => $membershipNumber ?: null,
                ];

                if ($membershipYear !== '') {
                    $memberPayload['membership_year'] = (int) $membershipYear;
                }

                $memberId = (int) $this->db->insert('members', $memberPayload);

                if ($hasAddress) {
                    $this->upsertAddress($memberId, $addrLine1, $addrLine2, $addrCity, $addrCounty, $addrPost, $addrCountry);
                }

                $created++;
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function upsertAddress(int $memberId, string $line1, string $line2, string $city, string $county, string $postcode, string $country): void
    {
        $existing = $this->db->fetch('SELECT id FROM member_addresses WHERE member_id = ?', [$memberId]);

        $payload = [
            'line_1'   => $line1 ?: null,
            'line_2'   => $line2 ?: null,
            'city'     => $city ?: null,
            'county'   => $county ?: null,
            'postcode' => $postcode ?: null,
            'country'  => $country ?: null,
        ];

        if ($existing) {
            $this->db->update('member_addresses', $payload, 'member_id = ?', [$memberId]);
        } else {
            $payload['member_id'] = $memberId;
            $this->db->insert('member_addresses', $payload);
        }
    }
}
