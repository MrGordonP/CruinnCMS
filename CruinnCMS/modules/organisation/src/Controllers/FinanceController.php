<?php
/**
 * CruinnCMS — Finance Controller
 *
 * Admin management of finance periods, categories, and ledger entries.
 * Routes live under /admin/organisation/finance*.
 */

namespace Cruinn\Module\Organisation\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;
use Cruinn\Database;
use Cruinn\Module\Organisation\Services\FinanceService;

class FinanceController extends BaseController
{
    private FinanceService $finance;

    public function __construct()
    {
        parent::__construct();
        $this->finance = new FinanceService($this->db);
    }

    // ══════════════════════════════════════════════════════════════════
    //  LEDGER INDEX
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/finance
     */
    public function index(): void
    {
        Auth::requireRole('admin');

        $periods = $this->db->fetchAll(
            'SELECT * FROM finance_periods ORDER BY starts_on DESC'
        );

        // Default to current period; fall back to most recent
        $activePeriod = null;
        foreach ($periods as $p) {
            if ($p['is_current']) { $activePeriod = $p; break; }
        }
        if (!$activePeriod && !empty($periods)) {
            $activePeriod = $periods[0];
        }

        $entries     = [];
        $summary     = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        $byCategory  = [];

        if ($activePeriod) {
            $periodId    = (int) $activePeriod['id'];
            $summary     = $this->finance->getSummary($periodId);
            $byCategory  = $this->finance->getByCategory($periodId);
            $entries     = $this->db->fetchAll(
                "SELECT e.*, c.name AS category_name
                 FROM finance_entries e
                 INNER JOIN finance_categories c ON e.category_id = c.id
                 WHERE e.period_id = ?
                 ORDER BY e.entry_date DESC, e.id DESC",
                [$periodId]
            );
        }

        $this->renderAdmin('admin/organisation/finance/index', [
            'title'        => 'Finance',
            'breadcrumbs'  => [
                ['Dashboard', '/admin/dashboard'],
                ['Finance'],
            ],
            'periods'      => $periods,
            'activePeriod' => $activePeriod,
            'entries'      => $entries,
            'summary'      => $summary,
            'byCategory'   => $byCategory,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  ENTRIES
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/finance/new?period_id=N
     */
    public function newEntry(): void
    {
        Auth::requireRole('admin');

        $periodId  = (int) ($_GET['period_id'] ?? 0);
        $periods   = $this->db->fetchAll('SELECT id, name FROM finance_periods ORDER BY starts_on DESC');
        $categories= $this->db->fetchAll('SELECT id, name, type FROM finance_categories ORDER BY type DESC, sort_order, name');

        $this->renderAdmin('admin/organisation/finance/form', [
            'title'      => 'New Entry',
            'breadcrumbs'=> [
                ['Dashboard', '/admin/dashboard'],
                ['Finance', '/admin/organisation/finance'],
                ['New Entry'],
            ],
            'entry'      => [],
            'periods'    => $periods,
            'categories' => $categories,
            'periodId'   => $periodId,
        ]);
    }

    /**
     * POST /admin/organisation/finance/create
     */
    public function createEntry(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $data = $this->collectEntryData();
        $this->db->insert('finance_entries', array_merge($data, [
            'source_type' => 'manual',
            'source_id'   => null,
            'recorded_by' => Auth::user()['id'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]));

        $this->redirect('/admin/organisation/finance?period_id=' . $data['period_id'] . '&msg=created');
    }

    /**
     * GET /admin/organisation/finance/edit/{id}
     */
    public function editEntry(int $id): void
    {
        Auth::requireRole('admin');

        $entry = $this->db->fetch('SELECT * FROM finance_entries WHERE id = ?', [$id]);
        if (!$entry) { $this->notFound(); return; }

        $periods    = $this->db->fetchAll('SELECT id, name FROM finance_periods ORDER BY starts_on DESC');
        $categories = $this->db->fetchAll('SELECT id, name, type FROM finance_categories ORDER BY type DESC, sort_order, name');

        $this->renderAdmin('admin/organisation/finance/form', [
            'title'      => 'Edit Entry',
            'breadcrumbs'=> [
                ['Dashboard', '/admin/dashboard'],
                ['Finance', '/admin/organisation/finance'],
                ['Edit Entry'],
            ],
            'entry'      => $entry,
            'periods'    => $periods,
            'categories' => $categories,
            'periodId'   => (int) $entry['period_id'],
        ]);
    }

    /**
     * POST /admin/organisation/finance/update/{id}
     */
    public function updateEntry(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $entry = $this->db->fetch('SELECT id, period_id FROM finance_entries WHERE id = ?', [$id]);
        if (!$entry) { $this->notFound(); return; }

        $data = $this->collectEntryData();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->update('finance_entries', $data, 'id = ?', [$id]);

        $this->redirect('/admin/organisation/finance?period_id=' . $data['period_id'] . '&msg=updated');
    }

    /**
     * POST /admin/organisation/finance/delete/{id}
     */
    public function deleteEntry(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $entry = $this->db->fetch('SELECT id, period_id FROM finance_entries WHERE id = ?', [$id]);
        if (!$entry) { $this->notFound(); return; }

        $this->db->execute('DELETE FROM finance_entries WHERE id = ?', [$id]);
        $this->redirect('/admin/organisation/finance?period_id=' . $entry['period_id'] . '&msg=deleted');
    }

    // ══════════════════════════════════════════════════════════════════
    //  INGEST
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /admin/organisation/finance/ingest
     */
    public function ingest(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $periodId = (int) $this->input('period_id', 0);
        if (!$periodId) {
            $this->redirect('/admin/organisation/finance?msg=no_period');
            return;
        }

        $mp = $this->finance->ingestMembershipPayments($periodId);
        $fp = $this->finance->ingestFormPayments($periodId);

        $this->redirect('/admin/organisation/finance?period_id=' . $periodId
            . '&msg=ingested&mp=' . $mp . '&fp=' . $fp);
    }

    // ══════════════════════════════════════════════════════════════════
    //  EXPORT
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/finance/export/{periodId}
     */
    public function exportCsv(int $periodId): void
    {
        Auth::requireRole('admin');

        $period = $this->db->fetch('SELECT name FROM finance_periods WHERE id = ?', [$periodId]);
        $filename = 'finance-' . ($period ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($period['name'])) : $periodId) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->finance->exportCsv($periodId);
        exit;
    }

    // ══════════════════════════════════════════════════════════════════
    //  PERIODS
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/organisation/finance/periods
     */
    public function periods(): void
    {
        Auth::requireRole('admin');

        $periods = $this->db->fetchAll('SELECT * FROM finance_periods ORDER BY starts_on DESC');

        $this->renderAdmin('admin/organisation/finance/periods', [
            'title'      => 'Finance Periods',
            'breadcrumbs'=> [
                ['Dashboard', '/admin/dashboard'],
                ['Finance', '/admin/organisation/finance'],
                ['Periods'],
            ],
            'periods'    => $periods,
        ]);
    }

    /**
     * POST /admin/organisation/finance/periods/create
     */
    public function createPeriod(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $name      = trim($this->input('name', ''));
        $startsOn  = $this->input('starts_on', '');
        $endsOn    = $this->input('ends_on', '');
        $isCurrent = $this->input('is_current', '0') ? 1 : 0;
        $notes     = trim($this->input('notes', '')) ?: null;

        if (!$name || !$startsOn || !$endsOn) {
            $this->redirect('/admin/organisation/finance/periods?msg=invalid');
            return;
        }

        if ($isCurrent) {
            // Only one current period at a time
            $this->db->execute('UPDATE finance_periods SET is_current = 0');
        }

        $this->db->insert('finance_periods', [
            'name'       => $name,
            'starts_on'  => $startsOn,
            'ends_on'    => $endsOn,
            'is_current' => $isCurrent,
            'notes'      => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->redirect('/admin/organisation/finance/periods?msg=created');
    }

    /**
     * POST /admin/organisation/finance/periods/set-current/{id}
     */
    public function setCurrentPeriod(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $this->db->execute('UPDATE finance_periods SET is_current = 0');
        $this->db->execute('UPDATE finance_periods SET is_current = 1 WHERE id = ?', [$id]);

        $this->redirect('/admin/organisation/finance/periods?msg=updated');
    }

    // ══════════════════════════════════════════════════════════════════
    //  INTERNAL HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function collectEntryData(): array
    {
        $type = in_array($this->input('type'), ['income', 'expense']) ? $this->input('type') : 'expense';

        return [
            'period_id'   => (int) $this->input('period_id', 0),
            'category_id' => (int) $this->input('category_id', 0),
            'type'        => $type,
            'amount'      => round((float) $this->input('amount', '0'), 2),
            'currency'    => strtoupper(preg_replace('/[^A-Z]/i', '', $this->input('currency', 'EUR'))) ?: 'EUR',
            'description' => trim($this->input('description', '')),
            'reference'   => trim($this->input('reference', '')) ?: null,
            'entry_date'  => $this->input('entry_date', date('Y-m-d')),
        ];
    }
}
