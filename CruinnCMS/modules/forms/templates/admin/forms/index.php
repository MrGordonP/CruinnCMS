<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-forms.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout no-detail" id="forms-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header">
        <h3>Forms</h3>
        <a href="<?= url('/admin/forms/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item active" href="<?= url('/admin/forms') ?>">All Forms</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Forms</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/forms/new') ?>" class="btn btn-small btn-primary">+ New Form</a>
        </div>
    </div>
    <div class="pl-main-scroll">

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

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
