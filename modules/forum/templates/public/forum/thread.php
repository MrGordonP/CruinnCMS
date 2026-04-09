<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="sep">›</span>
                <a href="<?= url('/forum/' . $crumb['slug']) ?>"><?= e($crumb['title']) ?></a>
            <?php endforeach; ?>
        </nav>
        <h1><?= e($thread['title']) ?></h1>
        <p class="forum-meta">
            Started by <?= e($thread['author_name']) ?> • <?= e(format_date($thread['created_at'], 'j M Y H:i')) ?>
            <?php if (!empty($thread['is_locked'])): ?> • Locked<?php endif; ?>
        </p>
    </header>

    <div class="forum-posts">
        <?php foreach ($posts as $post): ?>
            <article class="forum-post">
                <header>
                    <strong><?= e($post['author_name']) ?></strong>
                    <span class="forum-meta"> • <?= e(format_date($post['created_at'], 'j M Y H:i')) ?></span>
                </header>
                <div class="forum-post-body">
                    <?= sanitise_html($post['body_html']) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/thread/' . (int)$thread['id'] . '?page=' . ($page - 1)) ?>">Previous</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/thread/' . (int)$thread['id'] . '?page=' . ($page + 1)) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($canReply): ?>
        <section class="forum-reply-box">
            <h2>Post Reply</h2>
            <form method="post" action="<?= url('/forum/thread/' . (int)$thread['id'] . '/reply') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="body_html">Reply</label>
                    <textarea id="body_html" name="body_html" class="form-input" rows="6" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </form>
        </section>
    <?php elseif (empty($thread['is_locked'])): ?>
        <p><a href="<?= url('/login') ?>">Log in</a> with sufficient role access to reply.</p>
    <?php endif; ?>
</section>
