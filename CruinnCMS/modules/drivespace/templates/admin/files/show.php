<?php
/**
 * Drivespace — File Detail View
 *
 * Shows file info, parsed content preview, version history,
 * sharing controls, and publish workflow.
 */
\Cruinn\Template::requireCss('admin-drivespace.css');


use Cruinn\Services\DocumentService;

$statusLabels = [
    'draft' => 'Draft',
    'pending_review' => 'Pending Review',
    'approved' => 'Approved',
    'published' => 'Published',
    'archived' => 'Archived',
];
$metadata = json_decode($file['metadata'] ?? '{}', true) ?: [];
?>
<div class="admin-page">
    <!-- Header -->
    <div class="admin-page-header">
        <div>
            <h1>
                <?= DocumentService::fileIcon($file['file_ext'] ?? '') ?>
                <?= e($file['title']) ?>
            </h1>
            <?php if ($file['folder_name']): ?>
                <p class="text-muted">in <a href="/drivespace?folder=<?= (int)$file['folder_id'] ?>"><?= e($file['folder_name']) ?></a></p>
            <?php endif; ?>
        </div>
        <div class="admin-page-actions">
            <a href="/drivespace<?= $file['folder_id'] ? '?folder=' . (int)$file['folder_id'] : '' ?>" class="btn btn-secondary">← Back</a>
        </div>
    </div>

    <!-- Meta cards row -->
    <div class="fm-detail-grid">
        <div class="fm-detail-card">
            <span class="fm-detail-label">Status</span>
            <span class="badge badge-status-<?= e($file['status']) ?>">
                <?= e($statusLabels[$file['status']] ?? $file['status']) ?>
            </span>
        </div>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Type</span>
            <span><?= e(strtoupper($file['file_ext'] ?? 'DOC')) ?> · <?= e($file['content_type'] === 'composed' ? 'Composed' : 'Uploaded') ?></span>
        </div>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Version</span>
            <span>v<?= (int)$file['version'] ?></span>
        </div>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Size</span>
            <span><?= DocumentService::formatSize((int)($file['file_size'] ?? 0)) ?></span>
        </div>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Owner</span>
            <span><?= e($file['owner_name'] ?? '—') ?></span>
        </div>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Subject</span>
            <span><?= e($file['subject_title'] ?? '—') ?></span>
        </div>
        <?php if (!empty($metadata['word_count'])): ?>
            <div class="fm-detail-card">
                <span class="fm-detail-label">Words</span>
                <span><?= number_format($metadata['word_count']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($metadata['page_count'])): ?>
            <div class="fm-detail-card">
                <span class="fm-detail-label">Pages</span>
                <span><?= (int)$metadata['page_count'] ?></span>
            </div>
        <?php endif; ?>
        <div class="fm-detail-card">
            <span class="fm-detail-label">Modified</span>
            <span><time><?= format_date($file['updated_at'], 'j M Y H:i') ?></time></span>
        </div>
    </div>

    <?php if ($file['description']): ?>
        <div class="fm-description">
            <p><?= e($file['description']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Action buttons -->
    <div class="fm-actions">
        <?php if ($file['file_path']): ?>
            <a href="/drivespace/<?= (int)$file['id'] ?>/download" class="btn btn-primary">⬇ Download</a>
        <?php endif; ?>

        <?php if ($canEdit): ?>
            <form method="post" action="/drivespace/<?= (int)$file['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Permanently delete this file and all versions?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">🗑 Delete</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="fm-tabs">
        <button class="fm-tab active" onclick="switchTab(this, 'preview')">Preview</button>
        <button class="fm-tab" onclick="switchTab(this, 'versions')">Versions (<?= count($versions) ?>)</button>
        <button class="fm-tab" onclick="switchTab(this, 'sharing')">Sharing (<?= count($shares) ?>)</button>
        <button class="fm-tab" onclick="switchTab(this, 'upload-version')">New Version</button>
    </div>

    <!-- Tab: Preview -->
    <div class="fm-tab-content" id="tab-preview">
        <?php if ($file['parsed_content']): ?>
            <div class="fm-content-preview">
                <?= $file['parsed_content'] ?>
            </div>
        <?php elseif ($file['file_path']): ?>
            <?php
            $previewExt = strtolower($file['file_ext'] ?? '');
            $isImage = in_array($previewExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $isPdf = $previewExt === 'pdf';
            ?>
            <?php if ($isImage): ?>
                <div class="fm-image-preview">
                    <img src="<?= e($file['file_path']) ?>" alt="<?= e($file['title']) ?>">
                </div>
            <?php elseif ($isPdf): ?>
                <div class="fm-pdf-preview">
                    <iframe src="<?= e($file['file_path']) ?>" width="100%" height="600"></iframe>
                </div>
            <?php else: ?>
                <div class="fm-no-preview">
                    <p>No preview available for this file type.</p>
                    <a href="/drivespace/<?= (int)$file['id'] ?>/download" class="btn btn-primary">Download to View</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="fm-no-preview">
                <p>This document has no content yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab: Versions -->
    <div class="fm-tab-content" id="tab-versions" style="display:none">
        <?php if (empty($versions)): ?>
            <p class="text-muted">No version history.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>By</th>
                        <th>Size</th>
                        <th>Notes</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versions as $v): ?>
                        <tr <?= (int)$v['version_num'] === (int)$file['version'] ? 'class="fm-current-version"' : '' ?>>
                            <td>
                                v<?= (int)$v['version_num'] ?>
                                <?php if ((int)$v['version_num'] === (int)$file['version']): ?>
                                    <span class="badge badge-xs badge-success">Current</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($v['creator_name'] ?? '—') ?></td>
                            <td><?= DocumentService::formatSize((int)($v['file_size'] ?? 0)) ?></td>
                            <td><?= e($v['notes'] ?? '—') ?></td>
                            <td><time><?= format_date($v['created_at'], 'j M Y H:i') ?></time></td>
                            <td>
                                <?php if ($v['file_path']): ?>
                                    <a href="<?= e($v['file_path']) ?>" class="btn btn-sm btn-secondary" download>Download</a>
                                <?php else: ?>
                                    <span class="text-muted">Composed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Tab: Sharing -->
    <div class="fm-tab-content" id="tab-sharing" style="display:none">
        <?php if ($canEdit): ?>
            <form method="post" action="/drivespace/<?= (int)$file['id'] ?>/share" class="fm-share-form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Share with</label>
                        <select name="target_type" id="share-target-type" onchange="toggleShareTarget(this.value)">
                            <option value="user">User</option>
                            <option value="role">Role</option>
                        </select>
                    </div>
                    <div class="form-group" id="share-user-group">
                        <label>User</label>
                        <select name="target_id" id="share-user-select">
                            <option value="">Select user…</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= e($u['display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="share-role-group" style="display:none">
                        <label>Role</label>
                        <select name="target_id" id="share-role-select" disabled>
                            <option value="">Select role…</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Permission</label>
                        <select name="permission">
                            <option value="view">View</option>
                            <option value="edit">Edit</option>
                            <option value="manage">Manage</option>
                        </select>
                    </div>
                    <div class="form-group form-group-btn">
                        <button type="submit" class="btn btn-primary btn-sm">Share</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php if (empty($shares)): ?>
            <p class="text-muted">Not shared with anyone beyond folder permissions.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Shared With</th>
                        <th>Type</th>
                        <th>Permission</th>
                        <th>Date</th>
                        <?php if ($canEdit): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shares as $share): ?>
                        <tr>
                            <td><?= e($share['target_name'] ?? '—') ?></td>
                            <td><span class="badge badge-xs"><?= e(ucfirst($share['target_type'])) ?></span></td>
                            <td><span class="badge badge-xs badge-<?= e($share['permission']) ?>"><?= e(ucfirst($share['permission'])) ?></span></td>
                            <td><time><?= format_date($share['created_at'], 'j M Y') ?></time></td>
                            <?php if ($canEdit): ?>
                                <td>
                                    <form method="post" action="/drivespace/<?= (int)$file['id'] ?>/unshare" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Tab: Upload New Version -->
    <div class="fm-tab-content" id="tab-upload-version" style="display:none">
        <?php if ($canEdit): ?>
            <form method="post" action="/drivespace/<?= (int)$file['id'] ?>/version" enctype="multipart/form-data" class="form-card">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="version-file">New Version File</label>
                    <input type="file" name="file" id="version-file" required>
                </div>
                <div class="form-group">
                    <label for="version-notes">Version Notes</label>
                    <input type="text" name="notes" id="version-notes" placeholder="What changed?">
                </div>
                <button type="submit" class="btn btn-primary">Upload Version</button>
            </form>
        <?php else: ?>
            <p class="text-muted">You do not have permission to upload new versions.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(btn, tabId) {
    // Deactivate all tabs
    document.querySelectorAll('.fm-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.fm-tab-content').forEach(c => c.style.display = 'none');

    // Activate selected
    btn.classList.add('active');
    document.getElementById('tab-' + tabId).style.display = 'block';
}

function toggleShareTarget(type) {
    const userGroup = document.getElementById('share-user-group');
    const roleGroup = document.getElementById('share-role-group');
    const userSelect = document.getElementById('share-user-select');
    const roleSelect = document.getElementById('share-role-select');

    if (type === 'user') {
        userGroup.style.display = '';
        roleGroup.style.display = 'none';
        userSelect.disabled = false;
        userSelect.name = 'target_id';
        roleSelect.disabled = true;
        roleSelect.removeAttribute('name');
    } else {
        userGroup.style.display = 'none';
        roleGroup.style.display = '';
        roleSelect.disabled = false;
        roleSelect.name = 'target_id';
        userSelect.disabled = true;
        userSelect.removeAttribute('name');
    }
}
</script>
