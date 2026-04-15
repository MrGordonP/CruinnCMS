<section class="container forum-page">
    <header class="forum-header forum-header-row">
        <div>
            <nav class="forum-breadcrumbs">
                <a href="<?= url('/forum') ?>">Forum</a>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <span class="sep">›</span>
                    <?php if ((int)$crumb['id'] === (int)$category['id']): ?>
                        <span class="current"><?= e($crumb['title']) ?></span>
                    <?php else: ?>
                        <a href="<?= url('/forum/' . $crumb['slug']) ?>"><?= e($crumb['title']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <h1><?= e($category['title']) ?></h1>
            <?php if (!empty($category['description'])): ?>
                <p class="results-count"><?= e($category['description']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($canPost): ?>
            <a href="<?= url('/forum/' . $category['slug'] . '/new') ?>" class="btn btn-primary">New Thread</a>
        <?php endif; ?>
    </header>

    <?php if (!empty($subcategories)): ?>
        <div class="forum-subcategories">
            <h2 class="forum-section-title">Sub-forums</h2>
            <?php foreach ($subcategories as $sub): ?>
                <article class="forum-category-card forum-subcategory-card">
                    <h3><a href="<?= url('/forum/' . $sub['slug']) ?>"><?= e($sub['title']) ?></a></h3>
                    <p class="forum-stats">
                        <?php if (!empty($sub['subcategory_count'])): ?>
                            Sub-forums: <?= (int)$sub['subcategory_count'] ?> •
                        <?php endif; ?>
                        Threads: <?= (int)$sub['thread_count'] ?>
                        • Posts: <?= (int)$sub['post_count'] ?>
                        <?php if (!empty($sub['last_post_at'])): ?>
                            • Last post: <?= e(format_date($sub['last_post_at'], 'j M Y H:i')) ?>
                        <?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($threads)): ?>
        <div class="forum-threads">
            <?php if (!empty($subcategories)): ?>
                <h2 class="forum-section-title">Threads</h2>
            <?php endif; ?>
            <?php foreach ($threads as $thread): ?>
                <article class="forum-thread-row<?= !empty($thread['is_pinned']) ? ' is-pinned' : '' ?>">
                    <div>
                        <h2>
                            <a href="<?= url('/forum/thread/' . (int)$thread['id']) ?>"><?= e($thread['title']) ?></a>
                        </h2>
                        <p class="forum-meta">
                            Started by <?= e($thread['author_name']) ?>
                            • <?= e(format_date($thread['created_at'], 'j M Y H:i')) ?>
                            <?php if (!empty($thread['is_locked'])): ?>
                                • Locked
                            <?php endif; ?>
                            <?php if (!empty($thread['is_pinned'])): ?>
                                • Pinned
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="forum-thread-stats">
                        <span>Replies: <?= (int)$thread['reply_count'] ?></span>
                        <span>Last: <?= e(format_date($thread['last_post_at'], 'j M Y H:i')) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($subcategories)): ?>
        <p>No threads yet.</p>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/' . $category['slug'] . '?page=' . ($page - 1)) ?>">Previous</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/' . $category['slug'] . '?page=' . ($page + 1)) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
