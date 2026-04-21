<?php
/**
 * Admin — Media Library (3-panel layout)
 */
\Cruinn\Template::requireCss('admin-panel-layout.css');
?>

<style>
/* Media-specific overrides */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem 1rem;
}
.media-thumb {
    border: 2px solid var(--color-border, #ccd9d3);
    border-radius: 6px;
    background: #fff;
    padding: 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.1s, box-shadow 0.1s;
    position: relative;
}
.media-thumb:hover { border-color: var(--color-primary, #1d9e75); box-shadow: 0 2px 6px rgba(0,0,0,.08); }
.media-thumb.selected { border-color: var(--color-primary, #1d9e75); box-shadow: 0 0 0 2px #5dcaa580; }
.media-thumb img { width: 100%; height: 80px; object-fit: cover; border-radius: 3px; background: #f2f5f3; display: block; }
.media-thumb-name { font-size: 0.72rem; color: #555; margin-top: 0.3rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-thumb-del { position: absolute; top: 3px; right: 3px; background: rgba(200,30,30,.85); color: #fff; border: none; border-radius: 3px; font-size: 0.65rem; padding: 1px 5px; cursor: pointer; display: none; line-height: 1.4; }
.media-thumb:hover .media-thumb-del { display: block; }

/* Folder tree in sidebar */
.mf-folder {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.9rem;
    font-size: 0.84rem;
    color: var(--color-text, #0c1614);
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.1s, border-color 0.1s;
    user-select: none;
}
.mf-folder:hover { background: var(--color-bg-light, #f2f5f3); }
.mf-folder.active { border-left-color: var(--color-primary, #1d9e75); background: #eaf6f1; font-weight: 600; }

/* Detail image preview */
.media-detail-img { width: 100%; max-height: 160px; object-fit: contain; border-radius: 4px; background: #f2f5f3; margin-bottom: 0.75rem; display: block; }
.media-copy-url { font-size: 0.75rem; font-family: monospace; word-break: break-all; background: #f2f5f3; border-radius: 4px; padding: 0.3rem 0.5rem; margin-bottom: 0.75rem; cursor: pointer; border: 1px solid #ddd; color: #333; }
.media-copy-url:hover { border-color: var(--color-primary, #1d9e75); }
</style>

<div class="panel-layout" id="media-layout">

    <!-- ── Left: Folder tree ──────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Folders</h3>
            <button class="btn btn-sm btn-secondary" id="new-folder-btn">+ Folder</button>
        </div>
        <div class="pl-sidebar-scroll" id="media-folder-tree">
            <div class="mf-folder active" data-path="" id="folder-root">
                <span>🏠</span> All Media
            </div>
            <!-- dynamic sub-folders appended here -->
        </div>
        <div class="pl-sidebar-footer" id="new-folder-wrap" style="display:none">
            <input type="text" id="new-folder-name" class="pl-search-input" placeholder="Folder name (a-z, 0-9, -, _)" style="margin-bottom:.4rem">
            <div style="display:flex;gap:.4rem">
                <button class="btn btn-sm btn-primary" id="new-folder-save">Create</button>
                <button class="btn btn-sm btn-secondary" id="new-folder-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ── Middle: Media grid ─────────────────────────────────── -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="media-folder-label">All Media</span>
            <div class="pl-main-toolbar-actions">
                <button class="btn btn-sm btn-primary" id="upload-btn">⬆ Upload</button>
                <input type="file" id="upload-input" multiple accept="image/*,application/pdf,.doc,.docx,.zip" style="display:none">
            </div>
        </div>
        <div class="pl-main-search">
            <input type="search" class="pl-search-input" id="media-search" placeholder="Search files…" autocomplete="off">
        </div>
        <div id="media-grid-wrap" style="flex:1;overflow-y:auto">
            <div class="media-grid" id="media-grid">
                <div class="pl-empty" style="grid-column:1/-1">Loading…</div>
            </div>
        </div>
    </div>

    <!-- ── Right: File detail ─────────────────────────────────── -->
    <div class="pl-detail">
        <div class="pl-detail-header"><h3>File Details</h3></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="media-detail-placeholder">
                <div class="pl-detail-placeholder-icon">🖼</div>
                <span>Select a file to see details</span>
            </div>
            <div id="media-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<script>
(function () {
    const grid         = document.getElementById('media-grid');
    const folderTree   = document.getElementById('media-folder-tree');
    const folderLabel  = document.getElementById('media-folder-label');
    const searchInput  = document.getElementById('media-search');
    const uploadBtn    = document.getElementById('upload-btn');
    const uploadInput  = document.getElementById('upload-input');
    const newFolderBtn  = document.getElementById('new-folder-btn');
    const newFolderWrap = document.getElementById('new-folder-wrap');
    const newFolderName = document.getElementById('new-folder-name');
    const newFolderSave = document.getElementById('new-folder-save');
    const newFolderCancel = document.getElementById('new-folder-cancel');
    const placeholder  = document.getElementById('media-detail-placeholder');
    const detailContent = document.getElementById('media-detail-content');

    let currentFolder  = '';
    let selectedFile   = null;
    let searchTimeout  = null;

    // ── Load folder + render ──
    function loadFolder(folder, label) {
        currentFolder = folder;
        folderLabel.textContent = label || 'All Media';
        // Update active folder in tree
        document.querySelectorAll('.mf-folder').forEach(el => {
            el.classList.toggle('active', el.dataset.path === folder);
        });
        clearDetail();
        grid.innerHTML = '<div class="pl-empty" style="grid-column:1/-1">Loading…</div>';

        const url = '/admin/media/list' + (folder ? '?folder=' + encodeURIComponent(folder) : '');
        fetch(url)
            .then(r => r.json())
            .then(data => {
                renderSubFolders(data.folders || [], folder);
                renderGrid(data.folders || [], data.files || [], folder);
            })
            .catch(() => {
                grid.innerHTML = '<div class="pl-empty" style="grid-column:1/-1">Failed to load media.</div>';
            });
    }

    // ── Append sub-folders under current folder node in tree ──
    function renderSubFolders(folders, parentPath) {
        // Remove any existing children of this parent
        document.querySelectorAll(`.mf-folder-child[data-parent="${CSS.escape(parentPath)}"]`).forEach(el => el.remove());
        for (const f of folders) {
            const el = document.createElement('div');
            el.className = 'mf-folder mf-folder-child';
            el.dataset.path   = f.path;
            el.dataset.parent = parentPath;
            el.style.paddingLeft = '1.5rem';
            el.innerHTML = '<span>📁</span> ' + escHtml(f.name);
            el.addEventListener('click', () => loadFolder(f.path, f.name));
            folderTree.appendChild(el);
        }
    }

    // ── Render grid ──
    function renderGrid(folders, files, currentPath) {
        if (!folders.length && !files.length) {
            grid.innerHTML = '<div class="pl-empty" style="grid-column:1/-1">No files here.</div>';
            return;
        }
        let html = '';
        for (const f of folders) {
            html += `<div class="media-thumb" data-path="${escAttr(f.path)}" data-type="folder">
                <div style="font-size:2.5rem;line-height:90px;color:#ccc">📁</div>
                <div class="media-thumb-name">${escHtml(f.name)}</div>
            </div>`;
        }
        for (const f of files) {
            html += `<div class="media-thumb" data-url="${escAttr(f.url)}" data-name="${escAttr(f.name)}" data-size="${f.size}" data-type="file">
                <button class="media-thumb-del" data-url="${escAttr(f.url)}" title="Delete">✕</button>
                <img src="${escAttr(f.url)}" alt="" loading="lazy">
                <div class="media-thumb-name" title="${escAttr(f.name)}">${escHtml(f.name)}</div>
            </div>`;
        }
        grid.innerHTML = html;

        // Folder click
        grid.querySelectorAll('.media-thumb[data-type="folder"]').forEach(el => {
            el.addEventListener('click', () => loadFolder(el.dataset.path, el.querySelector('.media-thumb-name').textContent));
        });

        // File click → detail
        grid.querySelectorAll('.media-thumb[data-type="file"]').forEach(el => {
            el.addEventListener('click', e => {
                if (e.target.classList.contains('media-thumb-del')) return;
                document.querySelectorAll('.media-thumb').forEach(t => t.classList.remove('selected'));
                el.classList.add('selected');
                selectedFile = el;
                showDetail(el.dataset.url, el.dataset.name, el.dataset.size);
            });
        });

        // Delete
        grid.querySelectorAll('.media-thumb-del').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (!confirm('Delete ' + btn.dataset.url.split('/').pop() + '?')) return;
                const fd = new FormData();
                fd.append('file', btn.dataset.url);
                fd.append('csrf_token', getCsrf());
                fetch('/admin/media/delete-file', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) { btn.closest('.media-thumb').remove(); clearDetail(); }
                        else alert(resp.error || 'Delete failed');
                    });
            });
        });
    }

    // ── Detail panel ──
    function showDetail(url, name, size) {
        placeholder.style.display = 'none';
        const kb = size ? (size / 1024).toFixed(1) + ' KB' : '—';
        detailContent.innerHTML = `
            <img class="media-detail-img" src="${escAttr(url)}" alt="">
            <div class="pl-detail-title">${escHtml(name)}</div>
            <div class="pl-detail-subtitle">${kb}</div>
            <div class="pl-detail-actions">
                <a href="${escAttr(url)}" target="_blank" class="btn btn-outline btn-sm">View ↗</a>
                <button class="btn btn-primary btn-sm" id="copy-url-btn">Copy URL</button>
            </div>
            <div class="media-copy-url" id="file-url-display" title="Click to copy">${escHtml(url)}</div>
            <div class="pl-detail-actions">
                <button class="btn btn-sm" style="background:#c0392b;color:#fff;flex:1" id="delete-file-btn" data-url="${escAttr(url)}">Delete</button>
            </div>`;
        detailContent.style.display = '';

        document.getElementById('copy-url-btn').addEventListener('click', () => {
            navigator.clipboard.writeText(url);
            document.getElementById('copy-url-btn').textContent = 'Copied!';
            setTimeout(() => document.getElementById('copy-url-btn').textContent = 'Copy URL', 1500);
        });
        document.getElementById('file-url-display').addEventListener('click', () => {
            navigator.clipboard.writeText(url);
        });
        document.getElementById('delete-file-btn').addEventListener('click', () => {
            if (!confirm('Delete ' + name + '?')) return;
            const fd = new FormData();
            fd.append('file', url);
            fd.append('csrf_token', getCsrf());
            fetch('/admin/media/delete-file', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(resp => {
                    if (resp.success) {
                        if (selectedFile) selectedFile.remove();
                        clearDetail();
                        loadFolder(currentFolder, folderLabel.textContent);
                    } else alert(resp.error || 'Delete failed');
                });
        });
    }

    function clearDetail() {
        selectedFile = null;
        placeholder.style.display = '';
        detailContent.style.display = 'none';
        detailContent.innerHTML = '';
    }

    // ── Search ──
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const q = searchInput.value.trim();
            if (q) {
                folderLabel.textContent = 'Search: ' + q;
                grid.innerHTML = '<div class="pl-empty" style="grid-column:1/-1">Searching…</div>';
                fetch('/admin/media/list?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => renderGrid([], data.files || [], ''));
            } else {
                loadFolder(currentFolder, folderLabel.textContent);
            }
        }, 300);
    });

    // ── Upload ──
    uploadBtn.addEventListener('click', () => uploadInput.click());
    uploadInput.addEventListener('change', () => {
        const files = uploadInput.files;
        if (!files.length) return;
        let pending = files.length;
        for (const file of files) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('csrf_token', getCsrf());
            fetch('/admin/upload', { method: 'POST', body: fd })
                .then(r => r.json())
                .finally(() => { if (--pending === 0) loadFolder(currentFolder, folderLabel.textContent); });
        }
        uploadInput.value = '';
    });

    // ── New folder ──
    newFolderBtn.addEventListener('click', () => { newFolderWrap.style.display = ''; newFolderName.focus(); });
    newFolderCancel.addEventListener('click', () => { newFolderWrap.style.display = 'none'; newFolderName.value = ''; });
    newFolderSave.addEventListener('click', () => {
        const name = newFolderName.value.trim();
        if (!name) return;
        const fd = new FormData();
        fd.append('folder', currentFolder);
        fd.append('name', name);
        fd.append('csrf_token', getCsrf());
        fetch('/admin/media/folder', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    newFolderWrap.style.display = 'none';
                    newFolderName.value = '';
                    loadFolder(currentFolder, folderLabel.textContent);
                } else alert(resp.error || 'Failed to create folder');
            });
    });

    // ── Root folder click ──
    document.getElementById('folder-root').addEventListener('click', () => loadFolder('', 'All Media'));

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="csrf_token"]')?.value
            || '';
    }
    function escHtml(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
    function escAttr(s) { return String(s ?? '').replace(/"/g, '&quot;'); }

    // ── Init ──
    loadFolder('', 'All Media');
})();
</script>
<style>
.media-browser { display: flex; flex-direction: column; gap: 1rem; }
.media-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
.media-toolbar-left { display: flex; align-items: center; gap: 0.5rem; }
.media-toolbar-right { display: flex; align-items: center; gap: 0.5rem; }
.media-breadcrumb { display: flex; align-items: center; gap: 0.25rem; font-size: 0.9rem; }
.media-breadcrumb a { color: #4a90d9; text-decoration: none; }
.media-breadcrumb a:hover { text-decoration: underline; }
.media-breadcrumb span { color: #6b7890; }
.media-search { display: flex; align-items: center; gap: 0.5rem; }
.media-search input { padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px; width: 200px; }
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
.media-folder, .media-file { border: 1px solid #dce1e6; border-radius: 6px; background: #fff; padding: 0.75rem; text-align: center; cursor: pointer; transition: box-shadow 0.15s, border-color 0.15s; position: relative; }
.media-folder:hover, .media-file:hover { border-color: #4a90d9; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.media-folder-icon { font-size: 2.5rem; margin-bottom: 0.25rem; }
.media-folder-name { font-size: 0.85rem; word-break: break-word; color: #333; }
.media-file img { max-width: 100%; height: 100px; object-fit: cover; border-radius: 4px; background: #f5f5f5; }
.media-file-name { font-size: 0.8rem; margin-top: 0.5rem; color: #555; word-break: break-word; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-empty { text-align: center; padding: 3rem; color: #888; }
.media-loading { text-align: center; padding: 2rem; color: #888; }
.media-file.selected { border-color: #2a6bb8; box-shadow: 0 0 0 2px rgba(42,107,184,0.3); }
.media-file-actions { position: absolute; top: 4px; right: 4px; display: none; }
.media-file:hover .media-file-actions { display: block; }
.media-file-actions button { background: rgba(220,50,50,0.9); color: #fff; border: none; border-radius: 3px; font-size: 0.7rem; padding: 2px 6px; cursor: pointer; }
.media-file-actions button:hover { background: #c0392b; }
.media-new-folder { display: flex; gap: 0.5rem; align-items: center; }
.media-new-folder input { padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px; width: 150px; }
</style>

<div class="media-browser">
    <div class="admin-page-header">
        <h2>📁 Media Library</h2>
        <div>
            <button type="button" class="btn btn-primary" id="upload-btn">Upload Files</button>
            <input type="file" id="upload-input" multiple style="display:none;" accept="image/*">
        </div>
    </div>

    <div class="media-toolbar">
        <div class="media-toolbar-left">
            <nav class="media-breadcrumb" id="media-breadcrumb">
                <span>Loading...</span>
            </nav>
        </div>
        <div class="media-toolbar-right">
            <div class="media-new-folder" id="new-folder-form" style="display:none;">
                <input type="text" id="new-folder-name" placeholder="Folder name">
                <button class="btn btn-small btn-primary" id="new-folder-save">Create</button>
                <button class="btn btn-small" id="new-folder-cancel">Cancel</button>
            </div>
            <button class="btn btn-small" id="new-folder-btn">+ Folder</button>
            <div class="media-search">
                <input type="text" id="media-search" placeholder="Search files...">
            </div>
        </div>
    </div>

    <div class="media-grid" id="media-grid">
        <div class="media-loading">Loading...</div>
    </div>
</div>

<script>
(function() {
    const grid = document.getElementById('media-grid');
    const breadcrumb = document.getElementById('media-breadcrumb');
    const searchInput = document.getElementById('media-search');
    const uploadBtn = document.getElementById('upload-btn');
    const uploadInput = document.getElementById('upload-input');
    const newFolderBtn = document.getElementById('new-folder-btn');
    const newFolderForm = document.getElementById('new-folder-form');
    const newFolderName = document.getElementById('new-folder-name');
    const newFolderSave = document.getElementById('new-folder-save');
    const newFolderCancel = document.getElementById('new-folder-cancel');

    let currentFolder = '';
    let searchTimeout = null;

    function loadMedia(folder, search) {
        grid.innerHTML = '<div class="media-loading">Loading...</div>';
        let url = '/admin/media/list';
        const params = new URLSearchParams();
        if (folder) params.set('folder', folder);
        if (search) params.set('q', search);
        if (params.toString()) url += '?' + params.toString();

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.error) { grid.innerHTML = '<div class="media-empty">Error: ' + data.error + '</div>'; return; }
                renderBreadcrumb(data.current, data.parent);
                renderGrid(data.folders, data.files, data.current);
            })
            .catch(() => {
                grid.innerHTML = '<div class="media-empty">Failed to load media.</div>';
            });
    }

    function renderBreadcrumb(current, parent) {
        currentFolder = current || '';
        if (!current) {
            breadcrumb.innerHTML = '<strong>Media</strong>';
            return;
        }
        // Parse path: /storage/iga/media/2024/01
        const parts = current.replace(/^\/storage\/[^/]+\/media\/?/, '').split('/').filter(Boolean);
        let html = '<a href="#" data-folder="">Media</a>';
        let accum = '';
        const prefix = current.match(/^\/storage\/[^/]+\/media/) ? current.match(/^\/storage\/[^/]+\/media/)[0] : '';
        for (let i = 0; i < parts.length; i++) {
            accum += '/' + parts[i];
            const fullPath = prefix + accum;
            if (i < parts.length - 1) {
                html += ' <span>/</span> <a href="#" data-folder="' + fullPath + '">' + parts[i] + '</a>';
            } else {
                html += ' <span>/</span> <strong>' + parts[i] + '</strong>';
            }
        }
        breadcrumb.innerHTML = html;

        breadcrumb.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                loadMedia(a.dataset.folder, '');
                searchInput.value = '';
            });
        });
    }

    function renderGrid(folders, files, current) {
        if (!folders.length && !files.length) {
            grid.innerHTML = '<div class="media-empty">No files or folders here.</div>';
            return;
        }
        let html = '';
        for (const f of folders) {
            html += '<div class="media-folder" data-path="' + f.path + '">' +
                    '<div class="media-folder-icon">📁</div>' +
                    '<div class="media-folder-name">' + escapeHtml(f.name) + '</div></div>';
        }
        for (const f of files) {
            html += '<div class="media-file" data-url="' + f.url + '">' +
                    '<div class="media-file-actions"><button class="delete-file-btn" data-url="' + f.url + '">✕</button></div>' +
                    '<img src="' + f.url + '" alt="" loading="lazy">' +
                    '<div class="media-file-name" title="' + escapeHtml(f.name) + '">' + escapeHtml(f.name) + '</div></div>';
        }
        grid.innerHTML = html;

        grid.querySelectorAll('.media-folder').forEach(el => {
            el.addEventListener('click', () => {
                loadMedia(el.dataset.path, '');
                searchInput.value = '';
            });
        });

        grid.querySelectorAll('.media-file').forEach(el => {
            el.addEventListener('click', e => {
                if (e.target.closest('.delete-file-btn')) return;
                navigator.clipboard.writeText(el.dataset.url);
                el.classList.add('selected');
                setTimeout(() => el.classList.remove('selected'), 800);
            });
        });

        grid.querySelectorAll('.delete-file-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (!confirm('Delete this file?')) return;
                const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value ||
                                  document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('file', btn.dataset.url);
                fd.append('_csrf_token', csrfToken);
                fetch('/admin/media/delete-file', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) btn.closest('.media-file').remove();
                        else alert(resp.error || 'Delete failed');
                    });
            });
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Search
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const q = searchInput.value.trim();
            loadMedia(q ? '' : currentFolder, q);
        }, 300);
    });

    // Upload
    uploadBtn.addEventListener('click', () => uploadInput.click());
    uploadInput.addEventListener('change', () => {
        const files = uploadInput.files;
        if (!files.length) return;
        const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value ||
                          document.querySelector('meta[name="csrf-token"]')?.content || '';
        let pending = files.length;
        for (const file of files) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_csrf_token', csrfToken);
            fetch('/admin/upload', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => { if (--pending === 0) loadMedia(currentFolder, ''); })
                .catch(() => { if (--pending === 0) loadMedia(currentFolder, ''); });
        }
        uploadInput.value = '';
    });

    // New folder
    newFolderBtn.addEventListener('click', () => {
        newFolderForm.style.display = '';
        newFolderBtn.style.display = 'none';
        newFolderName.value = '';
        newFolderName.focus();
    });
    newFolderCancel.addEventListener('click', () => {
        newFolderForm.style.display = 'none';
        newFolderBtn.style.display = '';
    });
    newFolderSave.addEventListener('click', () => {
        const name = newFolderName.value.trim();
        if (!name) return;
        const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value ||
                          document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd = new FormData();
        fd.append('folder', currentFolder);
        fd.append('name', name);
        fd.append('_csrf_token', csrfToken);
        fetch('/admin/media/folder', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    newFolderForm.style.display = 'none';
                    newFolderBtn.style.display = '';
                    loadMedia(currentFolder, '');
                } else {
                    alert(resp.error || 'Failed to create folder');
                }
            });
    });

    // Init
    loadMedia('', '');
})();
</script>
