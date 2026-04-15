<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php
$isEdit = !empty($plan['id']);
$periods = ['annual','monthly','quarterly','lifetime','custom'];
?>

<h1><?= $isEdit ? 'Edit Plan' : 'New Plan' ?></h1>

<form method="post" action="<?= $isEdit ? url('/admin/membership/plans/' . (int) $plan['id']) : url('/admin/membership/plans') ?>" class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;display:grid;gap:0.9rem;max-width:640px;">
    <?= csrf_field() ?>

    <div>
        <label class="form-label" for="name">Name</label>
        <input class="form-input" id="name" name="name" type="text" value="<?= e($plan['name'] ?? '') ?>">
        <?php if (!empty($errors['name'])): ?><small class="text-danger"><?= e($errors['name']) ?></small><?php endif; ?>
    </div>

    <div>
        <label class="form-label" for="slug">Slug</label>
        <input class="form-input" id="slug" name="slug" type="text" value="<?= e($plan['slug'] ?? '') ?>" placeholder="ordinary-membership">
        <?php if (!empty($errors['slug'])): ?><small class="text-danger"><?= e($errors['slug']) ?></small><?php endif; ?>
    </div>

    <div>
        <label class="form-label" for="description">Description</label>
        <textarea class="form-input" id="description" name="description" rows="4"><?= e($plan['description'] ?? '') ?></textarea>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="billing_period">Billing Period</label>
            <select class="form-input" id="billing_period" name="billing_period">
                <?php foreach ($periods as $period): ?>
                <option value="<?= e($period) ?>"<?= ($plan['billing_period'] ?? 'annual') === $period ? ' selected' : '' ?>><?= e(ucfirst($period)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['billing_period'])): ?><small class="text-danger"><?= e($errors['billing_period']) ?></small><?php endif; ?>
        </div>
        <div>
            <label class="form-label" for="price">Price</label>
            <input class="form-input" id="price" name="price" type="number" min="0" step="0.01" value="<?= e((string) ($plan['price'] ?? '0.00')) ?>">
            <?php if (!empty($errors['price'])): ?><small class="text-danger"><?= e($errors['price']) ?></small><?php endif; ?>
        </div>
        <div>
            <label class="form-label" for="currency">Currency</label>
            <input class="form-input" id="currency" name="currency" type="text" maxlength="3" value="<?= e($plan['currency'] ?? 'EUR') ?>">
            <?php if (!empty($errors['currency'])): ?><small class="text-danger"><?= e($errors['currency']) ?></small><?php endif; ?>
        </div>
    </div>

    <label style="display:flex;align-items:center;gap:0.5rem;">
        <input type="checkbox" name="is_active" value="1"<?= !isset($plan['is_active']) || !empty($plan['is_active']) ? ' checked' : '' ?>>
        Active
    </label>

    <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Plan' : 'Create Plan' ?></button>
        <a class="btn btn-outline" href="<?= url('/admin/membership/plans') ?>">Cancel</a>
    </div>
</form>
