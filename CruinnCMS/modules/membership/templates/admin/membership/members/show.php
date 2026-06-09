<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$member        = $member ?? [];
$subscriptions = $subscriptions ?? [];
$payments      = $payments ?? [];
$plans         = $plans ?? [];
$linkedUser    = $linkedUser ?? null;
$address       = $address ?? null;

$memberName = trim((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? ''));
$memberId   = (int) ($member['id'] ?? 0);

$verBadge = static function (string $status): string {
    $map = ['verified' => '#16a34a', 'unverified' => '#d97706', 'disputed' => '#dc2626', 'waived' => '#6b7280'];
    $c = $map[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:0.75rem;font-weight:600;background:' . $c . '22;color:' . $c . ';">' . e($status) . '</span>';
};

$statusBadge = static function (string $status): string {
    $map = ['active' => '#16a34a', 'applicant' => '#2563eb', 'lapsed' => '#d97706', 'suspended' => '#dc2626', 'resigned' => '#6b7280', 'archived' => '#9ca3af'];
    $c = $map[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:2px 9px;border-radius:10px;font-size:0.8rem;font-weight:600;background:' . $c . '22;color:' . $c . ';">' . e(ucfirst($status)) . '</span>';
};
?>

<div class="panel-layout" id="member-profile-layout">

    <!-- LEFT: Member details -->
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Member Profile</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">

            <div style="margin-bottom:0.75rem;">
                <div style="font-size:1.1rem;font-weight:700;margin-bottom:0.2rem;"><?= e($memberName ?: '(unnamed)') ?></div>
                <?php if (!empty($member['membership_number'])): ?>
                <div style="font-size:0.82rem;color:#6b7280;">Mbr # <?= e((string) $member['membership_number']) ?></div>
                <?php endif; ?>
                <div style="margin-top:0.4rem;"><?= $statusBadge((string) ($member['status'] ?? 'applicant')) ?></div>
            </div>

            <table class="pl-meta" style="margin-bottom:0.9rem;">
                <tr><th>Email</th><td><?= e((string) ($member['email'] ?? '—')) ?></td></tr>
                <?php if (!empty($member['phone'])): ?>
                <tr><th>Phone</th><td><?= e((string) $member['phone']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($member['organisation'])): ?>
                <tr><th>Org</th><td><?= e((string) $member['organisation']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($member['joined_at'])): ?>
                <tr><th>Joined</th><td><?= e(substr((string) $member['joined_at'], 0, 10)) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($member['lapsed_at'])): ?>
                <tr><th>Lapsed</th><td><?= e(substr((string) $member['lapsed_at'], 0, 10)) ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($address): ?>
            <div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.6rem;margin-bottom:0.9rem;font-size:0.82rem;">
                <strong style="display:block;margin-bottom:0.3rem;font-size:0.78rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">Address</strong>
                <?php foreach (['line_1','line_2','city','county','postcode','country'] as $af): ?>
                <?php if (!empty($address[$af])): ?><div><?= e((string) $address[$af]) ?></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($member['notes'])): ?>
            <div style="border:1px solid #fde68a;border-radius:6px;padding:0.6rem;background:#fffbeb;font-size:0.82rem;margin-bottom:0.9rem;">
                <strong style="display:block;margin-bottom:0.2rem;font-size:0.78rem;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">Notes</strong>
                <?= e((string) $member['notes']) ?>
            </div>
            <?php endif; ?>

            <div style="display:flex;flex-direction:column;gap:0.4rem;">
                <a class="btn btn-primary btn-small" href="<?= url('/admin/membership/members/' . $memberId . '/edit') ?>">Edit Member</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members?member=' . $memberId) ?>">View in Members List</a>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">← Back to Members</a>
            </div>
        </div>
    </div>

    <!-- CENTRE: Subscriptions -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title">Subscriptions</span>
        </div>
        <div class="pl-main-scroll" style="padding:0.75rem;">

            <?php if (!empty($subscriptions)): ?>
            <table class="pl-table" style="margin-bottom:1.5rem;">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Tx ID</th>
                        <th>Verified</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><?= e((string) ($sub['period_start'] ?? '')) ?> – <?= e((string) ($sub['period_end'] ?? '')) ?></td>
                        <td><?= e((string) ($sub['plan_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($sub['currency'] ?? 'EUR')) ?> <?= number_format((float) ($sub['amount'] ?? 0), 2) ?></td>
                        <td><?= e((string) ($sub['payment_method'] ?? '—')) ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;" title="<?= e((string) ($sub['transaction_id'] ?? '')) ?>"><?= e((string) ($sub['transaction_id'] ?? '—')) ?></td>
                        <td><?= $verBadge((string) ($sub['verification_status'] ?? 'unverified')) ?></td>
                        <td><a href="<?= url('/admin/membership/subscriptions?sub=' . (int) $sub['id']) ?>" style="font-size:0.8rem;color:#2563eb;">View →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="pl-empty" style="margin-bottom:1.5rem;">No subscriptions recorded.</p>
            <?php endif; ?>

            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:0.9rem;background:#f9fafb;">
                <h3 style="margin:0 0 0.75rem;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;color:#374151;">Add Subscription</h3>
                <form method="post" action="<?= url('/admin/membership/members/' . $memberId . '/subscriptions') ?>" style="display:grid;gap:0.45rem;">
                    <?= csrf_field() ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                        <div><label class="form-label" style="font-size:0.78rem;">Period start</label><input class="form-input" type="date" name="period_start" required></div>
                        <div><label class="form-label" style="font-size:0.78rem;">Period end</label><input class="form-input" type="date" name="period_end" required></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 90px;gap:0.45rem;">
                        <div><label class="form-label" style="font-size:0.78rem;">Amount</label><input class="form-input" type="number" step="0.01" min="0" name="amount" placeholder="0.00" required></div>
                        <div><label class="form-label" style="font-size:0.78rem;">Currency</label><input class="form-input" type="text" name="currency" value="EUR" maxlength="3"></div>
                    </div>
                    <div><label class="form-label" style="font-size:0.78rem;">Plan</label>
                        <select class="form-input" name="plan_id">
                            <option value="">No plan</option>
                            <?php foreach ($plans as $plan): ?>
                            <?php
                                $pLabel = $plan['name'];
                                if (!empty($plan['parent_plan_id'])) {
                                    foreach ($plans as $gp) {
                                        if ((int) $gp['id'] === (int) $plan['parent_plan_id']) { $pLabel = $gp['name'] . ' → ' . $plan['name']; break; }
                                    }
                                }
                            ?>
                            <option value="<?= (int) $plan['id'] ?>"><?= e($pLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                        <div><label class="form-label" style="font-size:0.78rem;">Member type</label>
                            <select class="form-input" name="member_type"><option value="new">New member</option><option value="renewal">Renewal</option></select>
                        </div>
                        <div><label class="form-label" style="font-size:0.78rem;">Payment method</label>
                            <select class="form-input" name="payment_method">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                                <option value="waived">Waived</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                        <div><label class="form-label" style="font-size:0.78rem;">Tx ID</label><input class="form-input" type="text" name="transaction_id" placeholder="Optional"></div>
                        <div><label class="form-label" style="font-size:0.78rem;">Verification</label>
                            <select class="form-input" name="verification_status">
                                <?php foreach (['unverified','verified','disputed','waived'] as $vs): ?>
                                <option value="<?= e($vs) ?>"><?= e(ucfirst($vs)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div><label class="form-label" style="font-size:0.78rem;">Notes</label><input class="form-input" type="text" name="notes" placeholder="Optional"></div>
                    <button class="btn btn-primary btn-small" type="submit">Add Subscription</button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Linked user + payments -->
    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Payments &amp; Account</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">

            <div style="margin-bottom:0.9rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.4rem;">Linked User Account</strong>
                <?php if ($linkedUser): ?>
                <div style="font-size:0.85rem;margin-bottom:0.4rem;"><?= e((string) ($linkedUser['display_name'] ?? '')) ?><br><span style="color:#6b7280;font-size:0.8rem;"><?= e((string) ($linkedUser['email'] ?? '')) ?></span></div>
                <a class="btn btn-outline btn-small" href="<?= url('/admin/users/' . (int) $linkedUser['id']) ?>">View user account →</a>
                <?php else: ?>
                <p style="font-size:0.82rem;color:#6b7280;margin:0 0 0.4rem;">No user account linked.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($subscriptions)): ?>
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;margin-bottom:0.9rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.5rem;">Record Payment</strong>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $subscriptions[0]['id'] . '/payments') ?>" style="display:grid;gap:0.4rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= $memberId ?>">
                    <label class="form-label" style="font-size:0.78rem;">Subscription</label>
                    <select class="form-input" style="font-size:0.82rem;" name="subscription_id"
                        onchange="this.form.action='<?= url('/admin/membership/subscriptions') ?>/' + this.value + '/payments';">
                        <?php foreach ($subscriptions as $sub): ?>
                        <option value="<?= (int) $sub['id'] ?>">#<?= (int) $sub['id'] ?> <?= e((string) ($sub['period_start'] ?? '')) ?> (<?= e((string) ($sub['verification_status'] ?? '')) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:grid;grid-template-columns:1fr 70px;gap:0.4rem;">
                        <input class="form-input" style="font-size:0.82rem;" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                        <input class="form-input" style="font-size:0.82rem;" type="text" name="currency" value="EUR" maxlength="3">
                    </div>
                    <input class="form-input" style="font-size:0.82rem;" type="text" name="transaction_id" placeholder="Transaction ID">
                    <input class="form-input" style="font-size:0.82rem;" type="text" name="gateway" placeholder="Gateway">
                    <input class="form-input" style="font-size:0.82rem;" type="datetime-local" name="paid_at" value="<?= e(date('Y-m-d\\TH:i')) ?>">
                    <button class="btn btn-primary btn-small" type="submit">Record Payment</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($payments)): ?>
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.4rem;">Payment History</strong>
                <?php foreach ($payments as $p): ?>
                <div style="font-size:0.8rem;padding:0.35rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:0.4rem;">
                    <span><?= e((string) ($p['currency'] ?? 'EUR')) ?> <?= number_format((float) ($p['amount'] ?? 0), 2) ?><?= !empty($p['gateway']) ? ' · ' . e((string) $p['gateway']) : '' ?></span>
                    <span style="color:#6b7280;"><?= e(substr((string) ($p['paid_at'] ?? ''), 0, 10)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="font-size:0.82rem;color:#6b7280;margin:0;">No payments recorded.</p>
            <?php endif; ?>

        </div>
    </div>

</div>
