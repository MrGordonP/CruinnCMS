<div class="organisation-discussions">
    <div class="page-header">
        <h1>Discussions</h1>
        <a href="/organisation/discussions/new" class="btn btn-primary">New Discussion</a>
    </div>

    <!-- Filters -->
    <form class="filter-bar" method="get" action="/organisation/discussions">
        <div class="filter-group">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search discussions…" class="form-input">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($category || $search): ?>
            <a href="/organisation/discussions" class="btn btn-link">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <p class="empty-state">No discussions found. <a href="/organisation/discussions/new">Start the first one!</a></p>
    <?php else: ?>
        <p class="result-count"><?= (int)$total ?> discussion<?= $total !== 1 ? 's' : '' ?></p>
        <table class="admin-table discussion-table">
            <thead>
                <tr>
                    <th>Topic</th>
                    <th>Category</th>
                    <th>Started By</th>
                    <th>Posts</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discussions as $disc): ?>
                <tr class="<?= $disc['pinned'] ? 'row-pinned' : '' ?> <?= $disc['locked'] ? 'row-locked' : '' ?>">
                    <td class="discussion-title-cell">
                        <?php if ($disc['pinned']): ?><span class="pin-icon" title="Pinned">📌</span> <?php endif; ?>
                        <?php if ($disc['locked']): ?><span class="lock-icon" title="Locked">🔒</span> <?php endif; ?>
                        <a href="/organisation/discussions/<?= (int)$disc['id'] ?>"><?= e($disc['title']) ?></a>
                    </td>
                    <td>
                        <?php if ($disc['category']): ?>
                            <span class="badge badge-category"><?= e(ucfirst($disc['category'])) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($disc['author_name'] ?? '—') ?></td>
                    <td class="text-center"><?= (int)$disc['post_count'] ?></td>
                    <td>
                        <?php if ($disc['last_post_at']): ?>
                            <time datetime="<?= e($disc['last_post_at']) ?>"><?= format_date($disc['last_post_at'], 'j M Y H:i') ?></time>
                        <?php else: ?>
                            <span class="text-muted">No replies</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&category=<?= e($category) ?>&q=<?= e($search) ?>" class="btn btn-secondary btn-sm">&laquo; Previous</a>
            <?php endif; ?>
            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&category=<?= e($category) ?>&q=<?= e($search) ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
