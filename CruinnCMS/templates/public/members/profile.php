<?php
/** My Account -- user portal landing page. All logged-in roles land here. */
$isAdmin   = ($current_user['role'] ?? '') === 'admin';
$isCouncil = ($current_user['role'] ?? '') === 'council';
$userName  = $member
    ? e(trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')))
    : e($current_user['display_name'] ?? $current_user['email'] ?? 'there');
?>
<div class="container">

    <!-- Greeting header -->
    <div class="my-account-header">
        <div class="my-account-greeting">
            <h1>Hello, <?= $userName ?></h1>
            <?php if ($member): ?>
            <span class="badge badge-<?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span>
            <?php if (!empty($member['type_name'])): ?><span class="badge badge-outline"><?= e($member['type_name']) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($isAdmin): ?>
        <a href="<?= url('/admin') ?>" class="btn btn-primary">&#x2699; Admin Panel</a>
        <?php elseif ($isCouncil): ?>
        <a href="<?= url('/council') ?>" class="btn btn-primary">Council Workspace &rarr;</a>
        <?php endif; ?>
    </div>

    <div class="my-account-grid">

        <!-- Left column -->
        <div class="my-account-main">

            <?php if ($isAdmin && $adminStats): ?>
            <div class="detail-card my-account-admin-stats">
                <h2>Site at a glance</h2>
                <div class="dash-quick-grid">
                    <a href="<?= url('/admin/pages') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F4C4;</span><span><?= $adminStats['pages'] ?> Pages</span></a>
                    <a href="<?= url('/admin/members') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F465;</span><span><?= $adminStats['members'] ?> Members</span></a>
                    <a href="<?= url('/admin/users') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F511;</span><span><?= $adminStats['users'] ?> Users</span></a>
                    <a href="<?= url('/admin/site-builder') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F3D7;&#xFE0F;</span><span>Site Builder</span></a>
                    <a href="<?= url('/admin/settings/site') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x2699;&#xFE0F;</span><span>Settings</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($member): ?>
            <div class="detail-card">
                <div class="activity-header">
                    <h2>Your Details</h2>
                </div>
                <form method="post" action="/members/profile">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name</label>
                            <p class="form-static"><?= e(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')) ?></p>
                        </div>
                        <div class="form-group">
                            <label>Member ID</label>
                            <p class="form-static"><?= e($member['mem_id'] ?: '&mdash;') ?></p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= e($member['email'] ?? '') ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="institute">Institute / Organisation</label>
                            <input type="text" id="institute" name="institute" value="<?= e($member['institute'] ?? '') ?>" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="public_directory" value="1" <?= !empty($member['public_directory']) ? 'checked' : '' ?>>
                            Show my name in the public member directory
                        </label>
                    </div>
                    <h3 style="margin: var(--space-lg) 0 var(--space-sm)">Address</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address1">Address Line 1</label>
                            <input type="text" id="address1" name="address1" value="<?= e($address['address1'] ?? '') ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="address2">Address Line 2</label>
                            <input type="text" id="address2" name="address2" value="<?= e($address['address2'] ?? '') ?>" class="form-input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="county">County</label>
                            <input type="text" id="county" name="county" value="<?= e($address['county'] ?? '') ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" value="<?= e($address['country'] ?? 'Ireland') ?>" class="form-input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eircode">Eircode / Postcode</label>
                            <input type="text" id="eircode" name="eircode" value="<?= e($address['eircode'] ?? '') ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($address['phone'] ?? '') ?>" class="form-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="detail-card">
                <p class="text-muted">Your account is not linked to a membership record. If you think this is an error, please <a href="/contact">contact us</a>.</p>
            </div>
            <?php endif; ?>

            <?php if (\Cruinn\Module\Gdpr\Services\GdprService::enabled()): ?>
            <div class="detail-card">
                <h2>Your Data &amp; Privacy</h2>
                <p class="text-muted">Under GDPR you have the right to access and delete your personal data. Read our <a href="/privacy">Privacy Policy</a> for full details.</p>
                <div class="form-actions">
                    <form method="post" action="/members/data-export" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-small">Download My Data</button>
                    </form>
                    <a href="/members/delete-account" class="btn btn-danger btn-small">Delete My Account</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /my-account-main -->

        <!-- Right column -->
        <div class="my-account-sidebar">

            <?php if (!empty($notifications)): ?>
            <div class="detail-card">
                <div class="activity-header">
                    <h2>Notifications <?php if ($unreadCount): ?><span class="badge badge-primary"><?= $unreadCount ?></span><?php endif; ?></h2>
                    <a href="<?= url('/notifications') ?>" class="text-small">View all</a>
                </div>
                <ul class="notif-list">
                    <?php foreach ($notifications as $n): ?>
                    <li class="notif-item<?= $n['read_at'] ? '' : ' notif-unread' ?>">
                        <?php if (!empty($n['url'])): ?><a href="<?= e($n['url']) ?>"><?= e($n['title']) ?></a><?php else: ?><span><?= e($n['title']) ?></span><?php endif; ?>
                        <span class="notif-time"><?= e(date('j M', strtotime($n['created_at']))) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcomingEvents)): ?>
            <div class="detail-card">
                <div class="activity-header">
                    <h2>Upcoming Events</h2>
                    <a href="<?= url('/events') ?>" class="text-small">All events</a>
                </div>
                <ul class="event-list-compact">
                    <?php foreach ($upcomingEvents as $ev): ?>
                    <li>
                        <a href="<?= url('/events/' . e($ev['slug'])) ?>"><?= e($ev['title']) ?></a>
                        <span class="text-muted text-small"><?= e(date('j M Y', strtotime($ev['date_start']))) ?><?= $ev['location'] ? ' &middot; ' . e($ev['location']) : '' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($latestSub): ?>
            <div class="detail-card">
                <h2>Membership</h2>
                <dl class="detail-list">
                    <dt>Period</dt><dd><?= e($latestSub['period'] ?? '&mdash;') ?></dd>
                    <dt>Status</dt><dd><span class="badge badge-<?= $latestSub['status'] === 'paid' ? 'success' : 'warning' ?>"><?= e(ucfirst($latestSub['status'])) ?></span></dd>
                    <?php if (!empty($latestSub['payment_date'])): ?><dt>Paid</dt><dd><?= e(date('j M Y', strtotime($latestSub['payment_date']))) ?></dd><?php endif; ?>
                </dl>
            </div>
            <?php endif; ?>

            <div class="detail-card">
                <h2>Account</h2>
                <ul class="account-links">
                    <li><a href="<?= url('/directory') ?>">Member Directory</a></li>
                    <?php if ($isAdmin): ?><li><a href="<?= url('/admin') ?>">Admin Panel</a></li><?php endif; ?>
                    <?php if ($isCouncil): ?><li><a href="<?= url('/council') ?>">Council Workspace</a></li><?php endif; ?>
                    <li><a href="<?= url('/logout') ?>">Logout</a></li>
                </ul>
            </div>

        </div><!-- /my-account-sidebar -->

    </div><!-- /my-account-grid -->
</div>