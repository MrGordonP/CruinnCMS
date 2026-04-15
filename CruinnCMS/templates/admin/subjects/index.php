<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<div class="admin-subjects">
    <div class="admin-header-row">
        <h1>Subjects <span class="count">(<?= (int) $total ?>)</span></h1>
        <a href="/admin/subjects/new" class="btn btn-primary">+ New Subject</a>
    </div>

    <!-- Search & Filters -->
    <form class="admin-search-bar" method="get" action="/admin/subjects">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search subjects…">
        <select name="type">
            <option value="">All Types</option>
            <?php foreach (['series', 'event', 'news', 'campaign', 'project', 'general'] as $t): ?>
            <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['draft', 'active', 'archived'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-small">Filter</button>
        <?php if ($search || $type || $status): ?>
            <a href="/admin/subjects" class="btn btn-small btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($subjects)): ?>
        <p class="empty-state">No subjects found. <a href="/admin/subjects/new">Create one</a>.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Type</th>
                <th>Events</th>
                <th>Articles</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subjects as $subject): ?>
            <tr>
                <td><code><?= e($subject['code']) ?></code></td>
                <td><a href="/admin/subjects/<?= (int) $subject['id'] ?>/edit"><?= e($subject['title']) ?></a></td>
                <td><span class="badge badge-type"><?= e(ucfirst($subject['type'])) ?></span></td>
                <td><?= (int) $subject['event_count'] ?></td>
                <td><?= (int) $subject['article_count'] ?></td>
                <td><span class="badge badge-<?= e($subject['status']) ?>"><?= e(ucfirst($subject['status'])) ?></span></td>
                <td><time datetime="<?= e($subject['created_at']) ?>"><?= format_date($subject['created_at'], 'j M Y') ?></time></td>
                <td>
                    <a href="/admin/subjects/<?= (int) $subject['id'] ?>/edit" class="btn btn-small">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Subject pagination">
        <?php if ($page > 1): ?>
        <a href="/admin/subjects?page=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?>" class="btn btn-small">&larr; Previous</a>
        <?php endif; ?>
        <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
        <a href="/admin/subjects?page=<?= $page + 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?>" class="btn btn-small">Next &rarr;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
