<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">Membership Plans</h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">Plan definitions used when creating subscriptions.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a class="btn btn-outline" href="<?= url('/admin/membership') ?>">Back to Membership</a>
        <a class="btn btn-primary" href="<?= url('/admin/membership/plans/new') ?>">New Plan</a>
    </div>
</div>

<div class="card" style="margin-top:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:auto;">
    <?php if (empty($plans)): ?>
    <p style="padding:1rem;">No plans found.</p>
    <?php else: ?>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Name</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Slug</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Period</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Price</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Active</th>
                <th style="text-align:left;padding:0.75rem;border-bottom:1px solid #e5e7eb;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($plans as $plan): ?>
            <tr>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($plan['name']) ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><code><?= e($plan['slug']) ?></code></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($plan['billing_period']) ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= e($plan['currency']) ?> <?= number_format((float) $plan['price'], 2) ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><?= !empty($plan['is_active']) ? 'Yes' : 'No' ?></td>
                <td style="padding:0.75rem;border-bottom:1px solid #f1f5f9;"><a href="<?= url('/admin/membership/plans/' . (int) $plan['id'] . '/edit') ?>">Edit</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
