<?php
$member = $member ?? null;
?>
<?php if ($member): ?>
<div class="detail-card">
    <div class="activity-header">
        <h2>Membership Details</h2>
    </div>
    <form method="post" action="/members/profile">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <p class="form-static"><?= e((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? '')) ?></p>
            </div>
            <div class="form-group">
                <label>Member ID</label>
                <p class="form-static"><?= e((string) (($member['mem_id'] ?? '') ?: '—')) ?></p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e((string) ($member['email'] ?? '')) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="institute">Institute / Organisation</label>
                <input type="text" id="institute" name="institute" value="<?= e((string) ($member['institute'] ?? '')) ?>" class="form-input">
            </div>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="public_directory" value="1" <?= !empty($member['public_directory']) ? 'checked' : '' ?>>
                Show my name in the public member directory
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="detail-card">
    <p class="text-muted">Your account is not linked to a membership record. If you think this is an error, please <a href="/contact">contact us</a>.</p>
</div>
<?php endif; ?>
