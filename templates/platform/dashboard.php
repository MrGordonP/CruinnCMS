<?php
/**
 * Platform Dashboard
 * Variables: $instances (array of instance data), $engine, $multi, $username
 */
?>
<?php ob_start(); ?>

<div class="platform-page">

    <!-- ── Instances ───────────────────────────────────────────── -->
    <section class="platform-section">
        <div class="platform-section-header">
            <h2><?= $multi ? 'Instances' : 'Instance' ?></h2>
            <a href="/cms/instances/new" class="platform-btn platform-btn-primary">+ Provision Instance</a>
        </div>

        <?php if (empty($instances)): ?>
        <div class="platform-instance-card" style="text-align:center; padding:2rem; color:rgba(232,228,218,.45);">
            No instances provisioned yet.
        </div>
        <?php endif; ?>
        <?php foreach ($instances as $instance): ?>
        <div class="platform-instance-card <?= $instance['active'] ? 'instance-active' : 'instance-inactive' ?>">
            <div class="platform-instance-header">
                <div class="platform-instance-identity">
                    <strong class="platform-instance-name">
                        <?= e($instance['name']) ?>
                        <?php if ($instance['active']): ?>
                        <span class="platform-badge platform-badge-active">Active</span>
                        <?php endif; ?>
                    </strong>
                    <?php if ($instance['url']): ?>
                    <a href="<?= e($instance['url']) ?>" target="_blank" rel="noopener" class="platform-instance-url">
                        <?= e($instance['url']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($instance['folder_name']) && $instance['folder_name'] !== '(active)'): ?>
                    <span class="platform-instance-folder"><code>instance/<?= e($instance['folder_name']) ?>/</code></span>
                    <?php endif; ?>
                </div>
                <div class="platform-instance-actions">
                    <?php if ($instance['active']): ?>
                    <a href="/admin/platform-passthrough?to=/admin/dashboard" class="platform-btn platform-btn-primary">Open Admin</a>
                    <a href="/admin/platform-passthrough?to=/admin/settings/site" class="platform-btn platform-btn-secondary">ACP Settings</a>
                    <a href="/admin/platform-passthrough?to=/admin/maintenance/links" class="platform-btn platform-btn-secondary">Link Check</a>
                    <a href="/cms/database?instance=<?= e(urlencode($instance['folder_name'])) ?>" class="platform-btn platform-btn-secondary">DB Browser</a>
                    <?php elseif (!empty($instance['folder_name']) && $instance['folder_name'] !== '(active)'): ?>
                    <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/activate" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <button type="submit" class="platform-btn platform-btn-secondary">Activate</button>
                    </form>
                    <a href="/cms/database?instance=<?= e(urlencode($instance['folder_name'])) ?>" class="platform-btn platform-btn-secondary">DB Browser</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="platform-instance-meta">
                <div class="platform-meta-item <?= $instance['db_connected'] ? 'meta-ok' : 'meta-err' ?>">
                    <?= $instance['db_connected'] ? '✓' : '✗' ?>
                    DB <code><?= e($instance['db_name']) ?></code> @ <code><?= e($instance['db_host']) ?></code>
                    <?php if (!$instance['db_connected'] && !empty($instance['stats']['db_error'])): ?>
                    — <span class="text-danger"><?= e($instance['stats']['db_error']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($instance['db_connected']): ?>
            <div class="platform-stat-row">
                <?php if (isset($instance['stats']['pages'])): ?>
                <div class="platform-stat">
                    <span class="platform-stat-num"><?= (int)$instance['stats']['pages'] ?></span>
                    <span class="platform-stat-lbl">Pages</span>
                </div>
                <?php endif; ?>
                <?php if (isset($instance['stats']['users'])): ?>
                <div class="platform-stat">
                    <span class="platform-stat-num"><?= (int)$instance['stats']['users'] ?></span>
                    <span class="platform-stat-lbl">Users</span>
                </div>
                <?php endif; ?>
                <?php if (isset($instance['stats']['tables'])): ?>
                <div class="platform-stat">
                    <span class="platform-stat-num"><?= $instance['stats']['tables'] ?></span>
                    <span class="platform-stat-lbl">DB Tables</span>
                </div>
                <div class="platform-stat">
                    <span class="platform-stat-num"><?= number_format((float)($instance['stats']['db_mb'] ?? 0), 2) ?></span>
                    <span class="platform-stat-lbl">DB MB</span>
                </div>
                <div class="platform-stat">
                    <span class="platform-stat-num"><?= number_format((float)($engine['uploads_mb'] ?? 0), 2) ?></span>
                    <span class="platform-stat-lbl">Storage MB</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- ── Engine Health ──────────────────────────────────────── -->
    <section class="platform-section">
        <div class="platform-section-header">
            <h2>Engine Health</h2>
            <a href="/cms/database?instance=__platform__" class="platform-btn platform-btn-secondary">Platform DB</a>
        </div>

        <div class="platform-health-grid">
            <!-- Modules -->
            <div class="platform-health-card">
                <h3>Modules</h3>
                <div class="platform-health-stat">
                    <span class="platform-stat-num"><?= (int)$engine['modules_active'] ?></span>
                    <span class="platform-stat-lbl">Active of <?= (int)$engine['modules_total'] ?> installed</span>
                </div>
                <?php if (!empty($engine['module_slugs'])): ?>
                <ul class="platform-module-list">
                    <?php foreach ($engine['module_slugs'] as $slug): ?>
                    <li><span class="platform-module-badge"><?= e($slug) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php $activeInst = current(array_filter($instances, fn($i) => $i['active'])); ?>
                <?php if ($activeInst): ?>
                <a href="/admin/settings/modules" class="platform-link">Manage Modules →</a>
                <?php endif; ?>
            </div>

            <!-- Writability -->
            <div class="platform-health-card">
                <h3>File System</h3>
                <?php foreach ($engine['writable'] as $path => $ok): ?>
                <div class="platform-check <?= $ok ? 'check-ok' : 'check-fail' ?>">
                    <?= $ok ? '✓' : '✗' ?> <code><?= e($path) ?></code> <?= $ok ? 'writable' : 'NOT writable' ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Last Activity -->
            <div class="platform-health-card">
                <h3>Recent Activity</h3>
                <?php
                    $activeInst = current(array_filter($instances, fn($i) => $i['active'])) ?: ($instances[0] ?? []);
                    $lastAct = $activeInst['stats']['last_activity'] ?? null;
                ?>
                <?php if ($lastAct): ?>
                <p class="platform-health-value"><?= e(date('j M Y, H:i', strtotime($lastAct))) ?></p>
                <?php else: ?>
                <p class="text-muted">No activity recorded yet.</p>
                <?php endif; ?>
                <a href="/admin/dashboard" class="platform-link">View Activity Log →</a>
            </div>
        </div>
    </section>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
