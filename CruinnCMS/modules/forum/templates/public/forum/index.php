<section class="container forum-page">
    <header class="forum-header forum-header-row">
        <h1>Forum</h1>
        <a class="btn btn-outline btn-small" href="<?= url('/forum/search') ?>">&#128269; Search</a>
    </header>

    <?php if (empty($categories)): ?>
        <p>No forum categories are available yet.</p>
    <?php else: ?>
        <div class="forum-index">
            <?php foreach ($categories as $category): ?>
                <a class="forum-index-row" href="<?= url('/forum/' . $category['slug']) ?>">
                    <div class="forum-index-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="forum-index-main">
                        <span class="forum-index-title"><?= e($category['title']) ?></span>
                        <?php if (!empty($category['description'])): ?>
                            <span class="forum-index-desc"><?= e($category['description']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($category['subcategory_count'])): ?>
                            <span class="forum-index-sub"><?= (int)$category['subcategory_count'] ?> sub-forum<?= $category['subcategory_count'] != 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="forum-index-stats">
                        <span class="forum-index-stat"><strong><?= (int)$category['thread_count'] ?></strong> threads</span>
                        <span class="forum-index-stat"><strong><?= (int)$category['post_count'] ?></strong> posts</span>
                    </div>
                    <?php if (!empty($category['last_post_at'])): ?>
                    <div class="forum-index-last">
                        <span class="forum-index-last-label">Last post</span>
                        <span class="forum-index-last-date"><?= e(format_date($category['last_post_at'], 'j M Y')) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="forum-index-last forum-index-last--empty">
                        <span class="forum-index-last-label">No posts yet</span>
                    </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
