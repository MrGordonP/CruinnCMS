<?php
/**
 * Widget: Member Profile Summary
 * Quick view of the logged-in user's linked member record.
 * Data keys: member (may be null)
 */
$member = $data['member'] ?? null;
$memberName = '';
$memberEmail = '';
$memberStatus = 'unknown';
$memberInstitute = '';

if (is_array($member)) {
    $memberName = trim((string) (($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')));
    if ($memberName === '') {
        $memberName = trim((string) ($member['display_name'] ?? $member['name'] ?? $member['full_name'] ?? ''));
    }
    $memberEmail = (string) ($member['email'] ?? '');
    $memberStatus = (string) ($member['status'] ?? 'unknown');
    $memberInstitute = trim((string) ($member['institute'] ?? ''));
}
?>
<div class="activity-header">
    <h2>My Member Profile</h2>
    <a href="/members/profile" class="btn btn-outline btn-small">Edit Profile</a>
</div>
<?php if (is_array($member)): ?>
<div class="detail-card" style="margin-bottom: 0;">
    <table class="detail-table" style="margin-bottom: 0;">
        <tr>
            <th>Name</th>
            <td><?= e($memberName !== '' ? $memberName : 'Not set') ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= e($memberEmail !== '' ? $memberEmail : 'Not set') ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="badge badge-<?= $memberStatus === 'active' ? 'success' : 'muted' ?>"><?= e(ucfirst($memberStatus)) ?></span></td>
        </tr>
        <?php if ($memberInstitute !== ''): ?>
        <tr>
            <th>Institute</th>
            <td><?= e($memberInstitute) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>
    <?php else: ?>
    <p class="text-muted">No linked member record found. <a href="/members/profile">Set up your profile</a>.</p>
<?php endif; ?>
