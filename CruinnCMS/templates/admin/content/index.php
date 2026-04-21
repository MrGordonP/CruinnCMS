<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
?>
<div class="admin-page-header">
    <h1>Content Sets</h1>
    <div class="header-actions">
        <a href="/admin/content/new" class="btn btn-primary btn-small">+ New Set</a>
    </div>
</div>

<?php if (empty($sets)): ?>
    <p class="empty-state">No content sets yet. <a href="/admin/content/new">Create your first one</a>.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Fields</th>
            <th>Rows</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($sets as $set):
        $fields = json_decode($set['fields'] ?? '[]', true) ?: [];
    ?>
        <tr>
            <td><strong><?= e($set['name']) ?></strong>
                <?php if (!empty($set['description'])): ?>
                    <br><span class="form-help"><?= e($set['description']) ?></span>
                <?php endif; ?>
            </td>
            <td><code><?= e($set['slug']) ?></code></td>
            <td><?= count($fields) ?></td>
            <td><?= (int)$set['row_count'] ?></td>
            <td class="table-actions">
                <a href="/admin/content/<?= (int)$set['id'] ?>/rows" class="btn btn-outline btn-small">Rows</a>
                <a href="/admin/content/<?= (int)$set['id'] ?>/edit" class="btn btn-outline btn-small">Edit Schema</a>
                <form method="post" action="/admin/content/<?= (int)$set['id'] ?>/delete" class="inline-form"
                      onsubmit="return confirm('Delete this content set and all its rows?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
