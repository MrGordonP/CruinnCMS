<?php
/**
 * Mailbox access panel fragment — loaded into right (pl-detail) panel via fetch.
 * No layout (standalone HTML fragment).
 *
 * Shows current access grants for the selected mailbox and a form to add new ones.
 * A grant is either:
 *  - user_id set      → direct grant to a specific user
 *  - officer_position_id set → position-based grant (resolves to current holder)
 *
 * @var array  $mb                    Mailbox row (id, label, email)
 * @var array  $grants                Current access grants
 * @var int[]  $granted_user_ids      user_ids already granted
 * @var int[]  $granted_position_ids  officer_position_ids already granted
 * @var array  $available_users       All users
 * @var array  $available_positions   All officer positions
 * @var string $csrf_token
 */
?>
<style>
.access-panel { display: flex; flex-direction: column; height: 100%; font-size: 0.85rem; }
.access-panel-header {
    padding: 0.75rem 1rem 0.6rem; border-bottom: 1px solid var(--color-border, #ccd9d3);
    background: var(--color-surface, #f6f4ef); flex-shrink: 0;
}
.access-panel-header h3 { margin: 0 0 0.1rem; font-size: 0.9rem; }
.access-panel-header p  { margin: 0; font-size: 0.78rem; opacity: .55; }
.access-panel-body { flex: 1; overflow-y: auto; padding: 1rem; }
.access-section { margin-bottom: 1.5rem; }
.access-section h4 {
    margin: 0 0 0.6rem; font-size: 0.75rem; text-transform: uppercase;
    letter-spacing: .06em; opacity: .5;
}
.access-grant {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0.6rem; border-radius: 5px; margin-bottom: 0.35rem;
    background: #f9f9f7; border: 1px solid var(--color-border, #ccd9d3);
}
.access-grant-icon { font-size: 1rem; flex-shrink: 0; }
.access-grant-info { flex: 1; overflow: hidden; }
.access-grant-info strong { display: block; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
.access-grant-info span   { font-size: 0.75rem; opacity: .55; }
.access-grant-revoke {
    background: none; border: none; cursor: pointer; padding: 0.2rem 0.35rem;
    color: #c00; font-size: 0.78rem; border-radius: 3px; flex-shrink: 0;
}
.access-grant-revoke:hover { background: #fee; }
.access-add-form {
    border: 1px solid var(--color-border, #ccd9d3); border-radius: 6px;
    padding: 0.75rem; background: #fafaf8;
}
.access-add-form h4 { margin: 0 0 0.6rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .06em; opacity: .5; }
.access-add-form .form-row { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
.access-add-form select { flex: 1; border: 1px solid var(--color-border, #ccd9d3); border-radius: 4px; padding: 0.3rem 0.5rem; font-size: 0.82rem; }
.access-add-form .btn { flex-shrink: 0; }
</style>

<div class="access-panel">
    <div class="access-panel-header">
        <h3><?= e($mb['label']) ?></h3>
        <p><?= e($mb['email']) ?></p>
    </div>
    <div class="access-panel-body">

        <!-- Current grants -->
        <div class="access-section">
            <h4>Current Access</h4>
            <?php if (empty($grants)): ?>
            <p style="color:#aaa;padding:0.5rem 0">No access grants yet.</p>
            <?php else: ?>
            <?php foreach ($grants as $grant): ?>
            <div class="access-grant">
                <?php if ($grant['officer_position_id']): ?>
                <span class="access-grant-icon" title="Officer position">🪑</span>
                <div class="access-grant-info">
                    <strong><?= e($grant['officer_position']) ?></strong>
                    <span><?= e($grant['officer_email'] ?? '') ?> &nbsp;·&nbsp; via position</span>
                </div>
                <?php else: ?>
                <span class="access-grant-icon" title="Direct user grant">👤</span>
                <div class="access-grant-info">
                    <strong><?= e($grant['user_name']) ?></strong>
                    <span><?= e($grant['user_email'] ?? '') ?> &nbsp;·&nbsp; direct</span>
                </div>
                <?php endif; ?>
                <form method="post" action="/admin/mailbox/<?= (int)$mb['id'] ?>/access/<?= (int)$grant['grant_id'] ?>/revoke"
                      onsubmit="return confirm('Revoke this access grant?')">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <button type="submit" class="access-grant-revoke" title="Revoke">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Grant access — by officer position -->
        <div class="access-add-form" style="margin-bottom:1rem">
            <h4>Grant via Officer Position</h4>
            <form method="post" action="/admin/mailbox/<?= (int)$mb['id'] ?>/access/grant">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="grant_type" value="position">
                <div class="form-row">
                    <select name="target_id" required>
                        <option value="">Select position…</option>
                        <?php foreach ($available_positions as $pos): ?>
                        <?php if (in_array((int)$pos['id'], $granted_position_ids, true)) continue; ?>
                        <option value="<?= (int)$pos['id'] ?>"><?= e($pos['position']) ?><?= $pos['email'] ? ' (' . e($pos['email']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Grant</button>
                </div>
            </form>
            <p style="font-size:0.75rem;opacity:.5;margin:0.35rem 0 0">Access passes to whoever currently holds the position.</p>
        </div>

        <!-- Grant access — direct user -->
        <div class="access-add-form">
            <h4>Grant to Specific User</h4>
            <form method="post" action="/admin/mailbox/<?= (int)$mb['id'] ?>/access/grant">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="grant_type" value="user">
                <div class="form-row">
                    <select name="target_id" required>
                        <option value="">Select user…</option>
                        <?php foreach ($available_users as $u): ?>
                        <?php if (in_array((int)$u['id'], $granted_user_ids, true)) continue; ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?><?= $u['email'] ? ' (' . e($u['email']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Grant</button>
                </div>
            </form>
            <p style="font-size:0.75rem;opacity:.5;margin:0.35rem 0 0">Tied to this specific account regardless of position.</p>
        </div>

    </div>
</div>
