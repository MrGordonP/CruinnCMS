<div class="admin-page-header">
    <div>
        <h1>Forum Moderation</h1>
        <p class="text-muted">Manage thread visibility, lock state, and cleanup.</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= url('/admin/forum/reports') ?>" class="btn btn-outline">Post Reports</a>
    </div>
</div>

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
