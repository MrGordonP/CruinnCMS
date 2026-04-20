/**
 * Cruinn Admin — Media Browser
 *
 * Folder-aware media library modal. Supports folder navigation, upload,
 * create/delete folder, search, and single-select with callback.
 * Depends on: utils.js (Cruinn.getCSRFToken, Cruinn.escapeHtml, Cruinn.escapeAttr)
 */
(function (Cruinn) {

    // ── Private state ──────────────────────────────────────────

    var modal = null;
    var grid = null;
    var pathEl = null;
    var searchInput = null;
    var uploadInput = null;
    var selectBtn = null;
    var deleteBtn = null;

    var mediaCallback = null;
    var mediaSelected = null;   // selected file URL
    var mediaCurrentFolder = '';

    // ── Private helpers ────────────────────────────────────────

    function updateSelectBtn() {
        if (selectBtn) {
            selectBtn.textContent = mediaSelected ? 'Insert' : 'Upload';
        }
        if (deleteBtn) {
            deleteBtn.textContent = mediaSelected ? 'Delete File' : 'Delete Folder';
            deleteBtn.style.display = (mediaSelected || mediaCurrentFolder) ? '' : 'none';
        }
    }

    function closeMediaBrowser() {
        if (modal) { modal.style.display = 'none'; }
        mediaCallback = null;
        mediaSelected = null;
        mediaCurrentFolder = '';
        updateSelectBtn();
    }

    function loadMediaGrid(folder) {
        mediaCurrentFolder = folder || '';
        mediaSelected = null;
        updateSelectBtn();

        if (pathEl) {
            pathEl.textContent = mediaCurrentFolder
                ? mediaCurrentFolder.replace(/.*\/media/, '')
                : '/';
        }

        if (!grid) { return; }
        grid.innerHTML = '<p class="media-loading">Loading\u2026</p>';

        var url = '/admin/media/list?_=' + Date.now();
        if (mediaCurrentFolder) { url += '&folder=' + encodeURIComponent(mediaCurrentFolder); }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var folders = data.folders || [];
                var files = data.files || [];
                var parent = data.parent !== undefined ? data.parent : null;
                grid.innerHTML = '';

                // Up navigation
                if (parent !== null) {
                    var up = document.createElement('div');
                    up.className = 'media-item media-folder';
                    up.innerHTML = '<div class="media-folder-icon">&#8593;</div>'
                        + '<div class="media-item-name">.. (up)</div>';
                    up.addEventListener('click', function () { loadMediaGrid(parent); });
                    grid.appendChild(up);
                }

                if (!folders.length && !files.length) {
                    var empty = document.createElement('p');
                    empty.className = 'media-loading';
                    empty.textContent = 'No images here.';
                    grid.appendChild(empty);
                }

                // Subfolder items
                folders.forEach(function (f) {
                    var item = document.createElement('div');
                    item.className = 'media-item media-folder';
                    item.innerHTML = '<div class="media-folder-icon">&#128193;</div>'
                        + '<div class="media-item-name">' + Cruinn.escapeHtml(f.name) + '</div>';
                    item.addEventListener('click', function () { loadMediaGrid(f.path); });
                    grid.appendChild(item);
                });

                // Image items
                files.forEach(function (file) {
                    var fileUrl = file.url || file;
                    var name = file.name || fileUrl.split('/').pop();
                    var item = document.createElement('div');
                    item.className = 'media-item';
                    item.dataset.url = fileUrl;
                    item.innerHTML = '<img src="' + Cruinn.escapeAttr(fileUrl) + '" alt="' + Cruinn.escapeAttr(name) + '">'
                        + '<div class="media-item-name">' + Cruinn.escapeHtml(name) + '</div>';
                    item.addEventListener('click', function () {
                        grid.querySelectorAll('.media-item').forEach(function (i) { i.classList.remove('selected'); });
                        item.classList.add('selected');
                        mediaSelected = fileUrl;
                        updateSelectBtn();
                    });
                    item.addEventListener('dblclick', function () {
                        mediaSelected = fileUrl;
                        if (mediaCallback) { mediaCallback(fileUrl); }
                        closeMediaBrowser();
                    });
                    grid.appendChild(item);
                });
            })
            .catch(function () {
                if (grid) { grid.innerHTML = '<p class="media-loading">Failed to load media.</p>'; }
            });
    }

    function runSearch(q) {
        if (!grid) { return; }
        grid.innerHTML = '<p class="media-loading">Searching\u2026</p>';
        fetch('/admin/media/list?_=' + Date.now() + '&q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var files = data.files || [];
                grid.innerHTML = '';
                if (!files.length) {
                    grid.innerHTML = '<p class="media-loading">No results.</p>';
                    return;
                }
                files.forEach(function (file) {
                    var fileUrl = file.url;
                    var name = file.name;
                    var item = document.createElement('div');
                    item.className = 'media-item';
                    item.dataset.url = fileUrl;
                    item.innerHTML = '<img src="' + Cruinn.escapeAttr(fileUrl) + '" alt="' + Cruinn.escapeAttr(name) + '">'
                        + '<div class="media-item-name">' + Cruinn.escapeHtml(name) + '</div>';
                    item.addEventListener('click', function () {
                        grid.querySelectorAll('.media-item').forEach(function (i) { i.classList.remove('selected'); });
                        item.classList.add('selected');
                        mediaSelected = fileUrl;
                        updateSelectBtn();
                    });
                    item.addEventListener('dblclick', function () {
                        mediaSelected = fileUrl;
                        if (mediaCallback) { mediaCallback(fileUrl); }
                        closeMediaBrowser();
                    });
                    grid.appendChild(item);
                });
            })
            .catch(function () {
                if (grid) { grid.innerHTML = '<p class="media-loading">Search failed.</p>'; }
            });
    }

    // ── Public API ─────────────────────────────────────────────

    /**
     * Open the media browser modal.
     * @param {Function} onSelect  Called with the selected file URL string.
     */
    Cruinn.openMediaBrowser = function (onSelect) {
        mediaCallback = onSelect || null;
        mediaSelected = null;
        if (searchInput) { searchInput.value = ''; }
        updateSelectBtn();
        if (modal) { modal.style.display = 'flex'; }
        loadMediaGrid('');
    };

    /**
     * Bind all event handlers. Called once after DOMContentLoaded.
     */
    Cruinn.initMediaBrowser = function () {
        modal = document.getElementById('media-modal');
        grid = document.getElementById('media-grid');
        pathEl = document.getElementById('media-modal-path');
        searchInput = document.getElementById('media-search');
        uploadInput = document.getElementById('media-modal-upload');
        selectBtn = document.getElementById('media-modal-select-btn');
        deleteBtn = document.getElementById('media-modal-delete-btn');

        if (!modal) { return; }

        document.getElementById('media-modal-close-btn').addEventListener('click', closeMediaBrowser);
        document.getElementById('media-modal-cancel-btn').addEventListener('click', closeMediaBrowser);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) { closeMediaBrowser(); }
        });

        // Select / Upload button
        selectBtn.addEventListener('click', function () {
            if (mediaSelected && mediaCallback) {
                mediaCallback(mediaSelected);
                closeMediaBrowser();
            } else {
                uploadInput.click();
            }
        });

        // Upload
        uploadInput.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (!file) { return; }
            var fd = new FormData();
            fd.append('file', file);
            fd.append('_csrf_token', Cruinn.getCSRFToken());
            fetch('/admin/upload', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) { alert('Upload failed: ' + data.error); loadMediaGrid(mediaCurrentFolder); return; }
                    var destFolder = data.url ? data.url.substring(0, data.url.lastIndexOf('/')) : mediaCurrentFolder;
                    loadMediaGrid(destFolder);
                })
                .catch(function () { alert('Upload request failed.'); loadMediaGrid(mediaCurrentFolder); });
            e.target.value = '';
        });

        // New folder
        document.getElementById('media-modal-new-folder-btn').addEventListener('click', function () {
            var name = prompt('New folder name (letters, numbers, - _ only):');
            if (!name) { return; }
            var fd = new FormData();
            fd.append('csrf_token', Cruinn.getCSRFToken());
            fd.append('folder', mediaCurrentFolder);
            fd.append('name', name);
            fetch('/admin/media/folder', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) { alert(data.error); return; }
                    loadMediaGrid(mediaCurrentFolder);
                })
                .catch(function () { alert('Failed to create folder.'); });
        });

        // Delete file / folder
        deleteBtn.addEventListener('click', function () {
            if (mediaSelected) {
                if (!confirm('Delete this file? This cannot be undone.')) { return; }
                var fd = new FormData();
                fd.append('csrf_token', Cruinn.getCSRFToken());
                fd.append('file', mediaSelected);
                fetch('/admin/media/delete-file', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) { alert(data.error); return; }
                        mediaSelected = null;
                        loadMediaGrid(mediaCurrentFolder);
                    })
                    .catch(function () { alert('Failed to delete file.'); });
            } else if (mediaCurrentFolder) {
                if (!confirm('Delete this folder? It must be empty.')) { return; }
                var parts = mediaCurrentFolder.replace(/\/$/, '').split('/');
                parts.pop();
                var parentPath = parts.join('/');
                if (/^\/storage\/[^/]+\/media$/.test(parentPath)) { parentPath = ''; }
                var fd2 = new FormData();
                fd2.append('csrf_token', Cruinn.getCSRFToken());
                fd2.append('folder', mediaCurrentFolder);
                fetch('/admin/media/delete', { method: 'POST', body: fd2 })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) { alert(data.error); return; }
                        loadMediaGrid(parentPath);
                    })
                    .catch(function () { alert('Failed to delete folder.'); });
            }
        });

        // Search with debounce
        if (searchInput) {
            var searchTimer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                var q = searchInput.value.trim();
                searchTimer = setTimeout(function () {
                    if (q) { runSearch(q); } else { loadMediaGrid(mediaCurrentFolder); }
                }, 300);
            });
        }

        updateSelectBtn();
    };

})(window.Cruinn = window.Cruinn || {});
