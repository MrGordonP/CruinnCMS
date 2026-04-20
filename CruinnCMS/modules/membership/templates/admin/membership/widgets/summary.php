<?php
$recentMembers = $data['recent_members'] ?? [];
?>
<div class="activity-header">
    <h2>Membership Summary</h2>
    <a href="<?= url('/admin/membership') ?>" class="btn btn-primary btn-small">Open Membership</a>
</div>

<div class="dash-quick-grid">
    <div class="dash-quick-link">
        <span class="dash-quick-icon">👥</span><span>Total <?= (int) ($data['total_members'] ?? 0) ?></span>
    </div>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">✅</span><span>Active <?= (int) ($data['active_members'] ?? 0) ?></span>
    </div>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">⏳</span><span>Pending <?= (int) ($data['pending_members'] ?? 0) ?></span>
    </div>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">⚠️</span><span>Lapsed <?= (int) ($data['lapsed_members'] ?? 0) ?></span>
    </div>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">💳</span><span>Paid Current <?= (int) ($data['paid_subscriptions'] ?? 0) ?></span>
    </div>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">📌</span><span>Due Soon <?= (int) ($data['due_subscriptions'] ?? 0) ?></span>
    </div>
</div>

<div class="activity-header" style="margin-top:1rem;">
    <h3>Recently Updated Members</h3>
</div>

<?php if (empty($recentMembers)): ?>
<p class="text-muted">No membership records yet.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Member</th>
            <th>Number</th>
            <th>Status</th>
            <th>Updated</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recentMembers as $member): ?>
        <tr>
            <td>
                <a href="<?= url('/admin/membership/members/' . (int) $member['id']) ?>">
                    <?= e(trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? ''))) ?>
                </a>
            </td>
            <td><?= e($member['membership_number'] ?: '—') ?></td>
            <td><?= e(ucfirst((string) ($member['status'] ?? 'unknown'))) ?></td>
            <td><time datetime="<?= e($member['updated_at']) ?>"><?= format_date($member['updated_at'], 'j M Y') ?></time></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
