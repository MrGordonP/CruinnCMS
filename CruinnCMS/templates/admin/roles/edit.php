<?php
$isNew = empty($role['id']);
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$formAction = $isNew ? '/admin/roles' : '/admin/roles/' . (int)$role['id'];
$isSystem   = !$isNew && ($role['is_system'] ?? false);
$roleId     = $isNew ? 0 : (int)$role['id'];
if (!$isNew) { $GLOBALS['admin_flush_layout'] = true; }
?>

<?php if ($isNew): ?>
<!-- ── New Role: simple form, no 3-panel ───────────────────────── -->
<div class="admin-page-header">
    <h1>New Role</h1>
    <div class="header-actions">
        <a href="/admin/roles" class="btn btn-outline btn-small">Back to Roles</a>
    </div>
</div>
<?php if (!empty($errors)): ?>
<div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="<?= e($formAction) ?>" class="admin-form">
    <?= csrf_field() ?>
    <?php include __DIR__ . '/_role-fields.php'; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create Role</button>
        <a href="/admin/roles" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php else: ?>
<!-- ── Edit Role: 3-panel layout ───────────────────────────────── -->
<div class="panel-layout" id="role-edit-layout" data-role-id="<?= $roleId ?>">

    <!-- Left: role navigation -->
    <div class="pl-sidebar" id="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>All Roles</h3>
            <button type="button" class="pl-panel-toggle" id="pl-sidebar-toggle" title="Collapse">&#x25C0;</button>
            <a href="/admin/roles/new" class="btn btn-primary btn-small">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <?php foreach ($allRoles ?? [] as $r): ?>
            <a href="/admin/roles/<?= (int)$r['id'] ?>/edit"
               class="pl-sidebar-item<?= $r['id'] == $roleId ? ' active' : '' ?>">
                <span class="role-badge" style="background:<?= e($r['colour'] ?? '#6c757d') ?>;font-size:0.7rem;padding:1px 6px;margin-right:0.4rem"><?= e($r['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="pl-sidebar-footer">
            <a href="/admin/roles" class="btn btn-outline btn-small" style="width:100%;text-align:center">← All Roles</a>
        </div>
    </div>

    <!-- Main: role properties form -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($role['name']) ?></span>
            <div class="pl-main-toolbar-actions">
                <a href="/admin/roles/<?= $roleId ?>/dashboard" class="btn btn-outline btn-small">Dashboard Config</a>
                <a href="/admin/roles/<?= $roleId ?>/navigation" class="btn btn-outline btn-small">Nav Config</a>
                <?php if (!$isSystem): ?>
                <form method="post" action="/admin/roles/<?= $roleId ?>/clone" style="display:inline"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-small">Clone</button>
                </form>
                <form method="post" action="/admin/roles/<?= $roleId ?>/delete" style="display:inline"
                      data-confirm="Delete role '<?= e($role['name']) ?>'?"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="pl-main-body" style="padding:var(--space-xl) var(--space-md);overflow-y:auto;flex:1;min-height:0">

            <?php if (!empty($errors)): ?>
            <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="<?= e($formAction) ?>" class="admin-form">
                <?= csrf_field() ?>
                <?php include __DIR__ . '/_role-fields.php'; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

        </div>
    </div>

    <!-- Right: members panel -->
    <div class="pl-detail" id="pl-detail">
        <div class="pl-detail-header"><h3>Role Members</h3><button type="button" class="pl-panel-toggle" id="pl-detail-toggle" title="Collapse">&#x25B6;</button></div>
        <div class="pl-detail-scroll" style="padding:0.75rem">

            <!-- Add user -->
            <div class="detail-card" style="margin-bottom:1rem;padding:0.75rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.82rem;text-transform:uppercase;letter-spacing:0.05em">Add User</h4>
                <div style="display:flex;gap:0.4rem;align-items:center">
                    <select id="add-user-select" class="form-input" style="flex:1;font-size:0.85rem">
                        <option value="">— select user —</option>
                        <?php foreach ($usersNotInRole ?? [] as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="add-user-btn" class="btn btn-primary btn-small">Add</button>
                </div>
            </div>

            <!-- Current members -->
            <div id="role-members-list">
                <?php foreach ($roleUsers ?? [] as $u): ?>
                <div class="role-member-row" data-user-id="<?= (int)$u['id'] ?>">
                    <span class="role-member-name"><?= e($u['display_name']) ?></span>
                    <button type="button" class="btn btn-danger btn-small remove-user-btn"
                            data-user-id="<?= (int)$u['id'] ?>"
                            data-name="<?= e($u['display_name']) ?>">✕</button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($roleUsers)): ?>
                <p class="text-muted" id="no-members-msg" style="font-size:0.85rem">No users assigned to this role.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div><!-- /.panel-layout -->

<?php \Cruinn\Template::requireJs('roles.js'); ?>
<?php endif; ?>
<?php endif; ?>