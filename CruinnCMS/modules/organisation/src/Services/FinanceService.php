<?php
/**
 * CruinnCMS — Finance Service
 *
 * Handles finance summaries, category breakdowns, CSV export,
 * and idempotent ingestion from membership and form payments.
 */

namespace Cruinn\Module\Organisation\Services;

use Cruinn\Database;

class FinanceService
{
    public function __construct(private Database $db) {}

    // ── Summary ───────────────────────────────────────────────────────

    /**
     * Returns ['income' => float, 'expense' => float, 'balance' => float]
     */
    public function getSummary(int $periodId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT type, SUM(amount) AS total
             FROM finance_entries
             WHERE period_id = ?
             GROUP BY type",
            [$periodId]
        );

        $income  = 0.0;
        $expense = 0.0;
        foreach ($rows as $row) {
            if ($row['type'] === 'income')  { $income  = (float) $row['total']; }
            if ($row['type'] === 'expense') { $expense = (float) $row['total']; }
        }

        return [
            'income'  => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ];
    }

    /**
     * Returns per-category totals for a period.
     */
    public function getByCategory(int $periodId): array
    {
        return $this->db->fetchAll(
            "SELECT c.name, c.type, COALESCE(SUM(e.amount), 0) AS total
             FROM finance_categories c
             LEFT JOIN finance_entries e
                ON e.category_id = c.id AND e.period_id = ?
             GROUP BY c.id
             ORDER BY c.type DESC, c.sort_order, c.name",
            [$periodId]
        );
    }

    // ── Ingest ────────────────────────────────────────────────────────

    /**
     * Import completed membership payments into finance_entries.
     * Idempotent — skips rows already present by source_id.
     *
     * @return int Number of new entries created
     */
    public function ingestMembershipPayments(int $periodId): int
    {
        $period = $this->db->fetch(
            'SELECT starts_on, ends_on FROM finance_periods WHERE id = ?',
            [$periodId]
        );
        if (!$period) { return 0; }

        $category = $this->db->fetch(
            "SELECT id FROM finance_categories WHERE name = 'Membership Fees' LIMIT 1"
        );
        $categoryId = $category ? (int) $category['id'] : null;
        if (!$categoryId) { return 0; }

        // Find already-ingested IDs to avoid double-counting
        $existing = $this->db->fetchAll(
            "SELECT source_id FROM finance_entries
             WHERE period_id = ? AND source_type = 'membership_payment'",
            [$periodId]
        );
        $existingIds = array_column($existing, 'source_id');

        $payments = $this->db->fetchAll(
            "SELECT mp.id, mp.amount, mp.currency, mp.reference, mp.paid_at,
                    u.display_name AS member_name
             FROM membership_payments mp
             LEFT JOIN users u ON mp.member_id = u.id
             WHERE mp.status = 'completed'
               AND mp.paid_at BETWEEN ? AND ?",
            [$period['starts_on'] . ' 00:00:00', $period['ends_on'] . ' 23:59:59']
        );

        $inserted = 0;
        foreach ($payments as $p) {
            if (in_array((int) $p['id'], $existingIds, true)) { continue; }

            $this->db->insert('finance_entries', [
                'period_id'   => $periodId,
                'category_id' => $categoryId,
                'type'        => 'income',
                'amount'      => $p['amount'],
                'currency'    => $p['currency'] ?? 'EUR',
                'description' => 'Membership payment' . ($p['member_name'] ? ' — ' . $p['member_name'] : ''),
                'reference'   => $p['reference'],
                'entry_date'  => substr($p['paid_at'], 0, 10),
                'source_type' => 'membership_payment',
                'source_id'   => (int) $p['id'],
                'recorded_by' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Import verified form payments into finance_entries.
     * Idempotent — skips rows already present by source_id.
     *
     * @return int Number of new entries created
     */
    public function ingestFormPayments(int $periodId): int
    {
        $period = $this->db->fetch(
            'SELECT starts_on, ends_on FROM finance_periods WHERE id = ?',
            [$periodId]
        );
        if (!$period) { return 0; }

        $category = $this->db->fetch(
            "SELECT id FROM finance_categories WHERE name = 'Other Income' LIMIT 1"
        );
        $categoryId = $category ? (int) $category['id'] : null;
        if (!$categoryId) { return 0; }

        $existing = $this->db->fetchAll(
            "SELECT source_id FROM finance_entries
             WHERE period_id = ? AND source_type = 'form_payment'",
            [$periodId]
        );
        $existingIds = array_column($existing, 'source_id');

        // form_submissions joined with form_payment_options to get the amount
        $submissions = $this->db->fetchAll(
            "SELECT fs.id, fpo.amount, fpo.currency, fs.submitted_at
             FROM form_submissions fs
             INNER JOIN form_payment_options fpo ON fpo.id = fs.payment_option_id
             WHERE fs.payment_status = 'verified'
               AND fs.submitted_at BETWEEN ? AND ?",
            [$period['starts_on'] . ' 00:00:00', $period['ends_on'] . ' 23:59:59']
        );

        $inserted = 0;
        foreach ($submissions as $s) {
            if (in_array((int) $s['id'], $existingIds, true)) { continue; }

            $this->db->insert('finance_entries', [
                'period_id'   => $periodId,
                'category_id' => $categoryId,
                'type'        => 'income',
                'amount'      => $s['amount'],
                'currency'    => $s['currency'] ?? 'EUR',
                'description' => 'Form payment',
                'reference'   => null,
                'entry_date'  => substr($s['submitted_at'], 0, 10),
                'source_type' => 'form_payment',
                'source_id'   => (int) $s['id'],
                'recorded_by' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        return $inserted;
    }

    // ── Export ────────────────────────────────────────────────────────

    /**
     * Returns CSV string of all entries in a period.
     */
    public function exportCsv(int $periodId): string
    {
        $entries = $this->db->fetchAll(
            "SELECT e.entry_date, e.type, c.name AS category, e.amount, e.currency,
                    e.description, e.reference, e.source_type
             FROM finance_entries e
             INNER JOIN finance_categories c ON e.category_id = c.id
             WHERE e.period_id = ?
             ORDER BY e.entry_date, e.type DESC",
            [$periodId]
        );

        $out = fopen('php://memory', 'r+');
        fputcsv($out, ['Date', 'Type', 'Category', 'Amount', 'Currency', 'Description', 'Reference', 'Source']);
        foreach ($entries as $row) {
            fputcsv($out, [
                $row['entry_date'],
                $row['type'],
                $row['category'],
                number_format((float) $row['amount'], 2, '.', ''),
                $row['currency'],
                $row['description'],
                $row['reference'] ?? '',
                $row['source_type'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}
