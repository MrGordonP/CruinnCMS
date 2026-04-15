<?php
$recentDocuments = $data['recent_documents'] ?? [];
?>
<div class="activity-header">
    <h2>Documents</h2>
    <a href="<?= url('/documents') ?>" class="btn btn-primary btn-small">View All</a>
</div>

<div class="dash-quick-grid">
    <a href="<?= url('/documents') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📄</span><span>Documents <?= (int) ($data['documents'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/documents?status=submitted') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">⏳</span><span>Pending <?= (int) ($data['pending'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/documents/new') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">⬆️</span><span>Upload</span>
    </a>
</div>

<?php if (!empty($recentDocuments)): ?>
<table class="admin-table" style="margin-top:1rem;">
    <thead>
        <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Updated</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recentDocuments as $doc): ?>
        <tr>
            <td><a href="<?= url('/documents/' . (int) $doc['id']) ?>"><?= e($doc['title']) ?></a></td>
            <td><?= e(ucfirst((string) $doc['category'])) ?></td>
            <td><?= e(ucfirst((string) $doc['status'])) ?></td>
            <td><time datetime="<?= e($doc['updated_at']) ?>"><?= format_date($doc['updated_at'], 'j M Y') ?></time></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="text-muted" style="margin-top:1rem;">No documents yet.</p>
<?php endif; ?>
