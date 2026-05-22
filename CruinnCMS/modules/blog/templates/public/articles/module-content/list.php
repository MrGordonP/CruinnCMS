<?php \Cruinn\Template::requireCss('blog.css'); ?>

<?php $blogBasePath = $blog_base_path ?? '/blog'; ?>

<section class="blog-list" aria-label="Blog posts">
<?php if (empty($articles)): ?>
    <div class="blog-empty-state">
        <p>No blog posts published yet.</p>
    </div>
<?php else: ?>
    <div class="blog-list-grid">
        <?php foreach ($articles as $article): ?>
        <article class="blog-card" id="blog-post-<?= (int) ($article['id'] ?? 0) ?>">
            <?php if (!empty($article['featured_image'])): ?>
            <a href="<?= e($article['public_url'] ?? ($blogBasePath . '/' . ($article['slug'] ?? ''))) ?>" class="blog-card-media">
                <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
            </a>
            <?php endif; ?>
            <div class="blog-card-body">
                <p class="blog-card-meta">
                    <time datetime="<?= e($article['published_at']) ?>"><?= format_date($article['published_at']) ?></time>
                    <?php if (!empty($article['author_name'])): ?>
                        <span><?= e($article['author_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($article['subject_title'])): ?>
                        <span class="blog-card-subject"><?= e($article['subject_title']) ?></span>
                    <?php endif; ?>
                </p>
                <h2 class="blog-card-title"><a href="<?= e($article['public_url'] ?? ($blogBasePath . '/' . ($article['slug'] ?? ''))) ?>"><?= e($article['title']) ?></a></h2>
                <?php if (!empty($article['preview_text'])): ?>
                <p class="blog-card-excerpt"><?= e($article['preview_text']) ?></p>
                <?php endif; ?>
                <p class="blog-card-action">
                    <a href="<?= e($article['public_url'] ?? ($blogBasePath . '/' . ($article['slug'] ?? ''))) ?>">Read post</a>
                </p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if (($totalPages ?? 1) > 1): ?>
    <nav class="pagination blog-pagination" aria-label="Blog pagination">
        <?php if (($page ?? 1) > 1): ?>
        <a href="<?= e($blogBasePath . '?page=' . ((int) $page - 1)) ?>" class="btn btn-small">&larr; Newer</a>
        <?php endif; ?>
        <span class="pagination-info">Page <?= (int) ($page ?? 1) ?> of <?= (int) $totalPages ?></span>
        <?php if (($page ?? 1) < $totalPages): ?>
        <a href="<?= e($blogBasePath . '?page=' . ((int) $page + 1)) ?>" class="btn btn-small">Older &rarr;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>
</section>
