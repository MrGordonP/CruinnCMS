<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
?>

<div class="panel-layout no-detail" id="org-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header"><h3>Workspace</h3></div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Navigation</div>
        <a class="pl-nav-item" href="<?= url('/organisation') ?>">Dashboard</a>
        <a class="pl-nav-item active" href="<?= url('/documents') ?>">Documents</a>
        <a class="pl-nav-item" href="<?= url('/organisation/discussions') ?>">Discussions</a>
        <a class="pl-nav-item" href="<?= url('/organisation/inbox') ?>">Inbox</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Documents</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/documents/new') ?>" class="btn btn-small btn-primary">Upload Document</a>
        </div>
    </div>
    <div class="pl-main-scroll">

<form class="filter-bar" method="get" action="/documents">
    <div class="filter-group">
        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach (['minutes', 'reports', 'policies', 'correspondence', 'financial', 'other'] as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (['draft', 'submitted', 'approved', 'archived'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search documents…" class="form-input">
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($category || $status || $search): ?>
        <a href="/documents" class="btn btn-link">Clear</a>
    <?php endif; ?>
</form>

<?php if ($total === 0): ?>
    <p class="empty-state">No documents found.</p>
<?php else: ?>
    <p class="result-count"><?= (int)$total ?> document<?= $total !== 1 ? 's' : '' ?> found</p>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Type</th>
                <th>Version</th>
                <th>Status</th>
                <th>Uploaded By</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $doc): ?>
            <tr>
                <td><a href="/documents/<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></td>
                <td><span class="badge badge-category"><?= e(ucfirst($doc['category'])) ?></span></td>
                <td class="text-muted"><?= e(strtoupper($doc['file_type'] ?? '—')) ?></td>
                <td class="text-center">v<?= (int)$doc['version'] ?></td>
                <td><span class="badge badge-doc-<?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span></td>
                <td><?= e($doc['uploader_name'] ?? '—') ?></td>
                <td><time datetime="<?= e($doc['updated_at']) ?>"><?= format_date($doc['updated_at'], 'j M Y') ?></time></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&category=<?= e($category) ?>&status=<?= e($status) ?>&q=<?= e($search) ?>" class="btn btn-secondary btn-sm">&laquo; Previous</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&category=<?= e($category) ?>&status=<?= e($status) ?>&q=<?= e($search) ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
