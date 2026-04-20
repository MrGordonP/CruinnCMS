<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<?php
$baseUrl  = url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members');
$sortLink = fn(string $col) =>
    $baseUrl . '?' . http_build_query([
        'status' => $filterStatus,
        'year'   => $filterYear,
        'active' => $filterActive,
        'sort'   => $col,
        'dir'    => ($sort === $col && $dir === 'ASC') ? 'desc' : 'asc',
    ]);
$sortIcon = fn(string $col) =>
    $sort === $col ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
?>
<div class="card-header-row" style="margin-bottom: var(--space-lg);">
    <h1><?= e($title) ?></h1>
    <a href="<?= url('/admin/social/mailing-lists') ?>" class="btn btn-outline">← Back to Lists</a>
</div>

<?php /* ── Filter bar ─────────────────────────────────────────── */ ?>
<form method="GET" action="<?= $baseUrl ?>" class="card" style="margin-bottom: var(--space-lg);">
    <div class="card-body" style="padding: var(--space-md);">
        <div class="form-row" style="align-items: flex-end; flex-wrap: wrap; gap: var(--space-md);">
            <div class="form-group" style="flex: 1; min-width: 140px; margin-bottom: 0;">
                <label>Member Status</label>
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    <?php foreach (['active','applicant','lapsed','suspended','resigned','archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 0;">
                <label>Membership Year</label>
                <select name="year" class="form-control">
                    <option value="">All years</option>
                    <?php foreach ($years as $yr): ?>
                        <option value="<?= (int)$yr ?>" <?= (string)$filterYear === (string)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 110px; margin-bottom: 0;">
                <label>Account Active</label>
                <select name="active" class="form-control">
                    <option value="">Any</option>
                    <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= strtolower($dir) ?>">
            <div style="display: flex; gap: var(--space-sm); align-items: flex-end; margin-bottom: 0;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= $baseUrl ?>" class="btn btn-outline">Clear</a>
            </div>
        </div>
    </div>
</form>

<?php /* ── User picker ─────────────────────────────────────────── */ ?>
<form method="POST" action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members/add') ?>" id="bulk-add-form">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom: var(--space-lg);">
        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h2>Users <span class="badge badge-muted" id="selected-count">0 selected</span></h2>
            <div style="display: flex; gap: var(--space-sm);">
                <button type="button" class="btn btn-outline btn-small" id="select-all">Select All</button>
                <button type="button" class="btn btn-outline btn-small" id="clear-sel">Clear</button>
                <button type="submit" class="btn btn-primary btn-small">Add Selected to List</button>
            </div>
        </div>
        <?php if (empty($users)): ?>
            <div class="card-body"><p class="text-muted">No users match the current filters (or all are already on this list).</p></div>
        <?php else: ?>
        <table class="admin-table" id="user-picker-table">
            <thead>
                <tr>
                    <th style="width: 36px;"><input type="checkbox" id="header-check" title="Select all visible"></th>
                    <th><a href="<?= $sortLink('u.display_name') ?>" class="sort-link">Name<?= $sortIcon('u.display_name') ?></a></th>
                    <th><a href="<?= $sortLink('u.email') ?>" class="sort-link">Email<?= $sortIcon('u.email') ?></a></th>
                    <th><a href="<?= $sortLink('m.status') ?>" class="sort-link">Member Status<?= $sortIcon('m.status') ?></a></th>
                    <th><a href="<?= $sortLink('m.membership_year') ?>" class="sort-link">Year<?= $sortIcon('m.membership_year') ?></a></th>
                    <th>Account</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="user-row">
                    <td><input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" class="row-check"></td>
                    <td><?= e($u['display_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?php if ($u['member_status']): ?>
                            <span class="badge badge-<?= $u['member_status'] === 'active' ? 'success' : 'muted' ?>">
                                <?= e($u['member_status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['membership_year'] ? (int)$u['membership_year'] : '—' ?></td>
                    <td>
                        <span class="badge badge-<?= $u['active'] ? 'success' : 'muted' ?>">
                            <?= $u['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</form>

<?php /* ── Add by email ─────────────────────────────────────────── */ ?>
<div class="card" style="margin-bottom: var(--space-lg);">
    <div class="card-header"><h2>Add by Email Address</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members/add') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label>Name <span style="font-weight:400;">(optional)</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Display name">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add by Email</button>
        </form>
    </div>
</div>

<?php /* ── Pending approval ─────────────────────────────────── */ ?>
<?php if (!empty($pendingMembers)): ?>
<div class="card" style="margin-bottom: var(--space-lg); border-color: var(--color-warning, #f59e0b);">
    <div class="card-header" style="background: var(--color-warning-light, #fffbeb);">
        <h2>Pending Approval <span class="badge badge-warning"><?= count($pendingMembers) ?></span></h2>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>Email</th><th>Name</th><th>Requested</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($pendingMembers as $p): ?>
            <tr>
                <td><?= e($p['email']) ?></td>
                <td><?= e($p['display_name'] ?? $p['name'] ?? '') ?></td>
                <td><?= format_date($p['subscribed_at'], 'j M Y') ?></td>
                <td style="white-space: nowrap;">
                    <form action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members/' . (int)$p['sub_id'] . '/approve') ?>" method="POST" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-primary">Approve</button>
                    </form>
                    <form action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members/' . (int)$p['sub_id'] . '/reject') ?>" method="POST" style="display:inline"
                        onsubmit="return confirm('Reject and remove this request?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger btn-outline">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php /* ── Current list members ─────────────────────────────────── */ ?>
<div class="card">
    <div class="card-header">
        <h2>Current List Members <span class="badge badge-muted"><?= count($members) ?></span></h2>
    </div>
    <?php if (empty($members)): ?>
        <div class="card-body"><p class="text-muted">No members yet.</p></div>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Email</th>
                <th>Name</th>
                <th>Status</th>
                <th>Subscribed</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
                <td><?= e($m['email']) ?></td>
                <td><?= e($m['display_name'] ?? $m['name'] ?? '') ?></td>
                <td>
                    <span class="badge badge-<?= $m['status'] === 'active' ? 'success' : 'muted' ?>">
                        <?= e($m['status']) ?>
                    </span>
                </td>
                <td><?= format_date($m['subscribed_at'], 'j M Y') ?></td>
                <td>
                    <form action="<?= url('/admin/social/mailing-lists/' . (int)$list['id'] . '/members/' . (int)$m['sub_id'] . '/remove') ?>" method="POST" style="display:inline"
                        onsubmit="return confirm('Remove <?= e(addslashes($m['email'])) ?> from this list?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger btn-outline">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<style>
.sort-link { color: inherit; text-decoration: none; white-space: nowrap; }
.sort-link:hover { text-decoration: underline; }
.user-row.selected { background: var(--color-primary-light, #e8f0fe); }
.user-row { cursor: pointer; }
</style>
<script>
(function () {
    var table       = document.getElementById('user-picker-table');
    var headerCheck = document.getElementById('header-check');
    var countBadge  = document.getElementById('selected-count');

    function updateCount() {
        var n = document.querySelectorAll('.row-check:checked').length;
        countBadge.textContent = n + ' selected';
    }

    function setHighlight(row, on) {
        row.classList.toggle('selected', on);
    }

    if (table) {
        table.addEventListener('change', function (e) {
            if (e.target.classList.contains('row-check')) {
                setHighlight(e.target.closest('tr'), e.target.checked);
                updateCount();
            }
        });

        table.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'A') return;
            var row = e.target.closest('tr.user-row');
            if (!row) return;
            var cb = row.querySelector('.row-check');
            cb.checked = !cb.checked;
            setHighlight(row, cb.checked);
            updateCount();
        });
    }

    if (headerCheck) {
        headerCheck.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = headerCheck.checked;
                setHighlight(cb.closest('tr'), cb.checked);
            });
            updateCount();
        });
    }

    var selAll = document.getElementById('select-all');
    var clrSel = document.getElementById('clear-sel');

    if (selAll) selAll.addEventListener('click', function () {
        document.querySelectorAll('.row-check').forEach(function (cb) {
            cb.checked = true; setHighlight(cb.closest('tr'), true);
        });
        if (headerCheck) headerCheck.checked = true;
        updateCount();
    });

    if (clrSel) clrSel.addEventListener('click', function () {
        document.querySelectorAll('.row-check').forEach(function (cb) {
            cb.checked = false; setHighlight(cb.closest('tr'), false);
        });
        if (headerCheck) headerCheck.checked = false;
        updateCount();
    });
}());
</script>
