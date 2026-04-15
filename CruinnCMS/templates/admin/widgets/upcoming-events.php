<?php
/**
 * Widget: Upcoming Events
 * Next events from the calendar.
 * Data keys: events[]
 */
$events = $data['events'] ?? [];
?>
<div class="activity-header">
    <h2>Upcoming Events</h2>
    <a href="/events" class="btn btn-outline btn-small">View All</a>
</div>
<?php if (empty($events)): ?>
    <p class="text-muted">No upcoming events.</p>
<?php else: ?>
    <ul class="comms-article-list">
        <?php foreach ($events as $event): ?>
        <li>
            <a href="/events/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a>
            <span class="badge badge-muted"><?= e(ucfirst($event['event_type'])) ?></span>
            <time class="text-muted"><?= format_date($event['date_start'], 'j M Y') ?></time>
            <?php if ($event['location']): ?>
                <small class="text-muted">— <?= e($event['location']) ?></small>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
