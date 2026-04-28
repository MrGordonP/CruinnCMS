<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Modules</h2>

<?php
$allInstalled = array_merge($installed ?? [], []);
$allAvailable = array_merge($available ?? [], []);
?>

<!-- ── Installed Modules ─────────────────────────────────────────────── -->
<section class="module-section">
    <h3 class="module-section-title">Installed Modules</h3>

    <?php if (empty($allInstalled)): ?>
    <div class="acp-empty-state">
        <p>No modules installed yet. Install one from the list below.</p>
    </div>
    <?php else: ?>
    <div class="module-list">
    <?php foreach ($allInstalled as $slug => $m):
        $def      = $m['def'];
        $status   = $m['status'];
        $settings = $m['settings'];
        $mig      = $m['migrations'];
        $isActive = ($status === 'active');
    ?>
    <div class="module-card <?= $isActive ? 'status-active' : 'status-offline' ?>">
        <div class="module-card-header">
            <div class="module-card-title">
                <h4><?= e($def['name']) ?> <span class="module-version">v<?= e($def['version']) ?></span></h4>
                <span class="module-status-badge <?= $isActive ? 'status-active' : 'status-offline' ?>"><?= $isActive ? 'Active' : 'Offline' ?></span>
            </div>
            <div class="module-card-actions">
                <?php if ($mig['pending'] > 0): ?>
                <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/migrate') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-warning btn-small"
                            data-confirm="Apply <?= (int)$mig['pending'] ?> pending migration(s) for <?= e($def['name']) ?>?">
                        ⬆ Migrations (<?= (int)$mig['pending'] ?> pending)
                    </button>
                </form>
                <?php endif; ?>
                <?php if ($mig['total'] > 0): ?>
                <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/remigrate') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-small"
                            data-confirm="Re-run ALL <?= (int)$mig['total'] ?> migration(s) for <?= e($def['name']) ?>? Migration records will be cleared and all SQL re-applied. Safe for IF NOT EXISTS migrations.">
                        ↺ Re-run Migrations
                    </button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/toggle') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <?php if ($isActive): ?>
                        <button type="submit" class="btn btn-secondary btn-small"
                                data-confirm="Take <?= e($def['name']) ?> offline? Its routes will be disabled.">
                            ⏸ Take Offline
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-small">▶ Bring Online</button>
                    <?php endif; ?>
                </form>
                <button type="button" class="btn btn-danger btn-small"
                        data-show-id="uninstall-<?= e($slug) ?>">
                    🗑 Uninstall
                </button>
            </div>
        </div>

        <?php if (!empty($def['description'])): ?>
        <p class="module-description"><?= e($def['description']) ?></p>
        <?php endif; ?>

        <div class="module-meta">
            <?php if (!empty($def['dependencies'])): ?>
            <span class="module-meta-item">Depends on:
                <?php foreach ($def['dependencies'] as $dep):
                    $depOk = isset($allInstalled[$dep]) && $allInstalled[$dep]['status'] === 'active';
                ?><span class="dep-badge <?= $depOk ? 'dep-ok' : 'dep-missing' ?>"><?= e($dep) ?></span><?php
                endforeach; ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($def['provides'])): ?>
            <span class="module-meta-item">Provides: <?php foreach ($def['provides'] as $cap): ?><code><?= e($cap) ?></code> <?php endforeach; ?></span>
            <?php endif; ?>
            <?php if ($mig['total'] > 0): ?>
            <span class="module-meta-item migrations-meta <?= $mig['pending'] > 0 ? 'has-pending' : '' ?>">
                Migrations: <?= (int)$mig['applied'] ?>/<?= (int)$mig['total'] ?> applied<?= $mig['pending'] > 0 ? ' — <strong>' . (int)$mig['pending'] . ' pending</strong>' : '' ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Uninstall confirmation panel -->
        <div id="uninstall-<?= e($slug) ?>" class="uninstall-panel" style="display:none">
            <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/uninstall') ?>">
                <?= csrf_field() ?>
                <p class="uninstall-warning">⚠ Uninstalling <strong><?= e($def['name']) ?></strong> will deactivate it and remove it from the installed list. The module folder and its database tables are preserved.</p>
                <label class="form-checkbox">
                    <input type="checkbox" name="drop_migrations" value="1">
                    Also remove migration records (tables are kept, but migrations can be re-applied)
                </label>
                <div class="uninstall-actions">
                    <button type="submit" class="btn btn-danger btn-small">Confirm Uninstall</button>
                    <button type="button" class="btn btn-secondary btn-small"
                            data-hide-id="uninstall-<?= e($slug) ?>">Cancel</button>
                </div>
            </form>
        </div>

        <?php if (!empty($def['settings_schema'])): ?>
        <details class="module-settings-panel" <?= !empty($settings) ? 'open' : '' ?>>
            <summary>Settings</summary>
            <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/settings') ?>" class="module-settings-form">
                <?= csrf_field() ?>
                <?php foreach ($def['settings_schema'] as $field):
                    $key  = $field['key']    ?? '';
                    $type = $field['type']   ?? 'text';
                    $lbl  = $field['label']  ?? $key;
                    $hint = $field['hint']   ?? '';
                    $opts = $field['options'] ?? [];
                    $val  = $settings[$key]  ?? ($field['default'] ?? '');
                ?>
                <div class="form-group">
                    <?php if ($type === 'checkbox'): ?>
                    <label class="form-checkbox"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= $val ? ' checked' : '' ?>> <?= e($lbl) ?></label>
                    <?php elseif ($type === 'select' && !empty($opts)): ?>
                    <label class="form-label"><?= e($lbl) ?></label>
                    <select name="<?= e($key) ?>" class="form-input">
                        <?php foreach ($opts as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= $val == $ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
                    </select>
                    <?php elseif ($type === 'textarea'): ?>
                    <label class="form-label"><?= e($lbl) ?></label>
                    <textarea name="<?= e($key) ?>" class="form-input" rows="4"><?= e($val) ?></textarea>
                    <?php else: ?>
                    <label class="form-label"><?= e($lbl) ?></label>
                    <input type="<?= e($type) ?>" name="<?= e($key) ?>" class="form-input" value="<?= e($val) ?>">
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
</section>

<!-- ── Available Modules ─────────────────────────────────────────────── -->
<section class="module-section" style="margin-top:2rem">
    <h3 class="module-section-title">Available Modules</h3>

    <?php if (empty($allAvailable)): ?>
    <div class="acp-empty-state">
        <p>All modules in <code>modules/</code> are already installed.</p>
    </div>
    <?php else: ?>
    <div class="module-list">
    <?php foreach ($allAvailable as $slug => $m):
        $def  = $m['def'];
        $mig  = $m['migrations'];
        $subs = $m['submodules'] ?? [];
    ?>
    <div class="module-card status-available">
        <div class="module-card-header">
            <div class="module-card-title">
                <h4><?= e($def['name']) ?> <span class="module-version">v<?= e($def['version']) ?></span></h4>
                <span class="module-status-badge status-available">Not Installed</span>
            </div>
            <div class="module-card-actions">
                <button type="button" class="btn btn-primary btn-small"
                        data-show-id="install-<?= e($slug) ?>" data-hide-self>
                    ⬇ Install
                </button>
            </div>
        </div>
        <?php if (!empty($def['description'])): ?>
        <p class="module-description"><?= e($def['description']) ?></p>
        <?php endif; ?>
        <div class="module-meta">
            <?php if (!empty($def['dependencies'])): ?>
            <span class="module-meta-item">Depends on:
                <?php foreach ($def['dependencies'] as $dep):
                    $depOk = isset($allInstalled[$dep]) && $allInstalled[$dep]['status'] === 'active';
                ?><span class="dep-badge <?= $depOk ? 'dep-ok' : 'dep-missing' ?>"><?= e($dep) ?></span><?php
                endforeach; ?>
            </span>
            <?php endif; ?>
            <?php if ($mig['total'] > 0): ?>
            <span class="module-meta-item">Migrations: <?= (int)$mig['total'] ?></span>
            <?php endif; ?>
        </div>

        <!-- Install panel -->
        <div id="install-<?= e($slug) ?>" class="install-panel" style="display:none">
            <form method="post" action="<?= url('/admin/settings/modules/' . $slug . '/install') ?>">
                <?= csrf_field() ?>
                <?php if (!empty($subs)): ?>
                <fieldset class="submodule-fieldset">
                    <legend>Optional sub-modules</legend>
                    <?php foreach ($subs as $subSlug => $sub): ?>
                    <label class="form-checkbox submodule-option <?= !$sub['deps_met'] ? 'submodule-disabled' : '' ?>">
                        <input type="checkbox" name="submodules[]" value="<?= e($subSlug) ?>"
                               <?= !$sub['deps_met'] ? 'disabled' : '' ?>>
                        <strong><?= e($sub['name']) ?></strong>
                        <?php if (!empty($sub['description'])): ?> — <span class="module-description"><?= e($sub['description']) ?></span><?php endif; ?>
                        <?php if (!$sub['deps_met']): ?>
                        <span class="dep-badge dep-missing" style="margin-left:.5rem">Requires: <?= e(implode(', ', $sub['requires'] ?? [])) ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php endif; ?>
                <div class="install-actions">
                    <button type="submit" class="btn btn-primary btn-small">Confirm Install</button>
                    <button type="button" class="btn btn-secondary btn-small"
                            data-close-panel="install-panel">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<style>
.module-section-title { font-size: 1rem; font-weight: 700; color: var(--text-muted,#6b7280); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 .75rem; }
.module-list { display: flex; flex-direction: column; gap: 1rem; }
.module-card { background: var(--acp-card-bg,#fff); border: 1px solid var(--border-color,#ddd); border-radius: 6px; padding: 1.25rem; }
.module-card.status-active    { border-left: 4px solid #22c55e; }
.module-card.status-offline   { border-left: 4px solid #94a3b8; }
.module-card.status-available { border-left: 4px solid #3b82f6; }
.module-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .5rem; }
.module-card-title  { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.module-card-title h4 { margin: 0; font-size: 1rem; }
.module-version { font-size: .8rem; color: var(--text-muted,#6b7280); font-weight: normal; }
.module-card-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.module-status-badge { font-size: .75rem; padding: .15rem .55rem; border-radius: 999px; font-weight: 600; }
.module-status-badge.status-active    { background: #dcfce7; color: #166534; }
.module-status-badge.status-offline   { background: #f1f5f9; color: #475569; }
.module-status-badge.status-available { background: #dbeafe; color: #1e40af; }
.module-description { color: var(--text-muted,#6b7280); font-size: .9rem; margin: .35rem 0 .6rem; }
.module-meta { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .82rem; color: var(--text-muted,#6b7280); margin-bottom: .5rem; }
.module-meta-item { display: flex; align-items: center; gap: .3rem; }
.dep-badge { display: inline-block; padding: .1rem .4rem; border-radius: 4px; font-size: .78rem; }
.dep-ok      { background: #dcfce7; color: #166534; }
.dep-missing { background: #fee2e2; color: #991b1b; }
.migrations-meta.has-pending { color: #d97706; font-weight: 600; }
.module-settings-panel { margin-top: .75rem; border-top: 1px solid var(--border-color,#e5e7eb); padding-top: .75rem; }
.module-settings-panel summary { cursor: pointer; font-weight: 600; font-size: .9rem; margin-bottom: .75rem; }
.module-settings-form { display: grid; gap: .75rem; max-width: 480px; }
.uninstall-panel { margin-top: .75rem; padding: .75rem 1rem; background: #fff5f5; border: 1px solid #fca5a5; border-radius: 4px; }
.uninstall-warning { font-size: .9rem; margin: 0 0 .5rem; }
.uninstall-actions { display: flex; gap: .5rem; margin-top: .6rem; }
.install-panel { margin-top: .75rem; padding: .75rem 1rem; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 4px; }
.install-actions { display: flex; gap: .5rem; margin-top: .75rem; }
.submodule-fieldset { border: 1px solid var(--border-color,#ddd); border-radius: 4px; padding: .6rem .9rem; margin-bottom: .5rem; }
.submodule-fieldset legend { font-weight: 600; font-size: .85rem; padding: 0 .3rem; }
.submodule-option { display: flex; align-items: flex-start; gap: .4rem; padding: .3rem 0; font-size: .9rem; cursor: pointer; }
.submodule-disabled { opacity: .55; cursor: not-allowed; }
.acp-empty-state { text-align: center; padding: 2rem 1rem; color: var(--text-muted,#6b7280); }
</style>

<?php include __DIR__ . '/_tabs_end.php'; ?>
