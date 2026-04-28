<section class="container unsubscribe-page">
    <div class="unsubscribe-card">
        <?php if ($success): ?>
            <div class="unsubscribe-icon unsubscribe-icon--ok">✓</div>
            <h1>Unsubscribed</h1>
            <p><?= e($message) ?></p>
            <?php if (isset($listName)): ?>
                <p>You will no longer receive emails from the <strong><?= e($listName) ?></strong> list.</p>
            <?php endif; ?>
            <p>
                <a href="<?= url('/mailing-lists') ?>" class="btn btn-outline">Manage all subscriptions</a>
            </p>
        <?php else: ?>
            <div class="unsubscribe-icon unsubscribe-icon--err">✗</div>
            <h1>Could not unsubscribe</h1>
            <p><?= e($message) ?></p>
            <p>
                <a href="<?= url('/mailing-lists') ?>" class="btn btn-outline">Manage subscriptions</a>
            </p>
        <?php endif; ?>
    </div>
</section>
