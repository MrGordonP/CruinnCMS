<section class="container mailing-lists-page">
    <header class="page-header">
        <div>
            <h1>Mailing Lists</h1>
            <p class="page-subtitle">Subscribe or unsubscribe from our mailing lists. You can change your preferences at any time.</p>
        </div>
        <a href="<?= url('/notifications/preferences') ?>" class="btn btn-outline btn-small">Notification preferences</a>
    </header>

    <?php if (empty($lists)): ?>
        <p class="text-muted">No mailing lists are currently available.</p>
    <?php else: ?>
        <div class="mailing-lists-grid">
            <?php foreach ($lists as $list): ?>
                <?php $isSubscribed = !empty($list['subscribed']); ?>
                <div class="mailing-list-card <?= $isSubscribed ? 'is-subscribed' : '' ?>">
                    <div class="mailing-list-info">
                        <strong class="mailing-list-name"><?= e($list['name']) ?></strong>
                        <?php if (!empty($list['description'])): ?>
                            <p class="mailing-list-desc"><?= e($list['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mailing-list-action">
                        <?php if ($isSubscribed): ?>
                            <span class="badge badge-success">Subscribed</span>
                            <form method="post" action="<?= url('/mailing-lists/' . $list['id'] . '/unsubscribe') ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-small btn-danger-outline">Unsubscribe</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= url('/mailing-lists/' . $list['id'] . '/subscribe') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary btn-small">Subscribe</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
