<div class="council-discussion-detail">
    <div class="page-header">
        <div>
            <a href="/council/discussions" class="back-link">&larr; All Discussions</a>
            <h1>
                <?php if ($discussion['pinned']): ?><span class="pin-icon" title="Pinned">📌</span> <?php endif; ?>
                <?php if ($discussion['locked']): ?><span class="lock-icon" title="Locked">🔒</span> <?php endif; ?>
                <?= e($discussion['title']) ?>
            </h1>
            <div class="discussion-meta">
                <?php if ($discussion['category']): ?>
                    <span class="badge badge-category"><?= e(ucfirst($discussion['category'])) ?></span>
                <?php endif; ?>
                <span class="text-muted">
                    Started by <?= e($discussion['author_name'] ?? 'Unknown') ?>
                    on <?= format_date($discussion['created_at'], 'j M Y H:i') ?>
                    · <?= (int)$discussion['post_count'] ?> post<?= $discussion['post_count'] != 1 ? 's' : '' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Moderation Actions -->
    <div class="moderation-bar">
        <form method="post" action="/council/discussions/<?= (int)$discussion['id'] ?>/pin" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-secondary">
                <?= $discussion['pinned'] ? 'Unpin' : 'Pin' ?>
            </button>
        </form>
        <form method="post" action="/council/discussions/<?= (int)$discussion['id'] ?>/lock" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-secondary">
                <?= $discussion['locked'] ? 'Unlock' : 'Lock' ?>
            </button>
        </form>
        <form method="post" action="/council/discussions/<?= (int)$discussion['id'] ?>/delete" class="inline-form"
              onsubmit="return confirm('Delete this entire discussion and all posts? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete Thread</button>
        </form>
    </div>

    <!-- Posts -->
    <div class="discussion-posts">
        <?php if (empty($posts)): ?>
            <p class="empty-state">No posts yet. Be the first to reply!</p>
        <?php else: ?>
            <?php foreach ($posts as $i => $post): ?>
            <article class="discussion-post" id="post-<?= (int)$post['id'] ?>">
                <div class="post-header">
                    <strong class="post-author"><?= e($post['author_name'] ?? 'Unknown') ?></strong>
                    <time class="post-date" datetime="<?= e($post['created_at']) ?>">
                        <?= format_date($post['created_at'], 'j M Y H:i') ?>
                    </time>
                    <span class="post-number">#<?= $i + 1 ?></span>
                </div>
                <div class="post-body">
                    <?= nl2br(e($post['body'])) ?>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reply Form -->
    <?php if ($discussion['locked']): ?>
        <div class="locked-notice">
            <p>🔒 This discussion is locked. No new replies can be posted.</p>
        </div>
    <?php else: ?>
        <div class="reply-form" id="latest">
            <h3>Post a Reply</h3>
            <form method="post" action="/council/discussions/<?= (int)$discussion['id'] ?>/reply">
                <?= csrf_field() ?>
                <div class="form-group">
                    <textarea name="body" class="form-input" rows="5" placeholder="Write your reply…" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </form>
        </div>
    <?php endif; ?>
</div>
