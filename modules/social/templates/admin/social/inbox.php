<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>Social Inbox <?php if ($unreadCount): ?><span class="badge badge-danger"><?= $unreadCount ?> unread</span><?php endif; ?></h1>
        <div class="social-hub-actions">
            <form action="<?= url('/admin/social/inbox/sync') ?>" method="POST" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline">Sync Now</button>
            </form>
            <a href="<?= url('/admin/social') ?>" class="btn btn-outline">Back to Hub</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="inbox-filters">
        <form method="GET" action="<?= url('/admin/social/inbox') ?>" class="inbox-filter-form">
            <div class="filter-group">
                <label>Platform</label>
                <select name="platform" class="form-control form-control-sm">
                    <option value="">All Platforms</option>
                    <option value="facebook" <?= $filterPlatform === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                    <option value="twitter" <?= $filterPlatform === 'twitter' ? 'selected' : '' ?>>Twitter / X</option>
                    <option value="instagram" <?= $filterPlatform === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Type</label>
                <select name="type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    <option value="comment" <?= $filterType === 'comment' ? 'selected' : '' ?>>Comments</option>
                    <option value="message" <?= $filterType === 'message' ? 'selected' : '' ?>>Messages</option>
                    <option value="mention" <?= $filterType === 'mention' ? 'selected' : '' ?>>Mentions</option>
                    <option value="reply" <?= $filterType === 'reply' ? 'selected' : '' ?>>Replies</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="read" class="form-control form-control-sm">
                    <option value="">All</option>
                    <option value="unread" <?= $filterRead === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read" <?= $filterRead === 'read' ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
            <button type="submit" class="btn btn-small btn-outline">Filter</button>
        </form>
    </div>

    <!-- Messages List -->
    <?php if (empty($messages)): ?>
        <div class="empty-state">
            <p>No messages found. Try syncing your inbox or adjusting filters.</p>
        </div>
    <?php else: ?>
    <div class="inbox-list" id="inboxList">
        <?php foreach ($messages as $msg): ?>
        <div class="inbox-item <?= $msg['is_read'] ? 'read' : 'unread' ?> <?= $msg['is_starred'] ? 'starred' : '' ?>"
             data-id="<?= (int)$msg['id'] ?>">
            <div class="inbox-item-left">
                <button class="inbox-star-btn" data-id="<?= (int)$msg['id'] ?>" title="Star/Unstar">
                    <?= $msg['is_starred'] ? '&#9733;' : '&#9734;' ?>
                </button>
                <div class="inbox-item-avatar">
                    <?php if ($msg['author_avatar']): ?>
                        <img src="<?= e($msg['author_avatar']) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar-placeholder"><?= strtoupper(substr($msg['author_name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="inbox-item-body">
                <div class="inbox-item-header">
                    <span class="platform-dot platform-dot-<?= e($msg['platform']) ?>"></span>
                    <strong class="inbox-author"><?= e($msg['author_name']) ?></strong>
                    <span class="badge badge-<?= e($msg['message_type']) ?>"><?= e(ucfirst($msg['message_type'])) ?></span>
                    <?php if ($msg['replied']): ?>
                        <span class="badge badge-success">Replied</span>
                    <?php endif; ?>
                    <time class="inbox-time"><?= format_date($msg['received_at'], 'j M Y, H:i') ?></time>
                </div>
                <div class="inbox-item-text">
                    <?= e($msg['body']) ?>
                </div>
                <?php if ($msg['replied'] && $msg['reply_text']): ?>
                <div class="inbox-item-reply">
                    <strong>Your reply:</strong> <?= e($msg['reply_text']) ?>
                </div>
                <?php endif; ?>

                <!-- Reply Form (hidden by default) -->
                <div class="inbox-reply-form" id="reply-form-<?= (int)$msg['id'] ?>" style="display: none;">
                    <form action="<?= url('/admin/social/inbox/' . (int)$msg['id'] . '/reply') ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="reply-form-row">
                            <textarea name="reply_text" rows="2" class="form-control" placeholder="Type your reply..." required></textarea>
                            <button type="submit" class="btn btn-primary btn-small">Send</button>
                            <button type="button" class="btn btn-outline btn-small reply-cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="inbox-item-actions">
                <?php if (!$msg['is_read']): ?>
                <button class="inbox-action-btn mark-read-btn" data-id="<?= (int)$msg['id'] ?>" title="Mark as read">
                    &#10003;
                </button>
                <?php endif; ?>
                <?php if (!$msg['replied']): ?>
                <button class="inbox-action-btn reply-btn" data-target="reply-form-<?= (int)$msg['id'] ?>" title="Reply">
                    &#8617;
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
