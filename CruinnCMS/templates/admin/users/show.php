<?php \Cruinn\Template::requireCss('admin-members.css'); ?>
<div class="admin-page-header">
    <h1><?= e($user['display_name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="btn btn-primary btn-small">Edit</a>
        <a href="/admin/users" class="btn btn-outline btn-small">Back to Users</a>
    </div>
</div>

<div class="user-detail">
    <!-- Account Info -->
    <div class="detail-card">
        <h2>Account Information</h2>
        <table class="detail-table">
            <tr>
                <th>User ID</th>
                <td>#<?= (int)$user['id'] ?></td>
            </tr>
            <tr>
                <th>Display Name</th>
                <td><?= e($user['display_name']) ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a></td>
            </tr>
            <tr>
                <th>Roles</th>
                <td>
                    <?php if (!empty($userRoles)): ?>
                        <?php foreach ($userRoles as $r): ?>
                            <span class="badge badge-role-<?= e($r['slug']) ?>"><?= e($r['name']) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge badge-role-none">Unassigned</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if ($user['active']): ?>
                        <span class="badge badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge badge-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (array_key_exists('email_verified_at', $user)): ?>
            <tr>
                <th>Email Verified</th>
                <td>
                    <?php if ($user['email_verified_at']): ?>
                        <span class="badge badge-active">Verified</span>
                        <span class="text-muted"><?= format_date($user['email_verified_at'], 'j M Y H:i') ?></span>
                    <?php else: ?>
                        <span class="badge badge-inactive">Not verified</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Last Login</th>
                <td>
                    <?php if ($user['last_login']): ?>
                        <time datetime="<?= e($user['last_login']) ?>"><?= format_date($user['last_login'], 'j M Y H:i') ?></time>
                    <?php else: ?>
                        <span class="text-muted">Never logged in</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Account Created</th>
                <td><time datetime="<?= e($user['created_at']) ?>"><?= format_date($user['created_at'], 'j M Y H:i') ?></time></td>
            </tr>
        </table>
    </div>

    <!-- Quick Actions -->
    <div class="detail-card">
        <h2>Quick Actions</h2>
        <div class="quick-actions">
            <!-- Toggle Active -->
            <?php if ((int)$user['id'] !== \Cruinn\Auth::userId()): ?>
            <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/toggle" class="inline-form">
                <?= csrf_field() ?>
                <?php if ($user['active']): ?>
                    <button type="submit" class="btn btn-secondary btn-small"
                            onclick="return confirm('Deactivate this user? They will not be able to log in.')">
                        Deactivate Account
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success btn-small">Activate Account</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>

            <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="btn btn-secondary btn-small">Change Role</a>
            <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="btn btn-secondary btn-small">Reset Password</a>
        </div>
    </div>

    <!-- Linked Member Record -->
    <?php if ($member): ?>
    <div class="detail-card">
        <h2>Linked Member Record</h2>
        <table class="detail-table">
            <tr>
                <th>Member</th>
                <td>
                    <a href="/admin/members/<?= (int)$member['id'] ?>">
                        <?= e($member['forenames'] . ' ' . $member['surnames']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="badge badge-<?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span></td>
            </tr>
            <tr>
                <th>Type</th>
                <td><?= e($member['type'] ?? '—') ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="detail-card">
        <div class="activity-header">
            <h2>Recent Activity</h2>
        </div>
        <?php if (empty($activity)): ?>
            <p class="text-muted">No activity recorded for this user.</p>
        <?php else: ?>
        <div class="activity-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>What</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activity as $a): ?>
                <tr>
                    <td><time datetime="<?= e($a['created_at']) ?>"><?= format_date($a['created_at'], 'j M H:i') ?></time></td>
                    <td><span class="badge badge-<?= e($a['action']) ?>"><?= e(ucfirst($a['action'])) ?></span></td>
                    <td><?= e(ucfirst($a['entity_type'])) ?> <?= $a['entity_id'] ? '#' . $a['entity_id'] : '' ?></td>
                    <td><?= e(truncate($a['details'] ?? '', 80)) ?></td>
                    <td class="text-muted"><?= e($a['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Danger Zone -->
    <?php if ((int)$user['id'] !== \Cruinn\Auth::userId()): ?>
    <div class="danger-zone">
        <h3>Danger Zone</h3>
        <p>Permanently delete this user account. Their activity log entries will be preserved but unlinked. This cannot be undone.</p>
        <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/delete"
              onsubmit="return confirm('Permanently delete user <?= e($user['email']) ?>? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete User</button>
        </form>
    </div>
    <?php endif; ?>
</div>
