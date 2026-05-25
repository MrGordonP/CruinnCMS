<?php $eventBasePath = trim((string) ($event_base_path ?? '')); ?>
<?php $returnToListUrl = trim((string) ($return_to_list_url ?? $eventBasePath)); ?>
<?php $registerUrl = trim((string) ($register_url ?? '')); ?>

<div class="container">
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

            <?php if (!empty($event['external_form_url'])): ?>
            <a href="<?= e($event['external_form_url']) ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Register</a>
            <?php elseif (!empty($userRegistered)): ?>
            <div class="event-registered-notice">
                <strong>&#10003; You are registered</strong>
            </div>
            <?php elseif (!empty($canRegister) && $registerUrl !== ''): ?>
            <a href="<?= e($registerUrl) ?>" class="btn btn-primary btn-block">Register</a>
            <?php elseif ($spotsRemaining !== null && $spotsRemaining <= 0): ?>
            <p class="event-full">This event is fully booked.</p>
            <?php else: ?>
            <p class="event-closed">Registration is closed.</p>
            <?php endif; ?>

            <?php if (!empty($relatedArticle)): ?>
            <div class="event-related-article">
                <strong>Related post:</strong>
                <a href="<?= e('/blog/' . ($relatedArticle['slug'] ?? '')) ?>"><?= e($relatedArticle['title'] ?? 'Read more') ?></a>
            </div>
            <?php endif; ?>
        </aside>

        <?php include __DIR__ . '/../../components/share.php'; ?>
    </article>

    <?php if (!empty($show_event_navigation) && (!empty($previous_event) || !empty($next_event))): ?>
    <nav class="blog-post-nav" aria-label="Event navigation">
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

    <?php if (!empty($show_return_to_list) && $returnToListUrl !== ''): ?>
    <p><a href="<?= e($returnToListUrl) ?>">&larr; Back to events</a></p>
    <?php endif; ?>
</div>
