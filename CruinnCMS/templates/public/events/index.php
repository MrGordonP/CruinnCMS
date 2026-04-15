<div class="container">
    <h1>Events</h1>

    <div class="event-filter">
        <a href="/events?show=upcoming" class="btn btn-small <?= ($filter ?? 'upcoming') === 'upcoming' ? 'btn-primary' : 'btn-outline' ?>">Upcoming</a>
        <a href="/events?show=past" class="btn btn-small <?= ($filter ?? 'upcoming') === 'past' ? 'btn-primary' : 'btn-outline' ?>">Past Events</a>
    </div>

    <?php if (empty($events)): ?>
        <p>No <?= e($filter ?? 'upcoming') ?> events at the moment. Check back soon!</p>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($events as $event): ?>
            <article class="event-card">
                <div class="event-card-date">
                    <time datetime="<?= e($event['date_start']) ?>">
                        <span class="event-day"><?= format_date($event['date_start'], 'j') ?></span>
                        <span class="event-month"><?= format_date($event['date_start'], 'M') ?></span>
                        <span class="event-year"><?= format_date($event['date_start'], 'Y') ?></span>
                    </time>
                </div>
                <div class="event-card-body">
                    <h2><a href="/events/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a></h2>
                    <p class="event-type"><?= e(ucfirst($event['event_type'])) ?></p>
                    <?php if (!empty($event['location'])): ?>
                    <p class="event-location"><?= e($event['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($event['description'])): ?>
                    <p class="event-excerpt"><?= truncate(strip_tags($event['description']), 200) ?></p>
                    <?php endif; ?>
                    <?php if ($event['price'] > 0): ?>
                    <p class="event-price">&euro;<?= number_format($event['price'], 2) ?></p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
