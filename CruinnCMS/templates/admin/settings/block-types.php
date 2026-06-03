<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Block Types</h2>

<!-- ── Active Block Types ──────────────────────────────────────────────── -->
<section class="module-section">
    <h3 class="module-section-title">Active Block Types</h3>
    <?php if (empty($active)): ?>
    <div class="acp-empty-state">
        <p>No block types activated yet. Activate one from the list below.</p>
    </div>
    <?php else: ?>
    <div class="module-list">
    <?php foreach ($active as $slug => $bt): ?>
    <div class="module-card status-active">
        <div class="module-card-header">
            <div class="module-card-title">
                <h4><?= e($bt['label'] ?? $slug) ?> <span class="module-version"><?= e($slug) ?></span></h4>
                <span class="module-status-badge status-active">Active</span>
            </div>
            <div class="module-card-actions">
                <form method="post" action="<?= url('/admin/settings/block-types/' . $slug . '/deactivate') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small"
                            data-confirm="Deactivate block type '<?= e($slug) ?>'? It will be removed from the editor palette.">
                        Deactivate
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ── Available Block Types ───────────────────────────────────────────── -->
<section class="module-section">
    <h3 class="module-section-title">Available Block Types</h3>
    <?php if (empty($available)): ?>
    <div class="acp-empty-state">
        <p>All block types are active.</p>
    </div>
    <?php else: ?>
    <div class="module-list">
    <?php foreach ($available as $slug => $bt): ?>
    <div class="module-card status-discovered">
        <div class="module-card-header">
            <div class="module-card-title">
                <h4><?= e($slug) ?></h4>
                <span class="module-status-badge status-discovered">Not active</span>
            </div>
            <div class="module-card-actions">
                <form method="post" action="<?= url('/admin/settings/block-types/' . $slug . '/activate') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-small">Activate</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/_tabs_end.php'; ?>
