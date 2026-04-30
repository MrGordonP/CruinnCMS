<?php
/**
 * Organisation Admin — Officers
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Officers</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/profile"  class="btn btn-secondary btn-sm">Profile</a>
            <a href="/admin/organisation/meetings" class="btn btn-secondary btn-sm">Meetings</a>
        </div>
    </div>



    <!-- Add officer -->
    <div class="admin-card">
        <h2>Add Officer</h2>
        <form method="post" action="/admin/organisation/officers" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="position">Position <span class="required">*</span></label>
                    <input type="text" name="position" id="position" class="form-input" required
                           placeholder="e.g. President">
                </div>
                <div class="form-group">
                    <label for="sort_order">Order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-input input-narrow"
                           value="0" min="0" step="10">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="user_id">Linked User Account</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-group-grow">
                    <label for="name">Name (if no account)</label>
                    <input type="text" name="name" id="name" class="form-input">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-input">
                </div>
                <div class="form-group">
                    <label for="term_start">Term Start</label>
                    <input type="date" name="term_start" class="form-input">
                </div>
                <div class="form-group">
                    <label for="term_end">Term End</label>
                    <input type="date" name="term_end" class="form-input">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Add Officer</button>
        </form>
    </div>

    <!-- Current officers -->
    <?php if (empty($officers)): ?>
        <p class="empty-state">No officers recorded yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Name / Account</th>
                    <th>Email</th>
                    <th>Term</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($officers as $o): ?>
                <tr class="<?= $o['active'] ? '' : 'row-inactive' ?>">
                    <td><strong><?= e($o['position']) ?></strong></td>
                    <td>
                        <?php if ($o['user_display_name']): ?>
                            <?= e($o['user_display_name']) ?>
                            <small class="text-muted">(account)</small>
                        <?php else: ?>
                            <?= e($o['name'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($o['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($o['term_start'] || $o['term_end']): ?>
                            <?= e($o['term_start'] ?? '?') ?> –
                            <?= e($o['term_end'] ?? 'present') ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $o['active'] ? '✓' : '—' ?></td>
                    <td class="actions">
                        <button type="button" class="btn btn-xs btn-secondary"
                                onclick="this.closest('tr').nextElementSibling.style.display='table-row';this.style.display='none'">
                            Edit
                        </button>

                        <form method="post" action="/admin/organisation/officers/<?= (int)$o['id'] ?>/delete" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <button type="submit" class="btn btn-xs btn-danger"
                                    onclick="return confirm('Remove this officer?')">Remove</button>
                        </form>
                    </td>
                </tr>
                <!-- Inline edit row -->
                <tr class="edit-row" style="display:none">
                    <td colspan="6">
                        <form method="post" action="/admin/organisation/officers/<?= (int)$o['id'] ?>/update" class="admin-form inline-edit-form">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <div class="form-row">
                                <div class="form-group form-group-grow">
                                    <label>Position</label>
                                    <input type="text" name="position" class="form-input"
                                           value="<?= e($o['position']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Order</label>
                                    <input type="number" name="sort_order" class="form-input input-narrow"
                                           value="<?= (int)$o['sort_order'] ?>" min="0" step="10">
                                </div>
                                <div class="form-group">
                                    <label>Active</label>
                                    <select name="active" class="form-select">
                                        <option value="1" <?= $o['active'] ? 'selected' : '' ?>>Yes</option>
                                        <option value="0" <?= !$o['active'] ? 'selected' : '' ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group form-group-grow">
                                    <label>Linked User</label>
                                    <select name="user_id" class="form-select">
                                        <option value="">— None —</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>"
                                                <?= (int)$o['user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                                <?= e($u['display_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Name (if no account)</label>
                                    <input type="text" name="name" class="form-input"
                                           value="<?= e($o['name'] ?? '') ?>">
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-input"
                                           value="<?= e($o['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Term Start</label>
                                    <input type="date" name="term_start" class="form-input"
                                           value="<?= e($o['term_start'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Term End</label>
                                    <input type="date" name="term_end" class="form-input"
                                           value="<?= e($o['term_end'] ?? '') ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="button" class="btn btn-sm btn-link"
                                    onclick="this.closest('tr').style.display='none'">Cancel</button>
                        </form>

                        <?php if (!empty($allMailboxes)): ?>
                        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                            <strong style="font-size:0.85rem">Mailbox Access</strong>
                            <?php $grants = $mailboxGrantsByOfficer[(int)$o['id']] ?? []; ?>
                            <?php if (!empty($grants)): ?>
                            <ul style="margin:0.5rem 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:0.4rem">
                                <?php foreach ($grants as $g): ?>
                                <li style="display:flex;align-items:center;gap:0.3rem;background:var(--bg-alt,#f4f4f4);border-radius:4px;padding:0.2rem 0.5rem;font-size:0.82rem">
                                    ✉️ <?= e($g['label']) ?>
                                    <form method="post" action="/admin/organisation/officers/<?= (int)$o['id'] ?>/mailbox/<?= (int)$g['grant_id'] ?>/revoke" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                        <button type="submit" class="btn btn-xs btn-danger" style="padding:0 0.3rem;line-height:1.4" title="Revoke">×</button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p style="font-size:0.82rem;color:#888;margin:0.4rem 0">No mailboxes assigned.</p>
                            <?php endif; ?>
                            <?php
                            $assignedIds = array_column($grants, 'mailbox_id');
                            $available   = array_filter($allMailboxes, fn($m) => !in_array($m['id'], $assignedIds));
                            ?>
                            <?php if (!empty($available)): ?>
                            <form method="post" action="/admin/organisation/officers/<?= (int)$o['id'] ?>/mailbox/assign" style="display:flex;gap:0.5rem;align-items:center;margin-top:0.5rem">
                                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                <select name="mailbox_id" class="form-select" style="flex:1">
                                    <?php foreach ($available as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>"><?= e($m['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-secondary">Assign</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.form-group-grow { flex: 1; }
.input-narrow    { width: 80px; }
.row-inactive    { opacity: .55; }
</style>
