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
            'is_group'       => !empty($data['is_group']) ? 1 : 0,
            'max_members'    => !empty($data['max_members']) ? (int) $data['max_members'] : null,
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
            'is_group'       => !empty($data['is_group']) ? 1 : 0,
            'max_members'    => !empty($data['max_members']) ? (int) $data['max_members'] : null,
        ], 'id = ?', [$id]);
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
        $this->db->update('members', [
            'user_id'           => $data['user_id'] !== '' ? (int) $data['user_id'] : null,
            'membership_number' => $data['membership_number'] ?: null,
            'forenames'         => $data['forenames'],
            'surnames'          => $data['surnames'],
            'email'             => strtolower($data['email']),
            'organisation'      => $data['organisation'] ?: null,
        ], 'id = ?', [$id]);
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

    public function importMembers(array $rows, string $onDuplicate = 'skip', string $defaultStatus = 'applicant'): array
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
