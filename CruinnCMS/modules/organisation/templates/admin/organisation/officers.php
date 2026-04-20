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

    <?php if ($flash = \Cruinn\Auth::getFlash('success')): ?>
        <div class="alert alert-success"><?= $this->escape($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash = \Cruinn\Auth::getFlash('error')): ?>
        <div class="alert alert-error"><?= $this->escape($flash) ?></div>
    <?php endif; ?>

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
                            <option value="<?= (int)$u['id'] ?>"><?= $this->escape($u['display_name']) ?></option>
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
                    <td><strong><?= $this->escape($o['position']) ?></strong></td>
                    <td>
                        <?php if ($o['user_display_name']): ?>
                            <?= $this->escape($o['user_display_name']) ?>
                            <small class="text-muted">(account)</small>
                        <?php else: ?>
                            <?= $this->escape($o['name'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $this->escape($o['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($o['term_start'] || $o['term_end']): ?>
                            <?= $this->escape($o['term_start'] ?? '?') ?> –
                            <?= $this->escape($o['term_end'] ?? 'present') ?>
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
                                           value="<?= $this->escape($o['position']) ?>" required>
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
                                                <?= $this->escape($u['display_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Name (if no account)</label>
                                    <input type="text" name="name" class="form-input"
                                           value="<?= $this->escape($o['name'] ?? '') ?>">
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-input"
                                           value="<?= $this->escape($o['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Term Start</label>
                                    <input type="date" name="term_start" class="form-input"
                                           value="<?= $this->escape($o['term_start'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Term End</label>
                                    <input type="date" name="term_end" class="form-input"
                                           value="<?= $this->escape($o['term_end'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Mailbox (IMAP/SMTP) — set by mailbox module migration -->
                            <?php if (array_key_exists('imap_host', $o)): ?>
                            <details class="imap-details">
                                <summary>Mailbox credentials (IMAP / SMTP)</summary>
                                <div class="form-row">
                                    <div class="form-group form-group-grow">
                                        <label>IMAP Host</label>
                                        <input type="text" name="imap_host" class="form-input"
                                               value="<?= $this->escape($o['imap_host'] ?? '') ?>"
                                               placeholder="mail.geology.ie">
                                    </div>
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="imap_port" class="form-input input-narrow"
                                               value="<?= (int) ($o['imap_port'] ?? 993) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Encryption</label>
                                        <select name="imap_encryption" class="form-select">
                                            <option value="ssl"  <?= ($o['imap_encryption'] ?? 'ssl') === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                                            <option value="tls"  <?= ($o['imap_encryption'] ?? '') === 'tls'  ? 'selected' : '' ?>>TLS</option>
                                            <option value="none" <?= ($o['imap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-grow">
                                        <label>IMAP Username</label>
                                        <input type="text" name="imap_user" class="form-input"
                                               value="<?= $this->escape($o['imap_user'] ?? '') ?>"
                                               autocomplete="off">
                                    </div>
                                    <div class="form-group form-group-grow">
                                        <label>IMAP Password</label>
                                        <input type="password" name="imap_pass" class="form-input"
                                               placeholder="Leave blank to keep current"
                                               autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-grow">
                                        <label>SMTP Host</label>
                                        <input type="text" name="smtp_host" class="form-input"
                                               value="<?= $this->escape($o['smtp_host'] ?? '') ?>"
                                               placeholder="mail.geology.ie">
                                    </div>
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="smtp_port" class="form-input input-narrow"
                                               value="<?= (int) ($o['smtp_port'] ?? 587) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Encryption</label>
                                        <select name="smtp_encryption" class="form-select">
                                            <option value="tls" <?= ($o['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($o['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="none" <?= ($o['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-grow">
                                        <label>SMTP Username</label>
                                        <input type="text" name="smtp_user" class="form-input"
                                               value="<?= $this->escape($o['smtp_user'] ?? '') ?>"
                                               autocomplete="off">
                                    </div>
                                    <div class="form-group form-group-grow">
                                        <label>SMTP Password</label>
                                        <input type="password" name="smtp_pass" class="form-input"
                                               placeholder="Leave blank to keep current"
                                               autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Enable mailbox in Cruinn</label>
                                        <select name="imap_enabled" class="form-select">
                                            <option value="0" <?= empty($o['imap_enabled']) ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= !empty($o['imap_enabled']) ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </details>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="button" class="btn btn-sm btn-link"
                                    onclick="this.closest('tr').style.display='none'">Cancel</button>
                        </form>
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
