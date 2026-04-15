<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Modules</h2>

<?php if (empty($modules)): ?>
<div class="acp-empty-state">
    <p>No modules found. Drop a module folder into <code>modules/</code> to get started.</p>
</div>
<?php else: ?>

<div class="module-list">
<?php foreach ($modules as $slug => $m):
    $def        = $m['def'];
    $status     = $m['status'];
    $settings   = $m['settings'];
    $mig        = $m['migrations'];
    $isActive   = ($status === 'active');
    $isOffline  = ($status === 'offline');
    $isNew      = ($status === 'discovered');
    $statusLabel = $isActive  ? 'Active'   : ($isOffline ? 'Offline' : 'New');
    $statusClass = $isActive  ? 'status-active' : ($isOffline ? 'status-offline' : 'status-new');
?>
<div class="module-card <?= $statusClass ?>">
    <div class="module-card-header">
        <div class="module-card-title">
            <h3><?= e($def['name']) ?> <span class="module-version">v<?= e($def['version']) ?></span></h3>
            <span class="module-status-badge <?= $statusClass ?>"><?= e($statusLabel) ?></span>
        </div>
        <div class="module-card-actions">
            <?php if ($mig['pending'] > 0 && $isActive): ?>
            <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/migrate') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning btn-small"
                        onclick="return confirm('Apply <?= (int)$mig['pending'] ?> pending migration(s) for <?= e($def['name']) ?>?')">
                    ⬆ Apply Migrations (<?= (int)$mig['pending'] ?>)
                </button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/toggle') ?>" style="display:inline">
                <?= csrf_field() ?>
                <?php if ($isActive): ?>
                    <button type="submit" class="btn btn-secondary btn-small"
                            onclick="return confirm('Take <?= e($def['name']) ?> offline? Its routes will be disabled.')">
                        ⏸ Take Offline
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-small">
                        ▶ <?= $isNew ? 'Activate' : 'Bring Online' ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($def['description'])): ?>
    <p class="module-description"><?= e($def['description']) ?></p>
    <?php endif; ?>

    <div class="module-meta">
        <?php if (!empty($def['dependencies'])): ?>
        <span class="module-meta-item">
            Depends on: <?php foreach ($def['dependencies'] as $i => $dep):
                $depStatus = $modules[$dep]['status'] ?? 'missing';
                $depOk = ($depStatus === 'active');
            ?><span class="dep-badge <?= $depOk ? 'dep-ok' : 'dep-missing' ?>"><?= e($dep) ?></span><?php
            endforeach; ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($def['provides'])): ?>
        <span class="module-meta-item">
            Provides: <?php foreach ($def['provides'] as $cap): ?><code><?= e($cap) ?></code> <?php endforeach; ?>
        </span>
        <?php endif; ?>
        <?php if ($mig['total'] > 0): ?>
        <span class="module-meta-item migrations-meta <?= $mig['pending'] > 0 ? 'has-pending' : '' ?>">
            Migrations: <?= (int)$mig['applied'] ?>/<?= (int)$mig['total'] ?> applied
            <?= $mig['pending'] > 0 ? ' — <strong>' . (int)$mig['pending'] . ' pending</strong>' : '' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (!empty($def['settings_schema'])): ?>
    <details class="module-settings-panel" <?= ($isNew || !empty($settings)) ? 'open' : '' ?>>
        <summary>Settings</summary>
        <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/settings') ?>" class="module-settings-form">
            <?= csrf_field() ?>
            <?php foreach ($def['settings_schema'] as $field):
                $key      = $field['key']         ?? '';
                $type     = $field['type']         ?? 'text';
                $label    = $field['label']        ?? $key;
                $hint     = $field['hint']         ?? '';
                $opts     = $field['options']      ?? [];
                $val      = $settings[$key]        ?? ($field['default'] ?? '');
            ?>
            <div class="form-group">
                <?php if ($type === 'checkbox'): ?>
                <label class="form-checkbox">
                    <input type="checkbox" name="<?= e($key) ?>" value="1"<?= $val ? ' checked' : '' ?>>
                    <?= e($label) ?>
                </label>
                <?php elseif ($type === 'select' && !empty($opts)): ?>
                <label class="form-label" for="ms_<?= e($slug . '_' . $key) ?>"><?= e($label) ?></label>
                <select name="<?= e($key) ?>" id="ms_<?= e($slug . '_' . $key) ?>" class="form-input">
                    <?php foreach ($opts as $optVal => $optLabel): ?>
                    <option value="<?= e($optVal) ?>"<?= $val == $optVal ? ' selected' : '' ?>><?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php elseif ($type === 'textarea'): ?>
                <label class="form-label" for="ms_<?= e($slug . '_' . $key) ?>"><?= e($label) ?></label>
                <textarea name="<?= e($key) ?>" id="ms_<?= e($slug . '_' . $key) ?>" class="form-input" rows="4"><?= e($val) ?></textarea>
                <?php else: ?>
                <label class="form-label" for="ms_<?= e($slug . '_' . $key) ?>"><?= e($label) ?></label>
                <input type="<?= e($type) ?>" name="<?= e($key) ?>" id="ms_<?= e($slug . '_' . $key) ?>"
                       class="form-input" value="<?= e($val) ?>">
                <?php endif; ?>
                <?php if ($hint): ?><small class="form-hint"><?= e($hint) ?></small><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.module-list { display: flex; flex-direction: column; gap: 1rem; }
.module-card { background: var(--acp-card-bg, #fff); border: 1px solid var(--border-color, #ddd); border-radius: 6px; padding: 1.25rem; }
.module-card.status-active  { border-left: 4px solid #22c55e; }
.module-card.status-offline { border-left: 4px solid #94a3b8; }
.module-card.status-new     { border-left: 4px solid #f59e0b; }
.module-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.module-card-title  { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.module-card-title h3 { margin: 0; font-size: 1rem; }
.module-version { font-size: 0.8rem; color: var(--text-muted, #6b7280); font-weight: normal; }
.module-card-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.module-status-badge { font-size: 0.75rem; padding: 0.15rem 0.55rem; border-radius: 999px; font-weight: 600; }
.module-status-badge.status-active  { background: #dcfce7; color: #166534; }
.module-status-badge.status-offline { background: #f1f5f9; color: #475569; }
.module-status-badge.status-new     { background: #fef3c7; color: #92400e; }
.module-description { color: var(--text-muted, #6b7280); font-size: 0.9rem; margin: 0.35rem 0 0.6rem; }
.module-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.82rem; color: var(--text-muted, #6b7280); margin-bottom: 0.5rem; }
.module-meta-item { display: flex; align-items: center; gap: 0.3rem; }
.dep-badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.78rem; }
.dep-ok     { background: #dcfce7; color: #166534; }
.dep-missing { background: #fee2e2; color: #991b1b; }
.migrations-meta.has-pending { color: #d97706; font-weight: 600; }
.module-settings-panel { margin-top: 0.75rem; border-top: 1px solid var(--border-color, #e5e7eb); padding-top: 0.75rem; }
.module-settings-panel summary { cursor: pointer; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.75rem; }
.module-settings-form { display: grid; gap: 0.75rem; max-width: 480px; }
.acp-empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted, #6b7280); }
</style>

<?php include __DIR__ . '/_tabs_end.php'; ?>
