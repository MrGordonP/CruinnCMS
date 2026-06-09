<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$plans = $plans ?? [];
$groupPlans = $groupPlans ?? [];
$selectedPlanId = (int) ($selectedPlanId ?? 0);
$selectedPlan = $selectedPlan ?? null;
$subCountByPlan = $subCountByPlan ?? [];
$inlineMode = $inlineMode ?? null;
$inlinePlan = $inlinePlan ?? null;
$inlineErrors = $inlineErrors ?? [];
$inlineGroupPlans = $inlineGroupPlans ?? $groupPlans;
$inlineSubjects = $inlineSubjects ?? [];
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

// Inline form helpers
$periods = ['annual','monthly','quarterly','lifetime','custom'];
$inlineIsGroupMode = $inlineMode === 'group';
$inlineIsEdit = $inlinePlan && !empty($inlinePlan['id']);
$inlinePromoStarts = (string) ($inlinePlan['promo_starts_at'] ?? '');
if ($inlinePromoStarts !== '') { $inlinePromoStarts = str_replace(' ', 'T', substr($inlinePromoStarts, 0, 16)); }
$inlinePromoEnds = (string) ($inlinePlan['promo_ends_at'] ?? '');
if ($inlinePromoEnds !== '') { $inlinePromoEnds = str_replace(' ', 'T', substr($inlinePromoEnds, 0, 16)); }
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
            <?php
                // Determine which group to expand on load:
                // the one containing the selected plan, else the last group
                $lastGroupId = !empty($groupPlans) ? (int) end($groupPlans)['id'] : 0;
                $openGroupId = $lastGroupId;
                foreach ($groupPlans as $g) {
                    $gid = (int) $g['id'];
                    if ($gid === $selectedPlanId) { $openGroupId = $gid; break; }
                    foreach (($tiersByParent[$gid] ?? []) as $t) {
                        if ((int) $t['id'] === $selectedPlanId) { $openGroupId = $gid; break 2; }
                    }
                }
            ?>
            <?php foreach ($groupPlans as $group): ?>
            <?php $gid = (int) $group['id']; ?>
            <details class="pl-nav-group" id="nav-group-<?= $gid ?>"<?= $gid === $openGroupId ? ' open' : '' ?>>
                <summary class="pl-nav-item pl-nav-group-summary<?= $gid === $selectedPlanId ? ' active' : '' ?>" onclick="event.preventDefault(); this.closest('details').toggleAttribute('open');">
                    <span><?= e($group['name']) ?></span>
                    <span class="pl-nav-count"><?= (int) ($subCountByPlan[$gid] ?? 0) ?></span>
                </summary>
                <a class="pl-nav-item pl-nav-group-self<?= $gid === $selectedPlanId ? ' active' : '' ?>" href="<?= url('/admin/membership/plans?plan=' . $gid) ?>" style="padding-left:1rem;font-style:italic;font-size:0.82rem;">Group overview</a>
                <?php foreach (($tiersByParent[$gid] ?? []) as $tier): ?>
                <a class="pl-nav-item<?= (int) $tier['id'] === $selectedPlanId ? ' active' : '' ?>" href="<?= url('/admin/membership/plans?plan=' . (int) $tier['id']) ?>" style="padding-left:1.7rem;">
                    <span>&#8627; <?= e($tier['name']) ?></span>
                    <span class="pl-nav-count"><?= (int) ($subCountByPlan[(int) $tier['id']] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            </details>
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
                <a class="btn btn-primary btn-small" href="<?= url('/admin/membership/plans?action=new-group') ?>">+ New Group</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans?action=new-tier' . ($selectedPlan && isset($groupById[(int) $selectedPlan['id']]) ? '&parent_id=' . (int) $selectedPlan['id'] : '')) ?>">+ New Tier</a>
            </div>
        </div>

        <div class="pl-main-scroll">
            <?php if (empty($plans)): ?>
            <p class="pl-empty">No plans found.</p>
            <?php else: ?>
            <form method="post" action="<?= url('/admin/membership/plans/bulk') ?>" id="bulk-plans-form">
                <?= csrf_field() ?>
                <div id="bulk-plans-bar" style="display:none;align-items:center;gap:0.6rem;padding:0.5rem 1rem;background:#fef9c3;border-bottom:1px solid #fde047;">
                    <span id="bulk-plans-count" style="font-size:0.82rem;font-weight:600;"></span>
                    <select name="bulk_action" class="form-input" style="font-size:0.82rem;width:auto;">
                        <option value="">— Action —</option>
                        <option value="set_active">Set Active</option>
                        <option value="set_inactive">Set Inactive</option>
                        <option value="delete">Delete (no subscriptions only)</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-small" onclick="return confirm('Apply bulk action to selected plans?')">Apply</button>
                    <button type="button" class="btn btn-outline btn-small" onclick="document.querySelectorAll('.plan-cb').forEach(cb=>cb.checked=false);updateBulkPlansBar();">Deselect all</button>
                </div>
                <table class="pl-table" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:2.2rem;">
                        <col style="width:3.5rem;">
                        <col>
                        <col style="width:7%;">
                        <col style="width:13%;">
                        <col style="width:14%;">
                        <col style="width:14%;">
                        <col style="width:6%;">
                        <col style="width:6%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="plans-select-all" title="Select all" style="cursor:pointer;"></th>
                            <th>ID</th>
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
                        <?php
                            // Pre-compute which group is open in center panel
                            $centerOpenGroupId = $lastGroupId ?? 0;
                            foreach ($groupPlans as $g) {
                                $gid = (int) $g['id'];
                                if ($gid === $selectedPlanId) { $centerOpenGroupId = $gid; break; }
                                foreach (($tiersByParent[$gid] ?? []) as $t) {
                                    if ((int) $t['id'] === $selectedPlanId) { $centerOpenGroupId = $gid; break 2; }
                                }
                            }
                        ?>
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
                                if ($promoActive) { $effectivePrice = max(0.0, $basePrice * (1 - ($promoValue / 100))); }
                            } elseif ($promoType === 'fixed' && $promoValue > 0) {
                                $promoLabel = (string) ($plan['currency'] ?? 'EUR') . ' ' . number_format($promoValue, 2) . ' off';
                                if ($promoActive) { $effectivePrice = max(0.0, $basePrice - $promoValue); }
                            }
                            if ($promoLabel !== '—' && !$promoActive) { $promoLabel .= ' (scheduled)'; }
                            // Is this a group row? Render toggle + child count
                            $tierCount = $isStructuralGroup ? count($tiersByParent[$planId] ?? []) : 0;
                            $isOpen = $isStructuralGroup && $planId === $centerOpenGroupId;
                        ?>
                        <?php if ($isStructuralGroup): ?>
                        <tr class="plan-group-row<?= $planId === $selectedPlanId ? ' selected' : '' ?>"
                            data-group-id="<?= $planId ?>"
                            onclick="window.location='<?= url('/admin/membership/plans?plan=' . $planId) ?>'">
                            <td onclick="event.stopPropagation()"><input type="checkbox" class="plan-cb" name="plan_ids[]" value="<?= $planId ?>" onchange="updateBulkPlansBar()"></td>
                            <td style="color:var(--text-muted);font-size:0.8rem;font-variant-numeric:tabular-nums;"><?= $planId ?></td>
                            <td style="font-weight:600;">
                                <button type="button" class="plan-group-toggle" data-group="<?= $planId ?>" onclick="event.stopPropagation(); togglePlanGroup(<?= $planId ?>);"
                                        style="background:none;border:none;cursor:pointer;padding:0 0.3rem 0 0;font-size:0.85rem;line-height:1;color:var(--text-muted);"><?= $isOpen ? '▼' : '▶' ?></button>
                                <?= e($plan['name']) ?>
                                <?php if ($tierCount > 0): ?><small style="color:var(--text-muted);font-weight:normal;margin-left:0.3rem;">(<?= $tierCount ?> tier<?= $tierCount !== 1 ? 's' : '' ?>)</small><?php endif; ?>
                            </td>
                            <td>Group</td>
                            <td>—</td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($plan['subject_title'] ?? '—') ?></td>
                            <td>—</td>
                            <td><?= !empty($plan['is_active']) ? 'Yes' : 'No' ?></td>
                            <td><?= (int) ($subCountByPlan[$planId] ?? 0) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr class="<?= $parentId > 0 ? 'plan-tier-row' : '' ?><?= $planId === $selectedPlanId ? ' selected' : '' ?>"
                            <?= $parentId > 0 ? 'data-parent-group="' . $parentId . '"' : '' ?>
                            style="<?= $parentId > 0 && $parentId !== $centerOpenGroupId ? 'display:none;' : '' ?>"
                            onclick="window.location='<?= url('/admin/membership/plans?plan=' . $planId) ?>'">
                            <td onclick="event.stopPropagation()"><input type="checkbox" class="plan-cb" name="plan_ids[]" value="<?= $planId ?>" onchange="updateBulkPlansBar()"></td>
                            <td style="color:var(--text-muted);font-size:0.8rem;font-variant-numeric:tabular-nums;"><?= $planId ?></td>
                            <td style="<?= $parentId > 0 ? 'padding-left:1.5rem;' : '' ?>"><?= $parentId > 0 ? '&#8627; ' : '' ?><?= e($plan['name']) ?></td>
                            <td><?= $parentId > 0 ? 'Tier' : 'Plan' ?></td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($groupName) ?></td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($plan['subject_title'] ?? '—') ?></td>
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
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <script>
            (function(){
                var sa = document.getElementById('plans-select-all');
                if (sa) sa.addEventListener('change', function(){
                    document.querySelectorAll('.plan-cb').forEach(function(cb){ cb.checked = sa.checked; });
                    updateBulkPlansBar();
                });
            })();
            function updateBulkPlansBar() {
                var checked = document.querySelectorAll('.plan-cb:checked');
                var bar = document.getElementById('bulk-plans-bar');
                var count = document.getElementById('bulk-plans-count');
                if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
                if (count) count.textContent = checked.length + ' selected';
            }
            function togglePlanGroup(groupId) {
                var btn = document.querySelector('.plan-group-toggle[data-group="' + groupId + '"]');
                var tiers = document.querySelectorAll('tr.plan-tier-row[data-parent-group="' + groupId + '"]');
                var isOpen = tiers.length > 0 && tiers[0].style.display !== 'none';
                tiers.forEach(function(tr) { tr.style.display = isOpen ? 'none' : ''; });
                if (btn) btn.textContent = isOpen ? '▶' : '▼';
            }
            </script>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title"><?= $inlineMode ? ($inlineIsEdit ? 'Edit Plan' : ($inlineIsGroupMode ? ($inlinePlan['_clone_from_name'] ?? null ? 'New Group — From: ' . e($inlinePlan['_clone_from_name']) : 'New Group') : ($inlinePlan['_clone_from_name'] ?? null ? 'New Tier — From: ' . e($inlinePlan['_clone_from_name']) : 'New Tier'))) : 'Plan Detail' ?></span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
        <?php if ($inlineMode && $inlinePlan !== null): ?>
            <?php
                $formAction = $inlineIsEdit
                    ? url('/admin/membership/plans/' . (int) $inlinePlan['id'])
                    : url('/admin/membership/plans');
            ?>
            <?php if (!empty($inlineErrors)): ?>
            <div class="form-errors" style="margin-bottom:0.75rem;"><ul>
                <?php foreach ($inlineErrors as $field => $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul></div>
            <?php endif; ?>
            <form method="post" action="<?= $formAction ?>" style="display:grid;gap:0.75rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="<?= e($inlineMode) ?>">
                <input type="hidden" name="is_plan_group" value="<?= $inlineIsGroupMode ? '1' : '0' ?>">
                <?php if (!empty($inlinePlan['_clone_from'])): ?>
                <input type="hidden" name="clone_from" value="<?= (int) $inlinePlan['_clone_from'] ?>">
                <?php endif; ?>

                <div>
                    <label class="form-label">Name</label>
                    <input class="form-input" name="name" type="text" value="<?= e($inlinePlan['name'] ?? '') ?>">
                    <?php if (!empty($inlineErrors['name'])): ?><small class="text-danger"><?= e($inlineErrors['name']) ?></small><?php endif; ?>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea class="form-input" name="description" rows="3"><?= e($inlinePlan['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="form-label">Subject</label>
                    <select class="form-input" name="subject_id">
                        <option value="">No subject</option>
                        <?php foreach ($inlineSubjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"<?= (int) ($inlinePlan['subject_id'] ?? 0) === (int) $subject['id'] ? ' selected' : '' ?>><?= e($subject['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($inlineIsGroupMode): ?>
                <div>
                    <label class="form-label">Billing Period</label>
                    <select class="form-input" name="billing_period">
                        <?php foreach ($periods as $period): ?>
                        <option value="<?= e($period) ?>"<?= ($inlinePlan['billing_period'] ?? 'annual') === $period ? ' selected' : '' ?>><?= e(ucfirst($period)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="price" value="0">
                <input type="hidden" name="currency" value="EUR">
                <input type="hidden" name="promo_type" value="">
                <input type="hidden" name="promo_value" value="">
                <input type="hidden" name="promo_starts_at" value="">
                <input type="hidden" name="promo_ends_at" value="">
                <?php else: ?>
                <div>
                    <label class="form-label">Parent Group</label>
                    <select class="form-input" name="parent_plan_id">
                        <option value="">No parent group</option>
                        <?php foreach ($inlineGroupPlans as $gp): ?>
                        <option value="<?= (int) $gp['id'] ?>"<?= (int) ($inlinePlan['parent_plan_id'] ?? 0) === (int) $gp['id'] ? ' selected' : '' ?>><?= e($gp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="billing_period" value="<?= e((string) ($inlinePlan['billing_period'] ?? 'annual')) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                    <div>
                        <label class="form-label">Amount</label>
                        <input class="form-input" name="price" type="number" min="0" step="0.01" value="<?= e((string) ($inlinePlan['price'] ?? '0.00')) ?>">
                        <?php if (!empty($inlineErrors['price'])): ?><small class="text-danger"><?= e($inlineErrors['price']) ?></small><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Currency</label>
                        <input class="form-input" name="currency" type="text" maxlength="3" value="<?= e($inlinePlan['currency'] ?? 'EUR') ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                    <div>
                        <label class="form-label">Promo Type</label>
                        <select class="form-input" name="promo_type">
                            <option value="">None</option>
                            <option value="percent"<?= ($inlinePlan['promo_type'] ?? '') === 'percent' ? ' selected' : '' ?>>Percent</option>
                            <option value="fixed"<?= ($inlinePlan['promo_type'] ?? '') === 'fixed' ? ' selected' : '' ?>>Fixed</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Promo Value</label>
                        <input class="form-input" name="promo_value" type="number" min="0" step="0.01" value="<?= e((string) ($inlinePlan['promo_value'] ?? '')) ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                    <div>
                        <label class="form-label">Promo Starts</label>
                        <input class="form-input" name="promo_starts_at" type="datetime-local" value="<?= e($inlinePromoStarts) ?>">
                    </div>
                    <div>
                        <label class="form-label">Promo Ends</label>
                        <input class="form-input" name="promo_ends_at" type="datetime-local" value="<?= e($inlinePromoEnds) ?>">
                    </div>
                </div>
                <?php endif; ?>
                <label style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="is_active" value="1"<?= !isset($inlinePlan['is_active']) || !empty($inlinePlan['is_active']) ? ' checked' : '' ?>> Active
                </label>
                <label style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="is_group" value="1"<?= !empty($inlinePlan['is_group']) ? ' checked' : '' ?>> Shared subscription
                </label>
                <?php if (!$inlineIsGroupMode): ?>
                <div>
                    <label class="form-label">Max Members</label>
                    <input class="form-input" name="max_members" type="number" min="0" step="1" value="<?= e((string) ($inlinePlan['max_members'] ?? '0')) ?>">
                </div>
                <?php else: ?>
                <input type="hidden" name="max_members" value="<?= e((string) ($inlinePlan['max_members'] ?? '2')) ?>">
                <input type="hidden" name="parent_plan_id" value="0">
                <?php endif; ?>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <button class="btn btn-primary btn-small" type="submit"><?= $inlineIsEdit ? 'Save Plan' : 'Create Plan' ?></button>
                    <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans' . ($inlineIsEdit ? '?plan=' . (int) $inlinePlan['id'] : '')) ?>">Cancel</a>
                </div>
            </form>
        <?php elseif (!$selectedPlan): ?>
            <p class="text-muted" style="font-size:0.85rem;">Select a plan to view details.</p>
        <?php else: ?>
            <table class="pl-meta">
                <tr><th>ID</th><td><code><?= (int) $selectedPlan['id'] ?></code></td></tr>
                <tr><th>Name</th><td><?= e($selectedPlan['name']) ?></td></tr>
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
                    <?php else: ?>—<?php endif; ?>
                </td></tr>
                <tr><th>Active</th><td><?= !empty($selectedPlan['is_active']) ? 'Yes' : 'No' ?></td></tr>
                <tr><th>Subscriptions</th><td><?= (int) ($subCountByPlan[(int) $selectedPlan['id']] ?? 0) ?></td></tr>
            </table>
            <div class="pl-detail-actions-stack">
                <a class="btn btn-primary btn-small" href="<?= url('/admin/membership/plans?edit=' . (int) $selectedPlan['id']) ?>">Edit Plan</a>
                <?php if (isset($groupById[(int) $selectedPlan['id']])): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans?action=clone-group&from=' . (int) $selectedPlan['id']) ?>">Create From</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans?action=new-tier&parent_id=' . (int) $selectedPlan['id']) ?>">Add Tier to Group</a>
                <?php else: ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans?action=clone-tier&from=' . (int) $selectedPlan['id']) ?>">Create From</a>
                <?php endif; ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">Open Members Workspace</a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
