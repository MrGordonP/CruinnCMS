<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;

$blogBasePath = trim((string) ($blogBasePath ?? ''));
?>

<div class="panel-layout no-detail" id="blog-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header">
        <h3>Blog</h3>
        <a href="<?= url('/admin/blog/posts/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item" href="<?= url('/admin/blog') ?>">Overview</a>
        <a class="pl-nav-item active" href="<?= url('/admin/blog/posts') ?>">Posts</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/profiles') ?>">Profiles</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Blog Posts <span style="font-weight:400;color:#aaa">(<?= (int)$total ?>)</span></span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/blog/posts/new') ?>" class="btn btn-small btn-primary">+ New Post</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <!-- Search & Filters -->
    <form method="get" action="/admin/blog/posts" class="admin-filters">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search blog posts…" class="form-input">
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
            <a href="/admin/blog/posts" class="btn btn-text">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Blog Posts Table -->
    <?php if (empty($articles)): ?>
        <p class="admin-empty">No blog posts found.</p>
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
                    <a href="/admin/blog/posts/<?= (int)$article['id'] ?>/edit" class="strong-link">
                        <?= e($article['title']) ?>
                    </a>
                    <?php if (!empty($article['featured_image'])): ?>
                        <span class="badge badge-info" title="Has featured image">📷</span>
                    <?php endif; ?>
                </td>
                <td><?= e($article['subject_titles'] ?? '—') ?></td>
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
                    <a href="/admin/blog/posts/<?= (int)$article['id'] ?>/edit" class="btn btn-small">Edit</a>
                    <?php if ($article['status'] === 'published' && $blogBasePath !== ''): ?>
                        <a href="<?= e($blogBasePath . '/' . ($article['slug'] ?? '')) ?>" target="_blank" class="btn btn-small btn-outline">View</a>
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
            <a href="/admin/blog/posts?page=<?= $page - 1 ?><?= $qsPrefix ?>" class="btn btn-small">&laquo; Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="/admin/blog/posts?page=<?= $page + 1 ?><?= $qsPrefix ?>" class="btn btn-small">Next &raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
