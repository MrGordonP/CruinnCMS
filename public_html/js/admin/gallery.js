/**
 * Cruinn Admin — Gallery Block Editor
 *
 * Handles browse, upload, and thumbnail rendering for gallery blocks.
 * Depends on: utils.js, api.js, media-browser.js
 */
(function (Cruinn) {

    // ── Private helpers ────────────────────────────────────────

    function renderGalleryThumbs(container) {
        var thumbsDiv = container.querySelector('.gallery-thumbs');
        if (!thumbsDiv) return;

        var images = JSON.parse(container.dataset.images || '[]');
        if (!images.length) {
            thumbsDiv.innerHTML = '<p class="gallery-empty">No images yet. Add some using the buttons below.</p>';
            return;
        }

        var html = '';
        images.forEach(function (img, idx) {
            html += '<div class="gallery-thumb" data-index="' + idx + '">';
            html += '<img src="' + Cruinn.escapeAttr(img.url) + '" alt="' + Cruinn.escapeAttr(img.alt || '') + '">';
            html += '<button type="button" class="gallery-thumb-remove" title="Remove">&times;</button>';
            html += '</div>';
        });
        thumbsDiv.innerHTML = html;

        thumbsDiv.querySelectorAll('.gallery-thumb-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(this.closest('.gallery-thumb').dataset.index);
                var imgs = JSON.parse(container.dataset.images || '[]');
                imgs.splice(idx, 1);
                container.dataset.images = JSON.stringify(imgs);
                renderGalleryThumbs(container);
            });
        });
    }

    // ── Public API ─────────────────────────────────────────────

    /**
     * Initialise all .block-gallery-editor instances on the page.
     */
    Cruinn.initGalleryEditors = function () {
        document.querySelectorAll('.block-gallery-editor').forEach(function (container) {
            renderGalleryThumbs(container);

            var browseBtn = container.querySelector('.btn-browse-media');
            if (browseBtn) {
                browseBtn.addEventListener('click', function () {
                    Cruinn.openMediaBrowser(function (urls) {
                        var images = JSON.parse(container.dataset.images || '[]');
                        urls.forEach(function (url) {
                            images.push({ url: url, alt: '', caption: '' });
                        });
                        container.dataset.images = JSON.stringify(images);
                        renderGalleryThumbs(container);
                    }, true);
                });
            }

            var uploadInput = container.querySelector('.block-file-upload');
            if (uploadInput) {
                uploadInput.addEventListener('change', function () {
                    var files = Array.from(this.files);
                    var remaining = files.length;
                    files.forEach(function (file) {
                        Cruinn.uploadFile(file, function (url) {
                            var images = JSON.parse(container.dataset.images || '[]');
                            images.push({ url: url, alt: '', caption: '' });
                            container.dataset.images = JSON.stringify(images);
                            remaining--;
                            if (remaining <= 0) renderGalleryThumbs(container);
                        });
                    });
                    this.value = '';
                });
            }
        });
    };

})(window.Cruinn = window.Cruinn || {});
