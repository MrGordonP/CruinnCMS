<?php \Cruinn\Template::requireCss('admin-members.css'); ?>
<div class="admin-page-header">
    <h1><?= e($user['display_name']) ?></h1>
    <div class="header-actions">
        <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="btn btn-primary btn-small">Edit</a>
        <a href="/admin/users" class="btn btn-outline btn-small">Back to Users</a>
    </div>
</div>

<div class="user-detail" data-user-id="<?= (int)$user['id'] ?>">
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
                            data-confirm="Deactivate this user? They will not be able to log in.">
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
    <div class="detail-card">
        <h2>Linked Member Record</h2>
        <?php if ($member): ?>
        <table class="detail-table" style="margin-bottom:0.75rem">
            <tr>
                <th>Member</th>
                <td>
                    <a href="<?= url('/admin/membership?member=' . (int)$member['id']) ?>">
                        <?= e(trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? ''))) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th>Membership #</th>
                <td><?= e($member['membership_number'] ?? '—') ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="badge badge-<?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span></td>
            </tr>
        </table>
        <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/unlink-member">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-small"
                    data-confirm="Remove the link between this user and their member record?">Unlink Member</button>
        </form>
        <?php else: ?>
        <p class="text-muted" style="font-size:0.85rem;margin:0 0 0.75rem">No member record linked.</p>
        <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/link-member" style="display:flex;gap:0.4rem;position:relative">
            <?= csrf_field() ?>
            <div style="flex:1;position:relative">
                <input class="form-input" type="text" id="member-search-input" name="member_search"
                       data-search-url="<?= url('/admin/membership/members/search') ?>"
                       placeholder="Name, membership number or email" style="width:100%" required autocomplete="off">
                <ul id="member-search-list" style="display:none;position:absolute;z-index:999;top:100%;left:0;right:0;
                    margin:2px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #ccd9d3;
                    border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.12);max-height:220px;overflow-y:auto;font-size:0.85rem"></ul>
            </div>
            <button type="submit" class="btn btn-primary btn-small">Link</button>
        </form>
        <?php \Cruinn\Template::requireJs('member-search.js'); ?>
        <?php endif; ?>
    </div>

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
              data-confirm="Permanently delete user <?= e($user['email']) ?>? This cannot be undone.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete User</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php \Cruinn\Template::requireJs('user-profile.js'); ?>
