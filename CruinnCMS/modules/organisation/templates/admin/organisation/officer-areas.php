<?php
/**
 * Organisation Admin — Officer Position Area Grants
 * Configure which admin areas this position holder can access.
 */

$areasPath = \Cruinn\App::path('config/admin_areas.php');
$allAreas = file_exists($areasPath) ? require $areasPath : [];
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Area Grants — <?= e($officer['position']) ?></h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/officers" class="btn btn-secondary btn-sm">&larr; Back to Officers</a>
        </div>
    </div>

    <div class="admin-card">
        <?php if (!empty($officer['user_display_name'])): ?>
            <p class="info-banner">
                <strong>Linked account:</strong> <?= e($officer['user_display_name']) ?>
                — When this user logs in, they will have access to the areas selected below.
            </p>
        <?php else: ?>
            <p class="warning-banner">
                <strong>No linked account.</strong>
                Assign a user account to this position before granting admin area access.
            </p>
        <?php endif; ?>

        <form method="post" action="/admin/organisation/officers/<?= (int) $officer['id'] ?>/areas" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <h3>Admin Area Access</h3>
            <p class="form-hint">
                Select which admin sections this position holder can access.
                Users with this position will have editor-level access to these areas without full admin privileges.
            </p>

            <?php if (empty($allAreas)): ?>
                <p class="empty-state">No grantable admin areas configured.</p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($allAreas as $slug => $config): ?>
                        <?php
                        // Skip if module dependency not met
                        if (isset($config['module']) && !\Cruinn\Modules\ModuleRegistry::isActive($config['module'])) {
                            continue;
                        }
                        $isGranted = isset($grants[$slug]);
                        ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="areas[]" value="<?= e($slug) ?>" <?= $isGranted ? 'checked' : '' ?>>
                            <div class="checkbox-label">
                                <strong><?= e($config['name'] ?? ucfirst($slug)) ?></strong>
                                <?php if (!empty($config['description'])): ?>
                                    <small class="text-muted"><?= e($config['description']) ?></small>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Area Grants</button>
                <a href="/admin/organisation/officers" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.checkbox-item {
    display: flex;
    align-items: start;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid var(--border-color, #ddd);
    border-radius: var(--radius-md, 6px);
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-item:hover {
    border-color: var(--color-primary, #1d9e75);
    background: var(--bg-hover, #f8f9fa);
}

.checkbox-item input[type="checkbox"] {
    margin-top: 0.125rem;
    cursor: pointer;
}

.checkbox-label {
    flex: 1;
}

.checkbox-label strong {
    display: block;
    margin-bottom: 0.25rem;
}

.checkbox-label small {
    display: block;
    line-height: 1.4;
}

.info-banner, .warning-banner {
    padding: 1rem;
    border-radius: var(--radius-md, 6px);
    margin-bottom: 1.5rem;
}

.info-banner {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
}

.warning-banner {
    background: #fff3e0;
    border-left: 4px solid #ff9800;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}
</style>
