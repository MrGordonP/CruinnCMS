<?php $eventBasePath = trim((string) ($event_base_path ?? '')); ?>
<?php $returnToListUrl = trim((string) ($return_to_list_url ?? $eventBasePath)); ?>
<?php $registerUrl = trim((string) ($register_url ?? '')); ?>

<?php if (!empty($event) && is_array($event)): ?>
<article class="event-detail">
    <?php if (!empty($show_return_to_list) && $returnToListUrl !== ''): ?>
    <nav class="event-post-return event-post-return-top" aria-label="Return to events list">
        <a href="<?= e($returnToListUrl) ?>">&larr; Return to events list</a>
    </nav>
    <?php endif; ?>

    <?php if (!empty($show_event_navigation) && (!empty($previous_event) || !empty($next_event))): ?>
    <nav class="blog-post-nav blog-post-nav-top" aria-label="Event navigation">
        <?php if (!empty($previous_event)): ?>
        <a href="<?= e($previous_event['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-prev">
            <span class="blog-post-nav-label">&laquo;&laquo; Earlier event</span>
            <strong class="blog-post-nav-title"><?= e($previous_event['title'] ?? '') ?></strong>
        </a>
        <?php endif; ?>
        <?php if (!empty($next_event)): ?>
        <a href="<?= e($next_event['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-next">
            <span class="blog-post-nav-label">Later event &raquo;&raquo;</span>
            <strong class="blog-post-nav-title"><?= e($next_event['title'] ?? '') ?></strong>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <header class="event-header">
        <p class="event-type-badge"><?= e(ucfirst((string) ($event['event_type'] ?? 'event'))) ?></p>
        <h1><?= e($event['title'] ?? '') ?></h1>
        <div class="event-meta">
            <time datetime="<?= e($event['date_start'] ?? '') ?>">
                <?= format_date($event['date_start'] ?? null, 'l, j F Y') ?>
                <?php if (!empty($event['date_end']) && ($event['date_end'] ?? '') !== ($event['date_start'] ?? '')): ?>
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
        <?php if (($event['price'] ?? 0) > 0): ?>
        <div class="event-price-box">
            <strong>Fee:</strong> &euro;<?= number_format((float) $event['price'], 2) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($canRegister) && $registerUrl !== ''): ?>
        <a href="<?= e($registerUrl) ?>" class="btn btn-primary btn-block">Register</a>
        <?php endif; ?>
    </aside>
</article>
<?php else: ?>
<div class="event-empty-state">
    <p>That event could not be rendered.</p>
</div>
<?php endif; ?>
