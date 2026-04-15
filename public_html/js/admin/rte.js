/**
 * Cruinn Admin — Rich Text Editor
 *
 * Initialises all contenteditable-based RTE instances on the page.
 * Depends on: utils.js, media-browser.js
 */
(function (Cruinn) {

    // ── Private helpers ────────────────────────────────────────

    function syncToTextarea(editor, textarea) {
        textarea.value = editor.innerHTML;
    }

    function updateToolbarState(toolbar, editor) {
        var commands = ['bold', 'italic', 'underline', 'strikethrough'];
        commands.forEach(function (cmd) {
            var btn = toolbar.querySelector('[data-cmd="' + cmd + '"]');
            if (btn) {
                btn.classList.toggle('active', document.queryCommandState(cmd));
            }
        });

        var formatSelect = toolbar.querySelector('.rte-block-format');
        if (formatSelect) {
            var val = document.queryCommandValue('formatBlock').toLowerCase().replace(/[<>]/g, '');
            if (val === 'div' || val === '') val = 'p';
            for (var i = 0; i < formatSelect.options.length; i++) {
                if (formatSelect.options[i].value === val) {
                    formatSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }

    /**
     * Strip potentially dangerous elements and attributes from pasted HTML.
     * Keeps basic formatting tags; removes scripts, styles, event handlers.
     */
    function sanitisePastedHtml(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');

        doc.querySelectorAll(
            'script, style, iframe, object, embed, form, input, select, textarea, link, meta'
        ).forEach(function (el) { el.remove(); });

        doc.querySelectorAll('*').forEach(function (el) {
            var attrs = Array.from(el.attributes);
            attrs.forEach(function (attr) {
                if (
                    attr.name.startsWith('on') ||
                    attr.name === 'style' ||
                    attr.name === 'class' ||
                    attr.name === 'id'
                ) {
                    el.removeAttribute(attr.name);
                }
            });
        });

        return doc.body.innerHTML;
    }

    // ── Public API ─────────────────────────────────────────────

    /**
     * Initialise all .rte-wrap instances on the page.
     * Each wrap must contain .rte-toolbar, .rte-editor, and .rte-source-textarea.
     */
    Cruinn.initRichTextEditors = function () {
        document.querySelectorAll('.rte-wrap').forEach(function (wrap) {
            var toolbar = wrap.querySelector('.rte-toolbar');
            var editor = wrap.querySelector('.rte-editor');
            var source = wrap.querySelector('.rte-source-textarea');
            if (!toolbar || !editor || !source) return;

            var sourceMode = false;

            // ── Toolbar command buttons ───────────────────────
            toolbar.querySelectorAll('.rte-btn[data-cmd]').forEach(function (btn) {
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (sourceMode) return;
                    var cmd = this.dataset.cmd;
                    if (cmd === 'createLink') {
                        var url = prompt('Enter URL:', 'https://');
                        if (url) document.execCommand('createLink', false, url);
                    } else {
                        document.execCommand(cmd, false, null);
                    }
                    editor.focus();
                    syncToTextarea(editor, source);
                    updateToolbarState(toolbar, editor);
                });
            });

            // ── Block format dropdown ─────────────────────────
            var formatSelect = toolbar.querySelector('.rte-block-format');
            if (formatSelect) {
                formatSelect.addEventListener('mousedown', function (e) { e.stopPropagation(); });
                formatSelect.addEventListener('change', function () {
                    if (sourceMode) return;
                    document.execCommand('formatBlock', false, '<' + this.value + '>');
                    editor.focus();
                    syncToTextarea(editor, source);
                });
            }

            // ── Insert image (opens media browser) ───────────
            var imgBtn = toolbar.querySelector('.rte-btn-image');
            if (imgBtn) {
                imgBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                imgBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (sourceMode) return;
                    var sel = window.getSelection();
                    var savedRange = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
                    Cruinn.openMediaBrowser(function (url) {
                        editor.focus();
                        if (savedRange) {
                            sel.removeAllRanges();
                            sel.addRange(savedRange);
                        }
                        document.execCommand('insertImage', false, url);
                        syncToTextarea(editor, source);
                    });
                });
            }

            // ── Source / HTML view toggle ─────────────────────
            var sourceBtn = toolbar.querySelector('.rte-btn-source');
            if (sourceBtn) {
                sourceBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                sourceBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    sourceMode = !sourceMode;
                    if (sourceMode) {
                        source.value = editor.innerHTML;
                        editor.style.display = 'none';
                        source.style.display = '';
                        sourceBtn.classList.add('active');
                    } else {
                        editor.innerHTML = source.value;
                        source.style.display = 'none';
                        editor.style.display = '';
                        sourceBtn.classList.remove('active');
                    }
                });
            }

            // ── Sync on input ─────────────────────────────────
            editor.addEventListener('input', function () {
                syncToTextarea(editor, source);
            });

            // ── Keyboard shortcuts ────────────────────────────
            editor.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && !e.shiftKey) {
                    var key = e.key.toLowerCase();
                    if (key === 'b') {
                        e.preventDefault(); document.execCommand('bold');
                        syncToTextarea(editor, source);
                    } else if (key === 'i') {
                        e.preventDefault(); document.execCommand('italic');
                        syncToTextarea(editor, source);
                    } else if (key === 'u') {
                        e.preventDefault(); document.execCommand('underline');
                        syncToTextarea(editor, source);
                    } else if (key === 'k') {
                        e.preventDefault();
                        var linkUrl = prompt('Enter URL:', 'https://');
                        if (linkUrl) document.execCommand('createLink', false, linkUrl);
                        syncToTextarea(editor, source);
                    }
                }
            });

            // ── Paste: sanitise pasted HTML ───────────────────
            editor.addEventListener('paste', function (e) {
                e.preventDefault();
                var html = e.clipboardData.getData('text/html');
                var text = e.clipboardData.getData('text/plain');
                if (html) {
                    document.execCommand('insertHTML', false, sanitisePastedHtml(html));
                } else {
                    document.execCommand('insertText', false, text);
                }
                syncToTextarea(editor, source);
            });

            // ── Toolbar state tracking ────────────────────────
            editor.addEventListener('mouseup', function () { updateToolbarState(toolbar, editor); });
            editor.addEventListener('keyup', function () { updateToolbarState(toolbar, editor); });
        });
    };

})(window.Cruinn = window.Cruinn || {});
