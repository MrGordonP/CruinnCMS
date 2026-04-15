<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>
            <span class="platform-dot platform-dot-<?= e($platform) ?>"></span>
            <?= ucfirst($platform) === 'Twitter' ? 'Twitter / X' : ucfirst($platform) ?> Feed
        </h1>
        <a href="<?= url('/admin/social') ?>" class="btn btn-outline">Back to Hub</a>
    </div>

    <!-- Account Metrics -->
    <?php if ($metrics): ?>
    <div class="feed-metrics">
        <?php foreach ($metrics as $key => $value): ?>
            <?php if (is_numeric($value)): ?>
            <div class="feed-metric">
                <span class="metric-value"><?= number_format($value) ?></span>
                <span class="metric-label"><?= ucfirst($key) ?></span>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Posts Feed -->
    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <p>No posts found. Make sure your account is connected and has the right permissions.</p>
            <a href="<?= url('/admin/social/accounts') ?>" class="btn btn-outline">Check Account Settings</a>
        </div>
    <?php else: ?>
    <div class="social-feed">
        <?php foreach ($posts as $post): ?>
        <div class="feed-post feed-post-<?= e($platform) ?>">
            <?php if (!empty($post['image'])): ?>
            <div class="feed-post-image">
                <img src="<?= e($post['image']) ?>" alt="" loading="lazy">
            </div>
            <?php endif; ?>
            <div class="feed-post-body">
                <p class="feed-post-text"><?= nl2br(e($post['message'])) ?></p>
                <div class="feed-post-stats">
                    <span class="feed-stat" title="Likes">&#9829; <?= number_format($post['likes'] ?? 0) ?></span>
                    <span class="feed-stat" title="Comments">&#128172; <?= number_format($post['comments'] ?? 0) ?></span>
                    <?php if (($post['shares'] ?? 0) > 0): ?>
                    <span class="feed-stat" title="Shares">&#8634; <?= number_format($post['shares']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="feed-post-meta">
                    <time><?= format_date($post['created_at'], 'j M Y, H:i') ?></time>
                    <?php if (!empty($post['link'])): ?>
                    <a href="<?= e($post['link']) ?>" target="_blank" rel="noopener noreferrer" class="feed-post-link">
                        View on <?= ucfirst($platform) ?> &rarr;
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
