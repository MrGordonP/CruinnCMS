<div class="container">
    <article class="event-detail">
        <header class="event-header">
            <p class="event-type-badge"><?= e(ucfirst($event['event_type'])) ?></p>
            <h1><?= e($event['title']) ?></h1>
            <div class="event-meta">
                <time datetime="<?= e($event['date_start']) ?>">
                    <?= format_date($event['date_start'], 'l, j F Y') ?>
                    <?php if (!empty($event['date_end']) && $event['date_end'] !== $event['date_start']): ?>
                        &ndash; <?= format_date($event['date_end'], 'l, j F Y') ?>
                    <?php endif; ?>
                </time>
                <?php if (!empty($event['location'])): ?>
                <p class="event-location"><?= e($event['location']) ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="event-body">
            <?= $event['description'] ?? '' ?>
        </div>

        <aside class="event-sidebar">
            <?php if ($event['price'] > 0): ?>
            <div class="event-price-box">
                <strong>Fee:</strong> &euro;<?= number_format($event['price'], 2) ?>
            </div>
            <?php else: ?>
            <div class="event-price-box free">
                <strong>Free event</strong>
            </div>
            <?php endif; ?>

            <?php if ($event['capacity'] > 0): ?>
            <div class="event-capacity">
                <strong>Capacity:</strong> <?= (int)$event['capacity'] ?> places
                <?php if ($spotsRemaining !== null): ?>
                <br><span class="spots-remaining"><?= (int)$spotsRemaining ?> remaining</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($event['reg_deadline'])): ?>
            <div class="event-deadline">
                <strong>Register by:</strong> <?= format_date($event['reg_deadline'], 'j F Y') ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($userRegistered)): ?>
            <div class="event-registered-notice">
                <strong>&#10003; You are registered</strong>
            </div>
            <?php elseif (!empty($canRegister)): ?>
            <a href="/events/<?= e($event['slug']) ?>/register" class="btn btn-primary btn-block">Register</a>
            <?php elseif ($spotsRemaining !== null && $spotsRemaining <= 0): ?>
            <p class="event-full">This event is fully booked.</p>
            <?php else: ?>
            <p class="event-closed">Registration is closed.</p>
            <?php endif; ?>
        </aside>

        <?php include __DIR__ . '/../../components/share.php'; ?>
    </article>

    <p><a href="/events">&larr; Back to events</a></p>
</div>
