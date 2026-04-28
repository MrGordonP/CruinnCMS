<?php
/**
 * Finance Admin — Periods
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Finance Periods</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/finance" class="btn btn-secondary btn-sm">← Ledger</a>
        </div>
    </div>

    <?php
    $msg = $_GET['msg'] ?? '';
    if ($msg === 'created') echo '<div class="alert alert-success">Period created.</div>';
    if ($msg === 'updated') echo '<div class="alert alert-success">Current period updated.</div>';
    if ($msg === 'invalid') echo '<div class="alert alert-error">Please fill in all required fields.</div>';
    ?>

    <!-- Existing periods -->
    <?php if (!empty($periods)): ?>
    <div class="admin-card">
        <h2>Existing Periods</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Current</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['starts_on']) ?></td>
                    <td><?= e($p['ends_on']) ?></td>
                    <td><?= $p['is_current'] ? '✓' : '' ?></td>
                    <td><?= e($p['notes'] ?? '') ?></td>
                    <td class="actions">
                        <?php if (!$p['is_current']): ?>
                        <form method="post" action="/admin/organisation/finance/periods/set-current/<?= (int) $p['id'] ?>" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <button type="submit" class="btn btn-sm">Set Current</button>
                        </form>
                        <?php endif; ?>
                        <a href="/admin/organisation/finance?period_id=<?= (int) $p['id'] ?>" class="btn btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Add period -->
    <div class="admin-card">
        <h2>New Period</h2>
        <form method="post" action="/admin/organisation/finance/periods/create" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name" class="form-input" required
                           placeholder="e.g. 2025-2026">
                </div>
                <div class="form-group">
                    <label for="starts_on">Start Date <span class="required">*</span></label>
                    <input type="date" name="starts_on" id="starts_on" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="ends_on">End Date <span class="required">*</span></label>
                    <input type="date" name="ends_on" id="ends_on" class="form-input" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-check-label">
                        <input type="checkbox" name="is_current" value="1">
                        Set as current period
                    </label>
                </div>
            </div>

            <div class="form-group form-group-full">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-input" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Period</button>
            </div>
        </form>
    </div>
</div>
