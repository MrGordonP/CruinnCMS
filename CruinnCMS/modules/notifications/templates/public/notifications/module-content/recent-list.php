<?php /** @var string $title */ /** @var array $notifications */ /** @var string $basePath */ ?>
<section class="module-notifications-recent">
    <header class="module-notifications-header">
        <h2><?= e($title ?? 'Recent Notifications') ?></h2>
        <a href="<?= url($basePath ?? '/notifications') ?>" class="btn btn-outline btn-small">Inbox</a>
    </header>

    <?php if (empty($notifications)): ?>
        <p class="text-muted">No notification activity.</p>
    <?php else: ?>
        <ul class="module-notifications-list">
            <?php foreach ($notifications as $n): ?>
                <li>
                    <?php if (!empty($n['url'])): ?>
                        <a href="<?= url((string) $n['url']) ?>"><?= e((string) ($n['title'] ?? 'Notification')) ?></a>
                    <?php else: ?>
                        <span><?= e((string) ($n['title'] ?? 'Notification')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
