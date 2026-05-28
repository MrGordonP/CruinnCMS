<?php if (!empty($notifications)): ?>
<div class="detail-card">
    <div class="activity-header">
        <h2>Notifications <?php if (!empty($unreadCount)): ?><span class="badge badge-primary"><?= (int) $unreadCount ?></span><?php endif; ?></h2>
        <a href="<?= url('/notifications') ?>" class="text-small">View all</a>
    </div>
    <ul class="notif-list">
        <?php foreach ($notifications as $n): ?>
        <li class="notif-item<?= !empty($n['read_at']) ? '' : ' notif-unread' ?>">
            <?php if (!empty($n['url'])): ?><a href="<?= e((string) $n['url']) ?>"><?= e((string) ($n['title'] ?? '')) ?></a><?php else: ?><span><?= e((string) ($n['title'] ?? '')) ?></span><?php endif; ?>
            <span class="notif-time"><?= !empty($n['created_at']) ? e((string) date('j M', strtotime((string) $n['created_at']))) : '' ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
