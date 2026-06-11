<?php /** @var array $notifications */ /** @var int $unreadCount */ /** @var string $basePath */ ?>
<section class="module-notifications-inbox">
    <header class="module-notifications-header">
        <h2>Notifications <?php if ($unreadCount > 0): ?><span class="badge badge-primary"><?= (int) $unreadCount ?></span><?php endif; ?></h2>
        <a href="<?= url($basePath ?? '/notifications') ?>" class="btn btn-outline btn-small">View all</a>
    </header>

    <?php if (empty($notifications)): ?>
        <p class="text-muted">No notifications.</p>
    <?php else: ?>
        <ul class="module-notifications-list">
            <?php foreach ($notifications as $n): ?>
                <li>
                    <strong><?= e($n['title'] ?? '') ?></strong>
                    <?php if (!empty($n['created_at'])): ?>
                        <span class="text-muted"> • <?= e(format_date((string) $n['created_at'], 'j M Y H:i')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
