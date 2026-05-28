<?php if (!empty($upcomingEvents)): ?>
<div class="detail-card">
    <div class="activity-header">
        <h2>Upcoming Events</h2>
        <a href="<?= url('/events') ?>" class="text-small">All events</a>
    </div>
    <ul class="event-list-compact">
        <?php foreach ($upcomingEvents as $ev): ?>
        <li>
            <a href="<?= url('/events/' . e((string) ($ev['slug'] ?? ''))) ?>"><?= e((string) ($ev['title'] ?? '')) ?></a>
            <span class="text-muted text-small"><?= !empty($ev['date_start']) ? e((string) date('j M Y', strtotime((string) $ev['date_start']))) : '' ?><?= !empty($ev['location']) ? ' &middot; ' . e((string) $ev['location']) : '' ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
