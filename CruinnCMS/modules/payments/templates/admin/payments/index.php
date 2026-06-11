<?php
// Last edit: 2026-06-11 13:39 UTC.

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
$unmatchedTransactions = $unmatchedTransactions ?? [];
$selectedPaymentTransactions = $selectedPaymentTransactions ?? [];
$linkablePayments = $linkablePayments ?? [];
$backUrl = $_SERVER['REQUEST_URI'] ?? '/admin/payments';

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

            <div style="border-top:1px solid #e5e7eb;padding-top:0.7rem;">
                <strong style="font-size:0.82rem;color:#111827;">Linked Raw Transactions</strong>
                <?php if (empty($selectedPaymentTransactions)): ?>
                <p style="font-size:0.8rem;color:#6b7280;margin:0.35rem 0 0;">No raw transaction records currently linked to this payment.</p>
                <?php else: ?>
                <div style="margin-top:0.45rem;display:grid;gap:0.45rem;max-height:240px;overflow:auto;padding-right:0.25rem;">
                    <?php foreach ($selectedPaymentTransactions as $tx): ?>
                    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.45rem;background:#fff;">
                        <div style="display:flex;justify-content:space-between;gap:0.4rem;align-items:center;">
                            <strong style="font-size:0.78rem;">#<?= (int) $tx['id'] ?> · <?= e((string) ($tx['source'] ?? 'manual')) ?></strong>
                            <span style="font-size:0.76rem;color:#374151;"><?= e((string) ($tx['currency'] ?? 'EUR')) ?> <?= number_format((float) ($tx['amount'] ?? 0), 2) ?></span>
                        </div>
                        <div style="font-size:0.76rem;color:#6b7280;margin-top:0.2rem;word-break:break-word;">
                            <?= e((string) ($tx['reference'] ?? $tx['external_transaction_id'] ?? 'No reference')) ?>
                        </div>
                        <form method="post" action="<?= url('/admin/payments/transactions/' . (int) $tx['id'] . '/unlink') ?>" style="margin-top:0.35rem;">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <input type="hidden" name="back" value="<?= e((string) $backUrl) ?>">
                            <button class="btn btn-outline btn-small" type="submit">Unlink</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="border-top:1px solid #e5e7eb;padding-top:0.7rem;margin-top:0.75rem;">
            <strong style="font-size:0.82rem;color:#111827;">Unmatched Transactions</strong>
            <?php if (empty($unmatchedTransactions)): ?>
            <p style="font-size:0.8rem;color:#6b7280;margin:0.35rem 0 0;">No unmatched raw transactions.</p>
            <?php else: ?>
            <div style="margin-top:0.45rem;display:grid;gap:0.55rem;max-height:360px;overflow:auto;padding-right:0.25rem;">
                <?php foreach ($unmatchedTransactions as $tx): ?>
                <div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.5rem;background:#fff;">
                    <div style="display:flex;justify-content:space-between;gap:0.4rem;align-items:center;">
                        <strong style="font-size:0.78rem;">#<?= (int) $tx['id'] ?> · <?= e((string) ($tx['source'] ?? 'manual')) ?></strong>
                        <span style="font-size:0.76rem;color:#374151;"><?= e((string) ($tx['currency'] ?? 'EUR')) ?> <?= number_format((float) ($tx['amount'] ?? 0), 2) ?></span>
                    </div>
                    <div style="font-size:0.76rem;color:#6b7280;margin-top:0.2rem;word-break:break-word;">
                        <?= e((string) ($tx['reference'] ?? $tx['external_transaction_id'] ?? 'No reference')) ?><br>
                        <span><?= e((string) ($tx['transacted_at'] ?? '')) ?></span>
                    </div>

                    <form method="post" action="<?= url('/admin/payments/transactions/' . (int) $tx['id'] . '/link') ?>" style="margin-top:0.4rem;display:grid;gap:0.35rem;">
                        <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                        <input type="hidden" name="back" value="<?= e((string) $backUrl) ?>">
                        <select class="form-input" name="payment_id" style="font-size:0.78rem;">
                            <option value="">Link to payment…</option>
                            <?php foreach ($linkablePayments as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>">#<?= (int) $opt['id'] ?> · <?= e((string) ($opt['transaction_id'] ?? '—')) ?> · <?= e((string) ($opt['currency'] ?? 'EUR')) ?> <?= number_format((float) ($opt['amount'] ?? 0), 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="form-input" type="text" name="notes" placeholder="Optional reconciliation note" style="font-size:0.78rem;">
                        <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                            <button class="btn btn-primary btn-small" type="submit">Link</button>
                        </div>
                    </form>

                    <form method="post" action="<?= url('/admin/payments/transactions/' . (int) $tx['id'] . '/ignore') ?>" style="margin-top:0.35rem;">
                        <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                        <input type="hidden" name="back" value="<?= e((string) $backUrl) ?>">
                        <input class="form-input" type="text" name="notes" placeholder="Reason for ignore (optional)" style="font-size:0.78rem; margin-bottom:0.25rem;">
                        <button class="btn btn-outline btn-small" type="submit">Ignore</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>
