<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h1>Example Module</h1>
<p class="text-muted">This is the admin template scaffold for a custom module.</p>

<?php if (empty($items)): ?>
<p>No records found.</p>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= (int) $item['id'] ?></td>
            <td><?= e($item['title']) ?></td>
            <td><?= e($item['status']) ?></td>
            <td><?= e($item['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
