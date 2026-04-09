<?php
/**
 * File Manager — Main Browser
 *
 * Directory tree sidebar + file grid with search/filter.
 */
\IGA\Template::requireCss('admin-file-manager.css');

?>

<div class="file-manager">
    <!-- Sidebar: folder tree -->
    <aside class="fm-sidebar">
        <div class="fm-sidebar-header">
            <h3>Folders</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('new-folder-modal').style.display='flex'">+ Folder</button>
        </div>
        <nav class="fm-tree">
            <a href="/files" class="fm-tree-item <?= !$currentFolder ? 'active' : '' ?>">
                <span class="fm-tree-icon">🏠</span> All Files
            </a>
            <?php if (!empty($folderTree)): ?>
                <?php renderFolderTree($folderTree, $currentFolder['id'] ?? null); ?>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main content area -->
    <div class="fm-main">
        <!-- Toolbar -->
        <div class="fm-toolbar">
            <div class="fm-breadcrumb">
                <?php foreach ($breadcrumb as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span class="fm-breadcrumb-sep">›</span><?php endif; ?>
                    <?php if ($i < count($breadcrumb) - 1): ?>
                        <a href="<?= url($crumb['url']) ?>"><?= e($crumb['name']) ?></a>
                    <?php else: ?>
                        <span class="fm-breadcrumb-current"><?= e($crumb['name']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="fm-toolbar-actions">
                <a href="/files/upload<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>" class="btn btn-primary btn-sm">⬆ Upload</a>
                <a href="/files/compose<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>" class="btn btn-secondary btn-sm">✏ New Document</a>
            </div>
        </div>

        <!-- Search & filters -->
        <form class="fm-filters" method="get" action="/files">
            <?php if ($currentFolder): ?>
                <input type="hidden" name="folder" value="<?= (int)$currentFolder['id'] ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search files…" class="fm-search-input">
            <select name="status" class="fm-filter-select">
                <option value="">All Statuses</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending_review" <?= $status === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
            <select name="type" class="fm-filter-select">
                <option value="">All Types</option>
                <option value="docx" <?= $type === 'docx' ? 'selected' : '' ?>>Word (.docx)</option>
                <option value="pdf" <?= $type === 'pdf' ? 'selected' : '' ?>>PDF</option>
                <option value="html" <?= $type === 'html' ? 'selected' : '' ?>>HTML / Composed</option>
                <option value="txt" <?= $type === 'txt' ? 'selected' : '' ?>>Text</option>
                <option value="xlsx" <?= $type === 'xlsx' ? 'selected' : '' ?>>Excel</option>
            </select>
            <button type="submit" class="btn btn-sm">Filter</button>
            <?php if ($search || $status || $type): ?>
                <a href="/files<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>" class="btn btn-sm btn-link">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($currentFolder): ?>
            <!-- Folder info bar -->
            <div class="fm-folder-info">
                <div class="fm-folder-meta">
                    <span class="fm-folder-visibility badge badge-<?= e($currentFolder['visibility']) ?>">
                        <?= e(ucfirst($currentFolder['visibility'])) ?>
                    </span>
                    <?php if ($currentFolder['description']): ?>
                        <span class="text-muted"><?= e($currentFolder['description']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="fm-folder-actions">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('edit-folder-modal').style.display='flex'">⚙ Settings</button>
                    <?php if ($currentFolder['owner_id'] == ($current_user['id'] ?? 0) || ($current_user['role'] ?? '') === 'admin'): ?>
                        <form method="post" action="/files/folders/<?= (int)$currentFolder['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this folder? Contents will be moved to the parent folder.')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">Delete Folder</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subfolders -->
        <?php if (!empty($subfolders)): ?>
            <div class="fm-section">
                <h4 class="fm-section-title">Folders</h4>
                <div class="fm-folder-grid">
                    <?php foreach ($subfolders as $subfolder): ?>
                        <a href="/files?folder=<?= (int)$subfolder['id'] ?>" class="fm-folder-card">
                            <span class="fm-folder-card-icon">📁</span>
                            <span class="fm-folder-card-name"><?= e($subfolder['name']) ?></span>
                            <span class="fm-folder-card-count"><?= (int)($subfolder['file_count'] ?? 0) ?> files</span>
                            <span class="badge badge-xs badge-<?= e($subfolder['visibility']) ?>"><?= e(ucfirst($subfolder['visibility'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Files -->
        <div class="fm-section">
            <h4 class="fm-section-title">Files <span class="text-muted">(<?= count($files) ?>)</span></h4>

            <?php if (empty($files)): ?>
                <div class="fm-empty">
                    <p>No files in this location.</p>
                    <a href="/files/upload<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>" class="btn btn-primary">Upload a File</a>
                    <a href="/files/compose<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>" class="btn btn-secondary">Create a Document</a>
                </div>
            <?php else: ?>
                <table class="admin-table fm-file-table">
                    <thead>
                        <tr>
                            <th class="fm-col-icon"></th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Owner</th>
                            <th>Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr class="fm-file-row" onclick="window.location='/files/<?= (int)$file['id'] ?>'">
                                <td class="fm-col-icon"><?= \IGA\Services\DocumentService::fileIcon($file['file_ext'] ?? '') ?></td>
                                <td>
                                    <a href="/files/<?= (int)$file['id'] ?>" class="fm-file-title"><?= e($file['title']) ?></a>
                                    <?php if ($file['original_name'] && $file['original_name'] !== $file['title']): ?>
                                        <br><small class="text-muted"><?= e($file['original_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-xs"><?= e(strtoupper($file['file_ext'] ?? 'DOC')) ?></span></td>
                                <td class="text-muted"><?= \IGA\Services\DocumentService::formatSize((int)($file['file_size'] ?? 0)) ?></td>
                                <td class="text-muted"><?= e($file['subject_title'] ?? '—') ?></td>
                                <td><span class="badge badge-status-<?= e($file['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $file['status']))) ?></span></td>
                                <td class="text-muted"><?= e($file['owner_name'] ?? '—') ?></td>
                                <td class="text-muted"><time><?= format_date($file['updated_at'], 'j M Y') ?></time></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal-overlay" id="new-folder-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content">
        <h3>New Folder</h3>
        <form method="post" action="/files/folders">
            <?= csrf_field() ?>
            <?php if ($currentFolder): ?>
                <input type="hidden" name="parent_id" value="<?= (int)$currentFolder['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="folder-name">Name <span class="required">*</span></label>
                <input type="text" id="folder-name" name="name" required>
            </div>

            <div class="form-group">
                <label for="folder-desc">Description</label>
                <input type="text" id="folder-desc" name="description" placeholder="Optional description">
            </div>

            <div class="form-group">
                <label for="folder-visibility">Visibility</label>
                <select id="folder-visibility" name="visibility" onchange="document.getElementById('role-select-group').style.display=this.value==='role'?'block':'none'">
                    <option value="private">Private (owner only)</option>
                    <option value="role">Specific Roles</option>
                    <option value="members">All Members</option>
                    <option value="public">Public</option>
                </select>
            </div>

            <div class="form-group" id="role-select-group" style="display:none">
                <label>Allowed Roles</label>
                <?php
                $allRoles = $roles ?? [];
                if (empty($allRoles)) {
                    $db = \IGA\Database::getInstance();
                    $allRoles = $db->fetchAll('SELECT id, name FROM roles ORDER BY name');
                }
                ?>
                <?php foreach ($allRoles as $role): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="allowed_roles[]" value="<?= (int)$role['id'] ?>">
                        <?= e($role['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label for="folder-subject">Subject</label>
                <select id="folder-subject" name="subject_id">
                    <option value="">— None —</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Folder</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if ($currentFolder): ?>
<!-- Edit Folder Modal -->
<div class="modal-overlay" id="edit-folder-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content">
        <h3>Folder Settings: <?= e($currentFolder['name']) ?></h3>
        <form method="post" action="/files/folders/<?= (int)$currentFolder['id'] ?>/update">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="edit-folder-name">Name</label>
                <input type="text" id="edit-folder-name" name="name" value="<?= e($currentFolder['name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="edit-folder-desc">Description</label>
                <input type="text" id="edit-folder-desc" name="description" value="<?= e($currentFolder['description'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="edit-folder-visibility">Visibility</label>
                <select id="edit-folder-visibility" name="visibility" onchange="document.getElementById('edit-role-select-group').style.display=this.value==='role'?'block':'none'">
                    <?php foreach (['private', 'role', 'members', 'public'] as $vis): ?>
                        <option value="<?= $vis ?>" <?= $currentFolder['visibility'] === $vis ? 'selected' : '' ?>><?= ucfirst($vis) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="edit-role-select-group" style="<?= $currentFolder['visibility'] === 'role' ? '' : 'display:none' ?>">
                <label>Allowed Roles</label>
                <?php $currentRoles = json_decode($currentFolder['allowed_roles'] ?? '[]', true) ?: []; ?>
                <?php foreach ($allRoles as $role): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="allowed_roles[]" value="<?= (int)$role['id'] ?>" <?= in_array($role['id'], $currentRoles) ? 'checked' : '' ?>>
                        <?= e($role['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label for="edit-folder-subject">Subject</label>
                <select id="edit-folder-subject" name="subject_id">
                    <option value="">— None —</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ($currentFolder['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
/**
 * Render folder tree recursively.
 */
function renderFolderTree(array $folders, ?int $activeId, int $depth = 0): void {
    foreach ($folders as $folder) {
        $isActive = $activeId === (int)$folder['id'];
        $indent = str_repeat('  ', $depth);
        $hasChildren = !empty($folder['children']);
        ?>
        <div class="fm-tree-group" style="padding-left: <?= $depth * 1.2 ?>rem">
            <a href="/files?folder=<?= (int)$folder['id'] ?>" class="fm-tree-item <?= $isActive ? 'active' : '' ?>">
                <span class="fm-tree-icon"><?= $hasChildren ? '📂' : '📁' ?></span>
                <span class="fm-tree-name"><?= e($folder['name']) ?></span>
                <span class="fm-tree-count"><?= (int)($folder['file_count'] ?? 0) ?></span>
            </a>
            <?php if ($hasChildren): ?>
                <?php renderFolderTree($folder['children'], $activeId, $depth + 1); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>
