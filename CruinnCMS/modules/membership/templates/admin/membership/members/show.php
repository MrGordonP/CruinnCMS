<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<?php $memberName = trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')); ?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;"><?= e($memberName) ?></h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">Member #<?= (int) $member['id'] ?><?= !empty($member['membership_number']) ? ' • ' . e($member['membership_number']) : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a class="btn btn-outline" href="<?= url('/admin/membership/members/' . (int) $member['id'] . '/edit') ?>">Edit Member</a>
        <a class="btn btn-outline" href="<?= url('/admin/membership') ?>">Back</a>
    </div>
</div>

<div class="card" style="margin-top:1rem;padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
    <div><strong>Email:</strong> <?= e($member['email']) ?></div>
    <div><strong>Phone:</strong> <?= e($member['phone'] ?: '—') ?></div>
    <div><strong>Organisation:</strong> <?= e($member['organisation'] ?: '—') ?></div>
    <div><strong>Status:</strong> <span style="text-transform:capitalize;"><?= e($member['status']) ?></span></div>
    <div><strong>Plan:</strong> <?= e($member['plan_name'] ?: '—') ?></div>
    <div><strong>Linked User:</strong> <?= !empty($member['user_id']) ? (int) $member['user_id'] : '—' ?></div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-top:1rem;">
    <section class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
        <h2 style="margin-top:0;">Subscriptions</h2>
        <?php if (empty($subscriptions)): ?>
        <p>No subscriptions recorded.</p>
        <?php else: ?>
        <table class="table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:0.5rem;border-bottom:1px solid #e5e7eb;">Period</th>
                    <th style="text-align:left;padding:0.5rem;border-bottom:1px solid #e5e7eb;">Plan</th>
                    <th style="text-align:left;padding:0.5rem;border-bottom:1px solid #e5e7eb;">Amount</th>
                    <th style="text-align:left;padding:0.5rem;border-bottom:1px solid #e5e7eb;">Status</th>
                    <th style="text-align:left;padding:0.5rem;border-bottom:1px solid #e5e7eb;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"><?= e($sub['period_label']) ?></td>
                    <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"><?= e($sub['plan_name'] ?: '—') ?></td>
                    <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"><?= e($sub['currency']) ?> <?= number_format((float) $sub['amount'], 2) ?></td>
                    <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"><?= e($sub['status']) ?></td>
                    <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;">
                        <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) $sub['id'] . '/status') ?>" style="display:flex;gap:0.3rem;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
                            <select name="status" class="form-input" style="max-width:10rem;">
                                <?php foreach (['pending','paid','overdue','waived','refunded','cancelled'] as $status): ?>
                                <option value="<?= e($status) ?>"<?= $sub['status'] === $status ? ' selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline btn-small" type="submit">Set</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h3 style="margin-top:1.25rem;">Add Subscription</h3>
        <form method="post" action="<?= url('/admin/membership/members/' . (int) $member['id'] . '/subscriptions') ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.5rem;">
            <?= csrf_field() ?>
            <input class="form-input" type="text" name="period_label" placeholder="Period label (e.g. 2026)" required>
            <input class="form-input" type="date" name="period_start" required>
            <input class="form-input" type="date" name="period_end" required>
            <input class="form-input" type="number" step="0.01" min="0" name="amount" placeholder="Amount" required>
            <input class="form-input" type="text" name="currency" value="EUR" maxlength="3" required>
            <select class="form-input" name="plan_id">
                <option value="">No plan</option>
                <?php foreach ($plans as $plan): ?>
                <option value="<?= (int) $plan['id'] ?>"<?= (string) $member['plan_id'] === (string) $plan['id'] ? ' selected' : '' ?>><?= e($plan['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-input" name="status">
                <?php foreach (['pending','paid','overdue','waived','refunded','cancelled'] as $status): ?>
                <option value="<?= e($status) ?>"><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-input" type="date" name="due_date" placeholder="Due date">
            <input class="form-input" type="datetime-local" name="paid_at" placeholder="Paid at">
            <input class="form-input" type="text" name="payment_reference" placeholder="Reference">
            <input class="form-input" type="text" name="notes" placeholder="Notes">
            <button class="btn btn-primary" type="submit">Add Subscription</button>
        </form>
    </section>

    <section class="card" style="padding:1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
        <h2 style="margin-top:0;">Record Payment</h2>
        <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int) ($subscriptions[0]['id'] ?? 0) . '/payments') ?>" style="display:grid;gap:0.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
            <label class="form-label" for="subscription_id">Subscription</label>
            <select class="form-input" id="subscription_id" name="subscription_id" onchange="this.form.action='<?= url('/admin/membership/subscriptions') ?>/' + this.value + '/payments';">
                <?php foreach ($subscriptions as $sub): ?>
                <option value="<?= (int) $sub['id'] ?>">#<?= (int) $sub['id'] ?> — <?= e($sub['period_label']) ?> (<?= e($sub['status']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input class="form-input" type="number" step="0.01" min="0" name="amount" placeholder="Amount" required>
            <input class="form-input" type="text" name="currency" value="EUR" maxlength="3" required>
            <input class="form-input" type="text" name="method" placeholder="Method (card, transfer, cash)">
            <input class="form-input" type="text" name="reference" placeholder="Reference">
            <select class="form-input" name="status">
                <?php foreach (['completed','pending','failed','refunded'] as $status): ?>
                <option value="<?= e($status) ?>"><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-input" type="datetime-local" name="paid_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
            <textarea class="form-input" name="notes" rows="3" placeholder="Notes"></textarea>
            <button class="btn btn-primary" type="submit"<?= empty($subscriptions) ? ' disabled' : '' ?>>Record Payment</button>
        </form>

        <h3 style="margin-top:1.25rem;">Recent Payments</h3>
        <?php if (empty($payments)): ?>
        <p>No payments recorded.</p>
        <?php else: ?>
        <ul style="padding-left:1rem;display:grid;gap:0.35rem;">
            <?php foreach ($payments as $p): ?>
            <li>
                <strong><?= e($p['currency']) ?> <?= number_format((float) $p['amount'], 2) ?></strong>
                — <?= e($p['status']) ?>
                <div class="text-muted" style="font-size:0.85rem;"><?= e($p['paid_at']) ?><?= !empty($p['reference']) ? ' • ' . e($p['reference']) : '' ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
