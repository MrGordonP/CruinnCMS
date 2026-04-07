<?php $tab = 'pages'; include __DIR__ . '/../site-builder/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<div class="sb-full-width">
<div class="admin-page-header">
    <h2>All Pages</h2>
    <a href="/admin/pages/new" class="btn btn-primary btn-small">+ New Page</a>
</div>

<?php if (empty($pages)): ?>
    <p>No pages yet. <a href="/admin/pages/new">Create your first page</a>.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>URL</th>
            <th>Mode</th>
            <th>Template</th>
            <th>Updated</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pages as $pg): ?>
        <?php $mode = $pg['render_mode'] ?? 'cruinn'; ?>
        <tr>
            <td>
                <a href="/admin/editor/<?= (int)$pg['id'] ?>/edit"><?= e($pg['title']) ?></a>
                <?php if (($pg['status'] ?? 'published') !== 'published'): ?>
                <span class="badge" style="background:#d97706;color:#fff;font-size:.7rem;margin-left:.3rem"><?= e($pg['status']) ?></span>
                <?php endif; ?>
            </td>
            <td><code>/<?= e($pg['slug']) ?></code></td>
            <td>
                <span class="badge" style="<?= match($mode) {
                    'file'  => 'background:#d97706;color:#fff',
                    'html'  => 'background:#7c3aed;color:#fff',
                    default => 'background:#1d9e75;color:#fff'
                } ?>;font-size:.7rem"><?= e($mode) ?></span>
            </td>
            <td><span class="text-muted"><?= e(ucfirst(str_replace('-', ' ', $pg['template'] ?? 'default'))) ?></span></td>
            <td><?= format_date($pg['updated_at'], 'j M Y') ?></td>
            <td style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                <?php if ($mode === 'html'): ?>
                <a href="/admin/pages/<?= (int)$pg['id'] ?>/html" class="btn btn-small">HTML Editor</a>
                <?php else: ?>
                <a href="/admin/editor/<?= (int)$pg['id'] ?>/edit" class="btn btn-small">Edit</a>
                <?php endif; ?>
                <a href="/<?= e($pg['slug']) ?>" target="_blank" class="btn btn-small btn-outline">View</a>
                <?php if ($mode === 'cruinn'): ?>
                <form method="POST" action="/admin/pages/<?= (int)$pg['id'] ?>/export-html" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">
                    <button type="submit" class="btn btn-small btn-outline" title="Export to static HTML">↓ HTML</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</div>
<?php include __DIR__ . '/../site-builder/_tabs_close.php'; ?>
