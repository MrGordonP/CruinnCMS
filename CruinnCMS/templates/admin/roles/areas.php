<?php
/**
 * Admin Area Access Configuration for a Role
 *
 * Variables:
 * - $role: Role record
 * - $availableAreas: Array of area configs from config/admin_areas.php
 * - $grantedSlugs: Array of currently granted area slugs for this role
 */
$roleId = (int) $role['id'];
\Cruinn\Template::requireCss('admin-acp.css');
?>

<div class="admin-page-header">
    <h1>Admin Area Access — <?= e($role['name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/roles/<?= $roleId ?>/dashboard" class="btn btn-outline btn-small">Dashboard</a>
        <a href="/admin/roles/<?= $roleId ?>/navigation" class="btn btn-outline btn-small">Navigation</a>
        <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline btn-small">Edit Role</a>
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>

<?php if ((int) $role['level'] >= 100): ?>
    <div class="alert alert-info">
        <strong>Admin Role:</strong> This role has full access to all admin areas.
        Area grants cannot be configured for admin-level roles.
    </div>
    <p><a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-primary">Back to Role Settings</a></p>

<?php else: ?>
    <p class="text-muted" style="margin-bottom: var(--space-lg);">
        Grant access to specific admin sections for the <strong><?= e($role['name']) ?></strong> role.
        Users with this role will be able to access only the selected areas without full admin privileges.
    </p>

    <?php if (empty($availableAreas)): ?>
        <p class="text-muted"><em>No grantable areas defined. Check <code>config/admin_areas.php</code>.</em></p>
    <?php else: ?>
        <form method="post" action="/admin/roles/<?= $roleId ?>/areas">
            <?= csrf_field() ?>

            <div class="form-section">
                <h3>Available Admin Areas</h3>
                <div class="area-grants-list">
                    <?php foreach ($availableAreas as $slug => $config): ?>
                        <?php
                        // Skip module areas if module is inactive
                        if (!empty($config['module'])) {
                            // @TODO: check if module is active
                            // For now, show all areas
                        }

                        $isGranted = in_array($slug, $grantedSlugs, true);
                        ?>
                        <label class="area-grant-checkbox">
                            <input type="checkbox"
                                   name="areas[]"
                                   value="<?= e($slug) ?>"
                                   <?= $isGranted ? 'checked' : '' ?>>
                            <div class="area-grant-info">
                                <strong><?= e($config['name']) ?></strong>
                                <span class="text-muted"><?= e($config['description']) ?></span>
                                <?php if (!empty($config['module'])): ?>
                                    <span class="badge badge-module">Module: <?= e($config['module']) ?></span>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Area Access</button>
                <a href="/admin/roles/<?= $roleId ?>/edit" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

<style>
.area-grants-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    max-width: 800px;
}

.area-grant-checkbox {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    padding: var(--space-md);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
}

.area-grant-checkbox:hover {
    background: var(--color-bg);
    border-color: var(--color-primary);
}

.area-grant-checkbox input[type="checkbox"] {
    margin-top: 0.25rem;
    cursor: pointer;
}

.area-grant-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.area-grant-info strong {
    font-size: 1rem;
    color: var(--color-text);
}

.area-grant-info .text-muted {
    font-size: 0.9rem;
}

.badge-module {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
    background: var(--color-warning-soft);
    color: var(--color-warning-dark);
    border-radius: var(--radius-sm);
    margin-top: 0.25rem;
}
</style>
