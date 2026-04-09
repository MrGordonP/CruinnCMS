<?php \IGA\Template::requireCss('admin-site-builder.css'); ?>
<div class="admin-article-list">
    <div class="admin-list-header">
        <h1>Articles <span class="count">(<?= (int)$total ?>)</span></h1>
        <a href="/admin/articles/new" class="btn btn-primary">+ New Article</a>
    </div>

    <!-- Search & Filters -->
    <form method="get" action="/admin/articles" class="admin-filters">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search articles…" class="form-input">
        <select name="status" class="form-input">
            <option value="">All Statuses</option>
            <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <select name="subject" class="form-input">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $subjectId == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($search || $status || $subjectId): ?>
            <a href="/admin/articles" class="btn btn-text">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Articles Table -->
    <?php if (empty($articles)): ?>
        <p class="admin-empty">No articles found.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Subject</th>
                <th>Author</th>
                <th>Status</th>
                <th>Published</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($articles as $article): ?>
            <tr>
                <td>
                    <a href="/admin/articles/<?= (int)$article['id'] ?>/edit" class="strong-link">
                        <?= e($article['title']) ?>
                    </a>
                    <?php if (!empty($article['featured_image'])): ?>
                        <span class="badge badge-info" title="Has featured image">📷</span>
                    <?php endif; ?>
                </td>
                <td><?= e($article['subject_title'] ?? '—') ?></td>
                <td><?= e($article['author_name'] ?? '—') ?></td>
                <td>
                    <?php
                    $statusClass = match($article['status']) {
                        'published' => 'badge-success',
                        'archived'  => 'badge-muted',
                        default     => 'badge-warning',
                    };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= e(ucfirst($article['status'])) ?></span>
                </td>
                <td>
                    <?= $article['published_at'] ? date('j M Y', strtotime($article['published_at'])) : '—' ?>
                </td>
                <td><?= date('j M Y', strtotime($article['updated_at'])) ?></td>
                <td>
                    <a href="/admin/articles/<?= (int)$article['id'] ?>/edit" class="btn btn-small">Edit</a>
                    <?php if ($article['status'] === 'published'): ?>
                        <a href="/news/<?= e($article['slug']) ?>" target="_blank" class="btn btn-small btn-outline">View</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="admin-pagination">
        <?php
        $qs = http_build_query(array_filter(['q' => $search, 'status' => $status, 'subject' => $subjectId]));
        $qsPrefix = $qs ? '&' . $qs : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="/admin/articles?page=<?= $page - 1 ?><?= $qsPrefix ?>" class="btn btn-small">&laquo; Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="/admin/articles?page=<?= $page + 1 ?><?= $qsPrefix ?>" class="btn btn-small">Next &raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
