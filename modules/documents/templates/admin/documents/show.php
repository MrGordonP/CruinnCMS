<?php
use Cruinn\Module\Documents\Controllers\DocumentController;
?>
<div class="page-header">
    <div>
        <a href="/documents" class="back-link">&larr; All Documents</a>
        <h1><?= e($document['title']) ?></h1>
    </div>
</div>

<!-- Document Info Card -->
<div class="document-card">
    <div class="document-meta-grid">
        <div class="meta-item">
            <span class="meta-label">Category</span>
            <span class="badge badge-category"><?= e(ucfirst($document['category'])) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Status</span>
            <span class="badge badge-doc-<?= e($document['status']) ?>"><?= e(ucfirst($document['status'])) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Version</span>
            <span>v<?= (int)$document['version'] ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Type</span>
            <span><?= e(strtoupper($document['file_type'] ?? '—')) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Size</span>
            <span><?= DocumentController::formatFileSize((int)$document['file_size']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Uploaded By</span>
            <span><?= e($document['uploader_name'] ?? '—') ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Created</span>
            <time datetime="<?= e($document['created_at']) ?>"><?= format_date($document['created_at'], 'j M Y H:i') ?></time>
        </div>
        <div class="meta-item">
            <span class="meta-label">Updated</span>
            <time datetime="<?= e($document['updated_at']) ?>"><?= format_date($document['updated_at'], 'j M Y H:i') ?></time>
        </div>
        <?php if ($document['approved_by']): ?>
        <div class="meta-item">
            <span class="meta-label">Approved By</span>
            <span><?= e($document['approver_name'] ?? '—') ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Approved At</span>
            <time datetime="<?= e($document['approved_at']) ?>"><?= format_date($document['approved_at'], 'j M Y H:i') ?></time>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($document['description']): ?>
    <div class="document-description">
        <h3>Description</h3>
        <p><?= nl2br(e($document['description'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="document-actions">
        <a href="/documents/<?= (int)$document['id'] ?>/download" class="btn btn-primary">Download Current Version</a>

        <?php if ($document['status'] === 'draft'): ?>
            <form method="post" action="/documents/<?= (int)$document['id'] ?>/submit" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Submit for Approval</button>
            </form>
        <?php endif; ?>

        <?php if ($document['status'] === 'submitted'): ?>
            <form method="post" action="/documents/<?= (int)$document['id'] ?>/approve" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success">Approve</button>
            </form>
        <?php endif; ?>

        <?php if ($document['status'] !== 'archived'): ?>
            <form method="post" action="/documents/<?= (int)$document['id'] ?>/archive" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Archive</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Upload New Version -->
<section class="document-section">
    <h2>Upload New Version</h2>
    <form method="post" action="/documents/<?= (int)$document['id'] ?>/version" enctype="multipart/form-data" class="version-upload-form">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="file">File</label>
                <input type="file" name="file" id="file" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="notes">Version Notes</label>
                <input type="text" name="notes" id="notes" class="form-input" placeholder="What changed in this version?">
            </div>
            <div class="form-group form-group-btn">
                <button type="submit" class="btn btn-secondary">Upload Version</button>
            </div>
        </div>
    </form>
</section>

<!-- Version History -->
<?php if (!empty($versions)): ?>
<section class="document-section">
    <h2>Version History</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Version</th>
                <th>Uploaded By</th>
                <th>Size</th>
                <th>Notes</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($versions as $v): ?>
            <tr>
                <td>v<?= (int)$v['version_num'] ?></td>
                <td><?= e($v['uploader_name'] ?? '—') ?></td>
                <td><?= DocumentController::formatFileSize((int)$v['file_size']) ?></td>
                <td><?= e($v['notes'] ?? '—') ?></td>
                <td><time datetime="<?= e($v['created_at']) ?>"><?= format_date($v['created_at'], 'j M Y H:i') ?></time></td>
                <td>
                    <a href="/documents/<?= (int)$document['id'] ?>/versions/<?= (int)$v['id'] ?>/download" class="btn btn-sm btn-secondary">Download</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<!-- Danger Zone -->
<section class="danger-zone">
    <h3>Danger Zone</h3>
    <p>Permanently delete this document and all versions. This cannot be undone.</p>
    <form method="post" action="/documents/<?= (int)$document['id'] ?>/delete" onsubmit="return confirm('Delete this document permanently?')">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">Delete Document</button>
    </form>
</section>
