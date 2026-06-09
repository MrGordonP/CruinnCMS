<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$plans = $plans ?? [];
$groupPlans = $groupPlans ?? [];
$selectedPlanId = (int) ($selectedPlanId ?? 0);
$selectedPlan = $selectedPlan ?? null;
$subCountByPlan = $subCountByPlan ?? [];
$nowTs = time();

$groupById = [];
foreach ($groupPlans as $groupPlan) {
    $groupById[(int) $groupPlan['id']] = $groupPlan;
}
$tiersByParent = [];
$standalonePlans = [];
foreach ($plans as $plan) {
    $parentId = (int) ($plan['parent_plan_id'] ?? 0);
    if ($parentId > 0) {
        $tiersByParent[$parentId][] = $plan;
        continue;
    }
    if (isset($groupById[(int) $plan['id']])) {
        continue;
    }
    $standalonePlans[] = $plan;
}
?>

<div class="panel-layout" id="membership-plans-layout">
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Groups and Plans</span>
        </div>
        <div class="pl-panel-body" style="padding:0;">
            <div class="pl-nav-section">Groups</div>
            <?php if (empty($groupPlans)): ?>
            <div style="padding:0.6rem 0.9rem;color:#64748b;font-size:0.82rem;">No groups yet.</div>
            <?php else: ?>
            <?php foreach ($groupPlans as $group): ?>
            <a class="pl-nav-item<?= (int) $group['id'] === $selectedPlanId ? ' active' : '' ?>" href="<?= url('/admin/membership/plans?plan=' . (int) $group['id']) ?>">
                <span><?= e($group['name']) ?></span>
                <span class="pl-nav-count"><?= (int) ($subCountByPlan[(int) $group['id']] ?? 0) ?></span>
            </a>
                <?php foreach (($tiersByParent[(int) $group['id']] ?? []) as $tier): ?>
                <a class="pl-nav-item<?= (int) $tier['id'] === $selectedPlanId ? ' active' : '' ?>" href="<?= url('/admin/membership/plans?plan=' . (int) $tier['id']) ?>" style="padding-left:1.7rem;">
                    <span>&#8627; <?= e($tier['name']) ?></span>
                    <span class="pl-nav-count"><?= (int) ($subCountByPlan[(int) $tier['id']] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($standalonePlans)): ?>
            <div class="pl-nav-section">Standalone Plans</div>
            <?php foreach ($standalonePlans as $plan): ?>
            <a class="pl-nav-item<?= (int) $plan['id'] === $selectedPlanId ? ' active' : '' ?>" href="<?= url('/admin/membership/plans?plan=' . (int) $plan['id']) ?>">
                <span><?= e($plan['name']) ?></span>
                <span class="pl-nav-count"><?= (int) ($subCountByPlan[(int) $plan['id']] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title">Subscriptions and Plans</span>
            <div class="pl-main-toolbar-actions">
                <a class="btn btn-primary btn-small" href="<?= url('/admin/membership/plans/new-group') ?>">+ New Group</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans/new-tier' . ($selectedPlan && isset($groupById[(int) $selectedPlan['id']]) ? '?parent_id=' . (int) $selectedPlan['id'] : '')) ?>">+ New Tier</a>
            </div>
        </div>

        <div class="pl-main-scroll">
            <?php if (empty($plans)): ?>
            <p class="pl-empty">No plans found.</p>
            <?php else: ?>
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Group</th>
                        <th>Subject</th>
                        <th>Price</th>
                        <th>Active</th>
                        <th>Subs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                    <?php
                        $planId = (int) $plan['id'];
                        $isStructuralGroup = isset($groupById[$planId]);
                        $groupName = '—';
                        $parentId = (int) ($plan['parent_plan_id'] ?? 0);
                        if ($parentId > 0) {
                            $groupName = (string) ($groupById[$parentId]['name'] ?? ('#' . $parentId));
                        }
                            $basePrice = (float) ($plan['price'] ?? 0);
                            $effectivePrice = $basePrice;
                            $promoLabel = '—';
                            $promoType = (string) ($plan['promo_type'] ?? '');
                            $promoValue = isset($plan['promo_value']) ? (float) $plan['promo_value'] : 0.0;
                            $promoStarts = !empty($plan['promo_starts_at']) ? strtotime((string) $plan['promo_starts_at']) : false;
                            $promoEnds = !empty($plan['promo_ends_at']) ? strtotime((string) $plan['promo_ends_at']) : false;
                            $promoActive = $promoType !== ''
                                && ($promoStarts === false || $promoStarts <= $nowTs)
                                && ($promoEnds === false || $promoEnds >= $nowTs);
                            if ($promoType === 'percent' && $promoValue > 0) {
                                $promoLabel = rtrim(rtrim(number_format($promoValue, 2, '.', ''), '0'), '.') . '% off';
                                if ($promoActive) {
                                    $effectivePrice = max(0.0, $basePrice * (1 - ($promoValue / 100)));
                                }
                            } elseif ($promoType === 'fixed' && $promoValue > 0) {
                                $promoLabel = (string) ($plan['currency'] ?? 'EUR') . ' ' . number_format($promoValue, 2) . ' off';
                                if ($promoActive) {
                                    $effectivePrice = max(0.0, $basePrice - $promoValue);
                                }
                            }
                            if ($promoLabel !== '—' && !$promoActive) {
                                $promoLabel .= ' (scheduled)';
                            }
                    ?>
                    <tr<?= $planId === $selectedPlanId ? ' class="selected"' : '' ?> onclick="window.location='<?= url('/admin/membership/plans?plan=' . $planId) ?>'">
                        <td><?= e($plan['name']) ?></td>
                        <td><?= $isStructuralGroup ? 'Group' : ($parentId > 0 ? 'Tier' : 'Plan') ?></td>
                        <td><?= e($groupName) ?></td>
                        <td><?= e($plan['subject_title'] ?? '—') ?></td>
                        <td>
                            <?= e($plan['currency']) ?> <?= number_format($effectivePrice, 2) ?>
                            <?php if ($effectivePrice !== $basePrice): ?>
                            <small class="text-muted" style="display:block;">base <?= e($plan['currency']) ?> <?= number_format($basePrice, 2) ?></small>
                            <?php endif; ?>
                            <?php if ($promoLabel !== '—'): ?>
                            <small class="text-muted" style="display:block;"><?= $promoLabel ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($plan['is_active']) ? 'Yes' : 'No' ?></td>
                        <td><?= (int) ($subCountByPlan[$planId] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Plan Detail</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
            <?php if (!$selectedPlan): ?>
            <p class="text-muted" style="font-size:0.85rem;">Select a plan to view details.</p>
            <?php else: ?>
            <table class="pl-meta">
                <tr><th>Name</th><td><?= e($selectedPlan['name']) ?></td></tr>
                <tr><th>Slug</th><td><code><?= e($selectedPlan['slug']) ?></code></td></tr>
                <tr><th>Type</th><td><?= isset($groupById[(int) $selectedPlan['id']]) ? 'Group' : ((int) ($selectedPlan['parent_plan_id'] ?? 0) > 0 ? 'Tier' : 'Plan') ?></td></tr>
                <tr><th>Subject</th><td><?= e($selectedPlan['subject_title'] ?? '—') ?></td></tr>
                <tr><th>Billing</th><td><?= e($selectedPlan['billing_period']) ?></td></tr>
                <tr><th>Price</th><td><?= e($selectedPlan['currency']) ?> <?= number_format((float) $selectedPlan['price'], 2) ?></td></tr>
                  <tr><th>Promotion</th><td>
                    <?php if (!empty($selectedPlan['promo_type']) && (float) ($selectedPlan['promo_value'] ?? 0) > 0): ?>
                        <?php
                            $detailPromo = (string) $selectedPlan['promo_type'] === 'percent'
                                ? (rtrim(rtrim(number_format((float) $selectedPlan['promo_value'], 2, '.', ''), '0'), '.') . '% off')
                                : ((string) ($selectedPlan['currency'] ?? 'EUR') . ' ' . number_format((float) $selectedPlan['promo_value'], 2) . ' off');
                        ?>
                        <?= e($detailPromo) ?>
                        <?php if (!empty($selectedPlan['promo_starts_at']) || !empty($selectedPlan['promo_ends_at'])): ?>
                        <small class="text-muted" style="display:block;">
                            <?= !empty($selectedPlan['promo_starts_at']) ? e((string) $selectedPlan['promo_starts_at']) : 'now' ?>
                            to
                            <?= !empty($selectedPlan['promo_ends_at']) ? e((string) $selectedPlan['promo_ends_at']) : 'open' ?>
                        </small>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                  </td></tr>
                <tr><th>Active</th><td><?= !empty($selectedPlan['is_active']) ? 'Yes' : 'No' ?></td></tr>
                <tr><th>Subscriptions</th><td><?= (int) ($subCountByPlan[(int) $selectedPlan['id']] ?? 0) ?></td></tr>
            </table>
            <div class="pl-detail-actions-stack">
                <a class="btn btn-primary btn-small" href="<?= url('/admin/membership/plans/' . (int) $selectedPlan['id'] . '/edit') ?>">Edit Plan</a>
                <?php if (isset($groupById[(int) $selectedPlan['id']])): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans/new-tier?parent_id=' . (int) $selectedPlan['id']) ?>">Add Tier to Group</a>
                <?php endif; ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">Open Members Workspace</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
