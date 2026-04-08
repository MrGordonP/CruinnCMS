<?php
/**
 * Widget: Member Profile Summary
 * Quick view of the logged-in user's linked member record.
 * Data keys: member (may be null)
 */
$member = $data['member'] ?? null;
?>
<div class="activity-header">
    <h2>My Profile</h2>
    <a href="/users/profile" class="btn btn-outline btn-small">Edit Profile</a>
</div>
<?php if ($member): ?>
<div class="detail-card" style="margin-bottom: 0;">
    <table class="detail-table" style="margin-bottom: 0;">
        <tr>
            <th>Name</th>
            <td><?= e($member['forenames'] . ' ' . $member['surnames']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= e($member['email']) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="badge badge-<?= $member['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(ucfirst($member['status'])) ?></span></td>
        </tr>
        <?php if ($member['institute']): ?>
        <tr>
            <th>Institute</th>
            <td><?= e($member['institute']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>
    <?php else: ?>
    <p class="text-muted">No linked member record found. <a href="/users/profile">Set up your profile</a>.</p>
<?php endif; ?>
