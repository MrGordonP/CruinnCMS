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
                'SELECT p.*, s.title AS subject_title
                 FROM membership_plans p
                 LEFT JOIN subjects s ON s.id = p.subject_id
                 WHERE p.is_active = 1
                 ORDER BY
                    COALESCE(p.parent_plan_id, p.id) ASC,
                    CASE WHEN p.parent_plan_id IS NULL THEN 0 ELSE 1 END ASC,
                    p.name ASC'
            );
        }

        return $this->db->fetchAll(
            'SELECT p.*, s.title AS subject_title
             FROM membership_plans p
             LEFT JOIN subjects s ON s.id = p.subject_id
             ORDER BY
                COALESCE(p.parent_plan_id, p.id) ASC,
                CASE WHEN p.parent_plan_id IS NULL THEN 0 ELSE 1 END ASC,
                p.is_active DESC,
                p.name ASC'
        );
    }

    public function findPlan(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM membership_plans WHERE id = ?', [$id]);
        return $row ?: null;
    }

    public function createPlan(array $data): int
    {
        $payload = [
            'name'           => $data['name'],
            'description'    => $data['description'] ?: null,
            'billing_period' => $data['billing_period'],
            'price'          => $data['price'],
            'currency'       => strtoupper($data['currency'] ?: 'EUR'),
            'is_active'      => !empty($data['is_active']) ? 1 : 0,
            'is_group'       => !empty($data['is_group']) ? 1 : 0,
            'is_plan_group'  => !empty($data['is_plan_group']) ? 1 : 0,
            'max_members'    => !empty($data['max_members']) ? (int) $data['max_members'] : null,
            'parent_plan_id' => !empty($data['parent_plan_id']) ? (int) $data['parent_plan_id'] : null,
            'subject_id'     => !empty($data['subject_id']) ? (int) $data['subject_id'] : null,
            'promo_type'     => !empty($data['promo_type']) ? (string) $data['promo_type'] : null,
            'promo_value'    => isset($data['promo_value']) && $data['promo_value'] !== '' ? (float) $data['promo_value'] : null,
            'promo_starts_at'=> !empty($data['promo_starts_at']) ? (string) $data['promo_starts_at'] : null,
            'promo_ends_at'  => !empty($data['promo_ends_at']) ? (string) $data['promo_ends_at'] : null,
        ];

        try {
            return (int) $this->db->insert('membership_plans', $payload);
        } catch (\Throwable $e) {
            if (!$this->isUnknownPromoColumnError($e)) {
                throw $e;
            }

            unset($payload['promo_type'], $payload['promo_value'], $payload['promo_starts_at'], $payload['promo_ends_at'], $payload['is_plan_group']);
            return (int) $this->db->insert('membership_plans', $payload);
        }
    }

    public function updatePlan(int $id, array $data): void
    {
        $payload = [
            'name'           => $data['name'],
            'description'    => $data['description'] ?: null,
            'billing_period' => $data['billing_period'],
            'price'          => $data['price'],
            'currency'       => strtoupper($data['currency'] ?: 'EUR'),
            'is_active'      => !empty($data['is_active']) ? 1 : 0,
            'is_group'       => !empty($data['is_group']) ? 1 : 0,
            'is_plan_group'  => !empty($data['is_plan_group']) ? 1 : 0,
            'max_members'    => !empty($data['max_members']) ? (int) $data['max_members'] : null,
            'parent_plan_id' => !empty($data['parent_plan_id']) ? (int) $data['parent_plan_id'] : null,
            'subject_id'     => !empty($data['subject_id']) ? (int) $data['subject_id'] : null,
            'promo_type'     => !empty($data['promo_type']) ? (string) $data['promo_type'] : null,
            'promo_value'    => isset($data['promo_value']) && $data['promo_value'] !== '' ? (float) $data['promo_value'] : null,
            'promo_starts_at'=> !empty($data['promo_starts_at']) ? (string) $data['promo_starts_at'] : null,
            'promo_ends_at'  => !empty($data['promo_ends_at']) ? (string) $data['promo_ends_at'] : null,
        ];

        try {
            $this->db->update('membership_plans', $payload, 'id = ?', [$id]);
            return;
        } catch (\Throwable $e) {
            if (!$this->isUnknownPromoColumnError($e)) {
                throw $e;
            }

            unset($payload['promo_type'], $payload['promo_value'], $payload['promo_starts_at'], $payload['promo_ends_at'], $payload['is_plan_group']);
            $this->db->update('membership_plans', $payload, 'id = ?', [$id]);
        }
    }

    private function isUnknownPromoColumnError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        if (strpos($message, 'unknown column') === false) {
            return false;
        }

        return strpos($message, 'promo_type') !== false
            || strpos($message, 'promo_value') !== false
            || strpos($message, 'promo_starts_at') !== false
            || strpos($message, 'promo_ends_at') !== false
            || strpos($message, 'is_plan_group') !== false;
    }

    public function listMembers(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(m.forenames LIKE ? OR m.surnames LIKE ? OR m.email LIKE ? OR m.membership_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql = 'SELECT m.*,
                    s.id                 AS latest_sub_id,
                    s.period_start       AS latest_period_start,
                    s.period_end         AS latest_period_end,
                    s.verification_status,
                    p.name               AS plan_name
                FROM members m
                LEFT JOIN membership_subscriptions s
                    ON s.id = (
                        SELECT id FROM membership_subscriptions
                        WHERE member_id = m.id
                        ORDER BY period_end DESC, id DESC
                        LIMIT 1
                    )
                LEFT JOIN membership_plans p ON p.id = s.plan_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY m.updated_at DESC, m.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function countByStatus(): array
    {
        $total = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM members');

        $active = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT member_id) FROM membership_subscriptions
             WHERE period_end >= CURDATE()
             AND verification_status IN ('verified','waived')"
        );

        $unverified = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT member_id) FROM membership_subscriptions
             WHERE period_end >= CURDATE()
             AND verification_status = 'unverified'"
        );

        return [
            'total'      => $total,
            'active'     => $active,
            'unverified' => $unverified,
            'lapsed'     => max(0, $total - $active - $unverified),
        ];
    }

    public static function dashboardSummary(array $settings): array
    {
        $db    = Database::getInstance();
        $limit = max(1, (int) ($settings['limit'] ?? 5));

        $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM members');

        $active = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT member_id) FROM membership_subscriptions
             WHERE period_end >= CURDATE()
             AND verification_status IN ('verified','waived')"
        );

        $pending = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT member_id) FROM membership_subscriptions
             WHERE period_end >= CURDATE()
             AND verification_status = 'unverified'"
        );

        $recentMembers = $db->fetchAll(
            'SELECT id, forenames, surnames, membership_number, updated_at
             FROM members
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . $limit
        );

        return [
            'total_members'   => $total,
            'active_members'  => $active,
            'pending_members' => $pending,
            'lapsed_members'  => max(0, $total - $active - $pending),
            'recent_members'  => $recentMembers,
        ];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM members WHERE id = ?', [$id]);
        return $row ?: null;
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
            "SELECT COUNT(*) FROM membership_subscriptions
             WHERE member_id = ?
             AND period_end >= CURDATE()
             AND verification_status IN ('verified','waived')",
            [$memberId]
        );
        return (int) $count > 0;
    }

    public function currentSubscription(int $memberId): ?array
    {
        $row = $this->db->fetch(
            'SELECT s.*, p.name AS plan_name
             FROM membership_subscriptions s
             LEFT JOIN membership_plans p ON p.id = s.plan_id
             WHERE s.member_id = ?
             ORDER BY s.period_end DESC, s.id DESC
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
            'SELECT py.*
             FROM payments py
             INNER JOIN membership_subscriptions s ON s.id = py.subscription_id
             WHERE s.member_id = ?
             ORDER BY py.paid_at DESC, py.id DESC',
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
            'organisation'      => $data['organisation'] ?: null,
        ]);
    }

    public function updateMember(int $id, array $data): void
    {
        $allowed = ['applicant','active','lapsed','suspended','resigned','archived'];
        $status  = in_array($data['status'] ?? '', $allowed, true) ? $data['status'] : 'applicant';
        $payload = [
            'user_id'           => $data['user_id'] !== '' ? (int) $data['user_id'] : null,
            'membership_number' => $data['membership_number'] ?: null,
            'forenames'         => $data['forenames'],
            'surnames'          => $data['surnames'],
            'email'             => strtolower($data['email']),
            'organisation'      => $data['organisation'] ?: null,
            'status'            => $status,
            'joined_at'         => $data['joined_at'] !== '' ? $data['joined_at'] : null,
            'lapsed_at'         => $data['lapsed_at'] !== '' ? $data['lapsed_at'] : null,
        ];
        $this->db->update('members', $payload, 'id = ?', [$id]);
    }

    public function createSubscription(int $memberId, array $data): int
    {
        return (int) $this->db->insert('membership_subscriptions', [
            'member_id'           => $memberId,
            'plan_id'             => $data['plan_id'] !== '' ? (int) $data['plan_id'] : null,
            'period_start'        => $data['period_start'],
            'period_end'          => $data['period_end'],
            'member_type'         => $data['member_type'] ?? 'new',
            'geologist_level'     => $data['geologist_level'] ?: null,
            'institution'         => $data['institution'] ?: null,
            'position'            => $data['position'] ?: null,
            'student_level'       => $data['student_level'] ?: null,
            'amount'              => $data['amount'],
            'currency'            => strtoupper($data['currency'] ?: 'EUR'),
            'payment_method'      => $data['payment_method'] ?? 'bank_transfer',
            'transaction_id'      => $data['transaction_id'] ?: null,
            'verification_status' => $data['verification_status'] ?? 'unverified',
            'notes'               => $data['notes'] ?: null,
        ]);
    }

    public function updateVerificationStatus(int $subscriptionId, string $status): void
    {
        $allowed = ['unverified', 'verified', 'disputed', 'waived'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid verification status: ' . $status);
        }
        $payload = ['verification_status' => $status];
        if ($status === 'verified') {
            $payload['verified_at'] = date('Y-m-d H:i:s');
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

        $paymentId = (int) $this->db->insert('payments', [
            'subscription_id' => $subscriptionId,
            'transaction_id'  => $data['transaction_id'] ?: ('manual-' . date('YmdHis')),
            'gateway'         => $data['gateway'] ?: null,
            'amount'          => $data['amount'],
            'currency'        => strtoupper($data['currency'] ?: 'EUR'),
            'status'          => 'completed',
            'paid_at'         => $data['paid_at'] ?: date('Y-m-d H:i:s'),
            'notes'           => $data['notes'] ?: null,
        ]);

        $this->db->update('membership_subscriptions', [
            'payment_id'          => $paymentId,
            'verification_status' => 'verified',
            'verified_at'         => date('Y-m-d H:i:s'),
            'transaction_id'      => $data['transaction_id'] ?: null,
        ], 'id = ?', [$subscriptionId]);

        return $paymentId;
    }

    public function memberAdminForMember(int $memberId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM member_admin WHERE member_id = ?', [$memberId]);
        return $row ?: null;
    }

    public function upsertMemberAdmin(int $memberId, array $data): void
    {
        $existing = $this->db->fetch('SELECT id FROM member_admin WHERE member_id = ?', [$memberId]);
        if ($existing) {
            $this->db->update('member_admin', ['notes' => $data['notes'] ?: null], 'member_id = ?', [$memberId]);
        } else {
            $this->db->insert('member_admin', ['member_id' => $memberId, 'notes' => $data['notes'] ?: null]);
        }
    }

    public function saveAddress(int $memberId, array $data): void
    {
        $fields = [
            'line_1'   => $data['line_1']   ?: null,
            'line_2'   => $data['line_2']   ?: null,
            'city'     => $data['city']     ?: null,
            'county'   => $data['county']   ?: null,
            'postcode' => $data['postcode'] ?: null,
            'country'  => $data['country']  ?: null,
            'phone'    => $data['phone']    ?: null,
        ];
        $existing = $this->db->fetch('SELECT id FROM member_addresses WHERE member_id = ?', [$memberId]);
        if ($existing) {
            $this->db->update('member_addresses', $fields, 'member_id = ?', [$memberId]);
        } else {
            $this->db->insert('member_addresses', array_merge(['member_id' => $memberId], $fields));
        }
    }

    public function linkUser(int $memberId, int $userId): void
    {
        $this->db->update('members', ['user_id' => $userId], 'id = ?', [$memberId]);
    }

    public function unlinkUser(int $memberId): void
    {
        $this->db->update('members', ['user_id' => null], 'id = ?', [$memberId]);
    }

    public function markPaid(int $subscriptionId, ?string $transactionId = null): void
    {
        $this->db->update('membership_subscriptions', [
            'verification_status' => 'verified',
            'verified_at'         => date('Y-m-d H:i:s'),
            'transaction_id'      => $transactionId,
        ], 'id = ?', [$subscriptionId]);
    }

    public function importMembers(array $rows, string $onDuplicate = 'skip', string $defaultStatus = 'applicant', array $planValueMap = []): array
    {
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
                $errors[] = "Row {$line}: valid email is required.";
                continue;
            }

            $membershipNumber = $get($row, 'membership_number', 'membership_no', 'member_number', 'member_no', 'number');
            $organisation     = $get($row, 'organisation', 'organization', 'org', 'company');

            $addrLine1   = $get($row, 'address_line_1', 'line_1', 'address1', 'address');
            $addrLine2   = $get($row, 'address_line_2', 'line_2', 'address2');
            $addrCity    = $get($row, 'city', 'town');
            $addrCounty  = $get($row, 'county', 'state', 'region', 'province');
            $addrPost    = $get($row, 'postcode', 'postal_code', 'zip');
            $addrCountry = $get($row, 'country');
            $hasAddress  = ($addrLine1 !== '' || $addrCity !== '' || $addrPost !== '');

            $existing = $this->findByEmail($email);

            if ($existing) {
                if ($onDuplicate === 'skip') { $skipped++; continue; }

                $this->db->update('members', [
                    'forenames'         => $forenames,
                    'surnames'          => $surnames,
                    'organisation'      => $organisation ?: null,
                    'membership_number' => $membershipNumber ?: null,
                ], 'id = ?', [(int) $existing['id']]);

                if ($hasAddress) {
                    $this->upsertAddress((int) $existing['id'], $addrLine1, $addrLine2, $addrCity, $addrCounty, $addrPost, $addrCountry);
                }
                $updated++;
            } else {
                $memberId = (int) $this->db->insert('members', [
                    'forenames'         => $forenames,
                    'surnames'          => $surnames,
                    'email'             => $email,
                    'organisation'      => $organisation ?: null,
                    'membership_number' => $membershipNumber ?: null,
                ]);

                if ($hasAddress) {
                    $this->upsertAddress($memberId, $addrLine1, $addrLine2, $addrCity, $addrCounty, $addrPost, $addrCountry);
                }
                $created++;
            }

            // Assign plan subscription if plan value was mapped and resolved
            if (!empty($planValueMap)) {
                $rawPlanValue = trim((string) ($row['plan'] ?? ''));
                if ($rawPlanValue !== '' && isset($planValueMap[$rawPlanValue])) {
                    $planId = (int) $planValueMap[$rawPlanValue];
                    $yearRaw = trim((string) ($row['membership_year'] ?? ''));
                    $year = (int) $yearRaw > 0 ? (int) $yearRaw : (int) date('Y');
                    $alreadySub = $this->db->fetch(
                        'SELECT id FROM membership_subscriptions WHERE member_id = ? AND plan_id = ? AND YEAR(period_start) = ?',
                        [$memberId, $planId, $year]
                    );
                    if (!$alreadySub) {
                        $this->db->insert('membership_subscriptions', [
                            'member_id'    => $memberId,
                            'plan_id'      => $planId,
                            'period_start' => $year . '-01-01',
                            'period_end'   => $year . '-12-31',
                            'member_type'  => 'new',
                            'amount'       => 0,
                            'currency'     => 'EUR',
                        ]);
                    }
                }
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
