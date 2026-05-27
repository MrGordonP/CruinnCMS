<section class="subject-page container">
    <header class="subject-page-header">
        <h1><?= e($subject['title'] ?? 'Subject') ?></h1>
        <p class="subject-meta">
            <strong>Code:</strong> <?= e($subject['code'] ?? '') ?>
            <span> | </span>
            <strong>Type:</strong> <?= e(ucfirst((string) ($subject['type'] ?? 'general'))) ?>
        </p>
        <?php if (!empty($subject['description'])): ?>
            <p class="subject-description"><?= nl2br(e((string) $subject['description'])) ?></p>
        <?php endif; ?>
    </header>

    <div class="subject-page-grid">
        <section>
            <h2>Published Articles</h2>
            <?php if (empty($articles)): ?>
                <p>No published articles are linked to this subject.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($articles as $article): ?>
                        <li>
                            <?= e($article['title']) ?>
                            <?php if (!empty($article['published_at'])): ?>
                                <small>(<?= format_date($article['published_at'], 'j M Y') ?>)</small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section>
            <h2>Published Events</h2>
            <?php if (empty($events)): ?>
                <p>No published events are linked to this subject.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($events as $event): ?>
                        <li>
                            <?= e($event['title']) ?>
                            <?php if (!empty($event['date_start'])): ?>
                                <small>(<?= format_date($event['date_start'], 'j M Y') ?>)</small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
