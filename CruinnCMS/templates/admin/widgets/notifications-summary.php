<?php
/**
 * Widget: Notifications Summary
 * Unread count and recent notifications.
 * Data keys: unread, notifications[]
 */
$unread = $data['unread'] ?? 0;
$notifications = $data['notifications'] ?? [];
?>
<div class="activity-header">
    <h2>Notifications <?php if ($unread): ?><span class="badge badge-warning"><?= $unread ?> unread</span><?php endif; ?></h2>
    <a href="/notifications" class="btn btn-outline btn-small">View All</a>
</div>
<?php if (empty($notifications)): ?>
    <p class="text-muted">No notifications.</p>
<?php else: ?>
    <ul class="comms-article-list">
        <?php foreach ($notifications as $n): ?>
        <li class="<?= $n['read_at'] ? '' : 'notification-unread' ?>">
            <a href="<?= e($n['url'] ?? '/notifications') ?>"><?= e($n['title']) ?></a>
            <span class="badge badge-muted"><?= e(ucfirst($n['category'])) ?></span>
            <time class="text-muted"><?= format_date($n['created_at'], 'j M H:i') ?></time>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
