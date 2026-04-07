<div class="container">
    <h1>News</h1>

    <?php if (empty($articles)): ?>
        <p>No news articles published yet.</p>
    <?php else: ?>
        <div class="article-list">
            <?php foreach ($articles as $article): ?>
            <article class="article-card">
                <?php if (!empty($article['featured_image'])): ?>
                <a href="/news/<?= e($article['slug']) ?>" class="article-thumbnail">
                    <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
                </a>
                <?php endif; ?>
                <div class="article-card-body">
                    <h2><a href="/news/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a></h2>
                    <p class="article-meta">
                        <time datetime="<?= e($article['published_at']) ?>"><?= format_date($article['published_at']) ?></time>
                        <?php if (!empty($article['author_name'])): ?>
                            by <?= e($article['author_name']) ?>
                        <?php endif; ?>
                        <?php if (!empty($article['subject_title'])): ?>
                            <span class="article-subject"><?= e($article['subject_title']) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($article['excerpt'])): ?>
                    <p class="article-excerpt"><?= e($article['excerpt']) ?></p>
                    <?php elseif (!empty($article['body'])): ?>
                    <p class="article-excerpt"><?= truncate(strip_tags($article['body']), 250) ?></p>
                    <?php endif; ?>
                    <a href="/news/<?= e($article['slug']) ?>" class="read-more">Read more &rarr;</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="News pagination">
            <?php if ($page > 1): ?>
            <a href="/news?page=<?= $page - 1 ?>" class="btn btn-small">&larr; Newer</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="/news?page=<?= $page + 1 ?>" class="btn btn-small">Older &rarr;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
