<?php
$member = $member ?? null;
$address = is_array($address ?? null) ? $address : [];
?>
<?php if ($member): ?>
<div class="detail-card">
    <div class="activity-header">
        <h2>Address</h2>
    </div>
    <form method="post" action="/members/profile">
        <?= csrf_field() ?>
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
            <button type="submit" class="btn btn-primary">Save Address</button>
        </div>
    </form>
</div>
<?php endif; ?>
