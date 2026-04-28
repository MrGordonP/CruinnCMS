<?php
/**
 * Drivespace — Admin Quota Management
 */
\Cruinn\Template::requireCss('admin-drivespace.css');

/**
 * @param int $bytes
 * @return string
 */
function fmtBytes(int $bytes): string
{
    if ($bytes >= 1073741824) { return round($bytes / 1073741824, 1) . ' GB'; }
    if ($bytes >= 1048576)    { return round($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024)       { return round($bytes / 1024, 1) . ' KB'; }
    return $bytes . ' B';
}
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Drivespace — Quota Management</h1>
        <a href="/admin/drivespace/gdrive" class="btn btn-outline btn-sm">☁️ Google Drive Settings</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Used</th>
                <th>Quota</th>
                <th>Usage</th>
                <th>Set Quota (MB)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php
                $pct = $u['quota_bytes'] > 0
                    ? min(100, round($u['used_bytes'] / $u['quota_bytes'] * 100))
                    : 0;
                $barClass = $pct >= 90 ? 'quota-bar-danger' : ($pct >= 70 ? 'quota-bar-warn' : 'quota-bar-ok');
            ?>
            <tr>
                <td>
                    <strong><?= e($u['display_name']) ?></strong><br>
                    <small><?= e($u['email']) ?></small>
                </td>
                <td><?= fmtBytes((int)$u['used_bytes']) ?></td>
                <td><?= fmtBytes((int)$u['quota_bytes']) ?></td>
                <td>
                    <div class="quota-bar-wrap">
                        <div class="quota-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <small><?= $pct ?>%</small>
                </td>
                <td>
                    <form method="post" action="/admin/drivespace/<?= (int)$u['id'] ?>/quota" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                        <input type="number" name="quota_mb" value="<?= (int)round($u['quota_bytes'] / 1048576) ?>"
                               min="0" step="1" class="input-narrow">
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.quota-bar-wrap { background: #e0e0e0; border-radius: 4px; height: 8px; width: 120px; overflow: hidden; }
.quota-bar      { height: 100%; border-radius: 4px; transition: width .3s; }
.quota-bar-ok   { background: #1d9e75; }
.quota-bar-warn { background: #e6a817; }
.quota-bar-danger { background: #c0392b; }
.input-narrow   { width: 70px; }
.inline-form    { display: flex; align-items: center; gap: .4rem; }
</style>
