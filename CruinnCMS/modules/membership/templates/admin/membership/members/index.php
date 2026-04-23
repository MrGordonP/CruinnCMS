<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
$selectedId = $memberId ?? 0;
$statuses   = ['applicant','active','lapsed','suspended','resigned','archived'];
?>

<div class="panel-layout" id="membership-layout">

    <!-- Left: filter + member list -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header" style="flex-direction:column;align-items:stretch;gap:0.5rem">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Members</h3>
                <a href="<?= url('/admin/membership/members/new') ?>" class="btn btn-primary btn-small">+ New</a>
            </div>

            <!-- Status summary chips -->
            <div style="display:flex;flex-wrap:wrap;gap:0.3rem">
                <?php foreach ($statuses as $s): ?>
                <a href="<?= url('/admin/membership?status=' . $s) ?>"
                   style="font-size:0.7rem;padding:1px 6px;border-radius:3px;border:1px solid #ccc;text-decoration:none;
                          background:<?= ($filters['status'] ?? '') === $s ? '#1d9e75' : '#f8f8f8' ?>;
                          color:<?= ($filters['status'] ?? '') === $s ? '#fff' : '#555' ?>">
                    <?= e(ucfirst($s)) ?> <?= (int)($statusCount[$s] ?? 0) ?>
                </a>
                <?php endforeach; ?>
                <?php if ($filters['status'] ?? ''): ?>
                <a href="<?= url('/admin/membership') ?>" style="font-size:0.7rem;padding:1px 6px;border-radius:3px;border:1px solid #ccc;text-decoration:none;background:#f8f8f8;color:#555">All</a>
                <?php endif; ?>
            </div>

            <!-- Search / plan filter -->
            <form method="get" action="<?= url('/admin/membership') ?>" style="display:flex;gap:0.3rem">
                <input class="form-input" type="text" name="q" value="<?= e($filters['q'] ?? '') ?>"
                       placeholder="Search…" style="flex:1;font-size:0.8rem;padding:0.3rem 0.5rem">
                <?php if ($filters['status'] ?? ''): ?>
                <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                <?php endif; ?>
                <button class="btn btn-secondary btn-small" type="submit">Go</button>
            </form>
        </div>

        <div class="pl-sidebar-scroll">
            <?php if (empty($members)): ?>
            <p class="text-muted" style="font-size:0.85rem;padding:0.75rem">No members found.</p>
            <?php else: ?>
            <?php foreach ($members as $m): ?>
            <?php $mName = trim(($m['forenames'] ?? '') . ' ' . ($m['surnames'] ?? '')); ?>
            <a href="<?= url('/admin/membership?member=' . (int)$m['id'] . ($filters['status'] ? '&status=' . urlencode($filters['status']) : '') . ($filters['q'] ? '&q=' . urlencode($filters['q']) : '')) ?>"
               class="pl-sidebar-item<?= $m['id'] == $selectedId ? ' active' : '' ?>">
                <span style="flex:1">
                    <?= e($mName ?: '(unnamed)') ?>
                    <?php if (!empty($m['membership_number'])): ?>
                    <small style="color:var(--color-text-muted);font-size:0.7rem;display:block">#<?= e($m['membership_number']) ?></small>
                    <?php endif; ?>
                </span>
                <span style="font-size:0.7rem;padding:1px 5px;border-radius:3px;background:#e5e7eb;color:#555;text-transform:capitalize"><?= e($m['status']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main: member edit form or placeholder -->
    <div class="pl-main">
        <?php if (!$member): ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--color-text-muted);gap:0.5rem">
            <div style="font-size:2rem">👥</div>
            <p>Select a member, or <a href="<?= url('/admin/membership/members/new') ?>">add a new one</a>.</p>
        </div>
        <?php else: ?>
        <?php
        $mName = trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? ''));
        ?>
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($mName) ?></span>
            <div class="pl-main-toolbar-actions">
                <span style="font-size:0.8rem;padding:2px 8px;border-radius:3px;background:#e5e7eb;text-transform:capitalize"><?= e($member['status'] ?? '') ?></span>
            </div>
        </div>
        <div class="pl-main-body">

            <?php if (!empty($errors)): ?>
            <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/admin/membership/members/' . (int)$member['id']) ?>" class="admin-form">
                <?= csrf_field() ?>

                <div class="detail-card">
                    <h2>Member Details</h2>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                        <div class="form-group">
                            <label class="form-label">Forenames</label>
                            <input class="form-input" type="text" name="forenames" value="<?= e($member['forenames'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Surnames</label>
                            <input class="form-input" type="text" name="surnames" value="<?= e($member['surnames'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input class="form-input" type="email" name="email" value="<?= e($member['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input class="form-input" type="text" name="phone" value="<?= e($member['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Membership Number</label>
                            <input class="form-input" type="text" name="membership_number" value="<?= e($member['membership_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Linked User ID</label>
                            <input class="form-input" type="number" name="user_id" min="1" value="<?= e((string)($member['user_id'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Organisation</label>
                            <input class="form-input" type="text" name="organisation" value="<?= e($member['organisation'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Plan</label>
                            <select class="form-input" name="plan_id">
                                <option value="">No plan</option>
                                <?php foreach ($plans as $plan): ?>
                                <option value="<?= (int)$plan['id'] ?>"<?= (string)($member['plan_id'] ?? '') === (string)$plan['id'] ? ' selected' : '' ?>><?= e($plan['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-input" name="status">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>"<?= ($member['status'] ?? 'applicant') === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Joined At</label>
                            <input class="form-input" type="datetime-local" name="joined_at" value="<?= !empty($member['joined_at']) ? e(date('Y-m-d\TH:i', strtotime($member['joined_at']))) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lapsed At</label>
                            <input class="form-input" type="datetime-local" name="lapsed_at" value="<?= !empty($member['lapsed_at']) ? e(date('Y-m-d\TH:i', strtotime($member['lapsed_at']))) : '' ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:0.75rem">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" name="notes" rows="3"><?= e($member['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Member</button>
                </div>
            </form>

        </div>
        <?php endif; ?>
    </div>

    <!-- Right: subscriptions + payments -->
    <div class="pl-detail">
        <div class="pl-detail-header"><h3>Subscriptions</h3></div>
        <div class="pl-detail-scroll" style="padding:0.75rem">
        <?php if (!$member): ?>
            <p class="text-muted" style="font-size:0.85rem">Select a member to view subscriptions.</p>
        <?php else: ?>

            <!-- Subscription list -->
            <?php if (!empty($subscriptions)): ?>
            <?php foreach ($subscriptions as $sub): ?>
            <div class="detail-card" style="margin-bottom:0.75rem;padding:0.6rem">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem">
                    <div>
                        <strong style="font-size:0.85rem"><?= e($sub['period_label']) ?></strong>
                        <small style="display:block;color:var(--color-text-muted)"><?= e($sub['plan_name'] ?? '—') ?> · <?= e($sub['currency']) ?> <?= number_format((float)$sub['amount'], 2) ?></small>
                    </div>
                    <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int)$sub['id'] . '/status') ?>" style="display:flex;gap:0.3rem;align-items:center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
                        <select name="status" class="form-input" style="font-size:0.75rem;padding:1px 4px">
                            <?php foreach (['pending','paid','overdue','waived','refunded','cancelled'] as $s): ?>
                            <option value="<?= e($s) ?>"<?= $sub['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
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

            <!-- Add subscription -->
            <div class="detail-card" style="padding:0.75rem;margin-bottom:1rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em">Add Subscription</h4>
                <form method="post" action="<?= url('/admin/membership/members/' . (int)$member['id'] . '/subscriptions') ?>" style="display:grid;gap:0.4rem">
                    <?= csrf_field() ?>
                    <input class="form-input" style="font-size:0.82rem" type="text" name="period_label" placeholder="Period label (e.g. 2026)" required>
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
                        <option value="<?= (int)$plan['id'] ?>"<?= (string)($member['plan_id'] ?? '') === (string)$plan['id'] ? ' selected' : '' ?>><?= e($plan['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-input" style="font-size:0.82rem" name="status">
                        <?php foreach (['pending','paid','overdue','waived','refunded','cancelled'] as $s): ?>
                        <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-input" style="font-size:0.82rem" type="date" name="due_date" placeholder="Due date">
                    <input class="form-input" style="font-size:0.82rem" type="text" name="notes" placeholder="Notes">
                    <button class="btn btn-primary btn-small" type="submit">Add Subscription</button>
                </form>
            </div>

            <!-- Record payment -->
            <?php if (!empty($subscriptions)): ?>
            <div class="detail-card" style="padding:0.75rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em">Record Payment</h4>
                <form method="post" action="<?= url('/admin/membership/subscriptions/' . (int)$subscriptions[0]['id'] . '/payments') ?>" style="display:grid;gap:0.4rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
                    <select class="form-input" style="font-size:0.82rem" name="subscription_id"
                            onchange="this.form.action='<?= url('/admin/membership/subscriptions') ?>/' + this.value + '/payments';">
                        <?php foreach ($subscriptions as $sub): ?>
                        <option value="<?= (int)$sub['id'] ?>">#<?= (int)$sub['id'] ?> — <?= e($sub['period_label']) ?> (<?= e($sub['status']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:grid;grid-template-columns:1fr 80px;gap:0.4rem">
                        <input class="form-input" style="font-size:0.82rem" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                        <input class="form-input" style="font-size:0.82rem" type="text" name="currency" value="EUR" maxlength="3" required>
                    </div>
                    <input class="form-input" style="font-size:0.82rem" type="text" name="method" placeholder="Method (e.g. bank transfer)">
                    <input class="form-input" style="font-size:0.82rem" type="text" name="reference" placeholder="Reference">
                    <select class="form-input" style="font-size:0.82rem" name="status">
                        <?php foreach (['completed','pending','failed','refunded'] as $s): ?>
                        <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-input" style="font-size:0.82rem" type="datetime-local" name="paid_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
                    <input class="form-input" style="font-size:0.82rem" type="text" name="notes" placeholder="Notes">
                    <button class="btn btn-primary btn-small" type="submit">Record Payment</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Payments history -->
            <?php if (!empty($payments)): ?>
            <div style="margin-top:1rem">
                <h4 style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem">Payment History</h4>
                <?php foreach ($payments as $p): ?>
                <div style="font-size:0.8rem;padding:0.4rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span><?= e($p['currency']) ?> <?= number_format((float)$p['amount'], 2) ?> — <?= e($p['method'] ?? '—') ?></span>
                    <span style="color:var(--color-text-muted)"><?= e($p['status']) ?> · <?= e(substr((string)($p['paid_at'] ?? ''), 0, 10)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>

    <!-- Plans / Import quick links at top -->
</div><!-- /.panel-layout -->

<style>
/* Membership sidebar header needs more room */
#membership-layout .pl-sidebar-header { padding: 0.75rem; }
</style>
