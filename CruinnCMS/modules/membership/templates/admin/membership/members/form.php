<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php
$isEdit = !empty($member['id']);
?>

<h1><?= $isEdit ? 'Edit Member' : 'New Member' ?></h1>

<form method="post" action="<?= $isEdit ? url('/admin/membership/members/' . (int) $member['id']) : url('/admin/membership/members') ?>" class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;display:grid;gap:0.9rem;max-width:760px;">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="forenames">Forenames</label>
            <input class="form-input" id="forenames" name="forenames" type="text" value="<?= e($member['forenames'] ?? '') ?>">
            <?php if (!empty($errors['forenames'])): ?><small class="text-danger"><?= e($errors['forenames']) ?></small><?php endif; ?>
        </div>
        <div>
            <label class="form-label" for="surnames">Surnames</label>
            <input class="form-input" id="surnames" name="surnames" type="text" value="<?= e($member['surnames'] ?? '') ?>">
            <?php if (!empty($errors['surnames'])): ?><small class="text-danger"><?= e($errors['surnames']) ?></small><?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" name="email" type="email" value="<?= e($member['email'] ?? '') ?>">
            <?php if (!empty($errors['email'])): ?><small class="text-danger"><?= e($errors['email']) ?></small><?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="membership_number">Membership Number</label>
            <input class="form-input" id="membership_number" name="membership_number" type="text" value="<?= e($member['membership_number'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label" for="user_id">Linked User ID (optional)</label>
            <input class="form-input" id="user_id" name="user_id" type="number" min="1" step="1" value="<?= e((string) ($member['user_id'] ?? '')) ?>">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
        <div>
            <label class="form-label" for="organisation">Organisation</label>
            <input class="form-input" id="organisation" name="organisation" type="text" value="<?= e($member['organisation'] ?? '') ?>">
        </div>
    </div>

    <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Member' : 'Create Member' ?></button>
        <a class="btn btn-outline" href="<?= url('/admin/membership/members') ?>">Cancel</a>
    </div>
</form>
