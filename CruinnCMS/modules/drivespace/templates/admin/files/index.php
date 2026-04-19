<?php
/**
 * Drivespace — 3-column file manager
 *
 * Left:   folder tree (collapsible, navigate on click)
 * Middle: folder contents (subfolders + files; single-click = select, double-click = open/navigate)
 * Right:  properties / permissions panel (populated via AJAX on selection)
 */
\Cruinn\Template::requireCss('admin-drivespace.css');

// Fetch roles for the new-folder form (visibility = role)
$db = \Cruinn\Database::getInstance();
$allRoles = $db->fetchAll('SELECT id, name FROM roles ORDER BY name');

// Quota for the current user
$quotaUser = $db->fetch(
    'SELECT drivespace_quota_bytes, drivespace_used_bytes FROM users WHERE id = ?',
    [\Cruinn\Auth::userId()]
);
$quotaTotal = (int)($quotaUser['drivespace_quota_bytes'] ?? 0);
$quotaUsed  = (int)($quotaUser['drivespace_used_bytes']  ?? 0);
$quotaPct   = $quotaTotal > 0 ? min(100, round($quotaUsed / $quotaTotal * 100)) : 0;
$quotaClass = $quotaPct >= 90 ? 'danger' : ($quotaPct >= 75 ? 'warn' : '');

/**
 * Render folder tree nodes recursively.
 */
function renderTreeNodes(array $nodes, ?int $activeId, int $depth = 0): void {
    foreach ($nodes as $node):
        $isActive   = $activeId === (int)$node['id'];
        $hasKids    = !empty($node['children']);
        $indent     = $depth * 20 + 8;
        $openClass  = ($isActive && $hasKids) ? ' open' : '';
?>
        <div class="fm-tree-node">
            <a href="/drivespace?folder=<?= (int)$node['id'] ?>"
               class="fm-tree-row<?= $isActive ? ' active' : '' ?>"
               style="padding-left: <?= $indent ?>px"
               data-folder-id="<?= (int)$node['id'] ?>"
               onclick="return handleTreeClick(event, <?= (int)$node['id'] ?>, this)"
               ondblclick="event.preventDefault(); navigateFolder(<?= (int)$node['id'] ?>)">
                <span class="fm-tree-caret<?= $hasKids ? '' : ' leaf' ?><?= $openClass ?>"
                      onclick="event.preventDefault(); event.stopPropagation(); toggleTreeNode(this)">▶</span>
                <span class="fm-tree-icon"><?= $hasKids ? '📂' : '📁' ?></span>
                <span class="fm-tree-name"><?= e($node['name']) ?></span>
                <span class="fm-tree-count"><?= (int)($node['file_count'] ?? 0) ?></span>
            </a>
            <?php if ($hasKids): ?>
            <div class="fm-tree-children<?= $openClass ?>">
                <?php renderTreeNodes($node['children'], $activeId, $depth + 1); ?>
            </div>
            <?php endif; ?>
        </div>
<?php
    endforeach;
}

?>

<div class="drivespace" id="drivespace-app">

    <!-- ── Left: Folder Tree ──────────────────────────────────── -->
    <div class="fm-tree-panel">
        <div class="fm-tree-header">
            <h3>Folders</h3>
            <button type="button" class="btn btn-sm btn-primary"
                    onclick="document.getElementById('new-folder-modal').style.display='flex'">+ New</button>
        </div>
        <div class="fm-tree-scroll">
            <a href="/drivespace"
               class="fm-tree-root<?= !$currentFolder ? ' active' : '' ?>">
                <span>🏠</span> All Files
            </a>
            <?php if (!empty($folderTree)): ?>
                <?php renderTreeNodes($folderTree, $currentFolder['id'] ?? null); ?>
            <?php endif; ?>
        </div>
        <?php if ($quotaTotal > 0): ?>
        <div class="fm-quota-bar-wrap">
            <div class="fm-quota-label">
                <span>Storage</span>
                <span><?= \Cruinn\Module\Drivespace\Services\DocumentService::formatSize($quotaUsed) ?> / <?= \Cruinn\Module\Drivespace\Services\DocumentService::formatSize($quotaTotal) ?></span>
            </div>
            <div class="fm-quota-bar">
                <div class="fm-quota-fill <?= $quotaClass ?>" style="width:<?= $quotaPct ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Middle: Contents ───────────────────────────────────── -->
    <div class="fm-content-panel">

        <!-- Toolbar / breadcrumb -->
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
                <?php if ($currentFolder): ?>
                    <button type="button" class="btn btn-sm btn-secondary"
                            onclick="document.getElementById('edit-folder-modal').style.display='flex'">⚙ Folder</button>
                <?php endif; ?>
                <a href="/drivespace/upload<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>"
                   class="btn btn-sm btn-primary">⬆ Upload</a>
            </div>
        </div>

        <!-- Search bar -->
        <form class="fm-search-bar" method="get" action="/drivespace">
            <?php if ($currentFolder): ?>
                <input type="hidden" name="folder" value="<?= (int)$currentFolder['id'] ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= e($search) ?>"
                   placeholder="Search files…" class="fm-search-input">
            <select name="type" style="font-size:0.82rem;padding:0.3rem 0.5rem;border:1px solid var(--color-border,#ccd9d3);border-radius:4px;background:var(--color-bg-light,#f2f5f3)">
                <option value="">All types</option>
                <option value="docx" <?= $type === 'docx' ? 'selected' : '' ?>>Word (.docx)</option>
                <option value="pdf"  <?= $type === 'pdf'  ? 'selected' : '' ?>>PDF</option>
                <option value="xlsx" <?= $type === 'xlsx' ? 'selected' : '' ?>>Excel</option>
                <option value="txt"  <?= $type === 'txt'  ? 'selected' : '' ?>>Text</option>
                <option value="html" <?= $type === 'html' ? 'selected' : '' ?>>HTML</option>
            </select>
            <button type="submit" class="btn btn-sm">Filter</button>
            <?php if ($search || $type): ?>
                <a href="/drivespace<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>"
                   class="btn btn-sm btn-link">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Contents -->
        <div class="fm-contents-scroll" id="fm-contents">

            <?php if (empty($subfolders) && empty($files)): ?>
                <div class="fm-empty">
                    <p>This folder is empty.</p>
                    <a href="/drivespace/upload<?= $currentFolder ? '?folder=' . (int)$currentFolder['id'] : '' ?>"
                       class="btn btn-primary">Upload a File</a>
                </div>
            <?php else: ?>

                <?php if (!empty($subfolders)): ?>
                <p class="fm-section-title">Folders</p>
                <table class="fm-items-table">
                    <thead>
                        <tr>
                            <th class="fm-col-icon"></th>
                            <th>Name</th>
                            <th>Visibility</th>
                            <th>Files</th>
                            <th>Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subfolders as $sf): ?>
                        <tr data-type="folder" data-id="<?= (int)$sf['id'] ?>"
                            onclick="selectItem('folder', <?= (int)$sf['id'] ?>, this)"
                            ondblclick="navigateFolder(<?= (int)$sf['id'] ?>)">
                            <td class="fm-col-icon">📁</td>
                            <td class="fm-col-name">
                                <strong><?= e($sf['name']) ?></strong>
                                <?php if ($sf['description'] ?? ''): ?><small><?= e($sf['description']) ?></small><?php endif; ?>
                            </td>
                            <td class="fm-col-vis"><span class="badge badge-xs badge-<?= e($sf['visibility']) ?>"><?= e(ucfirst($sf['visibility'])) ?></span></td>
                            <td class="fm-col-meta"><?= (int)($sf['file_count'] ?? 0) ?></td>
                            <td class="fm-col-meta"><?= format_date($sf['updated_at'] ?? '', 'j M Y') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($files)): ?>
                <p class="fm-section-title" style="<?= !empty($subfolders) ? 'margin-top:1rem' : '' ?>">
                    Files <span style="font-weight:400;color:#bbb">(<?= count($files) ?>)</span>
                </p>
                <table class="fm-items-table">
                    <thead>
                        <tr>
                            <th class="fm-col-icon"></th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                        <tr data-type="file" data-id="<?= (int)$file['id'] ?>"
                            onclick="selectItem('file', <?= (int)$file['id'] ?>, this)"
                            ondblclick="openFile(<?= (int)$file['id'] ?>)">
                            <td class="fm-col-icon"><?= \Cruinn\Module\Drivespace\Services\DocumentService::fileIcon($file['file_ext'] ?? '') ?></td>
                            <td class="fm-col-name">
                                <strong><?= e($file['title']) ?></strong>
                                <?php if ($file['original_name'] && $file['original_name'] !== $file['title']): ?>
                                    <small><?= e($file['original_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="fm-col-meta"><span class="badge badge-xs"><?= e(strtoupper($file['file_ext'] ?? '—')) ?></span></td>
                            <td class="fm-col-meta"><?= \Cruinn\Module\Drivespace\Services\DocumentService::formatSize((int)($file['file_size'] ?? 0)) ?></td>
                            <td class="fm-col-meta"><span class="badge badge-xs badge-status-<?= e($file['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $file['status']))) ?></span></td>
                            <td class="fm-col-meta"><time><?= format_date($file['updated_at'], 'j M Y') ?></time></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Properties Panel ────────────────────────────── -->
    <div class="fm-properties-panel">
        <div class="fm-props-header">
            <h3>Properties</h3>
        </div>
        <div class="fm-props-scroll" id="fm-props-content">
            <div class="fm-props-placeholder">
                <span class="fm-props-placeholder-icon">📋</span>
                <span>Select a file or folder to see its properties and permissions.</span>
            </div>
        </div>
    </div>

</div>

<!-- ── New Folder Modal ───────────────────────────────────────── -->
<div class="modal-overlay" id="new-folder-modal"
     style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content">
        <h3>New Folder</h3>
        <form method="post" action="/drivespace/folders">
            <?= csrf_field() ?>
            <?php if ($currentFolder): ?>
                <input type="hidden" name="parent_id" value="<?= (int)$currentFolder['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="folder-name">Name <span class="required">*</span></label>
                <input type="text" id="folder-name" name="name" required autofocus>
            </div>

            <div class="form-group">
                <label for="folder-desc">Description</label>
                <input type="text" id="folder-desc" name="description" placeholder="Optional">
            </div>

            <div class="form-group">
                <label for="folder-visibility">Visibility</label>
                <select id="folder-visibility" name="visibility"
                        onchange="document.getElementById('new-role-group').style.display=this.value==='role'?'block':'none'">
                    <option value="private">Private (owner only)</option>
                    <option value="role">Specific Roles</option>
                    <option value="members">All Members</option>
                    <option value="public">Public</option>
                </select>
            </div>

            <div class="form-group" id="new-role-group" style="display:none">
                <label>Allowed Roles</label>
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
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if ($currentFolder): ?>
<!-- ── Edit Folder Modal ──────────────────────────────────────── -->
<div class="modal-overlay" id="edit-folder-modal"
     style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content">
        <h3>Edit Folder: <?= e($currentFolder['name']) ?></h3>
        <form method="post" action="/drivespace/folders/<?= (int)$currentFolder['id'] ?>/update">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="edit-folder-name">Name</label>
                <input type="text" id="edit-folder-name" name="name"
                       value="<?= e($currentFolder['name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="edit-folder-desc">Description</label>
                <input type="text" id="edit-folder-desc" name="description"
                       value="<?= e($currentFolder['description'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="edit-folder-vis">Visibility</label>
                <select id="edit-folder-vis" name="visibility"
                        onchange="document.getElementById('edit-role-group').style.display=this.value==='role'?'block':'none'">
                    <?php foreach (['private' => 'Private (owner only)', 'role' => 'Specific Roles', 'members' => 'All Members', 'public' => 'Public'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $currentFolder['visibility'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="edit-role-group"
                 style="<?= $currentFolder['visibility'] === 'role' ? '' : 'display:none' ?>">
                <label>Allowed Roles</label>
                <?php $currentRoles = json_decode($currentFolder['allowed_roles'] ?? '[]', true) ?: []; ?>
                <?php foreach ($allRoles as $role): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="allowed_roles[]" value="<?= (int)$role['id'] ?>"
                               <?= in_array($role['id'], $currentRoles) ? 'checked' : '' ?>>
                        <?= e($role['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label for="edit-folder-subject">Subject</label>
                <select id="edit-folder-subject" name="subject_id">
                    <option value="">— None —</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                                <?= ($currentFolder['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <?php if ($currentFolder['owner_id'] == \Cruinn\Auth::userId() || \Cruinn\Auth::role() === 'admin'): ?>
                <form method="post"
                      action="/drivespace/folders/<?= (int)$currentFolder['id'] ?>/delete"
                      style="display:inline"
                      onsubmit="return confirm('Delete this folder? Contents will be moved to the parent.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Delete Folder</button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    'use strict';

    var selectedRow = null;
    var clickTimer  = null;

    // ── Selection ─────────────────────────────────────────────
    window.selectItem = function (type, id, row) {
        if (selectedRow) selectedRow.classList.remove('selected');
        selectedRow = row;
        row.classList.add('selected');
        loadProperties(type, id);
    };

    window.navigateFolder = function (id) {
        if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
        window.location = '/drivespace?folder=' + id;
    };

    window.openFile = function (id) {
        if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
        window.location = '/drivespace/' + id;
    };

    // ── Tree single/double click ──────────────────────────────
    window.handleTreeClick = function (e, id) {
        e.preventDefault();
        if (clickTimer) {
            clearTimeout(clickTimer);
            clickTimer = null;
            navigateFolder(id);
            return false;
        }
        clickTimer = setTimeout(function () {
            clickTimer = null;
            loadProperties('folder', id);
        }, 220);
        return false;
    };

    // ── Tree expand/collapse ──────────────────────────────────
    window.toggleTreeNode = function (caretEl) {
        var node     = caretEl.closest('.fm-tree-node');
        var children = node.querySelector('.fm-tree-children');
        if (!children) return;
        var isOpen = children.classList.toggle('open');
        caretEl.classList.toggle('open', isOpen);
    };

    // Auto-expand to active folder on load
    var activeRow = document.querySelector('.fm-tree-row.active');
    if (activeRow) {
        var parent = activeRow.closest('.fm-tree-children');
        while (parent) {
            parent.classList.add('open');
            var parentNode = parent.parentElement;
            if (parentNode) {
                var caret = parentNode.querySelector(':scope > .fm-tree-row .fm-tree-caret');
                if (caret) caret.classList.add('open');
            }
            parent = parent.parentElement
                ? parent.parentElement.closest('.fm-tree-children') : null;
        }
    }

    // ── Load properties via AJAX ──────────────────────────────
    function loadProperties(type, id) {
        var panel = document.getElementById('fm-props-content');
        panel.innerHTML = '<div class="fm-props-loading">Loading\u2026</div>';

        var url = '/drivespace/' + (type === 'folder' ? 'folder' : 'file') + '/' + id + '/info';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data.error) {
                    panel.innerHTML = '<div class="fm-props-placeholder"><span class="fm-props-placeholder-icon">\u26a0\ufe0f</span><span>' + esc(data.error) + '</span></div>';
                    return;
                }
                if (type === 'folder') renderFolderProps(data, panel);
                else                   renderFileProps(data, panel);
            })
            .catch(function () {
                panel.innerHTML = '<div class="fm-props-placeholder"><span>Failed to load properties.</span></div>';
            });
    }

    // ── Folder properties ─────────────────────────────────────
    function renderFolderProps(data, panel) {
        var f      = data.folder;
        var shares = data.shares || [];
        var canEdit = !!data.can_edit;

        var visLabels  = { private: 'Private', role: 'Role-gated', members: 'All Members', public: 'Public' };
        var visBadges  = { private: 'badge-private', role: 'badge-role', members: 'badge-members', public: 'badge-public' };

        var html = '<div class="fm-props-icon">\ud83d\udcc1</div>';
        html += '<div class="fm-props-title">' + esc(f.name) + '</div>';
        html += '<div class="fm-props-subtitle">' + esc(f.file_count) + ' file(s) \u00b7 ' + esc(f.subfolder_count) + ' subfolder(s)</div>';

        html += '<div class="fm-props-actions">';
        html += '<a href="/drivespace?folder=' + f.id + '" class="btn btn-primary">Open \u2192</a>';
        if (canEdit) html += '<a href="/drivespace/upload?folder=' + f.id + '" class="btn btn-secondary">Upload here</a>';
        html += '</div>';

        html += '<table class="fm-props-meta">';
        html += mr('Owner',      esc(f.owner_name || '\u2014'));
        html += mr('Visibility', '<span class="badge badge-xs ' + (visBadges[f.visibility] || '') + '">' + esc(visLabels[f.visibility] || f.visibility) + '</span>');
        html += mr('Files',      esc(String(f.file_count)));
        html += mr('Subfolders', esc(String(f.subfolder_count)));
        if (f.description) html += mr('Description', esc(f.description));
        if (f.subject_title) html += mr('Subject', esc(f.subject_title));
        html += mr('Created',  esc(fmtDate(f.created_at)));
        html += mr('Modified', esc(fmtDate(f.updated_at)));
        html += '</table>';

        html += '<p class="fm-props-section-title">Permissions</p>';
        if (shares.length === 0) {
            var msg = f.visibility === 'private'  ? 'Private \u2014 owner access only.' :
                      f.visibility === 'public'   ? 'Public \u2014 visible to everyone.' :
                      'Access via folder visibility. No explicit shares.';
            html += '<p style="font-size:0.82rem;color:#aaa">' + msg + '</p>';
        } else {
            html += '<ul class="fm-shares-list">';
            shares.forEach(function (s) {
                var icon = s.target_type === 'role' ? '\ud83d\udc65' : '\ud83d\udc64';
                html += '<li><span class="fm-share-target">' + icon + ' ' + esc(s.target_name || 'ID:' + s.target_id) + '</span><span class="fm-share-perm">' + esc(s.permission) + '</span></li>';
            });
            html += '</ul>';
        }

        if (canEdit) html += '<p style="margin-top:0.5rem"><a href="/drivespace?folder=' + f.id + '" class="btn btn-sm btn-secondary" style="width:100%">\u2699 Manage Folder</a></p>';

        panel.innerHTML = html;
    }

    // ── File properties ───────────────────────────────────────
    function renderFileProps(data, panel) {
        var f      = data.file;
        var shares = data.shares || [];
        var canEdit = !!data.can_edit;

        var statusLabels = { draft: 'Draft', pending_review: 'Pending Review', approved: 'Approved', published: 'Published', archived: 'Archived' };
        var statusBadges = { draft: 'badge-status-draft', pending_review: 'badge-status-pending_review', approved: 'badge-status-approved', published: 'badge-status-published', archived: 'badge-status-archived' };
        var extIcons = { pdf:'📄', docx:'📝', doc:'📝', xlsx:'📊', xls:'📊', pptx:'📊', ppt:'📊', txt:'📃', csv:'📃', jpg:'🖼', jpeg:'🖼', png:'🖼', gif:'🖼', svg:'🖼', zip:'🗜', mp4:'🎬', mp3:'🎵', html:'🌐', htm:'🌐', php:'⚙' };

        var ext   = (f.file_ext || '').toLowerCase();
        var icon  = extIcons[ext] || '📄';

        var html = '<div class="fm-props-icon">' + icon + '</div>';
        html += '<div class="fm-props-title">' + esc(f.title) + '</div>';
        html += '<div class="fm-props-subtitle">' + ext.toUpperCase() + (f.original_name && f.original_name !== f.title ? ' \u00b7 ' + esc(f.original_name) : '') + '</div>';

        html += '<div class="fm-props-actions">';
        html += '<a href="/drivespace/' + f.id + '" class="btn btn-primary">Open</a>';
        html += '<a href="/drivespace/' + f.id + '/download" class="btn btn-secondary">Download</a>';
        html += '</div>';

        html += '<table class="fm-props-meta">';
        html += mr('Owner',    esc(f.owner_name || '\u2014'));
        html += mr('Size',     fmtBytes(parseInt(f.file_size, 10) || 0));
        html += mr('Status',   '<span class="badge badge-xs ' + (statusBadges[f.status] || '') + '">' + esc(statusLabels[f.status] || f.status) + '</span>');
        html += mr('Version',  'v' + esc(String(f.version)));
        html += mr('History',  esc(String(f.version_count)) + ' version(s)');
        if (f.folder_name)    html += mr('Folder',  esc(f.folder_name));
        if (f.subject_title)  html += mr('Subject', esc(f.subject_title));
        if (f.description)    html += mr('Notes',   esc(f.description));
        html += mr('Modified', fmtDate(f.updated_at));
        html += mr('Created',  fmtDate(f.created_at));
        html += '</table>';

        html += '<p class="fm-props-section-title">Shares</p>';
        if (shares.length === 0) {
            html += '<p style="font-size:0.82rem;color:#aaa">Not shared explicitly.</p>';
        } else {
            html += '<ul class="fm-shares-list">';
            shares.forEach(function (s) {
                var icon2 = s.target_type === 'role' ? '\ud83d\udc65' : '\ud83d\udc64';
                html += '<li><span class="fm-share-target">' + icon2 + ' ' + esc(s.target_name || 'ID:' + s.target_id) + '</span><span class="fm-share-perm">' + esc(s.permission) + '</span></li>';
            });
            html += '</ul>';
        }

        html += '<p style="margin-top:0.5rem"><a href="/drivespace/' + f.id + '" class="btn btn-sm btn-secondary" style="width:100%">\u2139 Full Details &amp; Sharing</a></p>';

        panel.innerHTML = html;
    }

    // ── Utilities ─────────────────────────────────────────────
    function mr(label, value) {
        return '<tr><th>' + esc(label) + '</th><td>' + value + '</td></tr>';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmtDate(str) {
        if (!str) return '\u2014';
        try {
            var d = new Date(str.replace(' ', 'T'));
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return str; }
    }

    function fmtBytes(b) {
        if (!b) return '\u2014';
        var units = ['B','KB','MB','GB'], i = 0;
        while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
        return (i === 0 ? b : b.toFixed(1)) + '\u00a0' + units[i];
    }

}());
</script>
