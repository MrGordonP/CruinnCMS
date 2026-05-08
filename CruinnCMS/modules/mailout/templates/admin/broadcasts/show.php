<div class="admin-page broadcasts-show-page">
    <div class="admin-page-header">
        <div>
            <h1><?= e($broadcast['subject']) ?></h1>
            <?php $statusMap = ['draft'=>'badge-secondary','queued'=>'badge-warning','sending'=>'badge-info','sent'=>'badge-success','failed'=>'badge-danger']; ?>
            <span class="badge <?= $statusMap[$broadcast['status']] ?? 'badge-secondary' ?>"><?= e(ucfirst($broadcast['status'])) ?></span>
        </div>
        <div class="admin-page-actions">
            <?php if ($broadcast['status'] === 'draft'): ?>
                <a href="<?= url('/admin/mailout/' . $broadcast['id'] . '/edit') ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
            <a href="<?= url('/admin/mailout/' . $broadcast['id'] . '/duplicate') ?>" class="btn btn-outline">Duplicate</a>
            <?php if (in_array($broadcast['status'], ['sent', 'failed'], true)): ?>
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/reopen') ?>"
                      onsubmit="return confirm('Reopen this mailout as a draft so it can be edited and resent?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Reopen as Draft</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($broadcast['status'], ['queued', 'sending'], true)): ?>
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/cancel') ?>"
                      onsubmit="return confirm('Cancel this mailout and move pending emails back to draft?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-danger-outline">Cancel Send</button>
                </form>
            <?php endif; ?>
            <?php if ($broadcast['status'] !== 'sending'): ?>
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/delete') ?>"
                      onsubmit="return confirm('Permanently delete this mailout?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-danger-outline">Delete</button>
                </form>
            <?php endif; ?>
            <a href="<?= url('/admin/mailout') ?>" class="btn btn-outline">← Mailout</a>
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

    <?php if (!empty($recipients)): ?>
        <div class="broadcast-recipients">
            <h2>Recipients (<?= count($recipients) ?>)</h2>
            <table class="admin-table" style="font-size:0.875rem;">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Sent At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipients as $r): ?>
                        <tr>
                            <td><?= e($r['recipient_email']) ?></td>
                            <td><?= e($r['recipient_name'] ?: '—') ?></td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'sent'    => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed'  => 'badge-danger',
                                    'skipped' => 'badge-secondary'
                                ];
                                $badge = $statusBadges[$r['status']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span>
                                <?php if ($r['status'] === 'failed' && !empty($r['error'])): ?>
                                    <span class="text-muted" style="font-size:0.75rem;" title="<?= e($r['error']) ?>">⚠</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['sent_at'] ? e(date('d M Y H:i', strtotime($r['sent_at']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    <?php if (in_array($broadcast['status'], ['draft', 'queued'], true)): ?>
        <div class="broadcast-queue-section">
            <h2>Send</h2>
            <?php if ($broadcast['status'] === 'queued'): ?>
                <p class="text-muted" style="font-size:0.875rem;">This mailout is queued. You can send it immediately or leave it for the queue processor.</p>
            <?php endif; ?>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/send-now') ?>"
                      onsubmit="return confirm('Send this mailout now to all recipients?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Send Now</button>
                </form>
                <?php if ($broadcast['status'] === 'draft'): ?>
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/queue') ?>"
                      onsubmit="return confirm('Queue this mailout for sending?')"
                      style="display:flex; gap:0.5rem; align-items:flex-end; flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <div>
                        <label for="scheduled_at" style="font-size:0.8rem; display:block; margin-bottom:0.2rem;">Schedule for (optional)</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                               style="padding:0.35rem 0.5rem; border:1px solid var(--color-border,#ccc); border-radius:4px;">
                    </div>
                    <button type="submit" class="btn btn-outline">Queue for Sending</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
