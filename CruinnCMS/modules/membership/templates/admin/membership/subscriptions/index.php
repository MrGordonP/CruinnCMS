<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireJs('membership.js');
$GLOBALS['admin_flush_layout'] = true;

$subscriptions     = $subscriptions ?? [];
$selectedId        = (int) ($selectedId ?? 0);
$selectedSub       = $selectedSub ?? null;
$unmatchedPayments = $unmatchedPayments ?? [];
$availableYears    = $availableYears ?? [];
$plans             = $plans ?? [];
$filters           = $filters ?? [];
$allowedStatuses   = $allowedStatuses ?? ['unverified', 'verified', 'disputed', 'waived'];

$yearFilter   = (string) ($filters['yearFilter'] ?? '');
$planFilter   = (int) ($filters['planFilter'] ?? 0);
$statusFilter = (string) ($filters['statusFilter'] ?? '');
$memberSearch = (string) ($filters['memberSearch'] ?? '');
$sort         = (string) ($filters['sort'] ?? 'period_start');
$dir          = (string) ($filters['dir'] ?? 'DESC');

$baseParams = array_filter([
    'year'   => $yearFilter,
    'plan'   => $planFilter > 0 ? $planFilter : null,
    'status' => $statusFilter,
    'q'      => $memberSearch,
], static fn($v): bool => $v !== null && $v !== '' && $v !== 0);

$sortLink = static function (string $key, string $label) use ($sort, $dir, $baseParams): string {
    $newDir = ($sort === $key && $dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow  = $sort === $key ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    $qs     = http_build_query(array_merge($baseParams, ['sort' => $key, 'dir' => $newDir]));
    return '<a href="' . url('/admin/membership/subscriptions?' . $qs) . '" style="color:inherit;text-decoration:none;">' . e($label) . $arrow . '</a>';
};

$verBadge = static function (string $status): string {
    $map = [
        'verified'   => '#16a34a',
        'unverified' => '#6b7280',
        'disputed'   => '#dc2626',
        'waived'     => '#9333ea',
    ];
    $colour = $map[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:0.75rem;font-weight:600;background:' . $colour . ';color:#fff;">' . e(ucfirst($status)) . '</span>';
};
?>

<div class="panel-layout" id="membership-subs-layout">
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Filters</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
            <form method="get" action="<?= url('/admin/membership/subscriptions') ?>" style="display:grid;gap:0.65rem;">
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Member search</label>
                    <input class="form-input" name="q" type="text" value="<?= e($memberSearch) ?>" placeholder="Name, email, mbr #" style="font-size:0.82rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Year</label>
                    <select class="form-input" name="year" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All years</option>
                        <?php foreach ($availableYears as $y): ?>
                        <option value="<?= (int) $y ?>"<?= (string) $y === $yearFilter ? ' selected' : '' ?>><?= (int) $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Plan</label>
                    <select class="form-input" name="plan" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All plans</option>
                        <?php foreach ($plans as $pl): ?>
                        <option value="<?= (int) $pl['id'] ?>"<?= (int) $pl['id'] === $planFilter ? ' selected' : '' ?>><?= e($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Verification</label>
                    <select class="form-input" name="status" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>"<?= $st === $statusFilter ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary btn-small" type="submit">Search</button>
                <?php if ($yearFilter || $planFilter || $statusFilter || $memberSearch): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/subscriptions') ?>">Clear filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title">Subscriptions</span>
            <div class="pl-main-toolbar-actions">
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">Members</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/plans') ?>">Plans</a>
            </div>
        </div>

        <div class="pl-main-scroll">
            <?php if (empty($subscriptions)): ?>
            <p class="pl-empty">No subscriptions match the current filters.</p>
            <?php else: ?>
            <table class="pl-table" style="table-layout:fixed;">
                <colgroup>
                    <col style="width:16%;">
                    <col style="width:17%;">
                    <col style="width:9%;">
                    <col style="width:9%;">
                    <col style="width:16%;">
                    <col style="width:9%;">
                    <col style="width:10%;">
                    <col style="width:14%;">
                </colgroup>
                <thead>
                    <tr>
                        <th><?= $sortLink('member', 'Member') ?></th>
                        <th><?= $sortLink('plan', 'Plan') ?></th>
                        <th><?= $sortLink('period_start', 'Start') ?></th>
                        <th>End</th>
                        <th>Ref / Tx ID</th>
                        <th><?= $sortLink('amount', 'Amount') ?></th>
                        <th>Payment</th>
                        <th><?= $sortLink('verification', 'Verified') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <?php
                        $subId   = (int) $sub['id'];
                        $subName = trim((string) ($sub['forenames'] ?? '') . ' ' . (string) ($sub['surnames'] ?? ''));
                        $subUrl  = url('/admin/membership/subscriptions?' . http_build_query(array_merge($baseParams, ['sub' => $subId, 'sort' => $sort, 'dir' => $dir])));
                        $hasPayment = !empty($sub['payment_id']);
                    ?>
                    <tr<?= $subId === $selectedId ? ' class="selected"' : '' ?> data-row-url="<?= e($subUrl) ?>">
                        <td>
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($subName ?: '(unnamed)') ?></div>
                            <?php if (!empty($sub['membership_number'])): ?><small class="text-muted"><?= e((string) $sub['membership_number']) ?></small><?php endif; ?>
                        </td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string) ($sub['plan_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($sub['period_start'] ?? '')) ?></td>
                        <td><?= e((string) ($sub['period_end'] ?? '')) ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string) ($sub['transaction_id'] ?? '')) ?>"><?= e((string) ($sub['transaction_id'] ?? '—')) ?></td>
                        <td><?= e((string) ($sub['currency'] ?? 'EUR')) ?> <?= number_format((float) ($sub['amount'] ?? 0), 2) ?></td>
                        <td style="text-align:center;"><?= $hasPayment ? '<span style="color:#16a34a;font-weight:700;" title="Payment #' . (int) $sub['payment_id'] . '">&#10003;</span>' : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td><?= $verBadge((string) ($sub['verification_status'] ?? 'unverified')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($subscriptions) === 500): ?>
            <p style="padding:0.5rem 1rem;font-size:0.8rem;color:#6b7280;">Showing first 500 results. Use filters to narrow down.</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Subscription Detail</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
        <?php if (!$selectedSub): ?>
            <p class="text-muted" style="font-size:0.85rem;">Select a subscription to view details.</p>
        <?php else: ?>
            <?php
                $subName = trim((string) ($selectedSub['forenames'] ?? '') . ' ' . (string) ($selectedSub['surnames'] ?? ''));
            ?>
            <table class="pl-meta" style="margin-bottom:0.9rem;">
                <tr><th>Member</th><td>
                    <?= e($subName ?: '(unnamed)') ?>
                    <?php if (!empty($selectedSub['membership_number'])): ?><br><small class="text-muted"><?= e((string) $selectedSub['membership_number']) ?></small><?php endif; ?>
                    <br><a href="<?= url('/admin/membership/members/' . (int) $selectedSub['member_id']) ?>" style="font-size:0.8rem;">View member →</a>
                </td></tr>
                <tr><th>Plan</th><td><?= e((string) ($selectedSub['plan_name'] ?? '—')) ?></td></tr>
                <tr><th>Period</th><td><?= e((string) ($selectedSub['period_start'] ?? '')) ?> – <?= e((string) ($selectedSub['period_end'] ?? '')) ?></td></tr>
                <tr><th>Amount</th><td><?= e((string) ($selectedSub['currency'] ?? 'EUR')) ?> <?= number_format((float) ($selectedSub['amount'] ?? 0), 2) ?></td></tr>
                <tr><th>Method</th><td><?= e((string) ($selectedSub['payment_method'] ?? '—')) ?></td></tr>
                <tr><th>Tx ID</th><td><?= e((string) ($selectedSub['transaction_id'] ?? '—')) ?></td></tr>
                <tr><th>Verified</th><td><?= $verBadge((string) ($selectedSub['verification_status'] ?? 'unverified')) ?></td></tr>
                <?php if (!empty($selectedSub['verified_at'])): ?>
                <tr><th>Verified at</th><td><?= e((string) $selectedSub['verified_at']) ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($selectedSub['payment_id'])): ?>
            <div style="border:1px solid #d1fae5;border-radius:6px;padding:0.65rem;margin-bottom:0.9rem;background:#f0fdf4;">
                <strong style="font-size:0.82rem;color:#16a34a;">Matched payment</strong>
                <table class="pl-meta" style="margin-top:0.4rem;">
                    <tr><th>#</th><td><?= (int) $selectedSub['payment_id'] ?></td></tr>
                    <tr><th>Tx</th><td><?= e((string) ($selectedSub['payment_transaction_id'] ?? '—')) ?></td></tr>
                    <tr><th>Amount</th><td><?= e((string) ($selectedSub['currency'] ?? 'EUR')) ?> <?= number_format((float) ($selectedSub['payment_amount'] ?? 0), 2) ?></td></tr>
                    <tr><th>Paid</th><td><?= e((string) ($selectedSub['payment_paid_at'] ?? '—')) ?></td></tr>
                    <tr><th>Gateway</th><td><?= e((string) ($selectedSub['payment_gateway'] ?? '—')) ?></td></tr>
                </table>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $selectedSub['id'] . '/link-payment') ?>" style="margin-top:0.5rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_id" value="0">
                    <button class="btn btn-outline btn-small" type="submit" data-confirm="Unlink this payment from the subscription?">Unlink payment</button>
                </form>
            </div>
            <?php else: ?>
            <div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.65rem;margin-bottom:0.9rem;background:#f9fafb;">
                <strong style="font-size:0.82rem;color:#6b7280;">No payment matched</strong>
                <?php if (!empty($unmatchedPayments)): ?>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $selectedSub['id'] . '/link-payment') ?>" style="margin-top:0.6rem;display:grid;gap:0.4rem;">
                    <?= csrf_field() ?>
                    <label class="form-label" style="font-size:0.8rem;">Link a payment</label>
                    <select class="form-input" name="payment_id" style="font-size:0.8rem;">
                        <option value="">— Select payment —</option>
                        <?php foreach ($unmatchedPayments as $pay): ?>
                        <option value="<?= (int) $pay['id'] ?>"><?= e((string) ($pay['transaction_id'] ?? '#' . $pay['id'])) ?> — <?= e((string) ($pay['currency'] ?? 'EUR')) ?> <?= number_format((float) ($pay['amount'] ?? 0), 2) ?> (<?= e((string) ($pay['paid_at'] ?? '')) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-small" type="submit">Link payment</button>
                </form>
                <?php else: ?>
                <p style="font-size:0.8rem;color:#6b7280;margin-top:0.4rem;">No unmatched payments available.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;">
                <strong style="font-size:0.82rem;display:block;margin-bottom:0.5rem;">Set verification status</strong>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $selectedSub['id'] . '/verify') ?>" style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
                    <?= csrf_field() ?>
                    <select class="form-input" name="verification_status" style="font-size:0.82rem;width:auto;">
                        <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>"<?= $st === (string) ($selectedSub['verification_status'] ?? 'unverified') ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-small" type="submit">Save</button>
                </form>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
