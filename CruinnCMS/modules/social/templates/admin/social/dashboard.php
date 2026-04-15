<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>Social Media Command Centre</h1>
        <div class="social-hub-actions">
            <a href="<?= url('/admin/social/inbox') ?>" class="btn btn-outline">
                Inbox <?php if ($unreadCount): ?><span class="badge badge-danger"><?= $unreadCount ?></span><?php endif; ?>
            </a>
            <a href="<?= url('/admin/social/distribute') ?>" class="btn btn-primary">Distribute Content</a>
        </div>
    </div>

    <!-- Platform Overview Cards -->
    <div class="social-platforms-grid">
        <?php foreach (['facebook', 'twitter', 'instagram'] as $pf): ?>
        <?php
            $info = $platforms[$pf] ?? null;
            $connected = $info && $info['connected'];
            $metrics = $info['metrics'] ?? [];
            $acct = $info['account'] ?? [];
        ?>
        <div class="social-platform-card social-platform-<?= $pf ?> <?= $connected ? 'connected' : 'disconnected' ?>">
            <div class="platform-card-header">
                <div class="platform-icon">
                    <?php if ($pf === 'facebook'): ?>
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <?php elseif ($pf === 'twitter'): ?>
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    <?php endif; ?>
                </div>
                <div class="platform-card-title">
                    <h2><?= ucfirst($pf) === 'Twitter' ? 'Twitter / X' : ucfirst($pf) ?></h2>
                    <?php if ($connected): ?>
                        <span class="badge badge-success">Connected</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Not Connected</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($connected && $metrics): ?>
            <div class="platform-metrics">
                <?php if (isset($metrics['followers'])): ?>
                <div class="platform-metric">
                    <span class="metric-value"><?= number_format($metrics['followers']) ?></span>
                    <span class="metric-label">Followers</span>
                </div>
                <?php endif; ?>
                <?php if (isset($metrics['fans'])): ?>
                <div class="platform-metric">
                    <span class="metric-value"><?= number_format($metrics['fans']) ?></span>
                    <span class="metric-label">Page Likes</span>
                </div>
                <?php endif; ?>
                <?php if (isset($metrics['tweets'])): ?>
                <div class="platform-metric">
                    <span class="metric-value"><?= number_format($metrics['tweets']) ?></span>
                    <span class="metric-label">Tweets</span>
                </div>
                <?php endif; ?>
                <?php if (isset($metrics['posts'])): ?>
                <div class="platform-metric">
                    <span class="metric-value"><?= number_format($metrics['posts']) ?></span>
                    <span class="metric-label">Posts</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="platform-card-actions">
                <?php if ($connected): ?>
                    <a href="<?= url("/admin/social/feed/{$pf}") ?>" class="btn btn-small btn-outline">View Feed</a>
                <?php else: ?>
                    <a href="<?= url('/admin/social/accounts') ?>" class="btn btn-small btn-primary">Connect</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Post -->
    <div class="social-section social-quick-post">
        <h2>Quick Post</h2>
        <p class="text-muted">Post a message to one or more platforms simultaneously.</p>
        <form action="<?= url('/admin/social/quick-post') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <textarea name="message" rows="3" placeholder="What would you like to share?" class="form-control" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group form-group-half">
                    <label>Link (optional)</label>
                    <input type="url" name="link" class="form-control" placeholder="https://example.com/...">
                </div>
                <div class="form-group form-group-half">
                    <label>Image URL (optional)</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="form-group">
                <label>Post to:</label>
                <div class="channel-checkboxes">
                    <?php foreach ($accounts as $acct): ?>
                        <?php if ($acct['is_active']): ?>
                        <label class="checkbox-label platform-check platform-check-<?= e($acct['platform']) ?>">
                            <input type="checkbox" name="channels[]" value="<?= (int)$acct['id'] ?>">
                            <?= ucfirst($acct['platform']) ?>
                            <?php if ($acct['account_name']): ?>
                                <small>(<?= e($acct['account_name']) ?>)</small>
                            <?php endif; ?>
                        </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Post Now</button>
        </form>
    </div>

    <div class="social-two-col">
        <!-- Recent Inbox Messages -->
        <div class="social-section">
            <div class="social-section-header">
                <h2>Recent Messages</h2>
                <a href="<?= url('/admin/social/inbox') ?>" class="btn btn-small btn-outline">
                    View All <?php if ($unreadCount): ?>(<?= $unreadCount ?> unread)<?php endif; ?>
                </a>
            </div>
            <?php if (empty($inboxRecent)): ?>
                <p class="text-muted">No messages yet. Connect your accounts and sync to see messages.</p>
            <?php else: ?>
                <div class="inbox-preview-list">
                    <?php foreach ($inboxRecent as $msg): ?>
                    <div class="inbox-preview-item <?= $msg['is_read'] ? '' : 'unread' ?>">
                        <div class="inbox-preview-platform">
                            <span class="platform-dot platform-dot-<?= e($msg['platform']) ?>"></span>
                        </div>
                        <div class="inbox-preview-content">
                            <div class="inbox-preview-meta">
                                <strong><?= e($msg['author_name']) ?></strong>
                                <span class="badge badge-<?= e($msg['message_type']) ?>"><?= e(ucfirst($msg['message_type'])) ?></span>
                                <time><?= format_date($msg['received_at'], 'j M H:i') ?></time>
                            </div>
                            <p><?= e(truncate($msg['body'], 120)) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Published Posts -->
        <div class="social-section">
            <div class="social-section-header">
                <h2>Published Posts</h2>
                <a href="<?= url('/admin/social/distribute') ?>" class="btn btn-small btn-outline">Distribute</a>
            </div>
            <?php if (empty($recentPosts)): ?>
                <p class="text-muted">No posts published yet from the portal.</p>
            <?php else: ?>
                <div class="published-posts-list">
                    <?php foreach ($recentPosts as $post): ?>
                    <div class="published-post-item">
                        <span class="platform-dot platform-dot-<?= e($post['platform']) ?>"></span>
                        <div class="published-post-content">
                            <p><?= e(truncate($post['message'], 100)) ?></p>
                            <div class="published-post-meta">
                                <span class="badge badge-<?= $post['status'] === 'published' ? 'success' : ($post['status'] === 'failed' ? 'danger' : 'muted') ?>">
                                    <?= e(ucfirst($post['status'])) ?>
                                </span>
                                <time><?= format_date($post['created_at'], 'j M H:i') ?></time>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sub-navigation -->
    <div class="social-subnav">
        <a href="<?= url('/admin/social/accounts') ?>" class="btn btn-outline">Manage Accounts</a>
        <a href="<?= url('/admin/social/mailing-lists') ?>" class="btn btn-outline">Mailing Lists</a>
    </div>
</div>
