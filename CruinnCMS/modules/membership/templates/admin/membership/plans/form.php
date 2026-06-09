<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php
$isEdit = !empty($plan['id']);
$periods = ['annual','monthly','quarterly','lifetime','custom'];
$groupPlans = $groupPlans ?? [];
$subjects = $subjects ?? [];
$isStructuralGroup = !empty($plan['is_plan_group'])
    || (!array_key_exists('is_plan_group', $plan)
        && !empty($plan['is_group'])
        && (int) ($plan['parent_plan_id'] ?? 0) === 0
        && (float) ($plan['price'] ?? 0) <= 0);
$mode = $mode ?? ($isStructuralGroup ? 'group' : 'tier');
$isGroupMode = $isStructuralGroup || $mode === 'group';

$promoStartsAt = (string) ($plan['promo_starts_at'] ?? '');
if ($promoStartsAt !== '') {
    $promoStartsAt = str_replace(' ', 'T', substr($promoStartsAt, 0, 16));
}
$promoEndsAt = (string) ($plan['promo_ends_at'] ?? '');
if ($promoEndsAt !== '') {
    $promoEndsAt = str_replace(' ', 'T', substr($promoEndsAt, 0, 16));
}
?>

<h1><?= e($title ?? ($isEdit ? 'Edit Plan' : 'New Plan')) ?></h1>

<form method="post" action="<?= $isEdit ? url('/admin/membership/plans/' . (int) $plan['id']) : url('/admin/membership/plans') ?>" class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;display:grid;gap:0.9rem;max-width:640px;">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <input type="hidden" name="is_plan_group" value="<?= $isGroupMode ? '1' : '0' ?>">

    <?php if (!empty($errors)): ?>
    <div class="form-errors"><ul>
        <?php foreach ($errors as $field => $error): ?>
            <?php if ($field === 'general') { continue; } ?>
        <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
    <div class="form-errors"><ul><li><?= e($errors['general']) ?></li></ul></div>
    <?php endif; ?>

    <div>
        <label class="form-label" for="name">Name</label>
        <input class="form-input" id="name" name="name" type="text" value="<?= e($plan['name'] ?? '') ?>">
        <?php if (!empty($errors['name'])): ?><small class="text-danger"><?= e($errors['name']) ?></small><?php endif; ?>
    </div>

    <div>
        <label class="form-label" for="description">Description</label>
        <textarea class="form-input" id="description" name="description" rows="4"><?= e($plan['description'] ?? '') ?></textarea>
    </div>

    <div>
        <label class="form-label" for="subject_id">Subject</label>
        <select class="form-input" id="subject_id" name="subject_id">
            <option value="">No subject</option>
            <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= (int) ($plan['subject_id'] ?? 0) === (int) $subject['id'] ? ' selected' : '' ?>>
                <?= e($subject['title']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['subject_id'])): ?><small class="text-danger"><?= e($errors['subject_id']) ?></small><?php endif; ?>
    </div>

    <?php if ($isGroupMode): ?>
    <div>
        <label class="form-label" for="billing_period">Billing Period (Group)</label>
        <select class="form-input" id="billing_period" name="billing_period">
            <?php foreach ($periods as $period): ?>
            <option value="<?= e($period) ?>"<?= ($plan['billing_period'] ?? 'annual') === $period ? ' selected' : '' ?>><?= e(ucfirst($period)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['billing_period'])): ?><small class="text-danger"><?= e($errors['billing_period']) ?></small><?php endif; ?>
    </div>
    <div class="detail-card" style="padding:0.7rem;">
        <strong>Group billing:</strong>
        <span class="text-muted">Groups define billing period. Billable amount lives on child plans/tiers.</span>
    </div>
    <input type="hidden" name="price" value="0">
    <input type="hidden" name="currency" value="EUR">
    <input type="hidden" name="promo_type" value="">
    <input type="hidden" name="promo_value" value="">
    <input type="hidden" name="promo_starts_at" value="">
    <input type="hidden" name="promo_ends_at" value="">
    <?php else: ?>
    <div class="detail-card" style="padding:0.7rem;">
        <strong>Billing period:</strong>
        <span class="text-muted">Inherited from parent group (<?= e(ucfirst((string) ($plan['billing_period'] ?? 'custom'))) ?>).</span>
    </div>
    <input type="hidden" name="billing_period" value="<?= e((string) ($plan['billing_period'] ?? 'custom')) ?>">

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="price">Billing Amount</label>
            <input class="form-input" id="price" name="price" type="number" min="0" step="0.01" value="<?= e((string) ($plan['price'] ?? '0.00')) ?>">
            <?php if (!empty($errors['price'])): ?><small class="text-danger"><?= e($errors['price']) ?></small><?php endif; ?>
        </div>
        <div>
            <label class="form-label" for="currency">Currency</label>
            <input class="form-input" id="currency" name="currency" type="text" maxlength="3" value="<?= e($plan['currency'] ?? 'EUR') ?>">
            <?php if (!empty($errors['currency'])): ?><small class="text-danger"><?= e($errors['currency']) ?></small><?php endif; ?>
        </div>
    </div>

    <div class="detail-card" style="padding:0.7rem;display:grid;gap:0.6rem;">
        <strong>Promotion / Sale Modifier</strong>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
            <div>
                <label class="form-label" for="promo_type">Promotion Type</label>
                <select class="form-input" id="promo_type" name="promo_type">
                    <option value="">No promotion</option>
                    <option value="percent"<?= ($plan['promo_type'] ?? '') === 'percent' ? ' selected' : '' ?>>Percent discount</option>
                    <option value="fixed"<?= ($plan['promo_type'] ?? '') === 'fixed' ? ' selected' : '' ?>>Fixed discount</option>
                </select>
                <?php if (!empty($errors['promo_type'])): ?><small class="text-danger"><?= e($errors['promo_type']) ?></small><?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="promo_value">Promotion Value</label>
                <input class="form-input" id="promo_value" name="promo_value" type="number" min="0" step="0.01" value="<?= e((string) ($plan['promo_value'] ?? '')) ?>" placeholder="e.g. 10">
                <?php if (!empty($errors['promo_value'])): ?><small class="text-danger"><?= e($errors['promo_value']) ?></small><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
            <div>
                <label class="form-label" for="promo_starts_at">Promotion Starts</label>
                <input class="form-input" id="promo_starts_at" name="promo_starts_at" type="datetime-local" value="<?= e($promoStartsAt) ?>">
                <?php if (!empty($errors['promo_starts_at'])): ?><small class="text-danger"><?= e($errors['promo_starts_at']) ?></small><?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="promo_ends_at">Promotion Ends</label>
                <input class="form-input" id="promo_ends_at" name="promo_ends_at" type="datetime-local" value="<?= e($promoEndsAt) ?>">
                <?php if (!empty($errors['promo_ends_at'])): ?><small class="text-danger"><?= e($errors['promo_ends_at']) ?></small><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <label style="display:flex;align-items:center;gap:0.5rem;">
        <input type="checkbox" name="is_active" value="1"<?= !isset($plan['is_active']) || !empty($plan['is_active']) ? ' checked' : '' ?>>
        Active
    </label>

    <label style="display:flex;align-items:center;gap:0.5rem;">
        <input type="checkbox" name="is_group" value="1"<?= !empty($plan['is_group']) ? ' checked' : '' ?><?= !$isEdit && $isGroupMode ? ' disabled' : '' ?>>
        <?php if (!$isEdit && $isGroupMode): ?>
        <input type="hidden" name="is_group" value="0">
        <?php endif; ?>
        Shared subscription (multiple members)
    </label>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="max_members">Max Members (Group)</label>
            <input class="form-input" id="max_members" name="max_members" type="number" min="0" step="1" value="<?= e((string) ($plan['max_members'] ?? '0')) ?>" placeholder="0 = none">
            <?php if (!empty($errors['max_members'])): ?><small class="text-danger"><?= e($errors['max_members']) ?></small><?php endif; ?>
        </div>
        <div>
            <label class="form-label" for="parent_plan_id">Parent Group (Tier)</label>
            <select class="form-input" id="parent_plan_id" name="parent_plan_id"<?= !$isEdit && $isGroupMode ? ' disabled' : '' ?>>
                <option value="">No parent group</option>
                <?php foreach ($groupPlans as $gp): ?>
                <option value="<?= (int) $gp['id'] ?>"<?= (int) ($plan['parent_plan_id'] ?? 0) === (int) $gp['id'] ? ' selected' : '' ?>>
                    <?= e($gp['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isEdit && $isGroupMode): ?>
            <input type="hidden" name="parent_plan_id" value="0">
            <?php endif; ?>
            <?php if (!empty($errors['parent_plan_id'])): ?><small class="text-danger"><?= e($errors['parent_plan_id']) ?></small><?php endif; ?>
        </div>
    </div>

    <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Plan' : 'Create Plan' ?></button>
        <a class="btn btn-outline" href="<?= url('/admin/membership/plans') ?>">Cancel</a>
    </div>
</form>
