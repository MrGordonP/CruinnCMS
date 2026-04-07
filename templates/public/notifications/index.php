<section class="container notifications-page">
    <header class="notifications-header">
        <div>
            <h1>Notifications</h1>
            <p class="results-count">Unread: <?= (int)$unreadCount ?></p>
        </div>
        <div class="notifications-actions">
            <a href="<?= url('/notifications/preferences') ?>" class="btn btn-outline btn-small">Preferences</a>
            <form method="post" action="<?= url('/notifications/read-all') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-small">Mark all read</button>
            </form>
        </div>
    </header>

    <section class="search-bar">
        <form method="get" class="search-form">
            <select name="category" class="form-input search-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"<?= $selectedCategory === $cat ? ' selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="notifications-unread-filter">
                <input type="checkbox" name="unread" value="1"<?= $showUnread ? ' checked' : '' ?>> Unread only
            </label>
            <button class="btn btn-outline" type="submit">Apply</button>
            <a href="<?= url('/notifications') ?>" class="btn btn-outline">Reset</a>
        </form>
    </section>

    <?php if (empty($notifications)): ?>
        <p>No notifications found.</p>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $item): ?>
                <article class="notification-item<?= empty($item['read_at']) ? ' is-unread' : '' ?>">
                    <header>
                        <div class="notification-title-row">
                            <h2><?= e($item['title']) ?></h2>
                            <span class="badge badge-member-type"><?= e(ucfirst($item['category'])) ?></span>
                        </div>
                        <p class="notification-meta">
                            <?= e(format_date($item['created_at'], 'j M Y H:i')) ?>
                            <?php if (!empty($item['subject_title'])): ?>
                                • Subject: <?= e($item['subject_title']) ?>
                            <?php endif; ?>
                        </p>
                    </header>

                    <?php if (!empty($item['body'])): ?>
                        <p><?= nl2br(e($item['body'])) ?></p>
                    <?php endif; ?>

                    <div class="notification-actions">
                        <?php if (!empty($item['url'])): ?>
                            <a href="<?= url($item['url']) ?>" class="btn btn-outline btn-small">Open</a>
                        <?php endif; ?>

                        <?php if (empty($item['read_at'])): ?>
                            <form method="post" action="<?= url('/notifications/' . (int)$item['id'] . '/read') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/notifications') ?>">
                                <button type="submit" class="btn btn-primary btn-small">Mark read</button>
                            </form>
                        <?php else: ?>
                            <span class="notification-read-tag">Read</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
