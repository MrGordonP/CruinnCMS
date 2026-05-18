<?php
/**
 * Organisation Admin — Officer Position Dashboard Canvas
 * Assign a widget dashboard to this position.
 */

$dashboardService = new \Cruinn\Services\DashboardService();
$canvases = $dashboardService->listDashboardCanvases();
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Dashboard Canvas — <?= e($officer['position']) ?></h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/officers" class="btn btn-secondary btn-sm">&larr; Back to Officers</a>
        </div>
    </div>

    <div class="admin-card">
        <?php if (!empty($officer['user_display_name'])): ?>
            <p class="info-banner">
                <strong>Linked account:</strong> <?= e($officer['user_display_name']) ?>
                — When this user logs in, they will see the dashboard selected below.
            </p>
        <?php else: ?>
            <p class="warning-banner">
                <strong>No linked account.</strong> 
                Assign a user account to this position before setting a dashboard.
            </p>
        <?php endif; ?>

        <form method="post" action="/admin/organisation/officers/<?= (int) $officer['id'] ?>/dashboard" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-group">
                <label for="dashboard_id">Widget Dashboard Canvas</label>
                <select name="dashboard_id" id="dashboard_id" class="form-select">
                    <option value="">— None (use role default) —</option>
                    <?php foreach ($canvases as $canvas): ?>
                        <option value="<?= (int) $canvas['id'] ?>" <?= $assignedDashboardId === $canvas['id'] ? 'selected' : '' ?>>
                            <?= e($canvas['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">
                    Position dashboards override role dashboards. Leave blank to use the user's role default dashboard.
                </small>
            </div>

            <?php if ($assignedDashboardId): ?>
                <div class="form-group">
                    <label>Current Dashboard</label>
                    <p>
                        <a href="/admin/site-builder/editor/dashboard/<?= (int) $assignedDashboardId ?>" 
                           class="btn btn-outline btn-sm" target="_blank">Edit Blocks &rarr;</a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Dashboard Assignment</button>
                <a href="/admin/organisation/officers" class="btn btn-outline">Cancel</a>
            </div>
        </form>

        <?php if (empty($canvases)): ?>
            <div class="help-section">
                <h3>No Widget Dashboards Created</h3>
                <p>Create widget dashboard canvases at <a href="/admin/site-builder/dashboards">Site Builder → Dashboards</a>.</p>
                <ol>
                    <li>Click "Create New Dashboard"</li>
                    <li>Add <code>Module Widget</code> blocks and layout blocks</li>
                    <li>Publish the dashboard</li>
                    <li>Return here to assign it to this position</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.info-banner {
    padding: 1rem;
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
    border-radius: var(--radius-md, 6px);
    margin-bottom: 1.5rem;
}

.warning-banner {
    padding: 1rem;
    background: #fff3e0;
    border-left: 4px solid #ff9800;
    border-radius: var(--radius-md, 6px);
    margin-bottom: 1.5rem;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

.help-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--bg-subtle, #f8f9fa);
    border-radius: var(--radius-md, 6px);
}

.help-section h3 {
    margin-top: 0;
}

.help-section ol {
    margin-bottom: 0;
}

.form-select {
    padding: var(--space-sm, 0.5rem);
}
</style>
