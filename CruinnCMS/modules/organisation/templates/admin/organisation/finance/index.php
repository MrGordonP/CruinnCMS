<?php
/**
 * Finance Admin — Ledger Index
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Finance</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/finance/periods" class="btn btn-secondary btn-sm">Periods</a>
            <?php if ($activePeriod): ?>
                <a href="/admin/organisation/finance/export/<?= (int) $activePeriod['id'] ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            <?php endif; ?>
            <?php if ($activePeriod): ?>
                <a href="/admin/organisation/finance/new?period_id=<?= (int) $activePeriod['id'] ?>" class="btn btn-primary btn-sm">+ New Entry</a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $msg = $_GET['msg'] ?? '';
    if ($msg === 'created')  echo '<div class="alert alert-success">Entry added.</div>';
    if ($msg === 'updated')  echo '<div class="alert alert-success">Entry updated.</div>';
    if ($msg === 'deleted')  echo '<div class="alert alert-success">Entry deleted.</div>';
    if ($msg === 'ingested') {
        $mp = (int) ($_GET['mp'] ?? 0);
        $fp = (int) ($_GET['fp'] ?? 0);
        echo '<div class="alert alert-success">Imported ' . $mp . ' membership payment(s) and ' . $fp . ' form payment(s).</div>';
    }
    ?>

    <!-- Period selector -->
    <?php if (!empty($periods)): ?>
    <div class="admin-card">
        <form method="get" action="/admin/organisation/finance" class="admin-form-inline">
            <div class="form-group">
                <label for="period_id">Period</label>
                <select name="period_id" id="period_id" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"
                            <?= $activePeriod && $p['id'] == $activePeriod['id'] ? 'selected' : '' ?>>
                            <?= $this->escape($p['name']) ?><?= $p['is_current'] ? ' (current)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div class="admin-card">
            <p>No finance periods yet. <a href="/admin/organisation/finance/periods">Create one</a> to start recording entries.</p>
        </div>
    <?php endif; ?>

    <?php if ($activePeriod): ?>

    <!-- Summary -->
    <div class="admin-card">
        <h2>Summary — <?= $this->escape($activePeriod['name']) ?></h2>
        <div class="stats-row">
            <div class="stat">
                <span class="stat-label">Income</span>
                <span class="stat-value">€<?= number_format($summary['income'], 2) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Expenses</span>
                <span class="stat-value">€<?= number_format($summary['expense'], 2) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Balance</span>
                <span class="stat-value <?= $summary['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    €<?= number_format(abs($summary['balance']), 2) ?><?= $summary['balance'] < 0 ? ' deficit' : '' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Ingest -->
    <div class="admin-card">
        <h2>Import from Other Modules</h2>
        <p>Idempotent — already-ingested records are skipped.</p>
        <form method="post" action="/admin/organisation/finance/ingest" class="admin-form-inline">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
            <input type="hidden" name="period_id" value="<?= (int) $activePeriod['id'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Import membership &amp; form payments</button>
        </form>
    </div>

    <!-- Ledger -->
    <div class="admin-card">
        <h2>Entries</h2>
        <?php if (empty($entries)): ?>
            <p>No entries for this period.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Ref</th>
                    <th class="text-right">Amount</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?= $this->escape($e['entry_date']) ?></td>
                    <td><?= $this->escape($e['type']) ?></td>
                    <td><?= $this->escape($e['category_name']) ?></td>
                    <td><?= $this->escape($e['description']) ?></td>
                    <td><?= $this->escape($e['reference'] ?? '') ?></td>
                    <td class="text-right"><?= $this->escape($e['currency']) ?> <?= number_format((float) $e['amount'], 2) ?></td>
                    <td><small><?= $this->escape($e['source_type']) ?></small></td>
                    <td class="actions">
                        <a href="/admin/organisation/finance/edit/<?= (int) $e['id'] ?>" class="btn btn-sm">Edit</a>
                        <form method="post" action="/admin/organisation/finance/delete/<?= (int) $e['id'] ?>"
                              onsubmit="return confirm('Delete this entry?')" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
