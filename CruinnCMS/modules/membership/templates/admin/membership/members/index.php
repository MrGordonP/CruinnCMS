<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">Membership</h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">Manage members, subscriptions, and payment states.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a class="btn btn-outline" href="<?= url('/admin/membership/plans') ?>">Plans</a>
        <a class="btn btn-outline" href="<?= url('/admin/membership/import') ?>">Import CSV</a>
        <a class="btn btn-primary" href="<?= url('/admin/membership/members/new') ?>">New Member</a>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.5rem;margin:1rem 0;">
    <?php foreach (['applicant','active','lapsed','suspended','resigned','archived'] as $status): ?>
    <div class="card" style="padding:0.75rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
        <div style="font-size:0.8rem;color:#6b7280;text-transform:capitalize;"><?= e($status) ?></div>
        <div style="font-size:1.4rem;font-weight:700;"><?= (int) ($statusCount[$status] ?? 0) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= url('/admin/membership') ?>" class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;margin-bottom:1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;align-items:end;">
    <div>
        <label class="form-label" for="status">Status</label>
        <select class="form-input" id="status" name="status">
            <option value="">All</option>
            <?php foreach (['applicant','active','lapsed','suspended','resigned','archived'] as $status): ?>
            <option value="<?= e($status) ?>"<?= ($filters['status'] ?? '') === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="plan_id">Plan</label>
        <select class="form-input" id="plan_id" name="plan_id">
            <option value="">All plans</option>
            <?php foreach ($plans as $plan): ?>
            <option value="<?= (int) $plan['id'] ?>"<?= (string) ($filters['plan_id'] ?? '') === (string) $plan['id'] ? ' selected' : '' ?>>
                <?= e($plan['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="q">Search</label>
        <input class="form-input" id="q" type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, email, number">
    </div>
    <div>
        <button class="btn btn-primary" type="submit">Filter</button>
    </div>
</form>

<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:auto;">
    <?php if (empty($members)): ?>
    <p style="padding:1rem;">No members found for the current filter.</p>
    <?php else: ?>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Member</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Email</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Plan</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Status</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Joined</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
            <tr>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;">
                    <a href="<?= url('/admin/membership/members/' . (int) $member['id']) ?>">
                        <?= e(trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? ''))) ?>
                    </a>
                    <div class="text-muted" style="font-size:0.8rem;">
                        #<?= (int) $member['id'] ?><?= !empty($member['membership_number']) ? ' • ' . e($member['membership_number']) : '' ?>
                    </div>
                </td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($member['email']) ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($member['plan_name'] ?? '—') ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><span style="text-transform:capitalize;"><?= e($member['status']) ?></span></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($member['joined_at'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
