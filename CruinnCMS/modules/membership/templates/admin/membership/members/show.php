<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireJs('membership.js');
$GLOBALS['admin_flush_layout'] = true;

$member        = $member ?? [];
$subscriptions = $subscriptions ?? [];
$payments      = $payments ?? [];
$plans         = $plans ?? [];
$memberAdmin   = $memberAdmin ?? null;
$linkedUser    = $linkedUser ?? null;
$address       = $address ?? null;
$errors        = $errors ?? [];

$memberName = trim((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? ''));
$memberId   = (int) ($member['id'] ?? 0);

$verBadge = static function (string $status): string {
    $map = ['verified' => '#16a34a', 'unverified' => '#d97706', 'disputed' => '#dc2626', 'waived' => '#6b7280'];
    $c = $map[$status] ?? '#6b7280';
    return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:0.75rem;font-weight:600;background:' . $c . '22;color:' . $c . ';">' . e(ucfirst($status)) . '</span>';
};

$allowedStatuses = ['applicant', 'active', 'lapsed', 'suspended', 'resigned', 'archived'];
?>

<div class="panel-layout" id="member-hub-layout">

    <!-- LEFT: Editable member details -->
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header" style="justify-content:space-between;">
            <span class="pl-panel-title"><?= e($memberName ?: '(unnamed)') ?></span>
            <?php if (!empty($member['membership_number'])): ?>
            <span style="font-size:0.78rem;color:#6b7280;">Mbr # <?= e((string) $member['membership_number']) ?></span>
            <?php endif; ?>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">

            <?php if (!empty($errors)): ?>
            <div style="border:1px solid #fca5a5;border-radius:6px;padding:0.6rem;background:#fef2f2;margin-bottom:0.75rem;font-size:0.82rem;color:#dc2626;">
                <?php foreach ($errors as $e_msg): ?><div><?= e((string) $e_msg) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= url('/admin/membership/members/' . $memberId) ?>" style="display:grid;gap:0.6rem;">
                <?= csrf_field() ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Forenames</label>
                        <input class="form-input" type="text" name="forenames" value="<?= e((string) ($member['forenames'] ?? '')) ?>" required style="font-size:0.82rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Surnames</label>
                        <input class="form-input" type="text" name="surnames" value="<?= e((string) ($member['surnames'] ?? '')) ?>" required style="font-size:0.82rem;">
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size:0.78rem;">Email</label>
                    <input class="form-input" type="email" name="email" value="<?= e((string) ($member['email'] ?? '')) ?>" required style="font-size:0.82rem;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Membership #</label>
                        <input class="form-input" type="text" name="membership_number" value="<?= e((string) ($member['membership_number'] ?? '')) ?>" style="font-size:0.82rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Status</label>
                        <select class="form-input" name="status" style="font-size:0.82rem;">
                            <?php foreach ($allowedStatuses as $st): ?>
                            <option value="<?= e($st) ?>"<?= ((string) ($member['status'] ?? 'applicant')) === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size:0.78rem;">Organisation</label>
                    <input class="form-input" type="text" name="organisation" value="<?= e((string) ($member['organisation'] ?? '')) ?>" style="font-size:0.82rem;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.45rem;">
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Joined</label>
                        <input class="form-input" type="date" name="joined_at" value="<?= e(substr((string) ($member['joined_at'] ?? ''), 0, 10)) ?>" style="font-size:0.82rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">Lapsed</label>
                        <input class="form-input" type="date" name="lapsed_at" value="<?= e(substr((string) ($member['lapsed_at'] ?? ''), 0, 10)) ?>" style="font-size:0.82rem;">
                    </div>
                </div>

                <div style="border-top:1px solid #e5e7eb;padding-top:0.6rem;margin-top:0.1rem;">
                    <div style="font-size:0.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.45rem;">Address</div>
                    <div style="display:grid;gap:0.35rem;">
                        <input class="form-input" type="text" name="addr_line_1" value="<?= e((string) ($address['line_1'] ?? '')) ?>" placeholder="Line 1" style="font-size:0.82rem;">
                        <input class="form-input" type="text" name="addr_line_2" value="<?= e((string) ($address['line_2'] ?? '')) ?>" placeholder="Line 2" style="font-size:0.82rem;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.35rem;">
                            <input class="form-input" type="text" name="addr_city" value="<?= e((string) ($address['city'] ?? '')) ?>" placeholder="City" style="font-size:0.82rem;">
                            <input class="form-input" type="text" name="addr_county" value="<?= e((string) ($address['county'] ?? '')) ?>" placeholder="County" style="font-size:0.82rem;">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.35rem;">
                            <input class="form-input" type="text" name="addr_postcode" value="<?= e((string) ($address['postcode'] ?? '')) ?>" placeholder="Postcode" style="font-size:0.82rem;">
                            <input class="form-input" type="text" name="addr_country" value="<?= e((string) ($address['country'] ?? '')) ?>" placeholder="Country" style="font-size:0.82rem;">
                        </div>
                        <input class="form-input" type="tel" name="phone" value="<?= e((string) ($address['phone'] ?? ($member['phone'] ?? ''))) ?>" placeholder="Phone" style="font-size:0.82rem;">
                    </div>
                </div>

                <div style="display:flex;gap:0.4rem;margin-top:0.1rem;">
                    <button class="btn btn-primary btn-small" type="submit">Save</button>
                    <a class="btn btn-outline btn-small" href="<?= url('/admin/membership/members') ?>">&#8592; Members</a>
                </div>
            </form>

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
                        <td><?= e((string) ($sub['period_start'] ?? '')) ?> &ndash; <?= e((string) ($sub['period_end'] ?? '')) ?></td>
                        <td><?= e((string) ($sub['plan_name'] ?? '')) ?></td>
                        <td><?= e((string) ($sub['currency'] ?? 'EUR')) ?> <?= number_format((float) ($sub['amount'] ?? 0), 2) ?></td>
                        <td><?= e((string) ($sub['payment_method'] ?? '')) ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px;" title="<?= e((string) ($sub['transaction_id'] ?? '')) ?>"><?= e((string) ($sub['transaction_id'] ?? '')) ?></td>
                        <td><?= $verBadge((string) ($sub['verification_status'] ?? 'unverified')) ?></td>
                        <td><a href="<?= url('/admin/membership/subscriptions?sub=' . (int) $sub['id']) ?>" style="font-size:0.8rem;color:#2563eb;">View &#8594;</a></td>
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
                                        if ((int) $gp['id'] === (int) $plan['parent_plan_id']) { $pLabel = $gp['name'] . ' -> ' . $plan['name']; break; }
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

    <!-- RIGHT: Admin notes + linked user + payments -->
    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Admin</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">

            <!-- Admin notes -->
            <div style="margin-bottom:0.9rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.4rem;">Admin Notes</strong>
                <form method="post" action="<?= url('/admin/membership/members/' . $memberId) ?>" style="display:grid;gap:0.4rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="forenames" value="<?= e((string) ($member['forenames'] ?? '')) ?>">
                    <input type="hidden" name="surnames" value="<?= e((string) ($member['surnames'] ?? '')) ?>">
                    <input type="hidden" name="email" value="<?= e((string) ($member['email'] ?? '')) ?>">
                    <input type="hidden" name="membership_number" value="<?= e((string) ($member['membership_number'] ?? '')) ?>">
                    <input type="hidden" name="status" value="<?= e((string) ($member['status'] ?? 'applicant')) ?>">
                    <input type="hidden" name="organisation" value="<?= e((string) ($member['organisation'] ?? '')) ?>">
                    <input type="hidden" name="joined_at" value="<?= e(substr((string) ($member['joined_at'] ?? ''), 0, 10)) ?>">
                    <input type="hidden" name="lapsed_at" value="<?= e(substr((string) ($member['lapsed_at'] ?? ''), 0, 10)) ?>">
                    <input type="hidden" name="phone" value="<?= e((string) ($address['phone'] ?? ($member['phone'] ?? ''))) ?>">
                    <input type="hidden" name="addr_line_1" value="<?= e((string) ($address['line_1'] ?? '')) ?>">
                    <input type="hidden" name="addr_line_2" value="<?= e((string) ($address['line_2'] ?? '')) ?>">
                    <input type="hidden" name="addr_city" value="<?= e((string) ($address['city'] ?? '')) ?>">
                    <input type="hidden" name="addr_county" value="<?= e((string) ($address['county'] ?? '')) ?>">
                    <input type="hidden" name="addr_postcode" value="<?= e((string) ($address['postcode'] ?? '')) ?>">
                    <input type="hidden" name="addr_country" value="<?= e((string) ($address['country'] ?? '')) ?>">
                    <textarea class="form-input" name="admin_notes" rows="5" style="font-size:0.82rem;resize:vertical;"><?= e((string) ($memberAdmin['notes'] ?? '')) ?></textarea>
                    <button class="btn btn-primary btn-small" type="submit">Save notes</button>
                </form>
            </div>

            <!-- Linked user account -->
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;margin-bottom:0.9rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.4rem;">Linked User Account</strong>
                <?php if ($linkedUser): ?>
                <div style="font-size:0.85rem;margin-bottom:0.4rem;"><?= e((string) ($linkedUser['display_name'] ?? '')) ?><br><span style="color:#6b7280;font-size:0.8rem;"><?= e((string) ($linkedUser['email'] ?? '')) ?></span></div>
                <div style="display:flex;flex-direction:column;gap:0.4rem;">
                    <a class="btn btn-outline btn-small" href="<?= url('/admin/users/' . (int) $linkedUser['id']) ?>">View user account &#8594;</a>
                    <form method="post" action="<?= url('/admin/membership/members/' . $memberId . '/unlink-user') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-small" type="submit" data-confirm="Unlink this user account from the member?">Unlink user account</button>
                    </form>
                </div>
                <?php else: ?>
                <p style="font-size:0.82rem;color:#6b7280;margin:0 0 0.5rem;">No user account linked.</p>
                <form method="post" action="<?= url('/admin/membership/members/' . $memberId . '/link-user') ?>" style="display:grid;gap:0.4rem;">
                    <?= csrf_field() ?>
                    <input class="form-input" style="font-size:0.82rem;" type="text" name="user_search" placeholder="Email or display name" required>
                    <button class="btn btn-primary btn-small" type="submit">Link user account</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Record payment -->
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
                    <input class="form-input" style="font-size:0.82rem;" type="datetime-local" name="paid_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
                    <button class="btn btn-primary btn-small" type="submit">Record Payment</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Payment history -->
            <?php if (!empty($payments)): ?>
            <div style="border-top:1px solid #e5e7eb;padding-top:0.75rem;">
                <strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7280;display:block;margin-bottom:0.4rem;">Payment History</strong>
                <?php foreach ($payments as $p): ?>
                <div style="font-size:0.8rem;padding:0.35rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:0.4rem;">
                    <span><?= e((string) ($p['currency'] ?? 'EUR')) ?> <?= number_format((float) ($p['amount'] ?? 0), 2) ?><?= !empty($p['gateway']) ? ' &middot; ' . e((string) $p['gateway']) : '' ?></span>
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
