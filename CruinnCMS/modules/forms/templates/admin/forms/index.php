<?php \Cruinn\Template::requireCss('admin-forms.css'); ?>
<div class="admin-form-list">
    <div class="admin-list-header">
        <h1>Forms</h1>
        <a href="/admin/forms/new" class="btn btn-primary">+ New Form</a>
    </div>

    <?php if (empty($forms)): ?>
        <p class="admin-empty">No forms created yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Status</th>
                <th>Submissions</th>
                <th>Pending</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($forms as $form): ?>
            <?php
                $statusClass = match($form['status']) {
                    'published' => 'badge-success',
                    'closed'    => 'badge-muted',
                    default     => 'badge-warning',
                };
            ?>
            <tr>
                <td>
                    <a href="/admin/forms/<?= (int)$form['id'] ?>/edit" class="strong-link"><?= e($form['title']) ?></a>
                    <br><small class="text-muted">/forms/<?= e($form['slug']) ?></small>
                </td>
                <td><?= e(ucwords(str_replace('_', ' ', $form['form_type']))) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= e(ucfirst($form['status'])) ?></span></td>
                <td><?= (int)($form['submission_count'] ?? 0) ?></td>
                <td>
                    <?php if (($form['pending_count'] ?? 0) > 0): ?>
                        <span class="badge badge-warning"><?= (int)$form['pending_count'] ?></span>
                    <?php else: ?>
                        0
                    <?php endif; ?>
                </td>
                <td><?= format_date($form['created_at'] ?? '', 'j M Y') ?></td>
                <td>
                    <a href="/admin/forms/<?= (int)$form['id'] ?>/submissions" class="btn btn-small">Submissions</a>
                    <a href="/admin/forms/<?= (int)$form['id'] ?>/edit" class="btn btn-small">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
