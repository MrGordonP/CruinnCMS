<?php \Cruinn\Template::requireCss('admin-events.css'); ?>
<div class="admin-event-detail">
    <div class="admin-header-row">
        <h1><?= e($event['title']) ?></h1>
        <div class="header-actions">
            <a href="/admin/events/<?= (int) $event['id'] ?>/edit" class="btn btn-primary">Edit Event</a>
            <a href="/admin/events/<?= (int) $event['id'] ?>/export" class="btn btn-outline">Export CSV</a>
            <?php if ($event['status'] === 'published'): ?>
                <a href="/events/<?= e($event['slug']) ?>" class="btn btn-small" target="_blank">View Public</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Details -->
    <div class="detail-grid">
        <div class="detail-card">
            <h3>Event Details</h3>
            <dl class="detail-list">
                <dt>Type</dt>
                <dd><span class="badge badge-type"><?= e(ucfirst($event['event_type'])) ?></span></dd>

                <dt>Status</dt>
                <dd><span class="badge badge-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></dd>

                <dt>Date</dt>
                <dd>
                    <?= format_date($event['date_start'], 'l, j F Y \a\t H:i') ?>
                    <?php if (!empty($event['date_end'])): ?>
                        <br>to <?= format_date($event['date_end'], 'l, j F Y \a\t H:i') ?>
                    <?php endif; ?>
                </dd>

                <dt>Location</dt>
                <dd><?= e($event['location'] ?? '—') ?></dd>

                <dt>Price</dt>
                <dd><?= $event['price'] > 0 ? '€' . number_format($event['price'], 2) . ' ' . e($event['currency']) : 'Free' ?></dd>

                <dt>Capacity</dt>
                <dd><?= $event['capacity'] > 0 ? (int) $event['capacity'] . ' places' : 'Unlimited' ?></dd>

                <?php if (!empty($event['reg_deadline'])): ?>
                <dt>Registration Deadline</dt>
                <dd><?= format_date($event['reg_deadline'], 'j F Y \a\t H:i') ?></dd>
                <?php endif; ?>

                <dt>Registration</dt>
                <dd>
                    <?php if (!empty($event['registration_open'])): ?>
                        <span class="badge badge-published">Open</span>
                    <?php else: ?>
                        <span class="badge badge-draft">Closed</span>
                    <?php endif; ?>
                </dd>

                <dt>Created by</dt>
                <dd><?= e($event['created_by_name'] ?? 'Unknown') ?> on <?= format_date($event['created_at'], 'j M Y') ?></dd>
            </dl>
        </div>

        <div class="detail-card">
            <h3>Registration Summary</h3>
            <div class="stats-grid stats-grid-small">
                <div class="stat-card">
                    <span class="stat-number"><?= $confirmedCount ?></span>
                    <span class="stat-label">Confirmed</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $cancelledCount ?></span>
                    <span class="stat-label">Cancelled</span>
                </div>
                <?php if ($event['price'] > 0): ?>
                <div class="stat-card">
                    <span class="stat-number"><?= $pendingPayment ?></span>
                    <span class="stat-label">Unpaid</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">&euro;<?= number_format($totalRevenue, 2) ?></span>
                    <span class="stat-label">Revenue</span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($event['capacity'] > 0): ?>
            <div class="capacity-bar-wrap">
                <?php $pct = $event['capacity'] > 0 ? min(100, round(($confirmedCount / $event['capacity']) * 100)) : 0; ?>
                <div class="capacity-bar">
                    <div class="capacity-fill" style="width: <?= $pct ?>%"></div>
                </div>
                <small><?= $confirmedCount ?> / <?= (int) $event['capacity'] ?> places filled (<?= $pct ?>%)</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($event['description'])): ?>
    <div class="detail-card">
        <h3>Description</h3>
        <div class="event-description-body"><?= $event['description'] ?></div>
    </div>
    <?php endif; ?>

    <!-- Registrations Table -->
    <div class="detail-card">
        <h3>Registrations (<?= count($registrations) ?>)</h3>

        <?php if (empty($registrations)): ?>
            <p class="empty-state">No registrations yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Dietary</th>
                    <th>Access</th>
                    <?php if ($event['price'] > 0): ?>
                    <th>Payment</th>
                    <?php endif; ?>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $reg): ?>
                <?php
                    $regName  = $reg['member_id']
                        ? trim(($reg['forenames'] ?? '') . ' ' . ($reg['surnames'] ?? ''))
                        : ($reg['guest_name'] ?? '—');
                    $regEmail = $reg['member_id']
                        ? ($reg['member_email'] ?? '')
                        : ($reg['guest_email'] ?? '');
                    $regType  = $reg['member_id'] ? 'Member' : 'Guest';
                ?>
                <tr class="<?= $reg['status'] === 'cancelled' ? 'row-cancelled' : '' ?>">
                    <td><?= (int) $reg['id'] ?></td>
                    <td>
                        <?= e($regName) ?>
                        <?php if ($reg['member_id']): ?>
                            <a href="/admin/members/<?= (int) $reg['member_id'] ?>" class="btn-link" title="View member">&nearr;</a>
                        <?php endif; ?>
                    </td>
                    <td><?= e($regEmail) ?></td>
                    <td><span class="badge badge-<?= strtolower($regType) ?>"><?= $regType ?></span></td>
                    <td><?= e($reg['dietary_notes'] ?? '—') ?></td>
                    <td><?= e($reg['access_notes'] ?? '—') ?></td>
                    <?php if ($event['price'] > 0): ?>
                    <td>
                        <span class="badge badge-<?= e($reg['payment_status']) ?>"><?= e(ucfirst($reg['payment_status'])) ?></span>
                        <?php if ($reg['amount_paid'] > 0): ?>
                            <br><small>&euro;<?= number_format((float) $reg['amount_paid'], 2) ?></small>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><span class="badge badge-<?= e($reg['status']) ?>"><?= e(ucfirst($reg['status'])) ?></span></td>
                    <td><time datetime="<?= e($reg['registered_at']) ?>"><?= format_date($reg['registered_at'], 'j M H:i') ?></time></td>
                    <td class="actions-cell">
                        <?php if ($reg['status'] === 'confirmed'): ?>
                            <?php if ($event['price'] > 0 && $reg['payment_status'] === 'pending'): ?>
                            <form method="post" action="/admin/events/<?= (int) $event['id'] ?>/registrations/<?= (int) $reg['id'] ?>/payment" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-success" onclick="return confirm('Mark as paid?')">Mark Paid</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="/admin/events/<?= (int) $event['id'] ?>/registrations/<?= (int) $reg['id'] ?>/cancel" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Cancel this registration?')">Cancel</button>
                            </form>
                        <?php else: ?>
                            <small class="text-muted"><?= $reg['cancelled_at'] ? format_date($reg['cancelled_at'], 'j M') : '' ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Delete Event -->
    <div class="detail-card danger-zone">
        <h3>Danger Zone</h3>
        <form method="post" action="/admin/events/<?= (int) $event['id'] ?>/delete" onsubmit="return confirm('Delete this event? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete Event</button>
            <span class="help-text">This will permanently delete the event and all associated registrations.</span>
        </form>
    </div>

    <p><a href="/admin/events">&larr; Back to events</a></p>
</div>
