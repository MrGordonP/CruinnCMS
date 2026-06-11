<?php /** @var string $title */ /** @var int $unreadCount */ /** @var array $notifications */ /** @var string $primary_url */ ?>
<div class="activity-header"><h2><?= e($title ?? 'My Notifications') ?></h2></div>
<p class="text-muted">Unread: <?= (int) ($unreadCount ?? 0) ?></p>
<?php if (empty($notifications)): ?>
    <p class="text-muted">No notifications.</p>
<?php else: ?>
    <ul class="text-small" style="margin:0; padding-left:1rem;">
        <?php foreach ($notifications as $n): ?>
            <li><?= e((string) ($n['title'] ?? 'Notification')) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p style="margin-top:.75rem;"><a href="<?= e($primary_url ?? '/admin/notifications') ?>" class="btn btn-outline btn-small">Open inbox</a></p>
