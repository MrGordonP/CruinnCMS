<?php
/**
 * Widget: Active Discussions
 * Latest organisation discussion threads.
 * Data keys: discussions[]
 */
$discussions = $data['discussions'] ?? [];
?>
<div class="activity-header">
    <h2>Active Discussions</h2>
    <a href="/organisation/discussions/new" class="btn btn-primary btn-sm">New Thread</a>
</div>
<?php if (empty($discussions)): ?>
    <p class="text-muted">No discussions yet.</p>
<?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Topic</th>
                    <th>Posts</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discussions as $disc): ?>
                <tr>
                    <td>
                        <?php if ($disc['pinned']): ?><span class="pin-icon" title="Pinned">📌</span> <?php endif; ?>
                        <?php if ($disc['locked']): ?><span class="lock-icon" title="Locked">🔒</span> <?php endif; ?>
                        <a href="/organisation/discussions/<?= (int)$disc['id'] ?>"><?= e($disc['title']) ?></a>
                    </td>
                    <td><?= (int)$disc['post_count'] ?></td>
                    <td>
                        <?php if ($disc['last_post_at']): ?>
                            <time datetime="<?= e($disc['last_post_at']) ?>"><?= format_date($disc['last_post_at'], 'j M H:i') ?></time>
                        <?php else: ?>
                            <span class="text-muted">No posts</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
