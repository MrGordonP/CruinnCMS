<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="sep">›</span>
                <a href="<?= url('/forum/' . $crumb['slug']) ?>"><?= e($crumb['title']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="forum-thread-header">
            <div class="forum-thread-title-row">
                <h1><?= e($thread['title']) ?></h1>
                <div class="forum-thread-badges">
                    <?php if (!empty($thread['is_pinned'])): ?>
                        <span class="badge badge-info">Pinned</span>
                    <?php endif; ?>
                    <?php if (!empty($thread['is_locked'])): ?>
                        <span class="badge badge-warning">Locked</span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="forum-meta">
                Started by <strong><?= e($thread['author_name']) ?></strong>
                &bull; <?= e(format_date($thread['created_at'], 'j M Y H:i')) ?>
                &bull; <?= (int)$thread['reply_count'] ?> repl<?= (int)$thread['reply_count'] === 1 ? 'y' : 'ies' ?>
            </p>
        </div>

        <?php if (!empty($isAdmin)): ?>
        <div class="forum-mod-bar">
            <span class="forum-mod-bar__label">Mod</span>

            <form method="post" action="<?= url('/admin/forum/' . (int)$thread['id'] . '/pin') ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-xs <?= !empty($thread['is_pinned']) ? 'btn-active' : 'btn-outline' ?>">
                    <?= !empty($thread['is_pinned']) ? 'Unpin' : 'Pin' ?>
                </button>
            </form>

            <form method="post" action="<?= url('/admin/forum/' . (int)$thread['id'] . '/lock') ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-xs <?= !empty($thread['is_locked']) ? 'btn-active' : 'btn-outline' ?>">
                    <?= !empty($thread['is_locked']) ? 'Unlock' : 'Lock' ?>
                </button>
            </form>

            <a class="btn btn-xs btn-outline" href="<?= url('/admin/forum/' . (int)$thread['id'] . '/move') ?>">Move</a>

            <form method="post" action="<?= url('/admin/forum/' . (int)$thread['id'] . '/edit-title') ?>"
                  class="forum-mod-bar__title-form inline-form">
                <?= csrf_field() ?>
                <input type="text" name="title" value="<?= e($thread['title']) ?>" class="forum-mod-bar__title-input">
                <button type="submit" class="btn btn-xs btn-primary">Rename</button>
            </form>

            <form method="post" action="<?= url('/admin/forum/' . (int)$thread['id'] . '/delete') ?>" class="inline-form"
                  onsubmit="return confirm('Delete this entire thread and all replies?')">
                <?= csrf_field() ?>
                <button class="btn btn-xs btn-danger">Delete Thread</button>
            </form>

            <a class="btn btn-xs btn-outline" href="<?= url('/admin/forum/reports') ?>">Reports</a>
        </div>
        <?php elseif (!empty($isLoggedIn) && (int)$thread['user_id'] === (int)$currentUserId): ?>
        <div class="forum-mod-bar forum-mod-bar--op">
            <span class="forum-mod-bar__label">Your thread</span>
            <a class="btn btn-xs btn-outline" href="<?= url('/forum/thread/' . (int)$thread['id'] . '/edit-title') ?>">Rename</a>
        </div>
        <?php endif; ?>
    </header>

    <div class="forum-posts">
        <?php foreach ($posts as $post): ?>
            <article class="forum-post<?= (int)$post['is_deleted'] ? ' forum-post--deleted' : '' ?>" id="post-<?= (int)$post['id'] ?>">

                <aside class="forum-post-author">
                    <div class="forum-post-author__avatar">
                        <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
                    </div>
                    <strong class="forum-post-author__name"><?= e($post['author_name']) ?></strong>
                    <span class="forum-post-author__posts">
                        <?php $pc = (int)($authorPostCounts[$post['user_id']] ?? 0); ?>
                        <?= $pc ?> post<?= $pc !== 1 ? 's' : '' ?>
                    </span>
                </aside>

                <div class="forum-post-main">
                    <div class="forum-post-meta">
                        <a class="forum-post-permalink" href="#post-<?= (int)$post['id'] ?>"><?= e(format_date($post['created_at'], 'j M Y H:i')) ?></a>
                        <?php if ($post['edited_at']): ?>
                            <span class="forum-edited">(edited <?= e(format_date($post['edited_at'], 'j M Y H:i')) ?>)</span>
                        <?php endif; ?>
                        <span class="forum-post-number">#<?= (int)$post['id'] ?></span>
                    </div>

                    <div class="forum-post-body">
                        <?php if ((int)$post['is_deleted']): ?>
                            <em class="forum-post-deleted">This post has been removed.</em>
                        <?php else: ?>
                            <?= sanitise_html($post['body_html']) ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($isLoggedIn) && !(int)$post['is_deleted']): ?>
                    <footer class="forum-post-actions">
                        <?php if ($canReply): ?>
                            <button class="btn btn-xs btn-outline forum-quote-btn"
                                    data-body="<?= htmlspecialchars(strip_tags($post['body_html']), ENT_QUOTES) ?>"
                                    data-author="<?= e($post['author_name']) ?>">Quote</button>
                        <?php endif; ?>
                        <?php if ((int)$post['author_user_id'] === (int)$currentUserId || !empty($isAdmin)): ?>
                            <a class="btn btn-xs btn-outline" href="<?= url('/forum/post/' . (int)$post['id'] . '/edit') ?>">Edit</a>
                            <form method="post" action="<?= url('/forum/post/' . (int)$post['id'] . '/delete') ?>" class="inline-form"
                                  onsubmit="return confirm('Delete this post?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((int)$post['author_user_id'] !== (int)$currentUserId): ?>
                            <a class="btn btn-xs btn-ghost" href="<?= url('/forum/post/' . (int)$post['id'] . '/report') ?>">Report</a>
                        <?php endif; ?>
                    </footer>
                    <?php endif; ?>
                </div>

            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/thread/' . (int)$thread['id'] . '?page=' . ($page - 1)) ?>">&#8592; Previous</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-outline btn-small" href="<?= url('/forum/thread/' . (int)$thread['id'] . '?page=' . ($page + 1)) ?>">Next &#8594;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($canReply): ?>
        <section class="forum-reply-box" id="reply-form">
            <h2>Post Reply</h2>
            <form method="post" action="<?= url('/forum/thread/' . (int)$thread['id'] . '/reply') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <textarea id="body_html" name="body_html" class="form-input" rows="6" required
                              placeholder="Write your reply…"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </form>
        </section>
        <script>
        document.querySelectorAll('.forum-quote-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var author = btn.dataset.author;
                var body   = btn.dataset.body;
                var ta     = document.getElementById('body_html');
                ta.value   = '[quote=' + author + ']\n' + body + '\n[/quote]\n\n';
                ta.focus();
                document.getElementById('reply-form').scrollIntoView({behavior: 'smooth'});
            });
        });
        </script>
    <?php elseif (!empty($thread['is_locked'])): ?>
        <p class="forum-locked-notice">&#128274; This thread is locked.</p>
    <?php elseif (!empty($isLoggedIn)): ?>
        <p class="forum-access-notice">You do not have permission to reply in this category.</p>
    <?php else: ?>
        <p class="forum-access-notice"><a href="<?= url('/login') ?>">Log in</a> to reply.</p>
    <?php endif; ?>
</section>

    <div class="forum-posts">
        <?php foreach ($posts as $post): ?>
            <article class="forum-post<?= (int)$post['is_deleted'] ? ' forum-post--deleted' : '' ?>" id="post-<?= (int)$post['id'] ?>">
                <header>
                    <strong><?= e($post['author_name']) ?></strong>
                    <span class="forum-meta"> • <?= e(format_date($post['created_at'], 'j M Y H:i')) ?></span>
                    <?php if ($post['edited_at']): ?>
                        <span class="forum-meta forum-edited"> • edited <?= e(format_date($post['edited_at'], 'j M Y H:i')) ?></span>
                    <?php endif; ?>
                </header>
                <div class="forum-post-body">
                    <?php if ((int)$post['is_deleted']): ?>
                        <em class="forum-post-deleted">This post has been removed.</em>
                    <?php else: ?>
                        <?= sanitise_html($post['body_html']) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($isLoggedIn) && !(int)$post['is_deleted']): ?>
                    <footer class="forum-post-actions">
                        <?php if ($canReply): ?>
                            <button class="btn btn-xs btn-outline forum-quote-btn"
                                    data-body="<?= htmlspecialchars(strip_tags($post['body_html']), ENT_QUOTES) ?>"
                                    data-author="<?= e($post['author_name']) ?>">Quote</button>
                        <?php endif; ?>
                        <?php if ((int)$post['author_user_id'] === (int)$currentUserId || !empty($isAdmin)): ?>
                            <a class="btn btn-xs btn-outline" href="<?= url('/forum/post/' . (int)$post['id'] . '/edit') ?>">Edit</a>
                            <form method="post" action="<?= url('/forum/post/' . (int)$post['id'] . '/delete') ?>" class="inline-form"
                                  onsubmit="return confirm('Delete this post?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((int)$post['author_user_id'] !== (int)$currentUserId): ?>
                            <a class="btn btn-xs btn-outline" href="<?= url('/forum/post/' . (int)$post['id'] . '/report') ?>">Report</a>
                        <?php endif; ?>
                        <?php if (!empty($isAdmin)): ?>
                            <a class="btn btn-xs btn-outline" href="<?= url('/admin/forum/post/' . (int)$post['id'] . '/edit') ?>">Mod Edit</a>
                            <form method="post" action="<?= url('/admin/forum/post/' . (int)$post['id'] . '/delete') ?>" class="inline-form"
                                  onsubmit="return confirm('Delete this post as moderator?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-xs btn-danger">Mod Delete</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                <?php endif; ?>
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
            <form method="post" action="<?= url('/forum/thread/' . (int)$thread['id'] . '/reply') ?>" id="reply-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="body_html">Reply</label>
                    <textarea id="body_html" name="body_html" class="form-input" rows="6" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </form>
        </section>
        <script>
        document.querySelectorAll('.forum-quote-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var author = btn.dataset.author;
                var body   = btn.dataset.body;
                var ta     = document.getElementById('body_html');
                ta.value   = '[quote=' + author + ']\n' + body + '\n[/quote]\n\n';
                ta.focus();
                ta.scrollIntoView({behavior: 'smooth'});
            });
        });
        </script>
    <?php elseif (empty($thread['is_locked'])): ?>
        <p><a href="<?= url('/login') ?>">Log in</a> with sufficient role access to reply.</p>
    <?php endif; ?>
</section>
