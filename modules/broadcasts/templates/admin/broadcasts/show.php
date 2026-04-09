<div class="admin-page broadcasts-show-page">
    <div class="admin-page-header">
        <div>
            <h1><?= e($broadcast['subject']) ?></h1>
            <?php $statusMap = ['draft'=>'badge-secondary','queued'=>'badge-warning','sending'=>'badge-info','sent'=>'badge-success','failed'=>'badge-danger']; ?>
            <span class="badge <?= $statusMap[$broadcast['status']] ?? 'badge-secondary' ?>"><?= e(ucfirst($broadcast['status'])) ?></span>
        </div>
        <div class="admin-page-actions">
            <?php if ($broadcast['status'] === 'draft'): ?>
                <a href="<?= url('/admin/broadcasts/' . $broadcast['id'] . '/edit') ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
            <?php if (in_array($broadcast['status'], ['queued', 'sending'], true)): ?>
                <form method="post" action="<?= url('/admin/broadcasts/' . $broadcast['id'] . '/cancel') ?>"
                      onsubmit="return confirm('Cancel this broadcast and move pending emails back to draft?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-danger-outline">Cancel Send</button>
                </form>
            <?php endif; ?>
            <?php if ($broadcast['status'] !== 'sending'): ?>
                <form method="post" action="<?= url('/admin/broadcasts/' . $broadcast['id'] . '/delete') ?>"
                      onsubmit="return confirm('Permanently delete this broadcast?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-danger-outline">Delete</button>
                </form>
            <?php endif; ?>
            <a href="<?= url('/admin/broadcasts') ?>" class="btn btn-outline">← Broadcasts</a>
        </div>
    </div>

    <div class="broadcast-meta">
        <dl class="meta-list">
            <dt>List</dt>       <dd><?= e($broadcast['list_name'] ?? 'None assigned') ?></dd>
            <dt>Recipients</dt> <dd><?= number_format((int)$broadcast['recipient_count']) ?></dd>
            <dt>Sent</dt>       <dd><?= number_format((int)$broadcast['sent_count']) ?></dd>
            <dt>Created</dt>    <dd><?= e(date('d M Y H:i', strtotime($broadcast['created_at']))) ?></dd>
            <?php if ($broadcast['started_at']): ?>
                <dt>Started</dt> <dd><?= e(date('d M Y H:i', strtotime($broadcast['started_at']))) ?></dd>
            <?php endif; ?>
            <?php if ($broadcast['completed_at']): ?>
                <dt>Completed</dt> <dd><?= e(date('d M Y H:i', strtotime($broadcast['completed_at']))) ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <?php if ($stats && (int)$stats['total'] > 0): ?>
        <div class="broadcast-stats">
            <h2>Delivery Statistics</h2>
            <div class="stats-row">
                <div class="stat-box stat-success"><span class="stat-num"><?= (int)$stats['sent'] ?></span><span class="stat-label">Sent</span></div>
                <div class="stat-box stat-pending"><span class="stat-num"><?= (int)$stats['pending'] ?></span><span class="stat-label">Pending</span></div>
                <div class="stat-box stat-danger"><span class="stat-num"><?= (int)$stats['failed'] ?></span><span class="stat-label">Failed</span></div>
                <div class="stat-box stat-muted"><span class="stat-num"><?= (int)$stats['skipped'] ?></span><span class="stat-label">Skipped</span></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="broadcast-preview">
        <h2>Email Preview</h2>
        <div class="email-preview-box">
            <?php if (!empty($broadcast['body_html'])): ?>
                <iframe srcdoc="<?= htmlspecialchars($broadcast['body_html'], ENT_QUOTES | ENT_HTML5) ?>"
                        style="width:100%;min-height:400px;border:1px solid var(--color-border);border-radius:4px"
                        sandbox="allow-same-origin"></iframe>
            <?php else: ?>
                <pre class="broadcast-body-text"><?= e($broadcast['body_text']) ?></pre>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($broadcast['status'] === 'draft'): ?>
        <div class="broadcast-queue-section">
            <h2>Ready to send?</h2>
            <p>Queue this broadcast to all active subscribers of <strong><?= e($broadcast['list_name'] ?? 'the selected list') ?></strong>.</p>
            <form method="post" action="<?= url('/admin/broadcasts/' . $broadcast['id'] . '/queue') ?>"
                  onsubmit="return confirm('Queue this broadcast for sending?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success">Queue for Sending</button>
            </form>
        </div>
    <?php endif; ?>
</div>
