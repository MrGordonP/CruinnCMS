<?php
/**
 * IGA Membership CSV Import Tool
 *
 * Reads the IGA membership Google Form response CSVs for 2021–2026.
 *
 * Two output modes:
 *
 *   Direct DB mode (default):
 *     php dev/tools/import-membership-csv.php [/path/to/csvs]
 *     Requires local DB access via instance config (iga-portal).
 *
 *   SQL output mode:
 *     php dev/tools/import-membership-csv.php [/path/to/csvs] --sql
 *     Writes a self-contained SQL file to dev/tools/membership_import.sql
 *     Upload this to the server and run it after the migration.
 *     Uses INSERT IGNORE on members (requires uk_members_email unique key
 *     added by migration 009) and subquery-based FK resolution throughout.
 */

// ── Mode ──────────────────────────────────────────────────────
$sqlMode = in_array('--sql', $argv ?? [], true);

define('CRUINN_ROOT', dirname(__DIR__, 2) . '/CruinnCMS');
define('CRUINN_PUBLIC', dirname(__DIR__, 2) . '/public_html');

// ── Autoloader (only needed for direct DB mode) ───────────────
if (!$sqlMode) {
    $composerAutoload = CRUINN_ROOT . '/vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require $composerAutoload;
    } else {
        spl_autoload_register(function (string $class) {
            if (!str_starts_with($class, 'Cruinn\\')) return;
            $file = CRUINN_ROOT . '/src/' . str_replace(['Cruinn\\', '\\'], ['', '/'], $class) . '.php';
            if (file_exists($file)) require $file;
        });
    }
    require CRUINN_ROOT . '/config/config.php';
    if (file_exists(CRUINN_ROOT . '/config/config.local.php')) {
        require CRUINN_ROOT . '/config/config.local.php';
    }
    $db = \Cruinn\Database::connectToInstance('iga-portal');
} else {
    $db = null;
}

// ── CSV directory ─────────────────────────────────────────────
$filteredArgs = array_values(array_filter(
    array_slice($argv ?? [], 1),
    fn($a) => $a !== '--sql'
));
$csvDir = $filteredArgs[0] ?? 'E:\\D - My Files\\IGA\\Membership';

$csvFiles = [
    2021 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2021 (Responses).csv',
    2022 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2022 (Responses).csv',
    2023 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2023 (Responses).csv',
    2024 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2024 (Responses).csv',
    2025 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2025 (Responses).csv',
    2026 => $csvDir . DIRECTORY_SEPARATOR . 'IGA Membership Form 2026 (Responses).csv',
];

// ── Plan definitions ──────────────────────────────────────────
$planDefs = [
    'individual'     => ['name' => 'Individual',       'price' => 20.00, 'is_group' => 0],
    'family'         => ['name' => 'Family',            'price' => 30.00, 'is_group' => 1],
    'oap-unemployed' => ['name' => 'OAP / Unemployed', 'price' => 15.00, 'is_group' => 0],
    'student'        => ['name' => 'Student',           'price' => 5.00,  'is_group' => 0],
];

// ══════════════════════════════════════════════════════════════
// SQL OUTPUT MODE
// ══════════════════════════════════════════════════════════════
if ($sqlMode) {
    $outFile = __DIR__ . '/membership_import.sql';
    $fout    = fopen($outFile, 'w');

    sql_write($fout, '-- ============================================================');
    sql_write($fout, '-- IGA Membership Import — generated ' . date('Y-m-d H:i:s'));
    sql_write($fout, '-- Run AFTER migration 009_membership_restructure.sql');
    sql_write($fout, '-- ============================================================');
    sql_write($fout, 'SET NAMES utf8mb4;');
    sql_write($fout, 'SET FOREIGN_KEY_CHECKS = 0;');
    sql_write($fout, '');

    // ── Plans ──────────────────────────────────────────────────
    sql_write($fout, '-- ── Membership plans ────────────────────────────────────────');
    foreach ($planDefs as $slug => $def) {
        sql_write($fout, sprintf(
            "INSERT IGNORE INTO `membership_plans`"
            . " (`slug`,`name`,`billing_period`,`price`,`currency`,`is_active`,`is_group`,`max_members`)"
            . " VALUES (%s,%s,'annual',%.2f,'EUR',1,%d,NULL);",
            qs($slug), qs($def['name']), $def['price'], $def['is_group']
        ));
    }
    sql_write($fout, '');

    // ── First pass: collect members deduped by email ──────────
    $membersByEmail = [];
    foreach ($csvFiles as $year => $filepath) {
        if (!file_exists($filepath)) continue;
        $fh = fopen($filepath, 'r');
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            $row = csv_to_utf8($row);
            if (empty(array_filter($row, fn($v) => trim($v) !== ''))) continue;
            $data = normalize_row($year, $row);
            if (!$data) continue;
            $email = $data['email'];
            if (!isset($membersByEmail[$email])) {
                $membersByEmail[$email] = $data;
            } else {
                if (empty(trim($membersByEmail[$email]['forenames'])) && !empty($data['forenames'])) {
                    $membersByEmail[$email]['forenames'] = $data['forenames'];
                    $membersByEmail[$email]['surnames']  = $data['surnames'];
                }
                if (empty($membersByEmail[$email]['address']) && !empty($data['address'])) {
                    $membersByEmail[$email]['address'] = $data['address'];
                }
                if (empty($membersByEmail[$email]['phone']) && !empty($data['phone'])) {
                    $membersByEmail[$email]['phone'] = $data['phone'];
                }
                if (empty($membersByEmail[$email]['county']) && !empty($data['county'])) {
                    $membersByEmail[$email]['county'] = $data['county'];
                }
            }
        }
        fclose($fh);
    }

    // ── Members ────────────────────────────────────────────────
    sql_write($fout, '-- ── Members ─────────────────────────────────────────────────');
    foreach ($membersByEmail as $email => $data) {
        sql_write($fout, sprintf(
            "INSERT IGNORE INTO `members` (`forenames`,`surnames`,`email`) VALUES (%s,%s,%s);",
            qs($data['forenames']), qs($data['surnames']), qs($email)
        ));
    }
    sql_write($fout, '');

    // ── Addresses ─────────────────────────────────────────────
    sql_write($fout, '-- ── Addresses ───────────────────────────────────────────────');
    foreach ($membersByEmail as $email => $data) {
        if (!$data['address'] && !$data['county'] && !$data['phone']) continue;
        sql_write($fout, sprintf(
            "INSERT IGNORE INTO `member_addresses` (`member_id`,`line_1`,`county`,`country`,`phone`)"
            . " SELECT `id`,%s,%s,'Ireland',%s FROM `members` WHERE `email`=%s LIMIT 1;",
            qs($data['address'] ?: null),
            qs($data['county']  ?: null),
            qs($data['phone']   ?: null),
            qs($email)
        ));
    }
    sql_write($fout, '');

    // ── Second pass: subscriptions ────────────────────────────
    sql_write($fout, '-- ── Subscriptions ───────────────────────────────────────────');
    $seenSubs = [];

    foreach ($csvFiles as $year => $filepath) {
        if (!file_exists($filepath)) continue;
        sql_write($fout, "-- Year $year");
        $fh = fopen($filepath, 'r');
        fgetcsv($fh);

        while (($row = fgetcsv($fh)) !== false) {
            $row = csv_to_utf8($row);
            if (empty(array_filter($row, fn($v) => trim($v) !== ''))) continue;
            $data = normalize_row($year, $row);
            if (!$data) continue;

            $planSlug = parse_plan_slug($data['membership_type']);
            if (!$planSlug) continue;

            $key = $data['email'] . ':' . $year;
            if (isset($seenSubs[$key])) continue;
            $seenSubs[$key] = true;

            $payMethod = parse_payment_method($data['payment_method']);
            $txId      = trim($data['transaction_id']) ?: null;
            if ($txId !== null) {
                // Strip non-UTF-8 bytes (e.g. Windows-1252 encoded chars from CSV)
                $txId = mb_convert_encoding($txId, 'UTF-8', 'UTF-8');
                $txId = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $txId) ?: null;
            }
            $geoLevel  = parse_geologist_level($data['geologist_level']);
            $memType   = str_contains(strtolower($data['is_renewal']), 'renewal') ? 'renewal' : 'new';
            $verified  = ($txId && $payMethod === 'bank_transfer') ? 'verified' : 'unverified';
            $planPrice = $planDefs[$planSlug]['price'];

            sql_write($fout, sprintf(
                "INSERT INTO `membership_subscriptions`"
                . " (`member_id`,`plan_id`,`period_start`,`period_end`,`member_type`,"
                . "`geologist_level`,`amount`,`currency`,`payment_method`,`transaction_id`,"
                . "`verification_status`)"
                . " SELECT m.`id`, p.`id`,"
                . " %s, %s, %s, %s, %.2f, 'EUR', %s, %s, %s"
                . " FROM `members` m"
                . " JOIN `membership_plans` p ON p.`slug` = %s"
                . " WHERE m.`email` = %s"
                . " AND NOT EXISTS ("
                .   "SELECT 1 FROM `membership_subscriptions` x"
                .   " WHERE x.`member_id` = m.`id` AND YEAR(x.`period_start`) = %d"
                . ");",
                qs("{$year}-01-01"),
                qs("{$year}-12-31"),
                qs($memType),
                qs($geoLevel),
                $planPrice,
                qs($payMethod),
                qs($txId),
                qs($verified),
                qs($planSlug),
                qs($data['email']),
                $year
            ));

            // subscription_members primary row
            sql_write($fout, sprintf(
                "INSERT IGNORE INTO `subscription_members`"
                . " (`subscription_id`,`member_id`,`role`,`status`,`requested_at`,`approved_at`)"
                . " SELECT s.`id`, m.`id`, 'primary', 'approved', NOW(), NOW()"
                . " FROM `membership_subscriptions` s"
                . " JOIN `members` m ON m.`email` = %s"
                . " WHERE YEAR(s.`period_start`) = %d"
                . " AND s.`member_id` = m.`id`;",
                qs($data['email']),
                $year
            ));
        }
        fclose($fh);
        sql_write($fout, '');
    }

    sql_write($fout, 'SET FOREIGN_KEY_CHECKS = 1;');
    fclose($fout);

    log_line("SQL output written to: $outFile");
    exit(0);
}

// ══════════════════════════════════════════════════════════════
// DIRECT DB MODE
// ══════════════════════════════════════════════════════════════

$planIds = [];
foreach ($planDefs as $slug => $def) {
    $existing = $db->fetch('SELECT id FROM membership_plans WHERE slug = ?', [$slug]);
    if ($existing) {
        $planIds[$slug] = (int) $existing['id'];
    } else {
        $planIds[$slug] = (int) $db->insert('membership_plans', [
            'slug'           => $slug,
            'name'           => $def['name'],
            'billing_period' => 'annual',
            'price'          => $def['price'],
            'currency'       => 'EUR',
            'is_active'      => 1,
            'is_group'       => (int) $def['is_group'],
            'max_members'    => null,
        ]);
        log_line("Plan seeded: {$def['name']} (id={$planIds[$slug]})");
    }
}

$stats = ['members_created' => 0, 'members_updated' => 0, 'subscriptions' => 0, 'skipped' => 0];

foreach ($csvFiles as $year => $filepath) {
    if (!file_exists($filepath)) {
        log_line("SKIP (not found): $filepath");
        continue;
    }

    log_line('');
    log_line("── $year ─────────────────────────────────────────────");

    $fh = fopen($filepath, 'r');
    fgetcsv($fh);
    $rowNum = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rowNum++;
        $row = csv_to_utf8($row);
        if (empty(array_filter($row, fn($v) => trim($v) !== ''))) continue;

        $data = normalize_row($year, $row);
        if (!$data) { $stats['skipped']++; continue; }

        $planSlug = parse_plan_slug($data['membership_type']);
        if (!$planSlug || !isset($planIds[$planSlug])) {
            log_line("  Row $rowNum [{$data['email']}]: unknown plan — skipped");
            $stats['skipped']++;
            continue;
        }

        $planId    = $planIds[$planSlug];
        $planPrice = $planDefs[$planSlug]['price'];

        $existingMember = $db->fetch(
            'SELECT id, forenames, surnames FROM members WHERE LOWER(email) = ?',
            [strtolower($data['email'])]
        );

        if ($existingMember) {
            $memberId = (int) $existingMember['id'];
            if (empty(trim($existingMember['forenames'])) && !empty($data['forenames'])) {
                $db->update('members', [
                    'forenames' => $data['forenames'],
                    'surnames'  => $data['surnames'],
                ], 'id = ?', [$memberId]);
            }
            $stats['members_updated']++;
        } else {
            $memberId = (int) $db->insert('members', [
                'forenames'    => $data['forenames'],
                'surnames'     => $data['surnames'],
                'email'        => strtolower($data['email']),
                'organisation' => null,
            ]);
            $stats['members_created']++;
            log_line("  Created: {$data['forenames']} {$data['surnames']} <{$data['email']}> (id=$memberId)");
        }

        if ($data['address'] || $data['county'] || $data['phone']) {
            $existingAddr = $db->fetch('SELECT id, phone FROM member_addresses WHERE member_id = ?', [$memberId]);
            if (!$existingAddr) {
                $db->insert('member_addresses', [
                    'member_id' => $memberId,
                    'line_1'    => $data['address'] ?: null,
                    'county'    => $data['county']  ?: null,
                    'country'   => 'Ireland',
                    'phone'     => $data['phone']   ?: null,
                ]);
            } elseif ($data['phone'] && empty($existingAddr['phone'])) {
                $db->update('member_addresses', ['phone' => $data['phone']], 'member_id = ?', [$memberId]);
            }
        }

        $existingSub = $db->fetch(
            'SELECT id FROM membership_subscriptions WHERE member_id = ? AND YEAR(period_start) = ?',
            [$memberId, $year]
        );
        if ($existingSub) { $stats['skipped']++; continue; }

        $payMethod = parse_payment_method($data['payment_method']);
        $txId      = trim($data['transaction_id']) ?: null;
        if ($txId !== null) {
            $txId = mb_convert_encoding($txId, 'UTF-8', 'UTF-8');
            $txId = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $txId) ?: null;
        }
        $geoLevel  = parse_geologist_level($data['geologist_level']);
        $memType   = str_contains(strtolower($data['is_renewal']), 'renewal') ? 'renewal' : 'new';
        $verified  = ($txId && $payMethod === 'bank_transfer') ? 'verified' : 'unverified';

        $subId = (int) $db->insert('membership_subscriptions', [
            'member_id'           => $memberId,
            'plan_id'             => $planId,
            'period_start'        => "{$year}-01-01",
            'period_end'          => "{$year}-12-31",
            'member_type'         => $memType,
            'geologist_level'     => $geoLevel,
            'institution'         => null,
            'position'            => null,
            'student_level'       => null,
            'amount'              => $planPrice,
            'currency'            => 'EUR',
            'payment_method'      => $payMethod,
            'transaction_id'      => $txId,
            'payment_id'          => null,
            'verification_status' => $verified,
            'verified_by'         => null,
            'verified_at'         => null,
            'notes'               => null,
        ]);

        $db->insert('subscription_members', [
            'subscription_id' => $subId,
            'member_id'       => $memberId,
            'role'            => 'primary',
            'status'          => 'approved',
            'requested_at'    => date('Y-m-d H:i:s'),
            'approved_by'     => null,
            'approved_at'     => date('Y-m-d H:i:s'),
            'notes'           => null,
        ]);

        $stats['subscriptions']++;
        log_line("  Sub #{$subId}: {$data['forenames']} {$data['surnames']} | $planSlug | $memType | $payMethod");
    }

    fclose($fh);
}

log_line('');
log_line('═══════════════════════════════════════');
log_line('Import complete');
log_line("  Members created:   {$stats['members_created']}");
log_line("  Members updated:   {$stats['members_updated']}");
log_line("  Subscriptions:     {$stats['subscriptions']}");
log_line("  Rows skipped:      {$stats['skipped']}");
log_line('═══════════════════════════════════════');

// ══════════════════════════════════════════════════════════════
// Shared helpers
// ══════════════════════════════════════════════════════════════

function log_line(string $msg): void
{
    echo date('[H:i:s] ') . $msg . PHP_EOL;
}

function sql_write($fh, string $sql): void
{
    fwrite($fh, $sql . "\n");
}

/**
 * Convert a raw CSV row (possibly Windows-1252 encoded) to clean UTF-8.
 * Fields that are already valid UTF-8 pass through unchanged.
 */
function csv_to_utf8(array $row): array
{
    return array_map(function (string $cell): string {
        // If the cell is already valid UTF-8, leave it alone.
        if (mb_check_encoding($cell, 'UTF-8')) {
            return $cell;
        }
        // Otherwise assume Windows-1252 and convert.
        return mb_convert_encoding($cell, 'UTF-8', 'Windows-1252');
    }, $row);
}

/** Quote a value for SQL output. NULL stays NULL, strings are escaped. */
function qs(mixed $value): string
{
    if ($value === null) return 'NULL';
    return "'" . addslashes((string) $value) . "'";
}

function normalize_row(int $year, array $row): ?array
{
    return match (true) {
        $year === 2021 => normalize_2021($row),
        $year === 2022 => normalize_2022($row),
        $year === 2023 => normalize_2023($row),
        $year === 2024 => normalize_2024($row),
        default        => normalize_2025_2026($row),
    };
}

/**
 * 2021: 0:Timestamp 1:Name(or FirstName) 2:Address(or Surname) 3:Email 4:Phone
 *       5:Renewal 6:MembershipType 7:PayMethod 8:Reference
 *
 * col[3] is always an email in this format. The anomaly (e.g. Gerald Dickens)
 * is that col[2] contains a surname instead of an address.
 * Detection: if col[2] is short (≤40 chars) and has no comma, treat it as a
 * surname; otherwise treat col[2] as address and split col[1] for the name.
 */
function normalize_2021(array $row): ?array
{
    if (count($row) < 7) return null;

    $col2 = trim($row[2] ?? '');
    // Surname detection: a plain surname (e.g. "Dickens") has no digits,
    // no commas, and no spaces. Addresses always contain at least one of those.
    $isSurname = strlen($col2) > 0
        && !preg_match('/[\d,\s]/', $col2);

    if ($isSurname) {
        // Gerald Dickens anomaly: col[1]=forenames, col[2]=surname
        [$forenames, $surnames] = [trim($row[1]), $col2];
        $address = '';
    } else {
        // Normal: col[1]=full name, col[2]=address
        [$forenames, $surnames] = split_last_word(trim($row[1]));
        $address = $col2;
    }

    $email = clean_email($row[3] ?? '');
    if (!$email) return null;

    return [
        'forenames'       => $forenames,
        'surnames'        => $surnames,
        'email'           => $email,
        'phone'           => trim($row[4] ?? ''),
        'address'         => $address,
        'is_renewal'      => trim($row[5] ?? ''),
        'membership_type' => trim($row[6] ?? ''),
        'payment_method'  => trim($row[7] ?? ''),
        'transaction_id'  => trim($row[8] ?? ''),
        'geologist_level' => '',
        'county'          => '',
    ];
}

/** 2022: 0:Ts 1:First 2:Sur 3:Addr 4:Email 5:Phone 6:Renew 7:Type 8:Pay 9:Ref 13:Geo 18:County */
function normalize_2022(array $row): ?array
{
    $email = clean_email($row[4] ?? '');
    if (!$email) return null;
    return [
        'forenames'       => trim($row[1]),
        'surnames'        => trim($row[2]),
        'email'           => $email,
        'phone'           => trim($row[5] ?? ''),
        'is_renewal'      => trim($row[6] ?? ''),
        'membership_type' => trim($row[7] ?? ''),
        'payment_method'  => trim($row[8] ?? ''),
        'transaction_id'  => trim($row[9] ?? ''),
        'address'         => trim($row[3] ?? ''),
        'geologist_level' => trim($row[13] ?? ''),
        'county'          => trim($row[18] ?? ''),
    ];
}

/** 2023: 0:Ts 1:Email 2:First 3:Sur 4:County 5:Renew 6:Type 7:Pay 8:Ref 12:Geo */
function normalize_2023(array $row): ?array
{
    $email = clean_email($row[1] ?? '');
    if (!$email) return null;
    return [
        'forenames'       => trim($row[2]),
        'surnames'        => trim($row[3]),
        'email'           => $email,
        'phone'           => '',
        'is_renewal'      => trim($row[5] ?? ''),
        'membership_type' => trim($row[6] ?? ''),
        'payment_method'  => trim($row[7] ?? ''),
        'transaction_id'  => trim($row[8] ?? ''),
        'address'         => '',
        'geologist_level' => trim($row[12] ?? ''),
        'county'          => trim($row[4] ?? ''),
    ];
}

/** 2024: 0:Ts 1:Email 2:First 3:Sur 4:County 5:Renew 6:Type 7:Sugg 8:Geo(1) 9:Pay 10:Ref 14:Geo(2) */
function normalize_2024(array $row): ?array
{
    $email = clean_email($row[1] ?? '');
    if (!$email) return null;
    $geo = trim($row[8] ?? '') ?: trim($row[14] ?? '');
    return [
        'forenames'       => trim($row[2]),
        'surnames'        => trim($row[3]),
        'email'           => $email,
        'phone'           => '',
        'is_renewal'      => trim($row[5] ?? ''),
        'membership_type' => trim($row[6] ?? ''),
        'payment_method'  => trim($row[9] ?? ''),
        'transaction_id'  => trim($row[10] ?? ''),
        'address'         => '',
        'geologist_level' => $geo,
        'county'          => trim($row[4] ?? ''),
    ];
}

/** 2025/2026: 0:Ts 1:Email 2:First 3:Sur 4:County 5:Renew 6:Type 7:Geo(1) 8:Pay 9:Ref 13:Geo(2) */
function normalize_2025_2026(array $row): ?array
{
    $email = clean_email($row[1] ?? '');
    if (!$email) return null;
    $geo = trim($row[7] ?? '') ?: trim($row[13] ?? '');
    return [
        'forenames'       => trim($row[2]),
        'surnames'        => trim($row[3]),
        'email'           => $email,
        'phone'           => '',
        'is_renewal'      => trim($row[5] ?? ''),
        'membership_type' => trim($row[6] ?? ''),
        'payment_method'  => trim($row[8] ?? ''),
        'transaction_id'  => trim($row[9] ?? ''),
        'address'         => '',
        'geologist_level' => $geo,
        'county'          => trim($row[4] ?? ''),
    ];
}

function parse_plan_slug(string $raw): ?string
{
    $r = strtolower($raw);
    if (str_contains($r, 'family'))                                  return 'family';
    if (str_contains($r, 'student'))                                 return 'student';
    if (str_contains($r, 'oap') || str_contains($r, 'unemployed'))  return 'oap-unemployed';
    if (str_contains($r, 'individual'))                              return 'individual';
    return null;
}

function parse_payment_method(string $raw): string
{
    $r = strtolower($raw);
    if (str_contains($r, 'bank') || str_contains($r, 'transfer')) return 'bank_transfer';
    if (str_contains($r, 'cash') || str_contains($r, 'cheque'))   return 'cash';
    return 'bank_transfer';
}

function parse_geologist_level(string $raw): ?string
{
    $r = strtolower(trim($raw));
    if (!$r)                              return null;
    if (str_contains($r, 'professional')) return 'professional';
    if (str_contains($r, 'academic'))     return 'academic';
    if (str_contains($r, 'student'))      return 'student';
    if (str_contains($r, 'amateur'))      return 'amateur';
    return 'other';
}

function split_last_word(string $name): array
{
    $pos = strrpos($name, ' ');
    if ($pos === false) return [$name, ''];
    return [trim(substr($name, 0, $pos)), trim(substr($name, $pos + 1))];
}

function is_valid_email(string $value): bool
{
    return (bool) filter_var(trim($value), FILTER_VALIDATE_EMAIL);
}

function clean_email(string $value): ?string
{
    $e = strtolower(trim($value));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
}
