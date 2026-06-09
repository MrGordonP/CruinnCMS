<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$payments          = $payments ?? [];
$selectedId        = (int) ($selectedId ?? 0);
$selectedPayment   = $selectedPayment ?? null;
$availableYears    = $availableYears ?? [];
$availableGateways = $availableGateways ?? [];
$allowedStatuses   = $allowedStatuses ?? ['pending', 'completed', 'failed', 'refunded'];
$filters           = $filters ?? [];

$gatewayFilter = (string) ($filters['gatewayFilter'] ?? '');
$statusFilter  = (string) ($filters['statusFilter'] ?? '');
$yearFilter    = (string) ($filters['yearFilter'] ?? '');
$memberSearch  = (string) ($filters['memberSearch'] ?? '');
$sort          = (string) ($filters['sort'] ?? 'paid_at');
$dir           = (string) ($filters['dir'] ?? 'DESC');

$baseParams = array_filter([
    'gateway' => $gatewayFilter,
    'status'  => $statusFilter,
    'year'    => $yearFilter,
    'q'       => $memberSearch,
], static fn($v): bool => $v !== null && $v !== '' && $v !== 0);

$sortLink = static function (string $key, string $label) use ($sort, $dir, $baseParams): string {
    $newDir = ($sort === $key && $dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow  = $sort === $key ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    $qs     = http_build_query(array_merge($baseParams, ['sort' => $key, 'dir' => $newDir]));
    return '<a href="' . url('/admin/payments?' . $qs) . '" style="color:inherit;text-decoration:none;">' . e($label) . $arrow . '</a>';
};

$statusBadge = static function (string $status): string {
    $map = [
        'completed' => '#16a34a',
        'pending'   => '#d97706',
        'failed'    => '#dc2626',
        'refunded'  => '#9333ea',
    ];
    $colour = $map[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:0.75rem;font-weight:600;background:' . $colour . ';color:#fff;">' . e(ucfirst($status)) . '</span>';
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

<div class="panel-layout" id="payments-layout">
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Filters</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
            <form method="get" action="<?= url('/admin/payments') ?>" style="display:grid;gap:0.65rem;">
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Member search</label>
                    <input class="form-input" name="q" type="text" value="<?= e($memberSearch) ?>" placeholder="Name, email, mbr #" style="font-size:0.82rem;">
                </div>
                <?php if (!empty($availableYears)): ?>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Year</label>
                    <select class="form-input" name="year" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All years</option>
                        <?php foreach ($availableYears as $y): ?>
                        <option value="<?= (int) $y ?>"<?= (string) $y === $yearFilter ? ' selected' : '' ?>><?= (int) $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($availableGateways)): ?>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Gateway</label>
                    <select class="form-input" name="gateway" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All gateways</option>
                        <?php foreach ($availableGateways as $gw): ?>
                        <option value="<?= e($gw) ?>"<?= $gw === $gatewayFilter ? ' selected' : '' ?>><?= e($gw) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label" style="font-size:0.8rem;">Status</label>
                    <select class="form-input" name="status" style="font-size:0.82rem;" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>"<?= $st === $statusFilter ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary btn-small" type="submit">Search</button>
                <?php if ($gatewayFilter || $statusFilter || $yearFilter || $memberSearch): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/payments') ?>">Clear filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title">Payments Ledger</span>
            <div class="pl-main-toolbar-actions">
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/subscriptions') ?>">Subscriptions</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">Members</a>
            </div>
        </div>

        <div class="pl-main-scroll">
            <?php if (empty($payments)): ?>
            <p class="pl-empty">No payments match the current filters.</p>
            <?php else: ?>
            <table class="pl-table" style="table-layout:fixed;">
                <colgroup>
                    <col style="width:20%;">
                    <col style="width:11%;">
                    <col style="width:9%;">
                    <col style="width:10%;">
                    <col style="width:14%;">
                    <col style="width:14%;">
                    <col style="width:11%;">
                    <col style="width:11%;">
                </colgroup>
                <thead>
                    <tr>
                        <th><?= $sortLink('tx', 'Transaction ID') ?></th>
                        <th><?= $sortLink('amount', 'Amount') ?></th>
                        <th><?= $sortLink('gateway', 'Gateway') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('paid_at', 'Paid') ?></th>
                        <th><?= $sortLink('member', 'Member') ?></th>
                        <th>Plan</th>
                        <th>Sub verified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <?php
                        $payId   = (int) $pay['id'];
                        $payName = trim((string) ($pay['forenames'] ?? '') . ' ' . (string) ($pay['surnames'] ?? ''));
                        $payUrl  = url('/admin/payments?' . http_build_query(array_merge($baseParams, ['payment' => $payId, 'sort' => $sort, 'dir' => $dir])));
                        $hasSubscription = !empty($pay['subscription_id']);
                    ?>
                    <tr<?= $payId === $selectedId ? ' class="selected"' : '' ?> onclick="window.location='<?= $payUrl ?>'">
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string) ($pay['transaction_id'] ?? '')) ?>"><?= e((string) ($pay['transaction_id'] ?? '—')) ?></td>
                        <td><?= e((string) ($pay['currency'] ?? 'EUR')) ?> <?= number_format((float) ($pay['amount'] ?? 0), 2) ?></td>
                        <td><?= e((string) ($pay['gateway'] ?? '—')) ?></td>
                        <td><?= $statusBadge((string) ($pay['status'] ?? 'pending')) ?></td>
                        <td><?= e((string) ($pay['paid_at'] ?? '—')) ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($payName !== '' ? $payName : ($hasSubscription ? '(sub #' . (int) $pay['subscription_id'] . ')' : '—')) ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string) ($pay['plan_name'] ?? '—')) ?></td>
                        <td><?= $hasSubscription ? $verBadge((string) ($pay['verification_status'] ?? 'unverified')) : '<span style="color:#9ca3af;font-size:0.8rem;">unlinked</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($payments) === 500): ?>
            <p style="padding:0.5rem 1rem;font-size:0.8rem;color:#6b7280;">Showing first 500 results. Use filters to narrow down.</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Payment Detail</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
        <?php if (!$selectedPayment): ?>
            <p class="text-muted" style="font-size:0.85rem;">Select a payment to view details.</p>
        <?php else: ?>
            <?php
                $detailName = trim((string) ($selectedPayment['forenames'] ?? '') . ' ' . (string) ($selectedPayment['surnames'] ?? ''));
                $hasLinkedSub = !empty($selectedPayment['subscription_id']);
            ?>
            <table class="pl-meta" style="margin-bottom:0.9rem;">
                <tr><th>ID</th><td>#<?= (int) $selectedPayment['id'] ?></td></tr>
                <tr><th>Tx ID</th><td><code style="font-size:0.8rem;word-break:break-all;"><?= e((string) ($selectedPayment['transaction_id'] ?? '—')) ?></code></td></tr>
                <tr><th>Amount</th><td><?= e((string) ($selectedPayment['currency'] ?? 'EUR')) ?> <?= number_format((float) ($selectedPayment['amount'] ?? 0), 2) ?></td></tr>
                <tr><th>Gateway</th><td><?= e((string) ($selectedPayment['gateway'] ?? '—')) ?></td></tr>
                <tr><th>Status</th><td><?= $statusBadge((string) ($selectedPayment['status'] ?? 'pending')) ?></td></tr>
                <tr><th>Paid at</th><td><?= e((string) ($selectedPayment['paid_at'] ?? '—')) ?></td></tr>
                <?php if (!empty($selectedPayment['notes'])): ?>
                <tr><th>Notes</th><td><?= e((string) $selectedPayment['notes']) ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($hasLinkedSub): ?>
            <div style="border:1px solid #dbeafe;border-radius:6px;padding:0.65rem;margin-bottom:0.9rem;background:#eff6ff;">
                <strong style="font-size:0.82rem;color:#1d4ed8;">Linked subscription</strong>
                <table class="pl-meta" style="margin-top:0.4rem;">
                    <tr><th>#</th><td><?= (int) $selectedPayment['subscription_id'] ?></td></tr>
                    <tr><th>Member</th><td>
                        <?= e($detailName !== '' ? $detailName : '(unnamed)') ?>
                        <?php if (!empty($selectedPayment['membership_number'])): ?><br><small class="text-muted"><?= e((string) $selectedPayment['membership_number']) ?></small><?php endif; ?>
                    </td></tr>
                    <tr><th>Plan</th><td><?= e((string) ($selectedPayment['plan_name'] ?? '—')) ?></td></tr>
                    <tr><th>Period</th><td><?= e((string) ($selectedPayment['period_start'] ?? '')) ?> – <?= e((string) ($selectedPayment['period_end'] ?? '')) ?></td></tr>
                    <tr><th>Verified</th><td><?= $verBadge((string) ($selectedPayment['verification_status'] ?? 'unverified')) ?></td></tr>
                </table>
                <div style="margin-top:0.6rem;display:flex;gap:0.4rem;flex-wrap:wrap;">
                    <?php if (!empty($selectedPayment['member_id'])): ?>
                    <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members?member=' . (int) $selectedPayment['member_id']) ?>">View member →</a>
                    <?php endif; ?>
                    <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/subscriptions?sub=' . (int) $selectedPayment['subscription_id']) ?>">View subscription →</a>
                </div>
            </div>
            <?php else: ?>
            <div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.65rem;margin-bottom:0.9rem;background:#f9fafb;">
                <strong style="font-size:0.82rem;color:#6b7280;">No subscription linked</strong>
                <p style="font-size:0.8rem;color:#6b7280;margin:0.3rem 0 0;">This payment has not been matched to a subscription. Open the <a href="<?= url('/admin/membership/subscriptions') ?>">Subscriptions workspace</a> to link it.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
