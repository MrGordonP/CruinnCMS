<?php
/**
 * Organisation Admin — Meetings
 */

$meetingTypes = ['agm' => 'AGM', 'egm' => 'EGM', 'committee' => 'Committee', 'working_group' => 'Working Group', 'other' => 'Other'];
$statusLabels  = ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$statusBadge   = ['scheduled' => 'info', 'completed' => 'success', 'cancelled' => 'secondary'];
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Meetings</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/profile"  class="btn btn-secondary btn-sm">Profile</a>
            <a href="/admin/organisation/officers" class="btn btn-secondary btn-sm">Officers</a>
        </div>
    </div>



    <!-- Add meeting -->
    <div class="admin-card">
        <h2>Schedule Meeting</h2>
        <form method="post" action="/admin/organisation/meetings" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" class="form-input" required
                           placeholder="e.g. Committee Meeting — May 2026">
                </div>
                <div class="form-group">
                    <label for="meeting_type">Type</label>
                    <select name="meeting_type" id="meeting_type" class="form-select">
                        <?php foreach ($meetingTypes as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="meeting_date">Date <span class="required">*</span></label>
                    <input type="date" name="meeting_date" id="meeting_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" name="start_time" id="start_time" class="form-input">
                </div>
                <div class="form-group form-group-grow">
                    <label for="location">Location</label>
                    <input type="text" name="location" id="location" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Notes</label>
                <textarea name="description" id="description" class="form-input" rows="2"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group form-group-grow">
                    <label for="agenda_doc_id">Agenda Document</label>
                    <select name="agenda_doc_id" id="agenda_doc_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($documents as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-group-grow">
                    <label for="minutes_doc_id">Minutes Document</label>
                    <select name="minutes_doc_id" id="minutes_doc_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($documents as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-select">
                        <?php foreach ($statusLabels as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Schedule Meeting</button>
        </form>
    </div>

    <!-- Filters -->
    <form class="filter-bar" method="get" action="/admin/organisation/meetings">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($statusLabels as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $status === $val ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($years): ?>
            <select name="year" class="form-select">
                <option value="">All Years</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int)$y ?>" <?= (string)$year === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($status || $year): ?>
            <a href="/admin/organisation/meetings" class="btn btn-link">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Meeting list -->
    <?php if (empty($meetings)): ?>
        <p class="empty-state">No meetings found.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Agenda</th>
                    <th>Minutes</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($meetings as $m): ?>
                <tr>
                    <td>
                        <?= e($m['meeting_date']) ?>
                        <?php if ($m['start_time']): ?>
                            <br><small><?= e(substr($m['start_time'], 0, 5)) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['title']) ?></td>
                    <td><?= e($meetingTypes[$m['meeting_type']] ?? $m['meeting_type']) ?></td>
                    <td><?= e($m['location'] ?? '—') ?></td>
                    <td>
                        <?php if ($m['agenda_title']): ?>
                            <a href="/documents/<?= (int)$m['agenda_doc_id'] ?>">
                                <?= e($m['agenda_title']) ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['minutes_title']): ?>
                            <a href="/documents/<?= (int)$m['minutes_doc_id'] ?>">
                                <?= e($m['minutes_title']) ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= e($statusBadge[$m['status']] ?? 'secondary') ?>">
                            <?= e($statusLabels[$m['status']] ?? $m['status']) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <button type="button" class="btn btn-xs btn-secondary"
                                onclick="this.closest('tr').nextElementSibling.style.display='table-row';this.style.display='none'">
                            Edit
                        </button>
                        <form method="post" action="/admin/organisation/meetings/<?= (int)$m['id'] ?>/delete" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <button type="submit" class="btn btn-xs btn-danger"
                                    onclick="return confirm('Delete this meeting?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <!-- Inline edit row -->
                <tr class="edit-row" style="display:none">
                    <td colspan="8">
                        <form method="post" action="/admin/organisation/meetings/<?= (int)$m['id'] ?>/update"
                              class="admin-form inline-edit-form">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <div class="form-row">
                                <div class="form-group form-group-grow">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-input"
                                           value="<?= e($m['title']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="meeting_type" class="form-select">
                                        <?php foreach ($meetingTypes as $val => $label): ?>
                                            <option value="<?= e($val) ?>"
                                                <?= $m['meeting_type'] === $val ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-select">
                                        <?php foreach ($statusLabels as $val => $label): ?>
                                            <option value="<?= e($val) ?>"
                                                <?= $m['status'] === $val ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" name="meeting_date" class="form-input"
                                           value="<?= e($m['meeting_date']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" name="start_time" class="form-input"
                                           value="<?= e(substr($m['start_time'] ?? '', 0, 5)) ?>">
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Location</label>
                                    <input type="text" name="location" class="form-input"
                                           value="<?= e($m['location'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group form-group-grow">
                                    <label>Agenda Document</label>
                                    <select name="agenda_doc_id" class="form-select">
                                        <option value="">— None —</option>
                                        <?php foreach ($documents as $d): ?>
                                            <option value="<?= (int)$d['id'] ?>"
                                                <?= (int)$m['agenda_doc_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                                <?= e($d['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group form-group-grow">
                                    <label>Minutes Document</label>
                                    <select name="minutes_doc_id" class="form-select">
                                        <option value="">— None —</option>
                                        <?php foreach ($documents as $d): ?>
                                            <option value="<?= (int)$d['id'] ?>"
                                                <?= (int)$m['minutes_doc_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                                <?= e($d['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="description" class="form-input" rows="2"><?= e($m['description'] ?? '') ?></textarea>
                            </div>
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
</style>
