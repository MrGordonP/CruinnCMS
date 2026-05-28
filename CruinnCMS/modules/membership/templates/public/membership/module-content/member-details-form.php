<?php
$member = $member ?? null;
$address = is_array($address ?? null) ? $address : [];
?>
<?php if ($member): ?>
<div class="detail-card">
    <div class="activity-header">
        <h2>Your Details</h2>
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
        <h3 style="margin: var(--space-lg) 0 var(--space-sm)">Address</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="address1">Address Line 1</label>
                <input type="text" id="address1" name="address1" value="<?= e((string) ($address['address1'] ?? '')) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="address2">Address Line 2</label>
                <input type="text" id="address2" name="address2" value="<?= e((string) ($address['address2'] ?? '')) ?>" class="form-input">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="county">County</label>
                <input type="text" id="county" name="county" value="<?= e((string) ($address['county'] ?? '')) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" value="<?= e((string) ($address['country'] ?? 'Ireland')) ?>" class="form-input">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="eircode">Eircode / Postcode</label>
                <input type="text" id="eircode" name="eircode" value="<?= e((string) ($address['eircode'] ?? '')) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?= e((string) ($address['phone'] ?? '')) ?>" class="form-input">
            </div>
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
