<?php $forumBasePath = trim((string) ($forum_base_path ?? '')); ?>
<?php $forumSearchUrl = trim((string) ($forum_search_url ?? ($forumBasePath !== '' ? rtrim($forumBasePath, '/') . '/search' : ''))); ?>

<section class="container forum-page">
    <header class="forum-header forum-header-row">
        <h1>Forum</h1>
        <?php if ($forumSearchUrl !== ''): ?>
        <a class="btn btn-outline btn-small" href="<?= e($forumSearchUrl) ?>">&#128269; Search</a>
        <?php endif; ?>
    </header>

    <?php if (empty($categories)): ?>
        <p>No forum categories are available yet.</p>
    <?php else: ?>
        <div class="forum-index-phpbb">
            <?php foreach ($categories as $category): ?>
                <div class="forum-category-section">
                    <div class="forum-category-header">
                        <h2>
                            <a href="<?= e(rtrim($forumBasePath, '/') . '/' . ltrim((string) ($category['slug'] ?? ''), '/')) ?>">
                                <?= e($category['title']) ?>
                            </a>
                        </h2>
                        <?php if (!empty($category['description'])): ?>
                            <p class="forum-category-desc"><?= e($category['description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="forum-category-forums">
                        <?php if (empty($category['children'])): ?>
                            <!-- No sub-forums, show the category itself as a forum -->
                            <div class="forum-row">
                                <div class="forum-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <div class="forum-info">
                                    <h3 class="forum-title">
                                        <a href="<?= e(rtrim($forumBasePath, '/') . '/' . ltrim((string) ($category['slug'] ?? ''), '/')) ?>">
                                            <?= e($category['title']) ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="forum-desc"><?= e($category['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="forum-stats">
                                    <span class="forum-stat-item">
                                        <strong><?= (int)$category['thread_count'] ?></strong> Topics
                                    </span>
                                    <span class="forum-stat-item">
                                        <strong><?= (int)$category['post_count'] ?></strong> Posts
                                    </span>
                                </div>
                                <div class="forum-last-post">
                                    <?php if (!empty($category['last_post_at'])): ?>
                                        <div class="last-post-info">
                                            <?php if (!empty($category['last_thread_title'])): ?>
                                                <a href="<?= e(rtrim($forumBasePath, '/') . '/thread/' . (int) $category['last_thread_id']) ?>" class="last-post-thread">
                                                    <?= e($category['last_thread_title']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <span class="last-post-meta">
                                                <?= e(format_date($category['last_post_at'], 'j M Y, H:i')) ?>
                                                <?php if (!empty($category['last_post_user_name'])): ?>
                                                    <br>by <?= e($category['last_post_user_name']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-posts">No posts yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Has sub-forums -->
                            <?php foreach ($category['children'] as $subforum): ?>
                                <div class="forum-row">
                                    <div class="forum-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="forum-info">
                                        <h3 class="forum-title">
                                            <a href="<?= e(rtrim($forumBasePath, '/') . '/' . ltrim((string) ($subforum['slug'] ?? ''), '/')) ?>">
                                                <?= e($subforum['title']) ?>
                                            </a>
                                        </h3>
                                        <?php if (!empty($subforum['description'])): ?>
                                            <p class="forum-desc"><?= e($subforum['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="forum-stats">
                                        <span class="forum-stat-item">
                                            <strong><?= (int)$subforum['thread_count'] ?></strong> Topics
                                        </span>
                                        <span class="forum-stat-item">
                                            <strong><?= (int)$subforum['post_count'] ?></strong> Posts
                                        </span>
                                    </div>
                                    <div class="forum-last-post">
                                        <?php if (!empty($subforum['last_post_at'])): ?>
                                            <div class="last-post-info">
                                                <?php if (!empty($subforum['last_thread_title'])): ?>
                                                    <a href="<?= e(rtrim($forumBasePath, '/') . '/thread/' . (int) $subforum['last_thread_id']) ?>" class="last-post-thread">
                                                        <?= e($subforum['last_thread_title']) ?>
                                                    </a>
                                                <?php endif; ?>
                                                <span class="last-post-meta">
                                                    <?= e(format_date($subforum['last_post_at'], 'j M Y, H:i')) ?>
                                                    <?php if (!empty($subforum['last_post_user_name'])): ?>
                                                        <br>by <?= e($subforum['last_post_user_name']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-posts">No posts yet</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
