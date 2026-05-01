<div class="admin-page-header">
    <div>
        <h1>Forum Moderation</h1>
        <p class="text-muted">Manage thread visibility, lock state, and cleanup.</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= url('/admin/forum/reports') ?>" class="btn btn-outline">Post Reports</a>
    </div>
</div>

<?php if (!empty($categoriesHierarchical)): ?>
<div style="margin-bottom: var(--space-xl);">
    <h2 style="font-size: 1.1rem; margin-bottom: var(--space-md); color: var(--color-text-light);">Forum Structure</h2>
    <div class="forum-index-phpbb">
        <?php foreach ($categoriesHierarchical as $category): ?>
            <div class="forum-category-section">
                <div class="forum-category-header">
                    <h3 style="margin: 0; font-size: 1rem;">
                        <a href="<?= url('/forum/' . $category['slug']) ?>" target="_blank">
                            <?= e($category['title']) ?>
                        </a>
                    </h3>
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
                                <h4 class="forum-title" style="margin: 0 0 0.25rem;">
                                    <a href="<?= url('/forum/' . $category['slug']) ?>" target="_blank">
                                        <?= e($category['title']) ?>
                                    </a>
                                </h4>
                                <?php if (!empty($category['description'])): ?>
                                    <p class="forum-desc" style="margin: 0;"><?= e($category['description']) ?></p>
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
                                            <a href="<?= url('/forum/thread/' . (int)$category['last_thread_id']) ?>" class="last-post-thread" target="_blank">
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
                                    <h4 class="forum-title" style="margin: 0 0 0.25rem;">
                                        <a href="<?= url('/forum/' . $subforum['slug']) ?>" target="_blank">
                                            <?= e($subforum['title']) ?>
                                        </a>
                                    </h4>
                                    <?php if (!empty($subforum['description'])): ?>
                                        <p class="forum-desc" style="margin: 0;"><?= e($subforum['description']) ?></p>
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
                                                <a href="<?= url('/forum/thread/' . (int)$subforum['last_thread_id']) ?>" class="last-post-thread" target="_blank">
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
</div>
<?php endif; ?>

<h2 style="font-size: 1.1rem; margin-bottom: var(--space-md); color: var(--color-text-light);">Thread Moderation</h2>

<form method="get" action="/admin/forum" class="card" style="margin-bottom: var(--space-lg);">
    <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:var(--space-md);align-items:end;">
        <div class="form-group" style="margin:0;">
            <label for="q">Search title</label>
            <input id="q" name="q" type="search" class="form-input" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search threads">
        </div>

        <div class="form-group" style="margin:0;">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" class="form-input">
                <option value="0">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>>
                        <?= e($category['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-input">
                <?php $status = (string)($filters['status'] ?? 'all'); ?>
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open only</option>
                <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked only</option>
                <option value="pinned" <?= $status === 'pinned' ? 'selected' : '' ?>>Pinned only</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="/admin/forum" class="btn btn-outline">Reset</a>
        </div>
    </div>
</form>

<?php if (empty($threads)): ?>
    <div class="card">
        <p style="margin:0;">No threads matched your filters.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Thread</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Replies</th>
                    <th>Last Post</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($threads as $thread): ?>
                    <tr>
                        <td>
                            <a href="/forum/thread/<?= (int)$thread['id'] ?>" target="_blank" rel="noopener noreferrer">
                                <?= e($thread['title']) ?>
                            </a>
                        </td>
                        <td><?= e($thread['category_title']) ?></td>
                        <td><?= e($thread['author_name']) ?></td>
                        <td><?= (int)$thread['reply_count'] ?></td>
                        <td>
                            <?php if (!empty($thread['last_post_at'])): ?>
                                <time datetime="<?= e($thread['last_post_at']) ?>"><?= format_date($thread['last_post_at'], 'j M Y H:i') ?></time>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$thread['is_pinned'] === 1): ?>
                                <span class="badge badge-info">Pinned</span>
                            <?php endif; ?>
                            <?php if ((int)$thread['is_locked'] === 1): ?>
                                <span class="badge badge-warning">Locked</span>
                            <?php endif; ?>
                            <?php if ((int)$thread['is_pinned'] !== 1 && (int)$thread['is_locked'] !== 1): ?>
                                <span class="badge">Open</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/pin" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-outline">
                                    <?= (int)$thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?>
                                </button>
                            </form>

                            <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/lock" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-outline">
                                    <?= (int)$thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?>
                                </button>
                            </form>

                            <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this thread and all replies? This cannot be undone.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>

                            <a href="/admin/forum/<?= (int)$thread['id'] ?>/move" class="btn btn-small btn-outline">Move</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
