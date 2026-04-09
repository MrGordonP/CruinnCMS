<div class="container layout-sidebar-right">
    <div class="page-grid">
    <article class="article-detail">
        <header>
            <h1><?= e($article['title']) ?></h1>
            <p class="article-meta">
                <time datetime="<?= e($article['published_at']) ?>"><?= format_date($article['published_at'], 'l, j F Y') ?></time>
                <?php if (!empty($article['author_name'])): ?>
                    by <?= e($article['author_name']) ?>
                <?php endif; ?>
                <?php if (!empty($article['subject_title'])): ?>
                    <span class="article-subject"><?= e($article['subject_title']) ?></span>
                <?php endif; ?>
            </p>
            <?php if (!empty($article['featured_image'])): ?>
            <div class="article-featured-image">
                <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title']) ?>">
            </div>
            <?php endif; ?>
        </header>
        <div class="article-body">
            <?php if (!empty($blocks)): ?>
                <?php foreach ($blocks as $block): ?>
                    <?php include __DIR__ . '/../../components/block.php'; ?>
                <?php endforeach; ?>
            <?php elseif (!empty($article['body'])): ?>
                <?= $article['body'] ?>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../../components/share.php'; ?>
    </article>
    <?php include __DIR__ . '/sidebar.php'; ?>
    </div>
    <p><a href="/news">&larr; Back to news</a></p>
</div>
