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
        <div class="platform-instance-card <?= $instance['online'] ? 'instance-active' : 'instance-inactive' ?>">
            <div class="platform-instance-header">
                <div class="platform-instance-identity">
                    <strong class="platform-instance-name">
                        <?= e($instance['name']) ?>
                        <?php if ($instance['online']): ?>
                        <span class="platform-badge platform-badge-active">Online</span>
                        <?php else: ?>
                        <span class="platform-badge platform-badge-inactive">Offline</span>
                        <?php endif; ?>
                    </strong>
                    <?php if ($instance['url']): ?>
                    <a href="<?= e($instance['url']) ?>" target="_blank" rel="noopener" class="platform-instance-url">
                        <?= e($instance['url']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($instance['hostnames'])): ?>
                    <span class="platform-instance-hosts"><?= e(implode(', ', $instance['hostnames'])) ?></span>
                    <?php endif; ?>
                    <span class="platform-instance-folder"><code>instance/<?= e($instance['folder_name']) ?>/</code></span>
                </div>
                <div class="platform-instance-actions">
                    <?php if ($instance['online']): ?>
                    <a href="<?= e($instance['base_url']) ?>/admin/platform-passthrough?to=/admin/dashboard" class="platform-btn platform-btn-primary">Open Admin</a>
                    <a href="<?= e($instance['base_url']) ?>/admin/settings/site" class="platform-btn platform-btn-secondary">ACP Settings</a>
                    <a href="/cms/database?instance=<?= e(urlencode($instance['folder_name'])) ?>" class="platform-btn platform-btn-secondary">DB Browser</a>
                    <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/toggle" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <button type="submit" class="platform-btn platform-btn-danger">Take Offline</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/toggle" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <button type="submit" class="platform-btn platform-btn-secondary">Bring Online</button>
                    </form>
                    <a href="/cms/database?instance=<?= e(urlencode($instance['folder_name'])) ?>" class="platform-btn platform-btn-secondary">DB Browser</a>
                    <?php endif; ?>
                    <!-- Backup (always available) -->
                    <button type="button"
                            onclick="document.getElementById('backup-panel-<?= e($instance['folder_name']) ?>').style.display = document.getElementById('backup-panel-<?= e($instance['folder_name']) ?>').style.display === 'none' ? 'block' : 'none'"
                            class="platform-btn platform-btn-secondary">Backups</button>
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

            <!-- ── Backup Panel ───────────────────────────────── -->
            <div id="backup-panel-<?= e($instance['folder_name']) ?>" style="display:none; border-top:1px solid rgba(255,255,255,.08); margin-top:1rem; padding-top:1rem;">

                <!-- Create backup -->
                <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/backup" style="margin-bottom:1rem;">
                    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                    <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <strong style="color:rgba(232,228,218,.8)">Create Backup</strong>
                        <label style="display:flex;align-items:center;gap:.3rem;font-size:.85rem;">
                            <input type="checkbox" name="include_uploads"> Uploads
                        </label>
                        <label style="display:flex;align-items:center;gap:.3rem;font-size:.85rem;">
                            <input type="checkbox" name="include_secrets"> Credentials
                        </label>
                        <button type="submit" class="platform-btn platform-btn-secondary">Run Backup</button>
                    </div>
                </form>

                <!-- Existing backups -->
                <?php if (empty($instance['backups'])): ?>
                <p style="color:rgba(232,228,218,.4); font-size:.85rem;">No backups yet.</p>
                <?php else: ?>
                <table style="width:100%; font-size:.85rem; border-collapse:collapse;">
                    <thead>
                        <tr style="color:rgba(232,228,218,.45); text-align:left;">
                            <th style="padding:.3rem .5rem;">File</th>
                            <th style="padding:.3rem .5rem;">Created</th>
                            <th style="padding:.3rem .5rem;">Size</th>
                            <th style="padding:.3rem .5rem;">Includes</th>
                            <th style="padding:.3rem .5rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($instance['backups'] as $bk): ?>
                        <tr style="border-top:1px solid rgba(255,255,255,.05);">
                            <td style="padding:.4rem .5rem; font-family:monospace; font-size:.8rem;"><?= e($bk['file']) ?></td>
                            <td style="padding:.4rem .5rem;"><?= e($bk['created_at']) ?></td>
                            <td style="padding:.4rem .5rem;"><?= e($bk['size_mb']) ?> MB</td>
                            <td style="padding:.4rem .5rem;">
                                <?= !empty($bk['manifest']['includes_uploads']) ? 'uploads ' : '' ?>
                                <?= !empty($bk['manifest']['includes_secrets']) ? 'credentials' : '' ?>
                            </td>
                            <td style="padding:.4rem .5rem; white-space:nowrap;">
                                <a href="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/backup/download?file=<?= e(urlencode($bk['file'])) ?>"
                                   class="platform-btn platform-btn-secondary" style="font-size:.8rem; padding:.2rem .6rem;">Download</a>
                                <?php if (!$instance['online']): ?>
                                <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/restore" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                                    <input type="hidden" name="file" value="<?= e($bk['file']) ?>">
                                    <button type="submit" class="platform-btn platform-btn-secondary" style="font-size:.8rem; padding:.2rem .6rem;"
                                            onclick="return confirm('Restore from this backup? This will overwrite the current database.')">Restore</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/backup/delete" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                                    <input type="hidden" name="file" value="<?= e($bk['file']) ?>">
                                    <button type="submit" class="platform-btn platform-btn-danger" style="font-size:.8rem; padding:.2rem .6rem;"
                                            onclick="return confirm('Delete this backup file?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Reset user password -->
                <div style="margin-top:1.2rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08);">
                    <strong style="color:rgba(232,228,218,.8); display:block; margin-bottom:.6rem;">Reset User Password</strong>
                    <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/reset-password"
                          style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end;">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <div class="platform-field" style="margin:0; flex:1; min-width:160px;">
                            <label style="font-size:.8rem;">Email</label>
                            <input type="email" name="email" placeholder="user@example.com" required style="padding:.3rem .5rem; font-size:.85rem;">
                        </div>
                        <div class="platform-field" style="margin:0; flex:1; min-width:160px;">
                            <label style="font-size:.8rem;">New Password</label>
                            <input type="password" name="new_password" minlength="8" required autocomplete="new-password" style="padding:.3rem .5rem; font-size:.85rem;">
                        </div>
                        <button type="submit" class="platform-btn platform-btn-secondary" style="font-size:.85rem;">Reset Password</button>
                    </form>
                </div>

                <!-- Delete instance (offline only) -->
                <?php if (!$instance['online']): ?>
                <div style="margin-top:1.2rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08);">
                    <form method="POST" action="/cms/instances/<?= e(urlencode($instance['folder_name'])) ?>/delete" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                        <button type="submit" class="platform-btn platform-btn-danger"
                                onclick="return confirm('Permanently delete this instance? The database will NOT be dropped — you must remove it manually.')">
                            Delete Instance
                        </button>
                    </form>
                    <span style="font-size:.8rem; color:rgba(232,228,218,.4); margin-left:.75rem;">The instance database will NOT be dropped automatically.</span>
                </div>
                <?php endif; ?>
            </div>

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
                <?php $firstOnline = current(array_filter($instances, fn($i) => $i['online'])); ?>
                <?php if ($firstOnline): ?>
                <a href="<?= e($firstOnline['base_url']) ?>/admin/settings/modules" class="platform-link">Manage Modules →</a>
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
                    $anyInst = current(array_filter($instances, fn($i) => $i['online'])) ?: ($instances[0] ?? []);
                    $lastAct = $anyInst['stats']['last_activity'] ?? null;
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
