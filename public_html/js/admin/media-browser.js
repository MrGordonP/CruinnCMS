/**
 * Cruinn Admin — Media Browser
 *
 * Modal-based media library browser with single- and multi-select support,
 * search, and inline upload.
 * Depends on: utils.js, api.js
 */
(function (Cruinn) {

    // ── Private state ──────────────────────────────────────────

    var mediaModal = null;
    var mediaGrid = null;
    var mediaSearch = null;
    var mediaUploadInput = null;

    var mediaCallback = null;
    var mediaSelectedUrl = null;
    var mediaAllowMultiple = false;
    var mediaSelectedUrls = [];

    // ── Private helpers ────────────────────────────────────────

    function closeMediaBrowser() {
        if (mediaModal) mediaModal.style.display = 'none';
        mediaCallback = null;
        mediaSelectedUrl = null;
        mediaSelectedUrls = [];
    }

    function loadMediaFiles(query) {
        if (!mediaGrid) return;
        mediaGrid.innerHTML = '<p class="media-loading">Loading\u2026</p>';

        var url = '/admin/media';
        if (query) url += '?q=' + encodeURIComponent(query);

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var files = data.files || [];
                if (!files.length) {
                    mediaGrid.innerHTML = '<p class="media-empty">No images found. Upload one above.</p>';
                    return;
                }

                var html = '';
                files.forEach(function (f) {
                    var sizeKB = Math.round(f.size / 1024);
                    html += '<div class="media-item" data-url="' + Cruinn.escapeAttr(f.url) + '">';
                    html += '<img src="' + Cruinn.escapeAttr(f.url) + '" alt="' + Cruinn.escapeAttr(f.name) + '" loading="lazy">';
                    html += '<div class="media-item-name">' + Cruinn.escapeHtml(f.name) + '</div>';
                    html += '<div class="media-item-size">' + sizeKB + ' KB</div>';
                    html += '</div>';
                });
                mediaGrid.innerHTML = html;

                mediaGrid.querySelectorAll('.media-item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        var itemUrl = this.dataset.url;
                        var selectBtn = mediaModal.querySelector('.media-select-btn');
                        if (mediaAllowMultiple) {
                            this.classList.toggle('selected');
                            var idx = mediaSelectedUrls.indexOf(itemUrl);
                            if (idx >= 0) mediaSelectedUrls.splice(idx, 1);
                            else mediaSelectedUrls.push(itemUrl);
                            if (selectBtn) selectBtn.disabled = !mediaSelectedUrls.length;
                        } else {
                            mediaGrid.querySelectorAll('.media-item').forEach(function (el) {
                                el.classList.remove('selected');
                            });
                            this.classList.add('selected');
                            mediaSelectedUrl = itemUrl;
                            if (selectBtn) selectBtn.disabled = false;
                        }
                    });
                });
            })
            .catch(function () {
                mediaGrid.innerHTML = '<p class="media-empty">Failed to load media library.</p>';
            });
    }

    // ── Public API ─────────────────────────────────────────────

    /**
     * Open the media browser modal.
     * @param {Function} onSelect  Called with the selected URL (string) for single-select,
     *                             or array of URLs for multi-select.
     * @param {boolean}  multi     Allow multiple selection.
     */
    Cruinn.openMediaBrowser = function (onSelect, multi) {
        mediaCallback = onSelect;
        mediaSelectedUrl = null;
        mediaSelectedUrls = [];
        mediaAllowMultiple = !!multi;
        if (mediaModal) {
            mediaModal.style.display = '';
            loadMediaFiles('');
        }
    };

    /**
     * Bind all event handlers for the media browser modal.
     * Call once after DOMContentLoaded.
     */
    Cruinn.initMediaBrowser = function () {
        mediaModal = document.getElementById('media-modal');
        mediaGrid = document.getElementById('media-grid');
        mediaSearch = document.getElementById('media-search');
        mediaUploadInput = document.getElementById('media-modal-upload');

        if (!mediaModal) return;

        mediaModal.querySelector('.media-modal-close').addEventListener('click', closeMediaBrowser);
        mediaModal.querySelector('.media-cancel-btn').addEventListener('click', closeMediaBrowser);

        mediaModal.querySelector('.media-select-btn').addEventListener('click', function () {
            if (mediaCallback) {
                if (mediaAllowMultiple) {
                    mediaCallback(mediaSelectedUrls);
                } else if (mediaSelectedUrl) {
                    mediaCallback(mediaSelectedUrl);
                }
            }
            closeMediaBrowser();
        });

        // Click outside modal content to close
        mediaModal.addEventListener('click', function (e) {
            if (e.target === mediaModal) closeMediaBrowser();
        });

        // Search with debounce
        if (mediaSearch) {
            var searchTimer = null;
            mediaSearch.addEventListener('input', function () {
                clearTimeout(searchTimer);
                var q = this.value;
                searchTimer = setTimeout(function () { loadMediaFiles(q); }, 300);
            });
        }

        // Upload from within modal
        if (mediaUploadInput) {
            mediaUploadInput.addEventListener('change', function () {
                if (!this.files.length) return;
                Cruinn.uploadFile(this.files[0], function () {
                    loadMediaFiles('');
                });
                this.value = '';
            });
        }
    };

})(window.Cruinn = window.Cruinn || {});
