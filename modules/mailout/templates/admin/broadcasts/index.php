<div class="admin-page broadcasts-page">
    <div class="admin-page-header">
        <div>
            <h1>Mailout</h1>
            <p class="admin-page-subtitle">Compose and send email campaigns to your mailing lists.</p>
        </div>
        <a href="<?= url('/admin/mailout/new') ?>" class="btn btn-primary">+ New Mailout</a>
    </div>

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
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
