<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireJs('membership.js');
$GLOBALS['admin_flush_layout'] = true;

$selectedId       = (int) ($memberId ?? 0);
$verificationStatuses = ['unverified', 'verified', 'disputed', 'waived'];
$memberTree       = $memberTree ?? ['groups' => [], 'plansByParent' => [], 'standalone' => []];
$category         = $category ?? 'all';
$categoryId       = (int) ($categoryId ?? 0);
$filters          = $filters ?? ['q' => '', 'status_filter' => '', 'org_filter' => '', 'sort' => '', 'dir' => 'asc'];
$allowedStatuses  = $allowedStatuses ?? ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
$distinctOrgs     = $distinctOrgs ?? [];

$subsByMember    = $subsByMember ?? [];
$groups          = $memberTree['groups'] ?? [];
$plansByParent   = $memberTree['plansByParent'] ?? [];
$standalonePlans = $memberTree['standalone'] ?? [];

$selectedCategoryLabel = 'All Members';
if ($category === 'group' && isset($groups[$categoryId])) {
    $selectedCategoryLabel = 'Group: ' . (string) $groups[$categoryId]['name'];
}
if ($category === 'plan') {
    foreach ($plans as $planOption) {
        if ((int) $planOption['id'] === $categoryId) {
            $selectedCategoryLabel = 'Plan: ' . (string) $planOption['name'];
            break;
        }
    }
}

// Build a base URL preserving current filters (for sort links)
$baseFilterParams = array_filter([
    'category'      => $category !== 'all' ? $category : '',
    'category_id'   => $categoryId > 0 ? $categoryId : '',
    'q'             => $filters['q'],
    'status_filter' => $filters['status_filter'],
    'org_filter'    => $filters['org_filter'],
]);
$baseFilterQuery = http_build_query($baseFilterParams);

$sortLink = static function(string $col, string $label) use ($filters, $baseFilterQuery): string {
    $currentSort = $filters['sort'] ?? '';
    $currentDir  = $filters['dir'] ?? 'asc';
    $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow  = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' ↑' : ' ↓';
    }
    $qs = $baseFilterQuery ? $baseFilterQuery . '&' : '';
    return '<a href="?' . $qs . 'sort=' . urlencode($col) . '&dir=' . $newDir . '" style="color:inherit;text-decoration:none;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
};

$statusBadge = static function(string $status): string {
    $colours = [
        'active'    => '#16a34a',
        'applicant' => '#2563eb',
        'lapsed'    => '#d97706',
        'suspended' => '#dc2626',
        'resigned'  => '#6b7280',
        'archived'  => '#9ca3af',
    ];
    $colour = $colours[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:0.72rem;font-weight:600;background:' . $colour . '22;color:' . $colour . ';">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
};

$verifiedBadge = static function(?string $vs): string {
    if ($vs === null || $vs === '') { return '<span style="color:#9ca3af;font-size:0.78rem;">—</span>'; }
    $colours = ['verified' => '#16a34a', 'unverified' => '#d97706', 'disputed' => '#dc2626', 'waived' => '#6b7280'];
    $colour = $colours[$vs] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:0.72rem;background:' . $colour . '22;color:' . $colour . ';">' . htmlspecialchars($vs, ENT_QUOTES, 'UTF-8') . '</span>';
};
?>

<div class="panel-layout" id="membership-layout">
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header" style="flex-direction:column;align-items:stretch;gap:0.45rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.4rem;">
                <span class="pl-panel-title">Members</span>
                <a href="<?= url('/admin/membership/members/new') ?>" class="btn btn-primary btn-small">+ New</a>
            </div>
            <form method="get" action="<?= url('/admin/membership/members') ?>" id="members-filter-form" style="display:flex;gap:0.3rem;">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
                <input type="hidden" name="sort" value="<?= e($filters['sort'] ?? '') ?>">
                <input type="hidden" name="dir" value="<?= e($filters['dir'] ?? 'asc') ?>">
                <input class="form-input" type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search members..." style="font-size:0.82rem;">
                <button class="btn btn-secondary btn-small" type="submit">Go</button>
            </form>
        </div>

        <div class="pl-panel-body" style="padding:0;">
            <div class="pl-nav-section">Categories</div>
            <a class="pl-nav-item<?= $category === 'all' && empty($filters['status_filter']) && empty($filters['org_filter']) ? ' active' : '' ?>" href="<?= url('/admin/membership/members?category=all') ?>">
                <span>All Members</span>
                <span class="pl-nav-count"><?= (int) ($statusCount['total'] ?? 0) ?></span>
            </a>

            <div class="pl-nav-section">Subscription Groups</div>
            <?php if (empty($groups)): ?>
            <div style="padding:0.6rem 0.9rem;color:#64748b;font-size:0.82rem;">No groups available.</div>
            <?php else: ?>
            <?php foreach ($groups as $group): ?>
            <a class="pl-nav-item<?= $category === 'group' && $categoryId === (int) $group['id'] ? ' active' : '' ?>" href="<?= url('/admin/membership/members?category=group&category_id=' . (int) $group['id']) ?>">
                <span><?= e($group['name']) ?></span>
            </a>
                <?php foreach (($plansByParent[(int) $group['id']] ?? []) as $tier): ?>
                <a class="pl-nav-item<?= $category === 'plan' && $categoryId === (int) $tier['id'] ? ' active' : '' ?>" href="<?= url('/admin/membership/members?category=plan&category_id=' . (int) $tier['id']) ?>" style="padding-left:1.7rem;">
                    <span>&#8627; <?= e($tier['name']) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($standalonePlans)): ?>
            <div class="pl-nav-section">Standalone Plans</div>
            <?php foreach ($standalonePlans as $plan): ?>
            <a class="pl-nav-item<?= $category === 'plan' && $categoryId === (int) $plan['id'] ? ' active' : '' ?>" href="<?= url('/admin/membership/members?category=plan&category_id=' . (int) $plan['id']) ?>">
                <span><?= e($plan['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <div class="pl-nav-section">Account Status</div>
            <form method="get" action="<?= url('/admin/membership/members') ?>" style="padding:0.4rem 0.9rem 0.6rem;">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
                <input type="hidden" name="q" value="<?= e($filters['q'] ?? '') ?>">
                <input type="hidden" name="org_filter" value="<?= e($filters['org_filter'] ?? '') ?>">
                <input type="hidden" name="sort" value="<?= e($filters['sort'] ?? '') ?>">
                <input type="hidden" name="dir" value="<?= e($filters['dir'] ?? 'asc') ?>">
                <select name="status_filter" class="form-input" style="font-size:0.82rem;width:100%;" onchange="this.form.submit()">
                    <option value="">— All statuses —</option>
                    <?php foreach ($allowedStatuses as $st): ?>
                    <option value="<?= e($st) ?>"<?= ($filters['status_filter'] ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (!empty($distinctOrgs)): ?>
            <div class="pl-nav-section">Organisation</div>
            <form method="get" action="<?= url('/admin/membership/members') ?>" style="padding:0.4rem 0.9rem 0.6rem;">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
                <input type="hidden" name="q" value="<?= e($filters['q'] ?? '') ?>">
                <input type="hidden" name="status_filter" value="<?= e($filters['status_filter'] ?? '') ?>">
                <input type="hidden" name="sort" value="<?= e($filters['sort'] ?? '') ?>">
                <input type="hidden" name="dir" value="<?= e($filters['dir'] ?? 'asc') ?>">
                <select name="org_filter" class="form-input" style="font-size:0.82rem;width:100%;" onchange="this.form.submit()">
                    <option value="">— All organisations —</option>
                    <?php foreach ($distinctOrgs as $org): ?>
                    <option value="<?= e($org) ?>"<?= ($filters['org_filter'] ?? '') === $org ? ' selected' : '' ?>><?= e($org) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($selectedCategoryLabel) ?></span>
            <div class="pl-main-toolbar-actions">
                <a href="<?= url('/admin/membership') ?>" class="btn btn-outline btn-small">Hub</a>
                <a href="<?= url('/admin/membership/plans') ?>" class="btn btn-outline btn-small">Plans</a>
                <a href="<?= url('/admin/membership/import') ?>" class="btn btn-outline btn-small">Import</a>
            </div>
        </div>

        <div class="pl-main-scroll">
            <?php if (empty($members)): ?>
            <p class="pl-empty">No members found for this selection.</p>
            <?php else: ?>
            <form method="post" action="<?= url('/admin/membership/members/bulk') ?>" id="bulk-form">
                <?= csrf_field() ?>
                <div id="bulk-bar" style="display:none;align-items:center;gap:0.6rem;padding:0.5rem 1rem;background:#fef9c3;border-bottom:1px solid #fde047;">
                    <span id="bulk-count" style="font-size:0.82rem;font-weight:600;"></span>
                    <select name="bulk_action" class="form-input" style="font-size:0.82rem;width:auto;">
                        <option value="">— Bulk action —</option>
                        <option value="set_status">Set Status…</option>
                        <option value="archive">Archive</option>
                        <option value="delete">Delete (no subscriptions only)</option>
                    </select>
                    <select name="bulk_status" class="form-input" style="font-size:0.82rem;width:auto;" id="bulk-status-select">
                        <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>"><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-small" data-confirm="Apply bulk action to selected members?">Apply</button>
                    <button type="button" class="btn btn-outline btn-small" data-action="deselect-all">Deselect all</button>
                </div>
                <table class="pl-table" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:2.2rem;">
                        <col style="width:16%;">
                        <col style="width:18%;">
                        <col style="width:9%;">
                        <col style="width:13%;">
                        <col style="width:9%;">
                        <col style="width:14%;">
                        <col style="width:12%;">
                        <col style="width:9%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" title="Select all" style="cursor:pointer;"></th>
                            <th><?= $sortLink('surnames', 'Name') ?></th>
                            <th><?= $sortLink('email', 'Email') ?></th>
                            <th><?= $sortLink('membership_number', 'Mbr #') ?></th>
                            <th><?= $sortLink('organisation', 'Organisation') ?></th>
                            <th><?= $sortLink('status', 'Status') ?></th>
                            <th><?= $sortLink('plan_name', 'Plan') ?></th>
                            <th>Group</th>
                            <th><?= $sortLink('verification_status', 'Verified') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <?php
                            $mId = (int) $m['id'];
                            $rowName = trim((string) ($m['forenames'] ?? '') . ' ' . (string) ($m['surnames'] ?? ''));
                            $memberUrl = '/admin/membership/members?member=' . $mId
                                . '&category=' . urlencode((string) $category)
                                . '&category_id=' . (int) $categoryId
                                . (!empty($filters['q']) ? '&q=' . urlencode((string) $filters['q']) : '')
                                . (!empty($filters['status_filter']) ? '&status_filter=' . urlencode((string) $filters['status_filter']) : '')
                                . (!empty($filters['org_filter']) ? '&org_filter=' . urlencode((string) $filters['org_filter']) : '');
                            $mSubs = $subsByMember[$mId] ?? [];
                        ?>
                        <tr<?= $mId === $selectedId ? ' class="selected"' : '' ?> style="cursor:pointer;"
                            data-row-url="<?= e(url($memberUrl)) ?>"
                            data-row-profile-url="<?= e(url('/admin/membership/members/' . $mId)) ?>">
                            <td data-stop-propagation><input type="checkbox" class="member-cb" name="member_ids[]" value="<?= $mId ?>"></td>
                            <td>
                                <a href="<?= e(url('/admin/membership/members/' . $mId)) ?>" data-stop-propagation><?= e($rowName !== '' ? $rowName : '(unnamed)') ?></a>
                                <?php if (!empty($mSubs)): ?>
                                <button type="button" class="sub-expand-btn" data-mid="<?= $mId ?>"
                                    title="Show subscriptions"
                                    style="margin-left:0.3rem;background:none;border:none;cursor:pointer;font-size:0.75rem;color:#6b7280;padding:0;">
                                    ▸ <?= count($mSubs) ?>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string) ($m['email'] ?? '')) ?>"><?= e((string) ($m['email'] ?? '—')) ?></td>
                            <td><?= e((string) ($m['membership_number'] ?? '—')) ?></td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string) ($m['organisation'] ?? '—')) ?></td>
                            <td><?= $statusBadge((string) ($m['status'] ?? 'applicant')) ?></td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string) ($m['plan_name'] ?? '—')) ?></td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string) ($m['group_name'] ?? '—')) ?></td>
                            <td>
                                <?= $verifiedBadge((string) ($m['verification_status'] ?? '')) ?>
                                <a href="<?= e(url('/admin/membership/members/' . $mId)) ?>" data-stop-propagation title="Open profile" style="margin-left:0.3rem;color:#6b7280;text-decoration:none;font-size:0.75rem;">↗</a>
                            </td>
                        </tr>
                        <?php if (!empty($mSubs)): ?>
                        <tr id="sub-row-<?= $mId ?>" style="display:none;background:#f8fafc;">
                            <td colspan="9" style="padding:0 0.5rem 0.5rem 2.5rem;">
                                <table style="width:100%;font-size:0.78rem;border-collapse:collapse;">
                                    <thead>
                                        <tr style="color:#6b7280;">
                                            <th style="text-align:left;padding:0.25rem 0.4rem;font-weight:600;">Period</th>
                                            <th style="text-align:left;padding:0.25rem 0.4rem;font-weight:600;">Plan</th>
                                            <th style="text-align:left;padding:0.25rem 0.4rem;font-weight:600;">Amount</th>
                                            <th style="text-align:left;padding:0.25rem 0.4rem;font-weight:600;">Verified</th>
                                            <th style="text-align:left;padding:0.25rem 0.4rem;font-weight:600;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($mSubs as $ms): ?>
                                    <tr style="border-top:1px solid #e5e7eb;">
                                        <td style="padding:0.25rem 0.4rem;"><?= e((string) ($ms['period_start'] ?? '')) ?> – <?= e((string) ($ms['period_end'] ?? '')) ?></td>
                                        <td style="padding:0.25rem 0.4rem;"><?= e((string) ($ms['plan_name'] ?? '—')) ?></td>
                                        <td style="padding:0.25rem 0.4rem;"><?= e((string) ($ms['currency'] ?? 'EUR')) ?> <?= number_format((float) ($ms['amount'] ?? 0), 2) ?></td>
                                        <td style="padding:0.25rem 0.4rem;"><?= $verifiedBadge((string) ($ms['verification_status'] ?? '')) ?></td>
                                        <td style="padding:0.25rem 0.4rem;"><a href="<?= url('/admin/membership/subscriptions?sub=' . (int) $ms['id']) ?>" style="font-size:0.75rem;color:#2563eb;">View →</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Subscriptions</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
        <?php if (!$member): ?>
            <p class="text-muted" style="font-size:0.85rem">Select a member to view subscriptions.</p>
        <?php else: ?>

            <?php if (!empty($subscriptions)): ?>
            <?php foreach ($subscriptions as $sub): ?>
            <?php
                $subLabel = e(($sub['period_start'] ?? '') . ' - ' . ($sub['period_end'] ?? ''));
                $subVs = $sub['verification_status'] ?? 'unverified';
            ?>
            <div class="detail-card" style="margin-bottom:0.75rem;padding:0.6rem">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem">
                    <div>
                        <strong style="font-size:0.85rem"><?= $subLabel ?></strong>
                        <small style="display:block;color:var(--color-text-muted)"><?= e($sub['plan_name'] ?? '-') ?> - <?= e($sub['currency']) ?> <?= number_format((float) $sub['amount'], 2) ?></small>
                    </div>
                    <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $sub['id'] . '/status') ?>" style="display:flex;gap:0.3rem;align-items:center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
                        <select name="status" class="form-input" style="font-size:0.75rem;padding:1px 4px">
                            <?php foreach ($verificationStatuses as $s): ?>
                            <option value="<?= e($s) ?>"<?= $subVs === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-small" type="submit">Set</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">No subscriptions recorded.</p>
            <?php endif; ?>

            <div class="detail-card" style="padding:0.75rem;margin-bottom:1rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em">Add Subscription</h4>
                <form method="post" action="<?= url('/admin/membership/members/' . (int) $member['id'] . '/subscriptions') ?>" style="display:grid;gap:0.4rem">
                    <?= csrf_field() ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem">
                        <input class="form-input" style="font-size:0.82rem" type="date" name="period_start" required>
                        <input class="form-input" style="font-size:0.82rem" type="date" name="period_end" required>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 80px;gap:0.4rem">
                        <input class="form-input" style="font-size:0.82rem" type="number" step="0.01" min="0" name="amount" placeholder="Amount" required>
                        <input class="form-input" style="font-size:0.82rem" type="text" name="currency" value="EUR" maxlength="3" required>
                    </div>
                    <select class="form-input" style="font-size:0.82rem" name="plan_id">
                        <option value="">No plan</option>
                        <?php foreach ($plans as $plan): ?>
                        <?php
                            $parentName = '';
                            if (!empty($plan['parent_plan_id'])) {
                                foreach ($plans as $gp) {
                                    if ((int) $gp['id'] === (int) $plan['parent_plan_id']) {
                                        $parentName = (string) $gp['name'];
                                        break;
                                    }
                                }
                            }
                            $label = $parentName !== '' ? ($parentName . ' -> ' . $plan['name']) : $plan['name'];
                        ?>
                        <option value="<?= (int)$plan['id'] ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-input" style="font-size:0.82rem" name="member_type">
                        <option value="new">New member</option>
                        <option value="renewal">Renewal</option>
                    </select>
                    <select class="form-input" style="font-size:0.82rem" name="payment_method">
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="cash">Cash</option>
                        <option value="online">Online</option>
                        <option value="waived">Waived</option>
                    </select>
                    <select class="form-input" style="font-size:0.82rem" name="verification_status">
                        <?php foreach ($verificationStatuses as $s): ?>
                        <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-input" style="font-size:0.82rem" type="text" name="notes" placeholder="Notes">
                    <button class="btn btn-primary btn-small" type="submit">Add Subscription</button>
                </form>
            </div>

            <?php if (!empty($subscriptions)): ?>
            <div class="detail-card" style="padding:0.75rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em">Record Payment</h4>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int)$subscriptions[0]['id'] . '/payments') ?>" style="display:grid;gap:0.4rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
                    <select class="form-input" style="font-size:0.82rem" name="subscription_id"
                            onchange="this.form.action='<?= url('/admin/membership/subscriptions') ?>/' + this.value + '/payments';">
                        <?php foreach ($subscriptions as $sub): ?>
                        <option value="<?= (int)$sub['id'] ?>">#<?= (int)$sub['id'] ?> - <?= e(($sub['period_start'] ?? '') . ' - ' . ($sub['period_end'] ?? '')) ?> (<?= e($sub['verification_status'] ?? 'unverified') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:grid;grid-template-columns:1fr 80px;gap:0.4rem">
                        <input class="form-input" style="font-size:0.82rem" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                        <input class="form-input" style="font-size:0.82rem" type="text" name="currency" value="EUR" maxlength="3" required>
                    </div>
                    <input class="form-input" style="font-size:0.82rem" type="text" name="transaction_id" placeholder="Transaction ID">
                    <input class="form-input" style="font-size:0.82rem" type="text" name="gateway" placeholder="Gateway (e.g. stripe)">
                    <input class="form-input" style="font-size:0.82rem" type="datetime-local" name="paid_at" value="<?= e(date('Y-m-d\\TH:i')) ?>">
                    <input class="form-input" style="font-size:0.82rem" type="text" name="notes" placeholder="Notes">
                    <button class="btn btn-primary btn-small" type="submit">Record Payment</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($payments)): ?>
            <div style="margin-top:1rem">
                <h4 style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem">Payment History</h4>
                <?php foreach ($payments as $p): ?>
                <div style="font-size:0.8rem;padding:0.4rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span><?= e($p['currency']) ?> <?= number_format((float)$p['amount'], 2) ?><?= !empty($p['gateway']) ? ' - ' . e($p['gateway']) : '' ?></span>
                    <span style="color:var(--color-text-muted)"><?= e($p['status'] ?? '') ?> - <?= e(substr((string)($p['paid_at'] ?? ''), 0, 10)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>
</div>
