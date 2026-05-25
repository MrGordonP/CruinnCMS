<?php $eventBasePath = trim((string) ($event_base_path ?? '')); ?>

<div class="container">
    <h1>Events</h1>

    <?php if ($eventBasePath !== ''): ?>
    <div class="event-filter">
        <a href="<?= e($eventBasePath) ?>" class="btn btn-small <?= ($filter ?? 'upcoming') === 'upcoming' ? 'btn-primary' : 'btn-outline' ?>">Upcoming</a>
        <a href="<?= e($eventBasePath . '?show=past') ?>" class="btn btn-small <?= ($filter ?? 'upcoming') === 'past' ? 'btn-primary' : 'btn-outline' ?>">Past Events</a>
    </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <p>No <?= e($filter ?? 'upcoming') ?> events at the moment. Check back soon!</p>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($events as $event): ?>
            <article class="event-card" id="event-<?= (int) ($event['id'] ?? 0) ?>">
                <div class="event-card-date">
                    <time datetime="<?= e($event['date_start']) ?>">
                        <span class="event-day"><?= format_date($event['date_start'], 'j') ?></span>
                        <span class="event-month"><?= format_date($event['date_start'], 'M') ?></span>
                        <span class="event-year"><?= format_date($event['date_start'], 'Y') ?></span>
                    </time>
                </div>
                <div class="event-card-body">
                    <h2>
                        <?php if (!empty($event['public_url'])): ?>
                        <a href="<?= e($event['public_url']) ?>"><?= e($event['title']) ?></a>
                        <?php else: ?>
                        <?= e($event['title']) ?>
                        <?php endif; ?>
                    </h2>
                    <p class="event-type"><?= e(ucfirst($event['event_type'])) ?></p>
                    <?php if (!empty($event['location'])): ?>
                    <p class="event-location"><?= e($event['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($event['preview_text'])): ?>
                    <p class="event-excerpt"><?= e($event['preview_text']) ?></p>
                    <?php endif; ?>
                    <?php if ($event['price'] > 0): ?>
                    <p class="event-price">&euro;<?= number_format($event['price'], 2) ?></p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if (($totalPages ?? 1) > 1 && $eventBasePath !== ''): ?>
        <nav class="pagination event-pagination" aria-label="Events pagination">
            <?php $prevParams = ['page' => max(1, (int) ($page ?? 1) - 1)]; ?>
            <?php $nextParams = ['page' => (int) ($page ?? 1) + 1]; ?>
            <?php if (($filter ?? 'upcoming') === 'past') { $prevParams['show'] = 'past'; $nextParams['show'] = 'past'; } ?>
            <?php if (($page ?? 1) > 1): ?>
            <a href="<?= e($eventBasePath . '?' . http_build_query($prevParams)) ?>" class="btn btn-small">&larr; Newer</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= (int) ($page ?? 1) ?> of <?= (int) $totalPages ?></span>
            <?php if (($page ?? 1) < $totalPages): ?>
            <a href="<?= e($eventBasePath . '?' . http_build_query($nextParams)) ?>" class="btn btn-small">Older &rarr;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
