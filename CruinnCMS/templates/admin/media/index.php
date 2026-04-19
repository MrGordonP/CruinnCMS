<?php
/**
 * Admin — Media Library
 *
 * File browser for uploaded images and documents.
 */
?>
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
