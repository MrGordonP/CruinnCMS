<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
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
            <th>Type</th>
            <th>Slug</th>
            <th>Fields / Config</th>
            <th>Rows</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($sets as $set):
        $isQuery = ($set['type'] ?? 'manual') === 'query';
        $fields  = $isQuery ? [] : (json_decode($set['fields'] ?? '[]', true) ?: []);
    ?>
        <tr>
            <td><strong><?= e($set['name']) ?></strong>
                <?php if (!empty($set['description'])): ?>
                    <br><span class="form-help"><?= e($set['description']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($isQuery): ?>
                    <span class="badge badge-info" style="font-size:0.7rem;padding:0.1rem 0.45rem;border-radius:999px;background:#dbeafe;color:#1e40af">Query</span>
                <?php else: ?>
                    <span class="badge" style="font-size:0.7rem;padding:0.1rem 0.45rem;border-radius:999px;background:#f3f4f6;color:#374151">Manual</span>
                <?php endif; ?>
            </td>
            <td><code><?= e($set['slug']) ?></code></td>
            <td><?= $isQuery ? '<em style="font-size:0.78rem;color:#6b7280">live query</em>' : count($fields) . ' field' . (count($fields) === 1 ? '' : 's') ?></td>
            <td><?= $isQuery ? '<em style="font-size:0.78rem;color:#6b7280">—</em>' : (int)$set['row_count'] ?></td>
            <td class="table-actions">
                <?php if (!$isQuery): ?>
                <a href="/admin/content/<?= (int)$set['id'] ?>/rows" class="btn btn-outline btn-small">Rows</a>
                <?php endif; ?>
                <a href="/admin/content/<?= (int)$set['id'] ?>/edit" class="btn btn-outline btn-small"><?= $isQuery ? 'Edit Query' : 'Edit Schema' ?></a>
                <form method="post" action="/admin/content/<?= (int)$set['id'] ?>/delete" class="inline-form"
                      data-confirm="Delete this content set and all its rows?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
