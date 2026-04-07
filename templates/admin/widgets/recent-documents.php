<?php
/**
 * Widget: Recent Documents
 * Latest council documents table.
 * Data keys: documents[]
 */
$docs = $data['documents'] ?? [];
?>
<div class="activity-header">
    <h2>Recent Documents</h2>
    <a href="/council/documents/new" class="btn btn-primary btn-sm">Upload New</a>
</div>
<?php if (empty($docs)): ?>
    <p class="text-muted">No documents yet.</p>
<?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $doc): ?>
                <tr>
                    <td><a href="/council/documents/<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></td>
                    <td><span class="badge badge-category"><?= e(ucfirst($doc['category'])) ?></span></td>
                    <td><span class="badge badge-doc-<?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span></td>
                    <td><time datetime="<?= e($doc['updated_at']) ?>"><?= format_date($doc['updated_at'], 'j M Y') ?></time></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
