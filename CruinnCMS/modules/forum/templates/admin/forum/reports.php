<div class="admin-page">
    <header class="admin-page-header">
        <h1>Post Reports</h1>
        <nav class="tab-bar">
            <a class="tab <?= $status === 'open'     ? 'active' : '' ?>" href="<?= url('/admin/forum/reports?status=open') ?>">Open</a>
            <a class="tab <?= $status === 'reviewed' ? 'active' : '' ?>" href="<?= url('/admin/forum/reports?status=reviewed') ?>">Reviewed</a>
            <a class="tab <?= $status === 'dismissed'? 'active' : '' ?>" href="<?= url('/admin/forum/reports?status=dismissed') ?>">Dismissed</a>
            <a class="tab <?= $status === 'all'      ? 'active' : '' ?>" href="<?= url('/admin/forum/reports?status=all') ?>">All</a>
        </nav>
    </header>

    <?php if (empty($reports)): ?>
        <p>No reports found.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Reported</th>
                    <th>Thread</th>
                    <th>Reporter</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= e(format_date($report['created_at'], 'j M Y H:i')) ?></td>
                        <td><a href="<?= url('/forum/thread/' . (int)$report['thread_id'] . '#post-' . (int)$report['post_id']) ?>"><?= e($report['thread_title']) ?></a></td>
                        <td><?= e($report['reporter_name']) ?></td>
                        <td><?= e(ucfirst($report['reason'])) ?></td>
                        <td><span class="badge badge-<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span></td>
                        <td>
                            <?php if ($report['status'] === 'open'): ?>
                                <form method="post" action="<?= url('/admin/forum/report/' . (int)$report['id'] . '/review') ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="reviewed">
                                    <button class="btn btn-xs btn-primary">Mark Reviewed</button>
                                </form>
                                <form method="post" action="<?= url('/admin/forum/report/' . (int)$report['id'] . '/review') ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="dismissed">
                                    <button class="btn btn-xs btn-outline">Dismiss</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">By <?= e($report['reviewer_name'] ?? '—') ?></span>
                            <?php endif; ?>
                            <a class="btn btn-xs btn-outline" href="<?= url('/admin/forum/post/' . (int)$report['post_id'] . '/edit') ?>">Edit Post</a>
                            <form method="post" action="<?= url('/admin/forum/post/' . (int)$report['post_id'] . '/delete') ?>" class="inline-form"
                                  onsubmit="return confirm('Delete reported post?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-xs btn-danger">Delete Post</button>
                            </form>
                        </td>
                    </tr>
                    <?php if ($report['body']): ?>
                        <tr class="report-detail-row">
                            <td colspan="6"><em><?= e($report['body']) ?></em></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
