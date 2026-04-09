<?php \IGA\Template::requireCss('admin-forms.css'); ?>
<div class="admin-submission-detail">
    <h1>Submission #<?= (int)$submission['id'] ?></h1>

    <div class="submission-meta">
        <table class="admin-table admin-table-meta">
            <tr>
                <th>Form</th>
                <td><a href="/admin/forms/<?= (int)$form['id'] ?>/edit"><?= e($form['title']) ?></a></td>
            </tr>
            <tr>
                <th>Submitted</th>
                <td><?= format_date($submission['submitted_at'], 'j M Y H:i') ?></td>
            </tr>
            <tr>
                <th>User</th>
                <td><?= e($submission['user_name'] ?? $submission['user_email'] ?? 'Guest') ?></td>
            </tr>
            <tr>
                <th>IP Address</th>
                <td><?= e($submission['ip_address'] ?? '—') ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php
                        $statusClass = match($submission['status']) {
                            'approved', 'processed' => 'badge-success',
                            'rejected'              => 'badge-danger',
                            default                 => 'badge-warning',
                        };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= e(ucfirst($submission['status'])) ?></span>
                </td>
            </tr>
            <?php if ($submission['reviewer_name']): ?>
            <tr>
                <th>Reviewed By</th>
                <td><?= e($submission['reviewer_name']) ?> at <?= format_date($submission['reviewed_at'], 'j M Y H:i') ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($submission['reviewer_notes']): ?>
            <tr>
                <th>Reviewer Notes</th>
                <td><?= e($submission['reviewer_notes']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- ── Submission Data ── -->
    <h2>Submitted Data</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:30%;">Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($form['fields'] as $field): ?>
                <?php if (in_array($field['field_type'], ['heading', 'paragraph'])) continue; ?>
                <tr>
                    <td><strong><?= e($field['label']) ?></strong></td>
                    <td>
                        <?php
                            $val = $submission['data'][$field['name']] ?? '';
                            if (is_array($val)) {
                                echo e(implode(', ', $val));
                            } elseif (is_bool($val)) {
                                echo $val ? 'Yes' : 'No';
                            } else {
                                echo nl2br(e((string)$val));
                            }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── Approval Actions ── -->
    <?php if ($submission['status'] === 'pending'): ?>
    <hr>
    <h2>Review</h2>
    <div class="form-row">
        <div class="form-group" style="flex:1;">
            <form method="post" action="/admin/forms/<?= (int)$form['id'] ?>/submissions/<?= (int)$submission['id'] ?>/approve" id="approve-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="approve-notes">Reviewer Notes (optional)</label>
                    <textarea id="approve-notes" name="reviewer_notes" class="form-input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Approve</button>
            </form>
        </div>
        <div class="form-group" style="flex:1;">
            <form method="post" action="/admin/forms/<?= (int)$form['id'] ?>/submissions/<?= (int)$submission['id'] ?>/reject" id="reject-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="reject-notes">Reason for Rejection</label>
                    <textarea id="reject-notes" name="reviewer_notes" class="form-input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions" style="margin-top:2rem;">
        <a href="/admin/forms/<?= (int)$form['id'] ?>/submissions" class="btn">&larr; Back to Submissions</a>
    </div>
</div>
