<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
$selectedId = $group ? (int)$group['id'] : 0;
$formAction = $selectedId ? '/admin/groups/' . $selectedId : '/admin/groups';
?>

<div class="panel-layout" id="groups-layout" data-group-id="<?= $selectedId ?>">

    <!-- Left: group list -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Groups</h3>
            <a href="/admin/groups/new" class="btn btn-primary btn-small">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <?php foreach ($allGroups as $g): ?>
            <a href="/admin/groups?group=<?= (int)$g['id'] ?>"
               class="pl-sidebar-item<?= $g['id'] == $selectedId ? ' active' : '' ?>">
                <span style="flex:1"><?= e($g['name']) ?></span>
                <small style="color:var(--color-text-muted);font-size:0.75rem"><?= (int)$g['member_count'] ?> members</small>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main: group form or placeholder -->
    <div class="pl-main">
        <?php if (!$group): ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--color-text-muted);gap:0.5rem">
            <div style="font-size:2rem">👥</div>
            <p>Select a group to edit, or <a href="/admin/groups/new">create a new one</a>.</p>
        </div>
        <?php else: ?>
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($group['name']) ?></span>
            <div class="pl-main-toolbar-actions">
                <form method="post" action="/admin/groups/<?= $selectedId ?>/delete" style="display:inline"
                      data-confirm="Delete group '<?= e($group['name']) ?>'? Members will be unlinked."><?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
            </div>
        </div>
        <div class="pl-main-body">

            <?php if (!empty($errors)): ?>
            <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="<?= e($formAction) ?>" class="admin-form">
                <?= csrf_field() ?>

                <div class="detail-card">
                    <h2>Group Details</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Group Name <span class="required">*</span></label>
                            <input type="text" name="name" id="name" class="form-input"
                                   value="<?= e($group['name'] ?? '') ?>" required>
                            <?php if (!empty($errors['name'])): ?><p class="form-error"><?= e($errors['name']) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="group_type">Group Type</label>
                            <select name="group_type" id="group_type" class="form-select">
                                <?php foreach (['committee' => 'Committee', 'working_group' => 'Working Group', 'interest' => 'Interest Group', 'custom' => 'Custom'] as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= ($group['group_type'] ?? 'custom') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" name="description" id="description" class="form-input"
                               value="<?= e($group['description'] ?? '') ?>" placeholder="What this group is for">
                    </div>
                    <div class="form-group">
                        <label for="role_id">Linked Role (optional)</label>
                        <select name="role_id" id="role_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= ((int)($group['role_id'] ?? 0)) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">Members inherit permissions from the linked role.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <!-- Positions -->
            <div class="detail-card" style="margin-top:1.25rem">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem">
                    <h2 style="margin:0">Positions</h2>
                </div>
                <p class="form-help" style="margin:0 0 0.75rem">Named roles within this group (e.g. President, Secretary). Members can be assigned one or more positions.</p>
                <div id="positions-list" style="margin-bottom:0.6rem">
                    <?php if (empty($positions)): ?>
                    <p class="text-muted" style="font-size:0.85rem" id="positions-empty">No positions defined yet.</p>
                    <?php else: ?>
                    <?php foreach ($positions as $pos): ?>
                    <div class="role-member-row" style="gap:0.4rem" data-position-id="<?= (int)$pos['id'] ?>">
                        <span style="flex:1;font-size:0.9rem"><?= e($pos['name']) ?></span>
                        <button type="button" class="btn btn-danger btn-small del-position-btn"
                                data-position-id="<?= (int)$pos['id'] ?>"
                                data-name="<?= e($pos['name']) ?>">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:0.4rem">
                    <input type="text" id="new-position-name" class="form-input"
                           placeholder="New position name e.g. President">
                    <button type="button" id="add-position-btn" class="btn btn-primary btn-small">Add</button>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <!-- Right: members panel -->
    <div class="pl-detail">
        <div class="pl-detail-header"><h3>Members</h3></div>
        <div class="pl-detail-scroll" style="padding:0.75rem">
        <?php if (!$group): ?>
            <p class="text-muted" style="font-size:0.85rem">Select a group to manage members.</p>
        <?php else: ?>

            <!-- Add user to group -->
            <div class="detail-card" style="margin-bottom:1rem;padding:0.75rem">
                <h4 style="margin:0 0 0.5rem;font-size:0.82rem;text-transform:uppercase;letter-spacing:0.05em">Add Member</h4>
                <div style="display:flex;gap:0.4rem;align-items:center">
                    <select id="add-user-select" class="form-input" style="flex:1;font-size:0.85rem">
                        <option value="">— select user —</option>
                        <?php foreach ($usersNotInGroup as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="add-user-btn" class="btn btn-primary btn-small">Add</button>
                </div>
            </div>

            <!-- Current members -->
            <div id="group-members-list">
                <?php if (empty($members)): ?>
                <p class="text-muted" style="font-size:0.85rem">No members in this group.</p>
                <?php else: ?>
                <?php foreach ($members as $m): ?>
                <div class="role-member-row" style="flex-direction:column;align-items:stretch;gap:0.3rem;padding:0.5rem 0.4rem"
                     data-user-id="<?= (int)$m['id'] ?>">
                    <div style="display:flex;align-items:center;gap:0.4rem">
                        <span style="flex:1;font-size:0.85rem;font-weight:500"><?= e($m['display_name']) ?></span>
                        <button type="button" class="btn btn-danger btn-small remove-user-btn"
                                data-user-id="<?= (int)$m['id'] ?>"
                                data-name="<?= e($m['display_name']) ?>">✕</button>
                    </div>
                    <div class="member-positions" style="display:flex;flex-wrap:wrap;gap:0.25rem;min-height:1.2rem">
                        <?php foreach ($m['positions'] as $p): ?>
                        <span class="position-chip" data-position-id="<?= (int)$p['id'] ?>">
                            <?= e($p['name']) ?>
                            <button type="button" class="remove-position-btn"
                                    data-user-id="<?= (int)$m['id'] ?>"
                                    data-position-id="<?= (int)$p['id'] ?>"
                                    title="Remove position">✕</button>
                        </span>
                        <?php endforeach; ?>
                        <?php if (!empty($positions)): ?>
                        <select class="assign-position-select"
                                data-user-id="<?= (int)$m['id'] ?>"
                                style="font-size:0.75rem;padding:2px 4px;border-radius:3px;border:1px solid #d1d5db;max-width:140px">
                            <option value="">+ assign position</option>
                            <?php foreach ($positions as $pos): ?>
                            <?php $alreadyHas = in_array($pos['id'], array_column($m['positions'], 'id')); ?>
                            <?php if (!$alreadyHas): ?>
                            <option value="<?= (int)$pos['id'] ?>"><?= e($pos['name']) ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

</div><!-- /.panel-layout -->

<style>
.position-chip {
    display:inline-flex;align-items:center;gap:0.2rem;
    font-size:0.72rem;padding:1px 5px;border-radius:3px;
    background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;
}
.position-chip .remove-position-btn {
    background:none;border:none;cursor:pointer;color:#0369a1;
    font-size:0.7rem;padding:0;line-height:1;opacity:0.6;
}
.position-chip .remove-position-btn:hover { opacity:1; }
</style>

<?php if ($group): ?>
<?php \Cruinn\Template::requireJs('groups.js'); ?>
<?php endif; ?>
