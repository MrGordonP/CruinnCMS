/**
 * Admin — Media Library
 * Handles the 3-panel media library (#media-layout) and the flat media browser (.media-browser).
 * Extracted from templates/admin/media/index.php — no PHP data injection required.
 */

// ── 3-panel media library ────────────────────────────────────────
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

    if (!grid || !folderTree) return; // not on this page variant

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

// ── Flat media browser (.media-browser) ─────────────────────────
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

    if (!grid || !breadcrumb) return; // not on this page variant

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
