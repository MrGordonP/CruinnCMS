<?php
/**
 * Widget: Recent Forum Threads
 * Latest forum activity.
 * Data keys: threads[]
 */
$threads = $data['threads'] ?? [];
?>
<div class="activity-header">
    <h2>Recent Forum Threads</h2>
    <a href="/forum" class="btn btn-outline btn-small">View Forum</a>
</div>
<?php if (empty($threads)): ?>
    <p class="text-muted">No forum activity yet.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Thread</th>
                <th>Category</th>
                <th>Replies</th>
                <th>Last Post</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($threads as $t): ?>
            <tr>
                <td>
                    <?php if ($t['is_pinned']): ?><span title="Pinned">📌</span> <?php endif; ?>
                    <?php if ($t['is_locked']): ?><span title="Locked">🔒</span> <?php endif; ?>
                    <a href="/forum/thread/<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                    <br><small class="text-muted">by <?= e($t['author_name'] ?? 'Unknown') ?></small>
                </td>
                <td><span class="badge badge-muted"><?= e($t['category_title']) ?></span></td>
                <td><?= (int)$t['reply_count'] ?></td>
                <td>
                    <?php if ($t['last_post_at']): ?>
                        <time datetime="<?= e($t['last_post_at']) ?>"><?= format_date($t['last_post_at'], 'j M H:i') ?></time>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
