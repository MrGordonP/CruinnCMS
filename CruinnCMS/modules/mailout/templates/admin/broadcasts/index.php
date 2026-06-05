<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout no-detail" id="mailout-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header">
        <h3>Mailout</h3>
        <a href="<?= url('/admin/mailout/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item active" href="<?= url('/admin/mailout') ?>">Broadcasts</a>
        <a class="pl-nav-item" href="<?= url('/admin/mailout/lists') ?>">Mailing Lists</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Mailout</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/mailout/new') ?>" class="btn btn-small btn-primary">+ New Mailout</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <?php if (empty($broadcasts)): ?>
        <p class="text-muted">No mailouts yet. <a href="<?= url('/admin/mailout/new') ?>">Create your first mailout.</a></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>List</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Sent</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($broadcasts as $b): ?>
                    <tr>
                        <td><a href="<?= url('/admin/mailout/' . $b['id']) ?>"><?= e($b['subject']) ?></a></td>
                        <td><?= e($b['list_name'] ?? '—') ?></td>
                        <td>
                            <?php $statusMap = ['draft'=>'badge-secondary','queued'=>'badge-warning','sending'=>'badge-info','sent'=>'badge-success','failed'=>'badge-danger']; ?>
                            <span class="badge <?= $statusMap[$b['status']] ?? 'badge-secondary' ?>"><?= e(ucfirst($b['status'])) ?></span>
                        </td>
                        <td class="text-right"><?= number_format((int)$b['recipient_count']) ?></td>
                        <td class="text-right"><?= number_format((int)$b['sent_count']) ?></td>
                        <td><?= e(date('d M Y', strtotime($b['created_at']))) ?></td>
                        <td class="actions">
                            <?php if ($b['status'] === 'draft'): ?>
                                <a href="<?= url('/admin/mailout/' . $b['id'] . '/edit') ?>" class="btn btn-outline btn-tiny">Edit</a>
                            <?php else: ?>
                                <a href="<?= url('/admin/mailout/' . $b['id']) ?>" class="btn btn-outline btn-tiny">View</a>
                            <?php endif; ?>
                            <a href="<?= url('/admin/mailout/' . $b['id'] . '/duplicate') ?>" class="btn btn-outline btn-tiny" title="Create a new draft from this mailout">Use as Template</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
