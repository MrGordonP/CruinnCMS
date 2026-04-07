/**
 * Cruinn Admin — Block Editor Core
 *
 * Handles adding, saving, and deleting blocks, inline image upload,
 * the child-block picker popover, and auto-save event bindings.
 *
 * Shared context lives in Cruinn.blockContext (set by admin/index.js).
 * Depends on: utils.js, api.js, media-browser.js
 */
(function (Cruinn) {

    // ── Shared context reference ───────────────────────────────
    // Populated by Cruinn.initBlockEditorCore() before use.
    // Structure: { csrfToken, parentType, parentId, editorMode, activeZone }

    // ── Block type list for child picker — driven by Cruinn.BlockTypes registry ──

    // ── Public: Add block ──────────────────────────────────────

    /**
     * POST to /admin/blocks to create a new block, then reload the page.
     *
     * @param {string} blockType      Block type identifier.
     * @param {string} parentBlockId  Parent block ID (for nested blocks), or null.
     * @param {number} column         Column index within a row block.
     * @param {string} zone           Zone name override (defaults to Cruinn.blockContext.activeZone).
     */
    Cruinn.addBlock = function (blockType, parentBlockId, column, zone) {
        var ctx = Cruinn.blockContext;
        var params = {
            parent_type: ctx.parentType,
            parent_id: ctx.parentId,
            block_type: blockType,
            zone: zone || ctx.activeZone,
            _csrf_token: ctx.csrfToken,
        };
        if (parentBlockId) {
            params.parent_block_id = parentBlockId;
            params.column = column || 0;
        }

        fetch('/admin/blocks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': ctx.csrfToken,
                'Accept': 'application/json',
            },
            body: new URLSearchParams(params),
        })
            .then(function (res) {
                if (!res.ok && res.redirected) {
                    throw new Error('Session expired. Please reload the page.');
                }
                return res.json();
            })
            .then(function (data) {
                if (data.success) {
                    if (window.Cruinn && Cruinn.suppressBeforeUnload) Cruinn.suppressBeforeUnload();
                    window.location.reload();
                } else {
                    alert('Failed to add block: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function (err) {
                alert('Error adding block: ' + err.message);
            });
    };

    // ── Public: Extract block content from DOM ─────────────────

    /**
     * Read the current content values from a block editor item's inputs.
     * Returns a plain object suitable for JSON serialisation.
     */
    Cruinn.getBlockContent = function (blockItem) {
        var blockType = (blockItem.dataset.blockType || '').toLowerCase();

        // Preview blocks store content as a data attribute — use it directly
        if (blockItem.classList.contains('block-preview-wrap') && blockItem.dataset.content) {
            try { return JSON.parse(blockItem.dataset.content); } catch (e) { return {}; }
        }

        // Delegate to the registered block type definition
        var def = Cruinn.BlockTypes && Cruinn.BlockTypes.get(blockType);
        if (def && typeof def.getContent === 'function') {
            return def.getContent(blockItem);
        }

        // Fallback: try reading a JSON <code> element embedded in the block
        var codeEl = blockItem.querySelector('code');
        if (codeEl) {
            try { return JSON.parse(codeEl.textContent); } catch (e) { return {}; }
        }
        return {};
    };

    // ── Public: Auto-save a block ──────────────────────────────

    /**
     * POST the current content + settings for a block to the server.
     * Fires and forgets — no UI feedback.
     */
    Cruinn.autoSaveBlock = function (blockItem) {
        var blockId = blockItem.dataset.blockId;
        if (!blockId) return;
        var ctx = Cruinn.blockContext;
        var content = Cruinn.getBlockContent(blockItem);
        var settings = {};
        try { settings = JSON.parse(blockItem.dataset.settings || '{}'); } catch (e) { }

        fetch('/admin/blocks/' + blockId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': ctx.csrfToken,
            },
            body: new URLSearchParams({
                content: JSON.stringify(content),
                settings: JSON.stringify(settings),
                _csrf_token: ctx.csrfToken,
            }),
        }).catch(function () {
            if (window.Cruinn && Cruinn.notify) {
                Cruinn.notify('Auto-save failed — changes may not have been saved.', 'error');
            }
        });
    };

    // ── Public: Delete a block ─────────────────────────────────

    Cruinn.deleteBlock = function (blockId, blockItem) {
        var ctx = Cruinn.blockContext;
        fetch('/admin/blocks/' + blockId + '/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': ctx.csrfToken,
            },
            body: new URLSearchParams({ _csrf_token: ctx.csrfToken }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    blockItem.remove();
                    var propsPanel = ctx.propsPanel;
                    if (propsPanel) {
                        propsPanel.style.display = 'none';
                        ctx.propsTargetBlock = null;
                        if (Cruinn.updateBlockSelector) Cruinn.updateBlockSelector();
                    }
                }
            })
            .catch(function (err) {
                alert('Error deleting block: ' + err.message);
            });
    };

    // ── Public: Bind events on a newly added block element ───

    /**
     * Attach click-to-select, auto-save, and RTE save handlers to a block item
     * that was added to the DOM dynamically (e.g., restored by undo).
     */
    Cruinn.bindBlockEvents = function (blockItem) {
        blockItem.addEventListener('click', function (e) {
            var tag = e.target.tagName;
            if (
                tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ||
                tag === 'BUTTON' || tag === 'OPTION' ||
                e.target.isContentEditable ||
                e.target.closest('[contenteditable]') ||
                e.target.closest('button')
            ) { return; }
            e.stopPropagation();
            if (Cruinn.selectBlock) Cruinn.selectBlock(blockItem);
        });

        blockItem.querySelectorAll('input, textarea, select').forEach(function (el) {
            el.addEventListener('change', function () { Cruinn.autoSaveBlock(blockItem); });
        });

        var rte = blockItem.querySelector('.rte-editor');
        if (rte) {
            var saveTimer;
            rte.addEventListener('input', function () {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function () { Cruinn.autoSaveBlock(blockItem); }, 1500);
            });
        }
    };

    // ── Public: Child block picker popover ────────────────────

    Cruinn.showChildBlockPicker = function (anchor, parentBlockIdVal, column, zone) {
        var old = document.querySelector('.child-block-picker');
        if (old) old.remove();

        var picker = document.createElement('div');
        picker.className = 'child-block-picker';

        var blockTypeDefs = Cruinn.BlockTypes ? Cruinn.BlockTypes.all() : [];
        blockTypeDefs.forEach(function (t) {
            var b = document.createElement('button');
            b.className = 'btn btn-small';
            b.textContent = t.label;
            b.addEventListener('click', function (e) {
                e.stopPropagation();
                Cruinn.addBlock(t.slug, parentBlockIdVal, column, zone);
                picker.remove();
            });
            picker.appendChild(b);
        });

        // Position relative to the anchor button using fixed coordinates
        document.body.appendChild(picker);
        var rect = anchor.getBoundingClientRect();
        var pickerW = picker.offsetWidth || 240;
        var pickerH = picker.offsetHeight || 160;
        var left = Math.min(rect.left, window.innerWidth - pickerW - 8);
        var top = rect.bottom + 4;
        if (top + pickerH > window.innerHeight) {
            top = rect.top - pickerH - 4;
        }
        picker.style.left = Math.max(8, left) + 'px';
        picker.style.top = Math.max(8, top) + 'px';

        setTimeout(function () {
            document.addEventListener('click', function closePicker(e) {
                if (!picker.contains(e.target) && e.target !== anchor) {
                    picker.remove();
                    document.removeEventListener('click', closePicker);
                }
            });
        }, 0);
    };

    // ── Public: Initialise the block editor core ──────────────

    /**
     * Set up core event bindings for the block editor.
     * Must be called from DOMContentLoaded after Cruinn.blockContext is populated.
     */
    Cruinn.initBlockEditorCore = function () {
        var ctx = Cruinn.blockContext;

        // Expose activeZone for site editor cross-communication
        Object.defineProperty(window, '__seActiveZone', {
            get: function () { return ctx.activeZone; },
            set: function (v) { ctx.activeZone = v; },
            configurable: true,
        });

        // Top-level add block buttons
        document.querySelectorAll('.btn-add-block').forEach(function (btn) {
            btn.addEventListener('click', function () {
                Cruinn.addBlock(this.dataset.type, null, 0, this.dataset.zone || ctx.activeZone);
            });
        });

        // Site editor palette buttons (left panel)
        document.querySelectorAll('.se-palette-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                Cruinn.addBlock(this.dataset.type, null, 0, ctx.activeZone);
            });
        });

        // Child add block buttons (inside rows/containers)
        document.querySelectorAll('.btn-add-child').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var parentBlockIdVal = this.dataset.parentBlock;
                var col = this.dataset.column || 0;
                // Prefer explicit data-zone stamped by PHP; fall back to DOM traversal
                var zone = this.dataset.zone ||
                    (function (el) {
                        var z = el.closest('.se-zone[data-zone]');
                        return z ? z.dataset.zone : null;
                    }(this));
                Cruinn.showChildBlockPicker(this, parentBlockIdVal, col, zone);
            });
        });

        // Inline file upload for image blocks
        document.querySelectorAll('.block-file-upload').forEach(function (input) {
            // Only operate on image block file inputs (not gallery — those are handled by gallery.js)
            if (input.closest('.block-gallery-editor')) return;
            input.addEventListener('change', function () {
                if (!this.files.length) return;
                var blockItem = this.closest('.block-editor-item');
                var urlInput = blockItem.querySelector('[name="content_src"]') || blockItem.querySelector('.block-url-input');
                var preview = blockItem.querySelector('.block-image-preview');
                Cruinn.uploadFile(this.files[0], function (url) {
                    if (urlInput) urlInput.value = url;
                    if (preview) {
                        preview.src = url;
                    } else {
                        var img = document.createElement('img');
                        img.src = url;
                        img.className = 'block-image-preview';
                        img.alt = '';
                        var fields = blockItem.querySelector('.block-image-fields');
                        if (fields) fields.appendChild(img);
                    }
                });
                this.value = '';
            });
        });

        // Browse media button for image blocks
        document.querySelectorAll('.block-image-fields .btn-browse-media').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var blockItem = this.closest('.block-editor-item');
                var urlInput = blockItem.querySelector('[name="content_src"]') || blockItem.querySelector('.block-url-input');
                var preview = blockItem.querySelector('.block-image-preview');
                Cruinn.openMediaBrowser(function (url) {
                    if (urlInput) urlInput.value = url;
                    if (preview) {
                        preview.src = url;
                    } else {
                        var img = document.createElement('img');
                        img.src = url;
                        img.className = 'block-image-preview';
                        img.alt = '';
                        var fields = blockItem.querySelector('.block-image-fields');
                        if (fields) fields.appendChild(img);
                    }
                });
            });
        });

        // Editor mode switching
        document.querySelectorAll('input[name="editor_mode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                fetch('/admin/editor-mode', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': ctx.csrfToken,
                    },
                    body: new URLSearchParams({
                        parent_type: ctx.parentType,
                        parent_id: ctx.parentId,
                        editor_mode: this.value,
                        _csrf_token: ctx.csrfToken,
                    }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (window.Cruinn && Cruinn.suppressBeforeUnload) Cruinn.suppressBeforeUnload();
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        console.error('Mode switch failed:', err);
                    });
            });
        });

        // Auto-save on change / blur for all existing block inputs
        document.querySelectorAll('.block-editor-item').forEach(function (blockItem) {
            blockItem.querySelectorAll('input, textarea, select').forEach(function (el) {
                el.addEventListener('change', function () { Cruinn.autoSaveBlock(blockItem); });
            });
            var rte = blockItem.querySelector('.rte-editor');
            if (rte) {
                var saveTimer;
                rte.addEventListener('input', function () {
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(function () { Cruinn.autoSaveBlock(blockItem); }, 1500);
                });
            }
        });

    };


})(window.Cruinn = window.Cruinn || {});
