/**
 * Cruinn CMS â€” Page Editor
 * Standalone IIFE. No external dependencies. No build step.
 * Sections: A Init, B IDs, C Selection, D contenteditable, E DnD,
 *           F Properties, G Palette, H Media, I Serialise, J Undo/Redo,
 *           K Publish, L Keyboard
 */
(function () {
    'use strict';

    // â”€â”€ Section A â€” Init â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    var wrap = document.getElementById('editor-wrap');
    var canvas = document.getElementById('editor-canvas');
    var panel = document.getElementById('editor-props');

    if (!wrap || !canvas || !panel) { return; }

    var PAGE_ID = wrap.dataset.pageId;
    var CSRF = wrap.dataset.csrf;
    var API_BASE = wrap.dataset.apiBase || '/admin/editor';
    var liveStyles = document.getElementById('editor-live-styles');

    // ── Viewport (responsive breakpoint) state ────────────────────
    // 'desktop' | 'tablet' | 'mobile'
    var activeViewport = 'desktop';
    var VIEWPORT_WIDTHS = { desktop: null, tablet: 600, mobile: 360 };

    // Template zones available for this page (empty on template canvas / zone pages)
    var TEMPLATE_ZONES = (function () {
        try { return JSON.parse(wrap.dataset.templateZones || '[]'); } catch (e) { return []; }
    }());

    // Context fields for content templates: [{key, label, type}]
    // Non-empty only when editing a content template canvas with a context_source assigned.
    var CONTEXT_FIELDS = (function () {
        try { return JSON.parse(wrap.dataset.contextFields || '[]'); } catch (e) { return []; }
    }());

    document.addEventListener('DOMContentLoaded', function () {
        restoreCssProps();
        reInitAll();
        bindPalette();
        bindToolbar();
        bindAccordions();
        bindKeyboard();
        initCanvasResize();
        bindDocPanel();
        // Auto-enter code view for HTML render-mode pages
        if (wrap.dataset.startInCodeView === '1') {
            _htmlPageMode = true;
            enterCodeView({ html: wrap.dataset.htmlContent || '' });
        }
        // Draft resume banner
        if (wrap.dataset.hasDraft === '1') {
            var draftBanner = document.getElementById('editor-draft-banner');
            if (draftBanner) {
                draftBanner.style.display = 'flex';
                document.getElementById('editor-draft-continue-btn').addEventListener('click', function () {
                    draftBanner.style.display = 'none';
                });
                document.getElementById('editor-draft-discard-btn').addEventListener('click', function () {
                    var btn = this;
                    btn.disabled = true;
                    btn.textContent = 'Discarding\u2026';
                    fetch(API_BASE + '/' + PAGE_ID + '/discard', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.redirect) { window.location.href = data.redirect; }
                            else { btn.disabled = false; btn.textContent = 'Discard & reload published'; }
                        })
                        .catch(function () { btn.disabled = false; btn.textContent = 'Discard & reload published'; });
                });
            }
        }
    });

    /**
     * Re-run after any DOM change (add/remove/move blocks).
     */
    function reInitAll() {
        initDnD(null);
        updateBlockTree();
        // Sync zone block data-zone-name attributes from their block_config
        canvas.querySelectorAll('[data-block-type="zone"]').forEach(function (zoneEl) {
            var cfg = {};
            try { cfg = JSON.parse(zoneEl.dataset.blockConfig || '{}'); } catch (e) { }
            zoneEl.setAttribute('data-zone-name', cfg.zone_name || 'main');
        });
        // Sync zone assignment badges on regular page blocks when TEMPLATE_ZONES is active
        if (TEMPLATE_ZONES.length > 0) {
            canvas.querySelectorAll('[data-block]').forEach(function (bl) {
                var parentBlockEl = bl.parentElement ? bl.parentElement.closest('[data-block]') : null;
                if (parentBlockEl) { return; } // only root-level blocks
                var cfg = {};
                try { cfg = JSON.parse(bl.dataset.blockConfig || '{}'); } catch (e) { }
                bl.setAttribute('data-zone-assigned', cfg.zone_name || 'main');
            });
        }
        if (activeBlock) {
            activeBlock.setAttribute('contenteditable', 'true');
        }
    }

    /**
     * On editor load, copy data-css-props onto each block's inline style
     * so writeProps / rebuildLiveStyles have a consistent baseline.
     */
    function restoreCssProps() {
        canvas.querySelectorAll('[data-block]').forEach(function (block) {
            // Desktop props → inline styles (base)
            var raw = block.dataset.cssProps;
            if (raw) {
                try {
                    var props = JSON.parse(raw);
                    Object.keys(props).forEach(function (p) {
                        if (p[0] === '_') { return; }
                        if ((p === 'height' || p === 'width') &&
                            (props[p] === '0' || props[p] === '0px')) { return; }
                        block.style.setProperty(p, props[p]);
                    });
                } catch (e) { /* ignore malformed */ }
            }
        });
        rebuildLiveStyles();
    }

    // —— Viewport switching ——————————————————————————
    function switchViewport(vp) {
        activeViewport = vp;
        var canvasWrap = document.getElementById('editor-canvas-wrap');
        if (canvasWrap) {
            canvasWrap.classList.remove('vp-tablet', 'vp-mobile');
            if (vp === 'tablet') { canvasWrap.classList.add('vp-tablet'); }
            if (vp === 'mobile') { canvasWrap.classList.add('vp-mobile'); }
        }
        document.querySelectorAll('.editor-vp-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.viewport === vp);
        });
        if (activeBlock) { loadProps(activeBlock); }
    }

    document.querySelectorAll('.editor-vp-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchViewport(btn.dataset.viewport);
        });
    });

    // â”€â”€ Section B â€” Block ID generation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    function newId() {
        return 'b-' + Math.random().toString(36).slice(2, 10);
    }

    // â”€â”€ Section C â€” Block selection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    var activeBlock = null;

    // Intercept all interactive element clicks in the canvas â€” prevent navigation/submission.
    // Ctrl+click or Cmd+click opens anchor href in a new tab (for preview).
    canvas.addEventListener('click', function (e) {
        // Prevent anchor navigation
        var anchor = e.target.closest('a');
        if (anchor && canvas.contains(anchor)) {
            if ((e.ctrlKey || e.metaKey) && anchor.href) {
                window.open(anchor.href, '_blank', 'noopener,noreferrer');
            }
            e.preventDefault();
        }

        // Prevent button clicks from doing anything (form submit, JS handlers, etc.)
        var button = e.target.closest('button, input[type="submit"], input[type="button"]');
        if (button && canvas.contains(button)) {
            e.preventDefault();
            e.stopPropagation();
        }

        var b = e.target.closest('[data-block]');
        if (!b) { deselect(); return; }
        e.stopPropagation();
        select(b);
    });

    // Prevent all form submissions inside the canvas
    canvas.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();
    });

    document.addEventListener('click', function (e) {
        if (!canvas.contains(e.target) && !panel.contains(e.target)) {
            deselect();
        }
    });

    function select(block) {
        if (activeBlock === block) { return; }
        if (activeBlock) {
            activeBlock.classList.remove('active');
            activeBlock.removeAttribute('contenteditable');
        }
        activeBlock = block;
        block.classList.add('active');
        enableEditable(block);
        loadProps(block);
        updateBlockTree();
    }

    function deselect() {
        if (activeBlock) {
            activeBlock.classList.remove('active');
            activeBlock.removeAttribute('contenteditable');
            activeBlock = null;
        }
        clearPanel();
        updateBlockTree();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { deselect(); }
    });

    // â”€â”€ Section D â€” contenteditable + mini-toolbar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    var EDITABLE_TYPES = ['text', 'heading', 'html', 'site-title'];
    var miniBar = document.getElementById('editor-mini-toolbar');

    function enableEditable(block) {
        // Any leaf block (no child blocks) gets contenteditable
        if (block.querySelector('[data-block]')) { return; }
        block.setAttribute('contenteditable', 'true');
        block.addEventListener('input', debounceAction);
    }

    // Position and show the mini-toolbar on text selection
    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || !activeBlock ||
            !activeBlock.hasAttribute('contenteditable')) {
            hideMiniBar();
            return;
        }
        var range = sel.getRangeAt(0);
        if (!activeBlock.contains(range.commonAncestorContainer)) {
            hideMiniBar();
            return;
        }
        var rect = range.getBoundingClientRect();
        miniBar.style.display = 'flex';
        miniBar.style.top = (rect.top + window.scrollY - miniBar.offsetHeight - 6) + 'px';
        miniBar.style.left = (rect.left + window.scrollX) + 'px';
    });

    function hideMiniBar() {
        if (miniBar) { miniBar.style.display = 'none'; }
    }

    miniBar.addEventListener('mousedown', function (e) {
        e.preventDefault(); // keep selection alive
    });

    miniBar.querySelectorAll('[data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cmd = btn.dataset.cmd;
            var prompt = btn.dataset.cmdPrompt;
            var val = prompt ? window.prompt(prompt) : null;
            if (cmd === 'createLink' && !val) { return; }
            document.execCommand(cmd, false, val || null);
            hideMiniBar();
            debounceAction();
        });
    });

    // â”€â”€ Section E â€” Drag and Drop â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    var dragSrc = null;

    function initDnD(root) {
        (root || canvas).querySelectorAll('[data-block]').forEach(function (block) {
            block.setAttribute('draggable', 'true');

            block.addEventListener('dragstart', function (e) {
                dragSrc = block;
                block.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', block.id);
                e.stopPropagation();
            });

            block.addEventListener('dragend', function () {
                block.classList.remove('dragging');
                canvas.querySelectorAll('.drag-over').forEach(function (el) {
                    el.classList.remove('drag-over');
                });
                dragSrc = null;
            });

            block.addEventListener('dragover', function (e) {
                if (!dragSrc || dragSrc === block) { return; }
                // Don't allow a block to be dropped into itself
                if (dragSrc.contains(block)) { return; }
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                canvas.querySelectorAll('.drag-over, .drag-over-inside').forEach(function (el) {
                    el.classList.remove('drag-over', 'drag-over-inside');
                });
                // Container: any block that has child data-block elements OR is a layout type
                var blockType = block.dataset.blockType;
                var def = BLOCK_DEFS[blockType] || {};
                var hasChildren = !!block.querySelector('[data-block]');
                var isContainer = def.isLayout || hasChildren;
                if (isContainer) {
                    // Empty layout containers: entire area is drop zone
                    // Non-empty containers: middle 60% is drop zone
                    var rect = block.getBoundingClientRect();
                    var relY = (e.clientY - rect.top) / rect.height;
                    var inDropZone = !hasChildren || (relY > 0.2 && relY < 0.8);
                    if (inDropZone) {
                        block.classList.add('drag-over-inside');
                        e.stopPropagation();
                        return;
                    }
                }
                block.classList.add('drag-over');
                e.stopPropagation();
            });

            block.addEventListener('dragleave', function () {
                block.classList.remove('drag-over', 'drag-over-inside');
            });

            block.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!dragSrc || dragSrc === block) { return; }
                if (dragSrc.contains(block)) { return; }

                block.classList.remove('drag-over', 'drag-over-inside');

                var blockType = block.dataset.blockType;
                var def = BLOCK_DEFS[blockType] || {};
                var hasChildren = !!block.querySelector('[data-block]');
                var isContainer = def.isLayout || hasChildren;
                if (isContainer) {
                    // Empty layout containers: entire area is drop zone
                    // Non-empty containers: middle 60% is drop zone
                    var rect = block.getBoundingClientRect();
                    var relY = (e.clientY - rect.top) / rect.height;
                    var inDropZone = !hasChildren || (relY > 0.2 && relY < 0.8);
                    if (inDropZone) {
                        // Drop inside the container
                        block.appendChild(dragSrc);
                        reInitAll();
                        recordAction();
                        return;
                    }
                }

                // Sibling insert: before or after the target block
                var midY = block.getBoundingClientRect().top + block.getBoundingClientRect().height / 2;
                var before = e.clientY < midY;
                var parent = block.parentNode;
                if (before) {
                    parent.insertBefore(dragSrc, block);
                } else {
                    parent.insertBefore(dragSrc, block.nextSibling);
                }

                reInitAll();
                recordAction();
            });
        });

        // Also allow dropping onto the canvas itself (top level)
        canvas.addEventListener('dragover', function (e) {
            if (!dragSrc) { return; }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        canvas.addEventListener('drop', function (e) {
            if (!dragSrc) { return; }
            e.preventDefault();
            // If the direct target is the canvas itself, append at end
            if (e.target === canvas) {
                canvas.appendChild(dragSrc);
                reInitAll();
                recordAction();
            }
        });
    }

    // â”€â”€ Section F â€” Properties panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    var LAYOUT_TYPES = ['section', 'columns', 'table', 'site-header', 'nav-menu'];
    var ZONE_TYPES = ['zone'];
    var IMAGE_TYPES = ['image', 'site-logo'];
    var TITLE_TYPES = ['site-title'];
    var DYNAMIC_TYPES = ['event-list', 'data-list'];
    var CONFIG_TYPES = ['event-list', 'nav-menu', 'php-include', 'data-list'];
    var PHP_CODE_TYPES = ['php-code'];
    // Block types whose inner_html slot is bindable
    var BIND_INNER_TYPES = ['text', 'html', 'heading', 'inline', 'anchor'];
    // Block types whose src slot is bindable
    var BIND_SRC_TYPES = ['image', 'site-logo'];
    // Block types whose href slot is bindable
    var BIND_HREF_TYPES = ['anchor'];

    function loadProps(block) {
        var type = block.dataset.blockType;
        var cs = getComputedStyle(block); // computed styles for reading actual CSS values

        // Show/hide groups
        panel.querySelector('.editor-props-empty').style.display = 'none';
        panel.querySelectorAll('.editor-accordion').forEach(function (acc) {
            acc.style.display = '';
        });

        // Show/hide the zone assignment row (root-level blocks only, when template has zones)
        var zoneAssignRow = document.getElementById('prop-zone-assign-row');
        var zoneAssignSel = document.getElementById('prop-zone-assign');
        if (zoneAssignRow && zoneAssignSel) {
            var parentBlockEl = block.parentElement ? block.parentElement.closest('[data-block]') : null;
            var isRootBlock = !parentBlockEl;
            if (TEMPLATE_ZONES.length > 0 && isRootBlock && ZONE_TYPES.indexOf(type) === -1) {
                // Populate options
                zoneAssignSel.innerHTML = '';
                TEMPLATE_ZONES.forEach(function (zn) {
                    var opt = document.createElement('option');
                    opt.value = zn;
                    opt.textContent = zn;
                    zoneAssignSel.appendChild(opt);
                });
                // Read current zone_name from block_config
                var bCfg = {};
                try { bCfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                zoneAssignSel.value = bCfg.zone_name || TEMPLATE_ZONES[0] || 'main';
                zoneAssignRow.style.display = '';
            } else {
                zoneAssignRow.style.display = 'none';
            }
        }

        // Zone group (zone blocks only)
        var zoneAcc = panel.querySelector('[data-group="zone"]');
        if (zoneAcc) {
            zoneAcc.style.display = ZONE_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // Layout group only for section/columns/table
        var layoutAcc = panel.querySelector('[data-group="layout"]');
        if (layoutAcc) {
            layoutAcc.style.display = LAYOUT_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // Column count row: only for 'columns' block type
        var colCountRow = document.getElementById('prop-col-count-row');
        if (colCountRow) {
            colCountRow.style.display = type === 'columns' ? '' : 'none';
        }

        // Image group for image/site-logo blocks
        var imageAcc = panel.querySelector('[data-group="image"]');
        if (imageAcc) {
            imageAcc.style.display = IMAGE_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // Content group for dynamic and configurable blocks
        var contentAcc = panel.querySelector('[data-group="content"]');
        if (contentAcc) {
            contentAcc.style.display = CONFIG_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // PHP Code group
        var phpCodeAcc = panel.querySelector('[data-group="php-code"]');
        if (phpCodeAcc) {
            phpCodeAcc.style.display = PHP_CODE_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // Bind group — only when this page has context fields and the block type has bindable slots
        var bindAcc = panel.querySelector('[data-group="bind"]');
        if (bindAcc) {
            var isBindable = CONTEXT_FIELDS.length > 0 && (
                BIND_INNER_TYPES.indexOf(type) !== -1 ||
                BIND_SRC_TYPES.indexOf(type) !== -1 ||
                BIND_HREF_TYPES.indexOf(type) !== -1
            );
            bindAcc.style.display = isBindable ? '' : 'none';
            if (isBindable) {
                populateBindAccordion(block, type);
            }
        }

        // Site title group
        var siteTitleAcc = panel.querySelector('[data-group="site-title"]');
        if (siteTitleAcc) {
            siteTitleAcc.style.display = TITLE_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // Show the right content sub-group
        panel.querySelectorAll('.editor-content-group').forEach(function (g) {
            g.style.display = g.dataset.contentType === type ? '' : 'none';
        });

        // Identity
        var idInput = document.getElementById('prop-block-id');
        if (idInput) { idInput.value = block.id || ''; }
        var typeInput = document.getElementById('prop-block-type');
        if (typeInput) { typeInput.value = type || ''; }
        var classInput = panel.querySelector('[data-prop-class]');
        if (classInput) { classInput.value = block.className.replace(/\b(active|collapsed)\b/g, '').replace(/\s+/g, ' ').trim(); }

        // Collapsed checkbox
        var collapsedCb = document.getElementById('prop-collapsed');
        if (collapsedCb) { collapsedCb.checked = block.classList.contains('collapsed'); }

        // Block config (for dynamic/configurable blocks and UI behavior)
        var config = {};
        try {
            config = JSON.parse(block.dataset.blockConfig || '{}');
        } catch (e) { /* ignore */ }

        // Responsive UI collapse controls
        var uiCollapseEnabled = document.getElementById('prop-ui-collapse-enabled');
        var uiCollapseRow = document.getElementById('prop-ui-collapse-row');
        var uiCollapseSel = document.getElementById('prop-ui-collapse');
        var uiCollapseLabelRow = document.getElementById('prop-ui-collapse-label-row');
        var uiCollapseLabelInp = document.getElementById('prop-ui-collapse-label');
        var uiCollapseAlignRow = document.getElementById('prop-ui-collapse-align-row');
        var uiCollapseAlignSel = document.getElementById('prop-ui-collapse-align');
        var uiCollapse = (config.ui_collapse || '').toString();
        if (uiCollapseEnabled && uiCollapseRow && uiCollapseSel) {
            var isEnabled = uiCollapse === 'tablet' || uiCollapse === 'mobile';
            uiCollapseEnabled.checked = isEnabled;
            uiCollapseRow.style.display = isEnabled ? '' : 'none';
            uiCollapseSel.value = isEnabled ? uiCollapse : 'tablet';
            if (uiCollapseAlignRow && uiCollapseAlignSel) {
                uiCollapseAlignRow.style.display = isEnabled ? '' : 'none';
                uiCollapseAlignSel.value = (config.ui_collapse_align || '').toString();
            }
            if (uiCollapseLabelRow && uiCollapseLabelInp) {
                uiCollapseLabelRow.style.display = isEnabled ? '' : 'none';
                uiCollapseLabelInp.value = (config.ui_collapse_label || '').toString();
            }
        }

        // CSS properties — read from active viewport overrides, fallback to computed desktop
        var vpPropsRaw = activeViewport === 'tablet' ? block.dataset.cssPropsTablet
            : activeViewport === 'mobile' ? block.dataset.cssPropsMobile
                : null;
        var vpProps = {};
        if (vpPropsRaw) { try { vpProps = JSON.parse(vpPropsRaw); } catch (e) { } }

        panel.querySelectorAll('[data-prop]').forEach(function (inp) {
            var prop = inp.dataset.prop;
            var val = (activeViewport !== 'desktop' && vpProps[prop] !== undefined)
                ? vpProps[prop]
                : (cs[prop] || '');
            // Skip transparent/initial values that aren't meaningful
            var isTransparent = val === 'transparent' || val === 'rgba(0, 0, 0, 0)';
            if (inp.type === 'color') {
                // Normalise to hex if possible
                inp.value = isTransparent ? '#000000' : (rgbToHex(val) || '#000000');
            } else if (inp.type === 'range') {
                // Range inputs: use 1 as default when unset (e.g. opacity)
                inp.value = val !== '' ? val : inp.defaultValue || '1';
            } else if (inp.type === 'number') {
                // Number inputs: extract numeric portion only
                var numMatch = val.match(/^([\d.]+)/);
                inp.value = numMatch ? numMatch[1] : '';
            } else {
                // Colour-text inputs paired with a swatch: normalise to hex or empty string
                if (panel.querySelector('[data-color-swatch="' + prop + '"]')) {
                    inp.value = isTransparent ? '' : (rgbToHex(val) || '');
                } else {
                    // For selects, only set if value is a valid option
                    if (inp.tagName === 'SELECT') {
                        var hasOption = Array.from(inp.options).some(function (o) { return o.value === val; });
                        inp.value = hasOption ? val : '';
                    } else {
                        inp.value = val;
                    }
                }
            }
        });

        // Sync colour swatches to their block CSS values (from computed styles)
        panel.querySelectorAll('[data-color-swatch]').forEach(function (swatch) {
            var prop = swatch.dataset.colorSwatch;
            var val = cs[prop] || '';
            var isTransparent = val === 'transparent' || val === 'rgba(0, 0, 0, 0)';
            swatch.value = isTransparent ? '#000000' : (rgbToHex(val) || '#000000');
        });

        // Numeric + unit props — on breakpoints, prefer stored override
        panel.querySelectorAll('[data-prop-num]').forEach(function (inp) {
            var prop = inp.dataset.propNum;
            var vpVal = (activeViewport !== 'desktop' && vpProps[prop] !== undefined) ? vpProps[prop] : null;
            // Check inline style first for 'auto', then fall back to computed
            var inline = vpVal !== null ? vpVal : (block.style[prop] || '');
            var raw = inline || cs[prop] || '';
            var unitSel = panel.querySelector('[data-unit-for="' + prop + '"]');
            if (inline === 'auto') {
                inp.value = '';
                inp.disabled = true;
                inp.placeholder = 'auto';
                if (unitSel) unitSel.value = 'auto';
            } else {
                inp.disabled = false;
                var match = raw.match(/^([\d.]+)([a-z%]*)$/);
                inp.value = match ? match[1] : '';
                if (unitSel && match && match[2]) {
                    unitSel.value = match[2];
                } else if (unitSel && !match) {
                    // Reset to default unit when no value
                    unitSel.value = 'px';
                }
            }
        });

        panel.querySelectorAll('[data-config]').forEach(function (inp) {
            var key = inp.dataset.config;
            if (config[key] !== undefined) {
                inp.value = config[key];
            }
        });

        // Columns block: apply grid style from saved column count
        if (type === 'columns') {
            var colCount = parseInt(config.columns, 10) || 2;
            block.style.display = 'grid';
            block.style.gridTemplateColumns = 'repeat(' + colCount + ', 1fr)';
            var colCountInp = document.getElementById('prop-col-count');
            if (colCountInp) { colCountInp.value = colCount; }
        }

        // PHP Code: populate textarea from block_config._php
        if (PHP_CODE_TYPES.indexOf(type) !== -1) {
            var phpCodeTa = document.getElementById('prop-php-code');
            if (phpCodeTa) { phpCodeTa.value = config._php || ''; }
        }

        // PHP Include: populate template picker + dynamic var rows + live canvas preview
        if (type === 'php-include') {
            var phpTplPicker = panel.querySelector('.php-include-tpl-picker');
            if (phpTplPicker) { phpTplPicker.value = config.template || ''; }
            var phpVarsContainer = panel.querySelector('.php-include-vars');
            if (phpVarsContainer) { buildPhpIncludeVarRows(phpVarsContainer, config, block); }
            refreshPhpIncludePreview(block);
        }

        // Data List: populate props from config
        if (type === 'data-list') {
            var dlSetSel2 = document.getElementById('prop-data-list-set');
            if (dlSetSel2) { dlSetSel2.value = config.set_slug || ''; }
            var dlViewSel = document.getElementById('prop-data-list-view');
            if (dlViewSel) { dlViewSel.value = config.view || 'continuous'; }
            var dlCard = document.getElementById('prop-data-list-card');
            if (dlCard) { dlCard.value = config.card_html || ''; }
            updateDataListTokenHints(config.set_slug || '');
        }

        // Site title / tagline text
        if (TITLE_TYPES.indexOf(type) !== -1) {
            var h1El = block.querySelector('.site-name');
            var tagEl = block.querySelector('.site-tagline');
            var tInp = document.getElementById('prop-site-title-text');
            var tagInp = document.getElementById('prop-site-tagline-text');
            if (tInp) { tInp.value = h1El ? (h1El.textContent || '') : ''; }
            if (tagInp) { tagInp.value = tagEl ? (tagEl.textContent || '') : ''; }
        }

        // Image attribute props (src/alt/width/height on the inner <img>)
        if (IMAGE_TYPES.indexOf(type) !== -1) {
            var img = block.querySelector('img');
            var srcInp = document.getElementById('prop-img-src');
            var altInp = document.getElementById('prop-img-alt');
            var wInp = document.getElementById('prop-img-width-num');
            var wUnit = document.getElementById('prop-img-width-unit');
            var hInp = document.getElementById('prop-img-height-num');
            var hUnit = document.getElementById('prop-img-height-unit');
            if (img) {
                if (srcInp) { srcInp.value = img.getAttribute('src') || ''; }
                if (altInp) { altInp.value = img.getAttribute('alt') || ''; }
                if (wInp) {
                    var wMatch = (img.style.width || img.getAttribute('width') || '').match(/^([\.\d]+)([a-z%]*)$/);
                    wInp.value = wMatch ? wMatch[1] : '';
                    if (wUnit && wMatch && wMatch[2]) { wUnit.value = wMatch[2]; }
                }
                if (hInp) {
                    var hMatch = (img.style.height || img.getAttribute('height') || '').match(/^([\.\d]+)([a-z%]*)$/);
                    hInp.value = hMatch ? hMatch[1] : '';
                    if (hUnit && hMatch && hMatch[2]) { hUnit.value = hMatch[2]; }
                }
            }
        }

        // Text shadow â€” parse back into sub-fields (from computed style)
        (function () {
            var tsX = document.getElementById('prop-text-shadow-x');
            var tsY = document.getElementById('prop-text-shadow-y');
            var tsBlur = document.getElementById('prop-text-shadow-blur');
            var tsCol = document.getElementById('prop-text-shadow-color');
            if (!tsX) { return; }
            var raw = cs.textShadow || '';
            // Skip 'none' value
            if (raw === 'none') { raw = ''; }
            var m = raw.match(/^(-?[\d.]+)px\s+(-?[\d.]+)px\s+([\d.]+)px\s+(.+)$/);
            if (m) {
                tsX.value = m[1];
                tsY.value = m[2];
                if (tsBlur) { tsBlur.value = m[3]; }
                if (tsCol) { tsCol.value = rgbToHex(m[4].trim()) || '#000000'; }
            } else {
                tsX.value = tsY.value = '';
                if (tsBlur) { tsBlur.value = ''; }
                if (tsCol) { tsCol.value = '#000000'; }
            }
        }());

        // Box shadow â€” parse back into sub-fields (from computed style)
        (function () {
            var bsX = document.getElementById('prop-box-shadow-x');
            var bsY = document.getElementById('prop-box-shadow-y');
            var bsBlur = document.getElementById('prop-box-shadow-blur');
            var bsSpread = document.getElementById('prop-box-shadow-spread');
            var bsCol = document.getElementById('prop-box-shadow-color');
            var bsInset = document.getElementById('prop-box-shadow-inset');
            if (!bsX) { return; }
            var raw = cs.boxShadow || '';
            // Skip 'none' value
            if (raw === 'none') { raw = ''; }
            var inset = /\binset\b/.test(raw);
            var clean = raw.replace(/\binset\b/g, '').trim();
            var m = clean.match(/^(-?[\d.]+)px\s+(-?[\d.]+)px\s+([\d.]+)px\s+(-?[\d.]+)px\s+(.+)$/);
            if (m) {
                bsX.value = m[1];
                bsY.value = m[2];
                if (bsBlur) { bsBlur.value = m[3]; }
                if (bsSpread) { bsSpread.value = m[4]; }
                if (bsCol) { bsCol.value = rgbToHex(m[5].trim()) || '#000000'; }
                if (bsInset) { bsInset.checked = inset; }
            } else {
                bsX.value = bsY.value = '';
                if (bsBlur) { bsBlur.value = ''; }
                if (bsSpread) { bsSpread.value = ''; }
                if (bsCol) { bsCol.value = '#000000'; }
                if (bsInset) { bsInset.checked = false; }
            }
        }());

        // Opacity label (from computed style)
        (function () {
            var opInp = panel.querySelector('[data-prop="opacity"]');
            var opLbl = document.getElementById('prop-opacity-label');
            if (opInp && opLbl) {
                var v = parseFloat(cs.opacity || 1);
                opLbl.textContent = Math.round(v * 100) + '%';
            }
        }());

        bindPropInputs(block);
    }

    function populateBindAccordion(block, type) {
        var bindCfg = {};
        try { bindCfg = (JSON.parse(block.dataset.blockConfig || '{}')).bind || {}; } catch (e) { }

        // Show/hide slot rows based on block type
        var innerRow = panel.querySelector('.editor-bind-row[data-bind-slot="inner_html"]');
        var srcRow = panel.querySelector('.editor-bind-row[data-bind-slot="src"]');
        var hrefRow = panel.querySelector('.editor-bind-row[data-bind-slot="href"]');

        if (innerRow) { innerRow.style.display = BIND_INNER_TYPES.indexOf(type) !== -1 ? '' : 'none'; }
        if (srcRow) { srcRow.style.display = BIND_SRC_TYPES.indexOf(type) !== -1 ? '' : 'none'; }
        if (hrefRow) { hrefRow.style.display = BIND_HREF_TYPES.indexOf(type) !== -1 ? '' : 'none'; }

        // Populate each select with context fields filtered by compatible type
        panel.querySelectorAll('.editor-bind-select').forEach(function (sel) {
            var slot = sel.dataset.bindSlot;
            // Determine which field types are compatible for this slot
            var compatTypes = slot === 'src' ? ['image'] :
                slot === 'href' ? ['url', 'text'] :
                    ['text', 'html', 'date', 'number', 'url'];
            // Rebuild options
            sel.innerHTML = '<option value="">— none —</option>';
            CONTEXT_FIELDS.forEach(function (f) {
                if (compatTypes.indexOf(f.type) !== -1 || slot === 'inner_html') {
                    var opt = document.createElement('option');
                    opt.value = f.key;
                    opt.textContent = f.label + ' (' + f.type + ')';
                    sel.appendChild(opt);
                }
            });
            // Restore saved binding
            sel.value = bindCfg[slot] || '';
            // Write binding back to block_config on change
            sel.onchange = function () {
                var cfg = {};
                try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                cfg.bind = cfg.bind || {};
                if (sel.value) {
                    cfg.bind[slot] = sel.value;
                } else {
                    delete cfg.bind[slot];
                    if (Object.keys(cfg.bind).length === 0) { delete cfg.bind; }
                }
                block.dataset.blockConfig = JSON.stringify(cfg);
                recordAction();
            };
        });
    }

    function clearPanel() {
        panel.querySelector('.editor-props-empty').style.display = '';
        panel.querySelectorAll('.editor-accordion').forEach(function (acc) {
            acc.style.display = 'none';
        });
    }

    function bindPropInputs(block) {
        // Remove previous listeners by cloning nodes for [data-prop] inputs
        // We use a delegated approach on panel instead â€” re-bind on each select
        panel.querySelectorAll('[data-prop]').forEach(function (inp) {
            var handler = function () {
                writeProps(block, inp.dataset.prop, inp.value);
                // Keep the paired colour swatch in sync when user types a valid hex
                var swatch = panel.querySelector('[data-color-swatch="' + inp.dataset.prop + '"]');
                if (swatch && /^#[0-9a-f]{6}$/i.test(inp.value)) { swatch.value = inp.value; }
            };
            inp.oninput = handler;
            // color pickers on some platforms only fire 'change' (not 'input') on commit
            if (inp.type === 'color') { inp.onchange = handler; }
        });

        panel.querySelectorAll('[data-prop-num]').forEach(function (inp) {
            inp.oninput = function () {
                var prop = inp.dataset.propNum;
                var unitSel = panel.querySelector('[data-unit-for="' + prop + '"]');
                var unit = unitSel ? unitSel.value : 'px';
                if (unit === 'auto') return;
                var val = inp.value !== '' ? inp.value + unit : '';
                writeProps(block, prop, val);
                // Width inside a flex/grid parent: also set flex-basis so the
                // container respects the value (flex layout ignores `width` on children).
                if (prop === 'width') {
                    var parentDisplay = block.parentElement
                        ? window.getComputedStyle(block.parentElement).display
                        : '';
                    if (parentDisplay === 'flex' || parentDisplay === 'inline-flex') {
                        block.style.flexBasis = val;
                        block.style.flexShrink = val ? '0' : '';
                    } else if (parentDisplay === 'grid' || parentDisplay === 'inline-grid') {
                        // grid items: width works, but ensure box-sizing is set
                        block.style.boxSizing = val ? 'border-box' : '';
                    }
                    rebuildLiveStyles();
                }
            };
        });

        panel.querySelectorAll('[data-unit-for]').forEach(function (sel) {
            sel.onchange = function () {
                var prop = sel.dataset.unitFor;
                var numInp = panel.querySelector('[data-prop-num="' + prop + '"]');
                if (sel.value === 'auto') {
                    if (numInp) { numInp.disabled = true; numInp.value = ''; numInp.placeholder = 'auto'; }
                    writeProps(block, prop, 'auto');
                } else {
                    if (numInp) { numInp.disabled = false; numInp.placeholder = ''; }
                    if (numInp && numInp.value !== '') {
                        writeProps(block, prop, numInp.value + sel.value);
                    }
                }
                // Sync flex-basis when width unit changes
                if (prop === 'width') {
                    var parentDisplay = block.parentElement
                        ? window.getComputedStyle(block.parentElement).display
                        : '';
                    if (parentDisplay === 'flex' || parentDisplay === 'inline-flex') {
                        block.style.flexBasis = block.style.width;
                    }
                    rebuildLiveStyles();
                }
            };
        });

        // Colour clear buttons
        panel.querySelectorAll('[data-clear-prop]').forEach(function (btn) {
            btn.onclick = function () {
                var prop = btn.dataset.clearProp;
                var textInp = panel.querySelector('[data-prop="' + prop + '"]');
                var swatch = panel.querySelector('[data-color-swatch="' + prop + '"]');
                if (textInp) { textInp.value = ''; }
                if (swatch) { swatch.value = '#000000'; }
                writeProps(block, prop, '');
            };
        });

        panel.querySelectorAll('[data-config]').forEach(function (inp) {
            inp.oninput = function () {
                writeConfig(block, inp.dataset.config, inp.value);
            };
            // columns block: update grid-template-columns and sync child sections
            if (inp.id === 'prop-col-count' && block.dataset.blockType === 'columns') {
                inp.oninput = function () {
                    var count = parseInt(inp.value, 10) || 2;
                    if (count < 1) count = 1;
                    if (count > 12) count = 12;
                    writeConfig(block, 'columns', count);
                    block.style.display = 'grid';
                    block.style.gridTemplateColumns = 'repeat(' + count + ', 1fr)';
                    syncColumnsChildren(block, count);
                    debounceAction();
                };
            }
            // nav-menu: live-update canvas preview when menu selection changes
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'menu_id' && block.dataset.blockType === 'nav-menu') {
                inp.onchange = function () {
                    writeConfig(block, 'menu_id', inp.value);
                    var menuId = parseInt(inp.value, 10);
                    if (!menuId) { block.innerHTML = ''; return; }
                    fetch(API_BASE + '/nav-menu-preview?menu_id=' + menuId, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) { block.innerHTML = data.html || ''; });
                };
            }
            // data-list: show/hide card HTML wrap depending on whether a content template is chosen
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'template_slug' && block.dataset.blockType === 'data-list') {
                var cardWrap = document.getElementById('prop-data-list-card-wrap');
                var syncCardWrap = function () {
                    if (cardWrap) { cardWrap.style.display = inp.value ? 'none' : ''; }
                };
                syncCardWrap();
                inp.onchange = function () {
                    writeConfig(block, 'template_slug', inp.value);
                    syncCardWrap();
                };
            }
        });

        // Zone name input: also update data-zone-name attribute for CSS label
        var zoneNameInp = document.getElementById('prop-zone-name');
        if (zoneNameInp && block.dataset.blockType === 'zone') {
            zoneNameInp.oninput = function () {
                writeConfig(block, 'zone_name', this.value);
                block.setAttribute('data-zone-name', this.value || 'main');
            };
        }

        // Zone assignment select: write zone_name to block_config + update badge attribute
        var zoneAssignSel2 = document.getElementById('prop-zone-assign');
        if (zoneAssignSel2 && TEMPLATE_ZONES.length > 0 && ZONE_TYPES.indexOf(block.dataset.blockType) === -1) {
            zoneAssignSel2.onchange = function () {
                writeConfig(block, 'zone_name', this.value);
                block.setAttribute('data-zone-assigned', this.value);
                debounceAction();
            };
        }

        var classInp = panel.querySelector('[data-prop-class]');
        if (classInp) {
            classInp.oninput = function () {
                // Preserve editor-managed and special classes; replace everything else
                var managed = ['active', 'dragging', 'drag-over', 'collapsed'];
                var keep = [];
                block.className.split(' ').forEach(function (c) {
                    if (managed.indexOf(c) !== -1) { keep.push(c); }
                });
                var extras = classInp.value.trim().split(/\s+/).filter(Boolean);
                block.className = keep.concat(extras).join(' ');
                debounceAction();
            };
        }

        // Collapsed checkbox
        var collapsedCb = document.getElementById('prop-collapsed');
        if (collapsedCb) {
            collapsedCb.onchange = function () {
                if (this.checked) {
                    block.classList.add('collapsed');
                } else {
                    block.classList.remove('collapsed');
                }
                debounceAction();
            };
        }

        // Responsive UI collapse controls
        var uiCollapseEnabled = document.getElementById('prop-ui-collapse-enabled');
        var uiCollapseRow = document.getElementById('prop-ui-collapse-row');
        var uiCollapseSel = document.getElementById('prop-ui-collapse');
        var uiCollapseLabelRow = document.getElementById('prop-ui-collapse-label-row');
        var uiCollapseLabelInp = document.getElementById('prop-ui-collapse-label');
        var uiCollapseAlignRow = document.getElementById('prop-ui-collapse-align-row');
        var uiCollapseAlignSel = document.getElementById('prop-ui-collapse-align');
        if (uiCollapseEnabled && uiCollapseRow && uiCollapseSel) {
            var writeUiCollapse = function () {
                var cfg = {};
                try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                if (uiCollapseEnabled.checked) {
                    cfg.ui_collapse = uiCollapseSel.value === 'mobile' ? 'mobile' : 'tablet';
                    if (uiCollapseAlignSel) {
                        var align = uiCollapseAlignSel.value;
                        if (align) { cfg.ui_collapse_align = align; } else { delete cfg.ui_collapse_align; }
                    }
                    if (uiCollapseLabelInp) {
                        var lbl = uiCollapseLabelInp.value.trim();
                        if (lbl) { cfg.ui_collapse_label = lbl; } else { delete cfg.ui_collapse_label; }
                    }
                } else {
                    delete cfg.ui_collapse;
                    delete cfg.ui_collapse_align;
                    delete cfg.ui_collapse_label;
                }
                block.dataset.blockConfig = JSON.stringify(cfg);
                recordAction();
            };

            uiCollapseEnabled.onchange = function () {
                uiCollapseRow.style.display = this.checked ? '' : 'none';
                if (uiCollapseAlignRow) { uiCollapseAlignRow.style.display = this.checked ? '' : 'none'; }
                if (uiCollapseLabelRow) { uiCollapseLabelRow.style.display = this.checked ? '' : 'none'; }
                if (this.checked && !uiCollapseSel.value) {
                    uiCollapseSel.value = 'tablet';
                }
                writeUiCollapse();
            };

            uiCollapseSel.onchange = function () {
                if (!uiCollapseEnabled.checked) { return; }
                writeUiCollapse();
            };

            if (uiCollapseAlignSel) {
                uiCollapseAlignSel.onchange = function () {
                    if (!uiCollapseEnabled.checked) { return; }
                    writeUiCollapse();
                };
            }

            if (uiCollapseLabelInp) {
                uiCollapseLabelInp.oninput = function () {
                    if (!uiCollapseEnabled.checked) { return; }
                    writeUiCollapse();
                };
            }
        }

        // Image block: src browse + attr bindings
        if (IMAGE_TYPES.indexOf(block.dataset.blockType) !== -1) {
            var imgSrcBrowse = document.getElementById('prop-img-src-browse-btn');
            if (imgSrcBrowse) {
                imgSrcBrowse.onclick = function () {
                    openMediaPanel(function (url) {
                        var img = block.querySelector('img');
                        if (img) { img.setAttribute('src', url); }
                        var srcInp = document.getElementById('prop-img-src');
                        if (srcInp) { srcInp.value = url; }
                        debounceAction();
                    });
                };
            }
            var srcInp = document.getElementById('prop-img-src');
            if (srcInp) {
                srcInp.oninput = function () {
                    var img = block.querySelector('img');
                    if (img) { img.setAttribute('src', srcInp.value); }
                    debounceAction();
                };
            }
            var altInp = document.getElementById('prop-img-alt');
            if (altInp) {
                altInp.oninput = function () {
                    var img = block.querySelector('img');
                    if (img) { img.setAttribute('alt', altInp.value); }
                    debounceAction();
                };
            }
            var wInp = document.getElementById('prop-img-width-num');
            var wUnit = document.getElementById('prop-img-width-unit');
            function applyImgWidth() {
                var img = block.querySelector('img');
                if (!img || !wInp || wInp.value === '') { return; }
                var unit = wUnit ? wUnit.value : 'px';
                img.style.width = wInp.value + unit;
                img.style.maxWidth = '100%';
                debounceAction();
            }
            if (wInp) { wInp.oninput = applyImgWidth; }
            if (wUnit) { wUnit.onchange = applyImgWidth; }

            var hInp = document.getElementById('prop-img-height-num');
            var hUnit = document.getElementById('prop-img-height-unit');
            function applyImgHeight() {
                var img = block.querySelector('img');
                if (!img || !hInp || hInp.value === '') { return; }
                var unit = hUnit ? hUnit.value : 'px';
                img.style.height = hInp.value + unit;
                debounceAction();
            }
            if (hInp) { hInp.oninput = applyImgHeight; }
            if (hUnit) { hUnit.onchange = applyImgHeight; }
        }

        // Site title / tagline text bindings
        if (TITLE_TYPES.indexOf(block.dataset.blockType) !== -1) {
            var tInp = document.getElementById('prop-site-title-text');
            if (tInp) {
                tInp.oninput = function () {
                    var h1El = block.querySelector('.site-name');
                    if (!h1El) {
                        h1El = document.createElement('h1');
                        h1El.className = 'site-name';
                        block.insertBefore(h1El, block.firstChild);
                    }
                    h1El.textContent = tInp.value;
                    debounceAction();
                };
            }
            var tagInp = document.getElementById('prop-site-tagline-text');
            if (tagInp) {
                tagInp.oninput = function () {
                    var tagEl = block.querySelector('.site-tagline');
                    if (!tagEl) {
                        tagEl = document.createElement('p');
                        tagEl.className = 'site-tagline';
                        block.appendChild(tagEl);
                    }
                    tagEl.textContent = tagInp.value;
                    debounceAction();
                };
            }
        }

        // Text shadow (compound property)
        (function () {
            var tsX = document.getElementById('prop-text-shadow-x');
            var tsY = document.getElementById('prop-text-shadow-y');
            var tsBlur = document.getElementById('prop-text-shadow-blur');
            var tsCol = document.getElementById('prop-text-shadow-color');
            function applyTextShadow() {
                if (!tsX || !tsY) { return; }
                if (tsX.value === '' && tsY.value === '') {
                    writeProps(block, 'textShadow', '');
                    return;
                }
                var x = (tsX.value || '0') + 'px';
                var y = (tsY.value || '0') + 'px';
                var b = (tsBlur && tsBlur.value !== '' ? tsBlur.value : '0') + 'px';
                var c = tsCol ? tsCol.value : '#000000';
                writeProps(block, 'textShadow', x + ' ' + y + ' ' + b + ' ' + c);
            }
            if (tsX) { tsX.oninput = applyTextShadow; }
            if (tsY) { tsY.oninput = applyTextShadow; }
            if (tsBlur) { tsBlur.oninput = applyTextShadow; }
            if (tsCol) { tsCol.oninput = applyTextShadow; }
        }());

        // Box shadow (compound property)
        (function () {
            var bsX = document.getElementById('prop-box-shadow-x');
            var bsY = document.getElementById('prop-box-shadow-y');
            var bsBlur = document.getElementById('prop-box-shadow-blur');
            var bsSpread = document.getElementById('prop-box-shadow-spread');
            var bsCol = document.getElementById('prop-box-shadow-color');
            var bsInset = document.getElementById('prop-box-shadow-inset');
            function applyBoxShadow() {
                if (!bsX || !bsY) { return; }
                if (bsX.value === '' && bsY.value === '') {
                    writeProps(block, 'boxShadow', '');
                    return;
                }
                var prefix = (bsInset && bsInset.checked) ? 'inset ' : '';
                var x = (bsX.value || '0') + 'px';
                var y = (bsY.value || '0') + 'px';
                var b = (bsBlur && bsBlur.value !== '' ? bsBlur.value : '0') + 'px';
                var s = (bsSpread && bsSpread.value !== '' ? bsSpread.value : '0') + 'px';
                var c = bsCol ? bsCol.value : '#000000';
                writeProps(block, 'boxShadow', prefix + x + ' ' + y + ' ' + b + ' ' + s + ' ' + c);
            }
            if (bsX) { bsX.oninput = applyBoxShadow; }
            if (bsY) { bsY.oninput = applyBoxShadow; }
            if (bsBlur) { bsBlur.oninput = applyBoxShadow; }
            if (bsSpread) { bsSpread.oninput = applyBoxShadow; }
            if (bsCol) { bsCol.oninput = applyBoxShadow; }
            if (bsInset) { bsInset.onchange = applyBoxShadow; }
        }());

        // Opacity: update % label live
        (function () {
            var opInp = panel.querySelector('[data-prop="opacity"]');
            var opLbl = document.getElementById('prop-opacity-label');
            if (opInp && opLbl) {
                opInp.oninput = function () {
                    opLbl.textContent = Math.round(parseFloat(opInp.value) * 100) + '%';
                    writeProps(block, 'opacity', opInp.value);
                };
            }
        }());

        // Background browse button
        var bgBrowse = document.getElementById('prop-bg-browse-btn');
        if (bgBrowse) {
            bgBrowse.onclick = function () {
                openMediaPanel(function (url) {
                    var urlInp = document.getElementById('prop-bg-image-url');
                    if (urlInp) { urlInp.value = 'url(' + url + ')'; }
                    writeProps(block, 'backgroundImage', 'url(' + url + ')');
                    // Default to no-repeat cover if not already set
                    if (!block.style.backgroundRepeat) {
                        writeProps(block, 'backgroundRepeat', 'no-repeat');
                        var repSel = document.getElementById('prop-bg-repeat');
                        if (repSel) { repSel.value = 'no-repeat'; }
                    }
                    if (!block.style.backgroundSize) {
                        writeProps(block, 'backgroundSize', 'cover');
                        var sizeSel = document.getElementById('prop-bg-size');
                        if (sizeSel) { sizeSel.value = 'cover'; }
                    }
                });
            };
        }

        // Delete button
        var delBtn = document.getElementById('prop-delete-btn');
        if (delBtn) {
            delBtn.onclick = deleteBlock;
        }

        // PHP Include: template picker triggers var-row rebuild + live canvas preview
        if (block.dataset.blockType === 'php-include') {
            var phpPicker = panel.querySelector('.php-include-tpl-picker');
            if (phpPicker) {
                phpPicker.onchange = function () {
                    var rel = phpPicker.value;
                    var cfg = {};
                    try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                    cfg.template = rel;
                    block.dataset.blockConfig = JSON.stringify(cfg);
                    var varsC = panel.querySelector('.php-include-vars');
                    if (!rel) {
                        if (varsC) { buildPhpIncludeVarRows(varsC, cfg, block); }
                        refreshPhpIncludePreview(block);
                        debounceAction();
                        return;
                    }
                    fetch('/admin/template-editor/vars?f=' + encodeURIComponent(rel))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            (data.vars || []).forEach(function (v) {
                                if (!(v in cfg) && v !== 'db' && v !== 'template') {
                                    cfg[v] = '';
                                }
                            });
                            block.dataset.blockConfig = JSON.stringify(cfg);
                            if (varsC) { buildPhpIncludeVarRows(varsC, cfg, block); }
                            refreshPhpIncludePreview(block);
                            debounceAction();
                        })
                        .catch(function () {
                            if (varsC) { buildPhpIncludeVarRows(varsC, cfg, block); }
                            refreshPhpIncludePreview(block);
                            debounceAction();
                        });
                };
            }
        }

        // Colour swatches â€” pre-apply on click (solves first-pick = same colour),
        // update live while dragging, commit on close
        panel.querySelectorAll('[data-color-swatch]').forEach(function (swatch) {
            var prop = swatch.dataset.colorSwatch;
            var textInp = panel.querySelector('[data-prop="' + prop + '"]');
            var applyColor = function () {
                if (textInp) { textInp.value = swatch.value; }
                writeProps(block, prop, swatch.value);
            };
            swatch.onclick = applyColor;
            swatch.oninput = applyColor;
            swatch.onchange = applyColor;
        });

        // PHP Code textarea â€” write back to block_config._php on change
        if (PHP_CODE_TYPES.indexOf(block.dataset.blockType) !== -1) {
            var phpCodeTa = document.getElementById('prop-php-code');
            if (phpCodeTa) {
                phpCodeTa.oninput = function () {
                    writeConfig(block, '_php', phpCodeTa.value);
                    debounceAction();
                };
            }
        }
    }

    function buildPhpIncludeVarRows(container, cfg, block) {
        container.innerHTML = '';
        var varKeys = Object.keys(cfg).filter(function (k) {
            return k !== 'template' && k !== 'db';
        });
        if (varKeys.length === 0) {
            container.innerHTML = '<p class="php-include-hint">Select a template to see its variables.</p>';
            return;
        }
        varKeys.forEach(function (k) {
            var row = document.createElement('div');
            row.className = 'editor-prop-row';
            var label = document.createElement('label');
            label.style.fontFamily = 'monospace';
            label.style.fontSize = '0.78rem';
            label.textContent = '$' + k;
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'editor-prop-input';
            inp.value = cfg[k] || '';
            var key = k;
            inp.oninput = function () {
                writeConfig(block, key, inp.value);
                refreshPhpIncludePreview(block);
            };
            row.appendChild(label);
            row.appendChild(inp);
            container.appendChild(row);
        });
    }

    function refreshPhpIncludePreview(block) {
        var cfg = {};
        try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
        var rel = cfg.template || '';
        if (!rel) {
            block.innerHTML = '<p style="color:#9ca3af;font-size:0.8rem;padding:0.5rem">PHP Include â€” no template selected</p>';
            return;
        }
        var qs = 'template=' + encodeURIComponent(rel);
        Object.keys(cfg).forEach(function (k) {
            if (k !== 'template' && k !== 'db') {
                qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(cfg[k] || '');
            }
        });
        fetch(API_BASE + '/php-include-preview?' + qs, {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { block.innerHTML = data.html || ''; })
            .catch(function () { /* leave existing content on error */ });
    }

    // â”€â”€ Data List token hints â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    function updateDataListTokenHints(slug) {
        var hint = document.getElementById('prop-data-list-tokens');
        if (!hint) { return; }
        var sets = window.CONTENT_SETS || [];
        var set = null;
        for (var i = 0; i < sets.length; i++) { if (sets[i].slug === slug) { set = sets[i]; break; } }
        if (!set || !set.fields || !set.fields.length) {
            hint.textContent = set && set.type === 'query'
                ? 'Query set â€” tokens depend on selected fields.'
                : 'Select a content set to see available tokens.';
            return;
        }
        hint.innerHTML = '<strong>Tokens:</strong> ' + set.fields.map(function (f) {
            return '<code style="cursor:pointer;text-decoration:underline dotted" title="Click to insert" data-token="{{' + f.name + '}}">{{' + f.name + '}}</code> <em>' + (f.label || f.name) + '</em>';
        }).join(' &nbsp; ');
        hint.querySelectorAll('[data-token]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var ta = document.getElementById('prop-data-list-card');
                if (!ta) { return; }
                var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
                ta.value = v.slice(0, s) + chip.dataset.token + v.slice(e);
                ta.selectionStart = ta.selectionEnd = s + chip.dataset.token.length;
                ta.dispatchEvent(new Event('input'));
                ta.focus();
            });
        });
    }

    // Wire data-list inputs (one-time setup)
    (function () {
        var dlSetSel = document.getElementById('prop-data-list-set');
        if (dlSetSel) {
            dlSetSel.addEventListener('change', function () {
                updateDataListTokenHints(dlSetSel.value);
            });
        }
    }());

    function writeProps(block, prop, value) {
        if (activeViewport === 'desktop') {
            block.style[prop] = value;
        } else {
            var dsKey = activeViewport === 'tablet' ? 'cssPropsTablet' : 'cssPropsMobile';
            var stored = {};
            try { stored = JSON.parse(block.dataset[dsKey] || '{}'); } catch (e) { }
            if (value !== '' && value !== null && value !== undefined) {
                stored[prop] = value;
            } else {
                delete stored[prop];
            }
            block.dataset[dsKey] = JSON.stringify(stored);
        }
        rebuildLiveStyles();
        recordAction();
    }

    function writeConfig(block, key, value) {
        var config = {};
        try { config = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
        config[key] = value;
        block.dataset.blockConfig = JSON.stringify(config);
        debounceAction();
    }


    function rebuildLiveStyles() {
        if (!liveStyles) { return; }
        var css = '';
        var tabletRules = '';
        var mobileRules = '';
        canvas.querySelectorAll('[data-block]').forEach(function (block) {
            if (!block.id) { return; }
            if (block.style.cssText) {
                css += '#' + block.id + ' { ' + block.style.cssText + ' }\n';
            }
            if (block.dataset.cssPropsTablet) {
                try {
                    var tp = JSON.parse(block.dataset.cssPropsTablet);
                    var trules = '';
                    Object.keys(tp).forEach(function (p) {
                        if (p[0] !== '_') { trules += p + ':' + tp[p] + ';'; }
                    });
                    if (trules) { tabletRules += '#' + block.id + '{' + trules + '}\n'; }
                } catch (e) { }
            }
            if (block.dataset.cssPropsMobile) {
                try {
                    var mp = JSON.parse(block.dataset.cssPropsMobile);
                    var mrules = '';
                    Object.keys(mp).forEach(function (p) {
                        if (p[0] !== '_') { mrules += p + ':' + mp[p] + ';'; }
                    });
                    if (mrules) { mobileRules += '#' + block.id + '{' + mrules + '}\n'; }
                } catch (e) { }
            }
        });
        if (tabletRules) { css += '@media (max-width:1023px){\n' + tabletRules + '}\n'; }
        if (mobileRules) { css += '@media (max-width:599px){\n' + mobileRules + '}\n'; }
        liveStyles.textContent = css;
    }

    function rgbToHex(val) {
        if (!val) { return ''; }
        if (val.charAt(0) === '#') { return val; }
        var m = val.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
        if (!m) { return ''; }
        return '#' +
            ('0' + parseInt(m[1]).toString(16)).slice(-2) +
            ('0' + parseInt(m[2]).toString(16)).slice(-2) +
            ('0' + parseInt(m[3]).toString(16)).slice(-2);
    }

    // ΓöÇΓöÇ Section G ΓÇö Block palette + delete ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    // Default CSS applied to new leaf blocks ΓÇö portrait ISO proportions (Γëê A4 at screen scale),
    // inline-block so that multiple blocks can sit side by side.
    var PORTRAIT_INIT = { display: 'inline-block', verticalAlign: 'top', width: '260px', boxSizing: 'border-box' };

    var BLOCK_DEFS = {
        'text': { tag: 'div', inner: '<p>New text block.</p>', initCss: PORTRAIT_INIT },
        'heading': { tag: 'h2', inner: 'New Heading', initCss: PORTRAIT_INIT },
        'image': { tag: 'figure', inner: '<img src="" alt=""><figcaption></figcaption>', initCss: PORTRAIT_INIT },
        'section': { tag: 'section', inner: '', isLayout: true, initCss: PORTRAIT_INIT },
        'columns': {
            tag: 'div', inner: function () {
                return '<section data-block data-block-type="section" id="' + newId() + '"></section>' +
                    '<section data-block data-block-type="section" id="' + newId() + '"></section>';
            }, isLayout: true, initCss: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }
        },
        'site-header': {
            tag: 'header', inner: function () {
                return '<div data-block data-block-type="site-logo" id="' + newId() + '"><a href="/"><img src="" alt="Site Logo"></a></div>' +
                    '<div data-block data-block-type="site-title" id="' + newId() + '"><h1 class="site-name">Site Name</h1><p class="site-tagline"></p></div>' +
                    '<nav data-block data-block-type="nav-menu" id="' + newId() + '" data-block-config="{&quot;menu_id&quot;:&quot;&quot;}"></nav>';
            }, isLayout: true, initCss: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%', padding: '1rem 2rem', boxSizing: 'border-box', backgroundSize: 'cover', backgroundPosition: 'center center', backgroundRepeat: 'no-repeat' }
        },
        'gallery': { tag: 'div', inner: '', initCss: PORTRAIT_INIT },
        'html': { tag: 'div', inner: '', initCss: PORTRAIT_INIT },
        'nav-menu': { tag: 'nav', inner: '', defaultConfig: { menu_id: '' }, initCss: PORTRAIT_INIT },
        'site-logo': { tag: 'div', inner: '<a href="/"><img src="" alt="Site Logo"></a>', initCss: PORTRAIT_INIT },
        'site-title': { tag: 'div', inner: '<h1 class="site-name">Site Name</h1><p class="site-tagline"></p>', initCss: PORTRAIT_INIT },
        'event-list': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Event list ΓÇö visible on live page.</p>', dynamic: true, defaultConfig: { count: 5, filter: 'upcoming' }, initCss: PORTRAIT_INIT },
        'data-list': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Data List ΓÇö visible on live page.</p>', dynamic: true, defaultConfig: { set_slug: '', view: 'continuous', card_html: '' }, initCss: PORTRAIT_INIT },
        'php-include': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">PHP Include ΓÇö visible on live page.</p>', dynamic: true, defaultConfig: { template: '' }, initCss: PORTRAIT_INIT },
        'zone': {
            tag: 'div', inner: '', isLayout: true, defaultConfig: { zone_name: 'main', zone_label: 'Main Content' },
            initCss: { display: 'block', width: '100%', boxSizing: 'border-box' }
        },
    };

    function bindPalette() {
        document.querySelectorAll('[data-add-block]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                addBlock(btn.dataset.addBlock);
            });
        });
    }

    function addBlock(type) {
        var def = BLOCK_DEFS[type];
        if (!def) { return; }

        var el = document.createElement(def.tag);
        el.id = newId();
        el.setAttribute('data-block', '');
        el.setAttribute('data-block-type', type);

        if (def.defaultConfig) {
            el.dataset.blockConfig = JSON.stringify(def.defaultConfig);
        }

        if (def.initCss) {
            Object.keys(def.initCss).forEach(function (p) {
                el.style[p] = def.initCss[p];
            });
        }

        el.innerHTML = typeof def.inner === 'function' ? def.inner() : (def.inner || '');

        // Remove any empty-canvas placeholder
        var emptyMsg = canvas.querySelector('.editor-empty-canvas');
        if (emptyMsg) { emptyMsg.remove(); }

        // Zone blocks: set data-zone-name attribute so CSS ::before label works
        if (type === 'zone') {
            var defCfg = def.defaultConfig || {};
            el.setAttribute('data-zone-name', defCfg.zone_name || 'main');
        }

        // Columns block: create initial 2 column sections
        if (type === 'columns') {
            el.dataset.blockConfig = JSON.stringify({ columns: 2 });
            el.style.display = 'grid';
            el.style.gridTemplateColumns = 'repeat(2, 1fr)';
            el.style.gap = '1rem';
            for (var ci = 0; ci < 2; ci++) {
                el.appendChild(createColumnSection());
            }
        }

        // Insert after active block, or append
        if (activeBlock && canvas.contains(activeBlock)) {
            activeBlock.parentNode.insertBefore(el, activeBlock.nextSibling);
        } else {
            canvas.appendChild(el);
        }

        reInitAll();
        select(el);
        recordAction();
    }

    // Helper: create a section block to act as a column cell
    function createColumnSection() {
        var sec = document.createElement('div');
        sec.id = newId();
        sec.setAttribute('data-block', '');
        sec.setAttribute('data-block-type', 'section');
        sec.style.minHeight = '60px';
        return sec;
    }

    // Helper: sync column child sections to match count
    function syncColumnsChildren(columnsBlock, count) {
        var children = columnsBlock.querySelectorAll(':scope > [data-block-type="section"]');
        var current = children.length;
        if (count > current) {
            for (var i = current; i < count; i++) {
                columnsBlock.appendChild(createColumnSection());
            }
        } else if (count < current) {
            for (var i = current - 1; i >= count; i--) {
                // Only remove if empty
                if (!children[i].innerHTML.trim()) {
                    children[i].remove();
                }
            }
        }
        reInitAll();
    }

    function deleteBlock() {
        if (!activeBlock) { return; }
        var toRemove = activeBlock;
        deselect();
        toRemove.remove();

        // Restore empty canvas if nothing left
        if (!canvas.querySelector('[data-block]')) {
            var msg = document.createElement('div');
            msg.className = 'editor-empty-canvas';
            msg.textContent = 'Click a block type on the left to begin.';
            canvas.appendChild(msg);
        }

        reInitAll();
        recordAction();
    }

    // ΓöÇΓöÇ Block tree ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function updateBlockTree() {
        var tree = document.getElementById('editor-block-tree');
        if (!tree) { return; }
        tree.innerHTML = '';
        // Only render top-level (direct children of canvas)
        var children = canvas.querySelectorAll('#editor-canvas > [data-block]');
        children.forEach(function (block) {
            tree.appendChild(buildTreeNode(block));
        });
    }

    function buildTreeNode(block) {
        var item = document.createElement('div');
        item.className = 'editor-tree-item' + (block === activeBlock ? ' active' : '');
        // Show actual tag + class for better identification
        var label = block.tagName.toLowerCase();
        var cls = block.className.replace(/\bactive\b/, '').trim();
        if (cls) { label += '.' + cls.split(/\s+/)[0]; }
        item.textContent = label + ' #' + (block.id || '');
        item.addEventListener('click', function (e) {
            e.stopPropagation();
            select(block);
            block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        var nested = block.querySelectorAll(':scope > [data-block]');
        if (nested.length) {
            var childWrap = document.createElement('div');
            childWrap.className = 'editor-tree-children';
            nested.forEach(function (child) {
                childWrap.appendChild(buildTreeNode(child));
            });
            item.appendChild(childWrap);
        }
        return item;
    }

    // ΓöÇΓöÇ Accordion behaviour ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function bindAccordions() {
        panel.querySelectorAll('.editor-accordion-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var acc = btn.closest('.editor-accordion');
                if (acc) { acc.classList.toggle('collapsed'); }
            });
        });
    }

    // ΓöÇΓöÇ Section H ΓÇö Media panel ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    // Delegated to Cruinn.openMediaBrowser (media-browser.js)

    function openMediaPanel(callback) {
        Cruinn.openMediaBrowser(callback);
    }

    // ΓöÇΓöÇ Section I ΓÇö Serialise + recordAction ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function serialiseCanvas() {
        var blocks = [];
        var sortCounters = {};

        canvas.querySelectorAll('[data-block]').forEach(function (block) {
            if (!block.id) { return; }

            var parentBlockEl = block.parentElement
                ? block.parentElement.closest('[data-block]')
                : null;
            var parentBlockId = parentBlockEl ? parentBlockEl.id : null;
            var parentKey = parentBlockId || '__root';

            sortCounters[parentKey] = (sortCounters[parentKey] || 0) + 1;

            // Shallow inner_html: children that are blocks are stored as their own rows
            var cloned = block.cloneNode(true);
            cloned.removeAttribute('draggable');
            cloned.removeAttribute('contenteditable');
            cloned.removeAttribute('data-css-props');
            // Remove nested block elements from the clone (they are their own rows)
            cloned.querySelectorAll('[data-block]').forEach(function (child) {
                child.remove();
            });
            var innerHtml = cloned.innerHTML.trim();

            var cssProps = parseCssProps(block.style);

            // Include CSS class (excluding editor-managed classes) in css_props._class
            var cssClass = block.className
                .replace(/\b(active|dragging|drag-over|drag-over-inside)\b/g, '')
                .replace(/\s+/g, ' ')
                .trim();
            if (cssClass) {
                if (!cssProps) { cssProps = {}; }
                cssProps['_class'] = cssClass;
            }

            var config = null;
            if (block.dataset.blockConfig) {
                try { config = JSON.parse(block.dataset.blockConfig); } catch (e) { }
            }

            // Breakpoint-specific CSS props
            var cssPropsTablet = null;
            if (block.dataset.cssPropsTablet) {
                try { cssPropsTablet = JSON.parse(block.dataset.cssPropsTablet); } catch (e) { }
            }
            var cssPropsMobile = null;
            if (block.dataset.cssPropsMobile) {
                try { cssPropsMobile = JSON.parse(block.dataset.cssPropsMobile); } catch (e) { }
            }

            blocks.push({
                block_id: block.id,
                block_type: block.dataset.blockType || 'text',
                inner_html: innerHtml || null,
                css_props: cssProps,
                css_props_tablet: cssPropsTablet,
                css_props_mobile: cssPropsMobile,
                block_config: config,
                sort_order: sortCounters[parentKey],
                parent_block_id: parentBlockId || null,
            });
        });

        return blocks;
    }

    // ΓöÇΓöÇ Document panel (file-mode) ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    var _docSaveTimer = null;

    function bindDocPanel() {
        var panel = document.getElementById('editor-doc-panel');
        if (!panel) { return; }

        panel.querySelectorAll('[data-doc-html-attr], [data-doc-head-html], [data-doc-body-attr]')
            .forEach(function (el) {
                el.addEventListener('input', function () {
                    clearTimeout(_docSaveTimer);
                    _docSaveTimer = setTimeout(saveDocAttrs, 600);
                });
            });

        panel.querySelectorAll('.editor-doc-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.dataset.target;
                var body = document.getElementById(targetId);
                if (!body) { return; }
                var expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', String(!expanded));
                body.hidden = expanded;
            });
        });
    }

    function saveDocAttrs() {
        var panel = document.getElementById('editor-doc-panel');
        if (!panel) { return; }

        var htmlAttrs = {};
        panel.querySelectorAll('[data-doc-html-attr]').forEach(function (el) {
            htmlAttrs[el.dataset.docHtmlAttr] = el.value;
        });

        var headEl = panel.querySelector('[data-doc-head-html]');
        var headHtml = headEl ? headEl.value : null;

        var bodyAttrs = {};
        panel.querySelectorAll('[data-doc-body-attr]').forEach(function (el) {
            bodyAttrs[el.dataset.docBodyAttr] = el.value;
        });

        fetch(API_BASE + '/' + PAGE_ID + '/doc-attrs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                html_attrs: htmlAttrs,
                head_html: headHtml,
                body_attrs: bodyAttrs,
            }),
        }).catch(function (err) {
            console.error('[Cruinn] saveDocAttrs failed:', err);
        });
    }
    // ΓöÇΓöÇ End Document panel ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function parseCssProps(style) {
        if (!style) { return null; }
        // Always return an object (possibly empty {}) ΓÇö an empty object signals
        // "this block's styles have been managed and are currently empty", which
        // lets reconstructTree strip a previously-baked style attr on publish.
        var obj = {};
        var text = style.cssText || '';
        text.split(';').forEach(function (rule) {
            var colon = rule.indexOf(':');
            if (colon === -1) { return; }
            var prop = rule.slice(0, colon).trim();
            var val = rule.slice(colon + 1).trim();
            if (prop && val) { obj[prop] = val; }
        });
        return obj;
    }

    var _actionTimer = null;

    function recordAction() {
        if (_htmlPageMode) { return; } // HTML pages: save only on publish
        clearTimeout(_actionTimer);
        var blocks = serialiseCanvas();

        pushLocalUndo();

        fetch(API_BASE + '/' + PAGE_ID + '/action', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ blocks: blocks }),
        })
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (t) {
                        console.error('recordAction HTTP ' + r.status + ':', t);
                        showSaveError('Save failed (' + r.status + ') — check console');
                    });
                }
                return r.json().then(function (data) {
                    if (data.success) {
                        setUndoRedoState(data.can_undo, data.can_redo);
                        showDraftBadge(true);
                        clearSaveError();
                    } else {
                        console.error('recordAction error:', data.error || data);
                        showSaveError('Save error: ' + (data.error || 'unknown'));
                    }
                });
            })
            .catch(function (err) {
                console.error('recordAction failed:', err);
                showSaveError('Save failed — check console');
            });
    }

    var _saveErrorEl = null;
    function showSaveError(msg) {
        if (!_saveErrorEl) {
            _saveErrorEl = document.createElement('div');
            _saveErrorEl.id = 'editor-save-error';
            _saveErrorEl.style.cssText = 'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);' +
                'background:#c0392b;color:#fff;padding:0.5rem 1rem;border-radius:6px;' +
                'font-size:0.82rem;z-index:9999;pointer-events:none;';
            document.body.appendChild(_saveErrorEl);
        }
        _saveErrorEl.textContent = msg;
        _saveErrorEl.style.display = '';
    }
    function clearSaveError() {
        if (_saveErrorEl) { _saveErrorEl.style.display = 'none'; }
    }

    var _debounceTimer = null;
    function debounceAction() {
        if (_htmlPageMode) { return; }
        clearTimeout(_debounceTimer);
        _debounceTimer = setTimeout(recordAction, 2000);
    }

    // ΓöÇΓöÇ Section J ΓÇö Undo / Redo ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    // Local ring-buffer for optimistic undo (stores canvas.innerHTML snapshots)
    var localUndoStack = [];
    var localRedoStack = [];
    var MAX_LOCAL = 10;

    function pushLocalUndo() {
        localUndoStack.push(canvas.innerHTML);
        if (localUndoStack.length > MAX_LOCAL) { localUndoStack.shift(); }
        localRedoStack.length = 0;
    }

    function undo() {
        // Optimistic: restore from local buffer immediately
        if (localUndoStack.length) {
            localRedoStack.push(canvas.innerHTML);
            canvas.innerHTML = localUndoStack.pop();
            reInitAll();
        }

        // Confirm with server
        fetch(API_BASE + '/' + PAGE_ID + '/undo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    setUndoRedoState(data.can_undo, data.can_redo);
                    reconcile(data.blocks);
                }
            })
            .catch(function (err) {
                console.error('undo failed:', err);
            });
    }

    function redo() {
        if (localRedoStack.length) {
            localUndoStack.push(canvas.innerHTML);
            canvas.innerHTML = localRedoStack.pop();
            reInitAll();
        }

        fetch(API_BASE + '/' + PAGE_ID + '/redo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    setUndoRedoState(data.can_undo, data.can_redo);
                    reconcile(data.blocks);
                }
            })
            .catch(function (err) {
                console.error('redo failed:', err);
            });
    }

    /**
     * Reconcile canvas with server-authoritative block list.
     * Only replaces the canvas if the block IDs/order differ.
     */
    function reconcile(blocks) {
        if (!blocks || !blocks.length) { return; }

        var serverIds = blocks.map(function (b) { return b.block_id; }).join(',');
        var domIds = Array.from(canvas.querySelectorAll('[data-block]'))
            .map(function (el) { return el.id; }).join(',');

        if (serverIds !== domIds) {
            // Rebuild canvas from server state
            canvas.innerHTML = '';
            var byId = {};
            blocks.forEach(function (b) { byId[b.block_id] = b; });
            var tagMap = {
                heading: 'h2', image: 'figure', section: 'section',
                'nav-menu': 'nav', list: 'ul', 'list-item': 'li',
                anchor: 'a', form: 'form', table: 'table',
                inline: 'span', text: 'div', element: 'div'
            };

            // Build a lookup and children map for DFS rendering
            var byBlockId = {};
            var childrenOf = {};
            blocks.forEach(function (b) {
                byBlockId[b.block_id] = b;
                var pid = b.parent_block_id || '__root';
                if (!childrenOf[pid]) { childrenOf[pid] = []; }
                childrenOf[pid].push(b.block_id);
            });

            function renderBlock(b) {
                var cfg = {};
                try { cfg = JSON.parse(b.block_config || '{}'); } catch (e) { }
                // Use _tag from config (imported blocks) or tagMap/default
                var tag = cfg._tag || tagMap[b.block_type] || 'div';

                var el = document.createElement(tag);
                el.id = b.block_id;
                el.setAttribute('data-block', '');
                el.setAttribute('data-block-type', b.block_type);
                if (b.block_config) {
                    el.dataset.blockConfig = typeof b.block_config === 'string'
                        ? b.block_config
                        : JSON.stringify(b.block_config);
                }
                if (b.css_props) {
                    var props = typeof b.css_props === 'string'
                        ? JSON.parse(b.css_props)
                        : b.css_props;
                    Object.keys(props).forEach(function (p) { el.style[p] = props[p]; });
                }
                if (b.css_props_tablet) {
                    el.dataset.cssPropsTablet = typeof b.css_props_tablet === 'string'
                        ? b.css_props_tablet : JSON.stringify(b.css_props_tablet);
                }
                if (b.css_props_mobile) {
                    el.dataset.cssPropsMobile = typeof b.css_props_mobile === 'string'
                        ? b.css_props_mobile : JSON.stringify(b.css_props_mobile);
                }
                // Container blocks: leave innerHTML empty so DFS can
                // append child blocks into this element without duplication.
                if (!cfg._container) {
                    el.innerHTML = b.inner_html || '';
                }
                // Restore original HTML attributes from imported blocks
                if (cfg._attrs) {
                    Object.keys(cfg._attrs).forEach(function (k) {
                        if (k !== 'id') { el.setAttribute(k, cfg._attrs[k]); }
                    });
                }
                return el;
            }

            // DFS: render parents before children at any depth
            function buildSubtree(parentEl, parentKey) {
                (childrenOf[parentKey] || []).forEach(function (blockId) {
                    var el = renderBlock(byBlockId[blockId]);
                    parentEl.appendChild(el);
                    buildSubtree(el, blockId);
                });
            }
            buildSubtree(canvas, '__root');

            rebuildLiveStyles();
            reInitAll();
        }
    }

    function setUndoRedoState(canUndo, canRedo) {
        var undoBtn = document.getElementById('editor-undo-btn');
        var redoBtn = document.getElementById('editor-redo-btn');
        if (undoBtn) { undoBtn.disabled = !canUndo; }
        if (redoBtn) { redoBtn.disabled = !canRedo; }
    }

    function showDraftBadge(hasDraft) {
        var badge = document.querySelector('.editor-draft-badge');
        if (badge) { badge.style.display = hasDraft ? '' : 'none'; }
    }

    // ΓöÇΓöÇ Section K ΓÇö Publish / Discard ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function bindToolbar() {
        var publishBtn = document.getElementById('editor-publish-btn');
        if (publishBtn) {
            publishBtn.addEventListener('click', function () {
                if (!window.confirm('Publish this page? The live site will update immediately.')) { return; }
                publishBtn.disabled = true;

                // HTML page mode: send current code-area content with the publish request
                var publishBody = null;
                var publishHeaders = { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' };
                if (_htmlPageMode && _codeArea) {
                    publishBody = JSON.stringify({ html: _codeArea.value });
                    publishHeaders['Content-Type'] = 'application/json';
                }

                fetch(API_BASE + '/' + PAGE_ID + '/publish', {
                    method: 'POST',
                    headers: publishHeaders,
                    body: publishBody,
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (data.reimported) {
                                alert('Published. Reloading editor ΓÇö undo history has been reset.');
                                location.reload();
                            } else {
                                showDraftBadge(false);
                                localUndoStack.length = 0;
                                localRedoStack.length = 0;
                                setUndoRedoState(false, false);
                                publishBtn.disabled = false;
                                alert('Page published successfully.');
                            }
                        }
                    })
                    .catch(function (err) {
                        console.error('publish failed:', err);
                        publishBtn.disabled = false;
                    });
            });
        }

        var discardBtn = document.getElementById('editor-discard-btn');
        if (discardBtn) {
            discardBtn.addEventListener('click', function () {
                if (!window.confirm('Clear all draft history for this page?')) { return; }
                fetch(API_BASE + '/' + PAGE_ID + '/discard', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    })
                    .catch(function (err) {
                        console.error('discard failed:', err);
                    });
            });
        }

        var reloadSourceBtn = document.getElementById('editor-reload-source-btn');
        if (reloadSourceBtn) {
            reloadSourceBtn.addEventListener('click', function () {
                if (!window.confirm('Reload from source? This will clear current draft history first.')) { return; }
                fetch(API_BASE + '/' + PAGE_ID + '/reload-source', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    })
                    .catch(function (err) {
                        console.error('reload source failed:', err);
                    });
            });
        }

        // Toolbar undo/redo buttons
        var undoBtn = document.getElementById('editor-undo-btn');
        if (undoBtn) { undoBtn.addEventListener('click', undo); }
        var redoBtn = document.getElementById('editor-redo-btn');
        if (redoBtn) { redoBtn.addEventListener('click', redo); }

        // ΓöÇΓöÇ Code view toggle ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
        var codeBtn = document.getElementById('editor-code-toggle-btn');
        if (codeBtn) { codeBtn.addEventListener('click', toggleCodeView); }
    }

    // ΓöÇΓöÇ Section N ΓÇö Code View ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    var _inCodeView = false;
    var _codeArea = null;
    var _codeFileMode = null; // { rel } when editing a template file
    var _htmlPageMode = false; // true when page render_mode=html

    // Block type ΓåÆ HTML tag mapping (mirrors PHP BlockRegistry)
    var BLOCK_TAGS = {
        'text': 'div', 'heading': 'h2', 'image': 'figure', 'gallery': 'div',
        'html': 'div', 'section': 'section', 'columns': 'div', 'site-logo': 'div',
        'site-title': 'div', 'nav-menu': 'nav', 'map': 'div', 'event-list': 'div',
        'php-include': 'div', 'anchor': 'a', 'document': 'span', 'element': 'div',
        'form': 'form', 'inline': 'span', 'list': 'ul', 'list-item': 'li',
        'table': 'table', 'php-code': 'div'
    };

    /**
     * Convert a block element (and children) to clean publishable HTML.
     */
    function blockToHtml(block, indent) {
        indent = indent || '';
        var type = block.dataset.blockType || 'text';
        var tag = BLOCK_TAGS[type] || 'div';
        var config = {};
        if (block.dataset.blockConfig) {
            try { config = JSON.parse(block.dataset.blockConfig); } catch (e) { }
        }

        // Special handling for heading levels
        if (type === 'heading' && config.level) {
            tag = 'h' + Math.min(6, Math.max(1, parseInt(config.level, 10) || 2));
        }

        // Special handling for php-code: output raw PHP
        if (type === 'php-code' && config._php) {
            return indent + config._php;
        }

        // Build attributes
        var attrs = '';

        // ID for anchors
        if (type === 'anchor' && config.id) {
            attrs += ' id="' + escapeAttr(config.id) + '"';
        }
        if (type === 'anchor' && config.href) {
            attrs += ' href="' + escapeAttr(config.href) + '"';
        }

        // CSS class (excluding editor classes)
        var cssClass = (block.className || '')
            .replace(/\b(active|dragging|drag-over|drag-over-inside|cruinn-php-chip)\b/g, '')
            .replace(/\s+/g, ' ').trim();
        if (cssClass) { attrs += ' class="' + escapeAttr(cssClass) + '"'; }

        // Inline style
        var style = block.style.cssText || '';
        if (style) { attrs += ' style="' + escapeAttr(style) + '"'; }

        // Image special case
        if (type === 'image') {
            var imgSrc = config.src || '';
            var imgAlt = config.alt || '';
            var imgHtml = imgSrc ? '<img src="' + escapeAttr(imgSrc) + '" alt="' + escapeAttr(imgAlt) + '">' : '';
            return indent + '<figure' + attrs + '>' + imgHtml + '</figure>';
        }

        // Collect child blocks and text content
        var childBlocks = Array.from(block.querySelectorAll(':scope > [data-block]'));
        var hasChildBlocks = childBlocks.length > 0;

        var innerContent = '';
        if (hasChildBlocks) {
            // Recurse into child blocks
            innerContent = '\n' + childBlocks.map(function (child) {
                return blockToHtml(child, indent + '    ');
            }).join('\n') + '\n' + indent;
        } else {
            // Get inner content, stripping editor chip markup for php-code etc.
            var clone = block.cloneNode(true);
            clone.querySelectorAll('.cruinn-php-chip-label, .cruinn-php-chip-code').forEach(function (el) { el.remove(); });
            innerContent = clone.innerHTML.trim();
        }

        return indent + '<' + tag + attrs + '>' + innerContent + '</' + tag + '>';
    }

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Convert entire canvas to clean publishable HTML.
     */
    function blocksToHtml() {
        var rootBlocks = Array.from(canvas.querySelectorAll(':scope > [data-block]'));
        return rootBlocks.map(function (block) {
            return blockToHtml(block, '');
        }).join('\n\n');
    }

    function enterCodeView(opts) {
        // opts: optional { html, rel, content } for seeding the textarea
        if (_inCodeView) { return; }
        _inCodeView = true;

        // Create textarea if not already present
        if (!_codeArea) {
            _codeArea = document.createElement('textarea');
            _codeArea.id = 'editor-code-area';
            _codeArea.spellcheck = false;
            _codeArea.autocomplete = 'off';
            _codeArea.style.cssText = [
                'display:block', 'width:100%', 'height:100%', 'min-height:400px',
                'font-family:Consolas,Cascadia Code,Fira Code,monospace',
                'font-size:0.82rem', 'line-height:1.55', 'padding:1rem',
                'background:#0d1117', 'color:#e6edf3',
                'border:none', 'outline:none', 'resize:none', 'box-sizing:border-box',
                'tab-size:4',
            ].join(';');
            _codeArea.addEventListener('keydown', function (e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    var s = _codeArea.selectionStart;
                    var end = _codeArea.selectionEnd;
                    _codeArea.value = _codeArea.value.substring(0, s) + '    ' + _codeArea.value.substring(end);
                    _codeArea.selectionStart = _codeArea.selectionEnd = s + 4;
                }
            });
        }

        if (opts && opts.rel) {
            // File-editing mode: show file content, track filename
            _codeFileMode = { rel: opts.rel };
            _codeArea.value = opts.content || '';
        } else if (opts && typeof opts.html !== 'undefined') {
            // HTML page mode: seed with stored body_html
            _codeFileMode = null;
            _codeArea.value = opts.html;
        } else {
            // Block mode: show clean publishable HTML (not editor DOM)
            _codeFileMode = null;
            _codeArea.value = blocksToHtml();
        }

        // Hide canvas content, show code area inside the same wrapper
        canvas.querySelectorAll(':scope > *').forEach(function (el) {
            el.style.display = 'none';
        });
        canvas.style.background = '#0d1117';
        canvas.style.padding = '0';
        canvas.appendChild(_codeArea);

        // Update toolbar button
        var btn = document.getElementById('editor-code-toggle-btn');
        if (btn) {
            btn.classList.add('active');
            btn.textContent = _codeFileMode ? '├ù Close File' : 'Blocks';
        }

        deselect();
    }

    function exitCodeView() {
        if (!_inCodeView || !_codeArea) { return; }
        _inCodeView = false;

        var savedContent = _codeArea.value;
        var fileMode = _codeFileMode;
        _codeFileMode = null;
        _codeArea.remove();
        canvas.style.background = '';
        canvas.style.padding = '';

        if (fileMode) {
            // Save template file via POST, then restore canvas
            canvas.querySelectorAll(':scope > *').forEach(function (el) { el.style.display = ''; });
            reInitAll();

            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('content', savedContent);
            fd.append('_json', '1');
            fetch('/admin/template-editor/edit?f=' + encodeURIComponent(fileMode.rel), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd,
            })
                .then(function (r) { return r.json(); })
                .then(function () {
                    // Refresh php-include preview if a block is selected
                    if (activeBlock && activeBlock.dataset.blockType === 'php-include') {
                        refreshPhpIncludePreview(activeBlock);
                    }
                })
                .catch(function (err) { console.error('template save failed:', err); });
        } else {
            // Parse canvas HTML back from textarea
            canvas.innerHTML = savedContent;
            reInitAll();
            recordAction();
        }

        var btn = document.getElementById('editor-code-toggle-btn');
        if (btn) { btn.classList.remove('active'); btn.textContent = '</> Code'; }
    }

    function toggleCodeView() {
        if (_inCodeView) { exitCodeView(); } else { enterCodeView(); }
    }

    /**
     * Very basic HTML formatter ΓÇö adds newlines before block-level tags.
     */
    function formatHtml(html) {
        return html
            .replace(/>\s*</g, '>\n<')
            .replace(/(<\/(div|section|header|nav|figure|h[1-6]|p|ul|ol|li|table|tr|td|th)>)/gi, '$1\n')
            .replace(/(<(div|section|header|nav|figure|h[1-6]|p|ul|ol|li|table|tr|td|th)[^>]*>)/gi, '\n$1')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    // Wire the "Edit Template Source" button whenever a php-include block is selected
    (function () {
        var editSrcBtn = document.getElementById('prop-php-edit-source-btn');
        if (!editSrcBtn) { return; }
        editSrcBtn.addEventListener('click', function () {
            if (!activeBlock || activeBlock.dataset.blockType !== 'php-include') { return; }
            var cfg = {};
            try { cfg = JSON.parse(activeBlock.dataset.blockConfig || '{}'); } catch (e) { }
            var rel = cfg.template || '';
            if (!rel) {
                alert('Select a template file first.');
                return;
            }
            editSrcBtn.disabled = true;
            editSrcBtn.textContent = 'LoadingΓÇª';
            fetch('/admin/template-editor/edit?f=' + encodeURIComponent(rel) + '&format=json', {
                headers: { 'Accept': 'application/json' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    editSrcBtn.disabled = false;
                    editSrcBtn.textContent = '</> Edit Template Source';
                    enterCodeView({ rel: data.rel || rel, content: data.content || '' });
                })
                .catch(function () {
                    editSrcBtn.disabled = false;
                    editSrcBtn.textContent = '</> Edit Template Source';
                    alert('Could not load template file.');
                });
        });
    }());

    // ΓöÇΓöÇ Section M ΓÇö Canvas resize ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function initCanvasResize() {
        var handle = document.getElementById('cruinn-canvas-resize-handle');
        var heightInp = document.getElementById('cruinn-canvas-height-input');
        var clearBtn = document.getElementById('cruinn-canvas-height-clear');

        // Sync input to current canvas height if one is already set
        if (heightInp && canvas.style.height) {
            heightInp.value = parseInt(canvas.style.height, 10) || '';
        }

        // Height input: type a value to set canvas height
        if (heightInp) {
            heightInp.oninput = function () {
                var v = parseInt(heightInp.value, 10);
                if (!isNaN(v) && v > 0) {
                    canvas.style.height = v + 'px';
                    canvas.style.minHeight = v + 'px';
                }
            };
        }

        // Clear button: reset to auto (content height)
        if (clearBtn) {
            clearBtn.onclick = function () {
                canvas.style.height = '';
                canvas.style.minHeight = '';
                if (heightInp) { heightInp.value = ''; }
            };
        }

        // Drag handle: pointer-based drag to resize canvas height
        if (!handle) { return; }

        var dragStartY = 0;
        var dragStartH = 0;

        handle.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            dragStartY = e.clientY;
            dragStartH = canvas.getBoundingClientRect().height;
            handle.classList.add('dragging');
            handle.setPointerCapture(e.pointerId);
        });

        handle.addEventListener('pointermove', function (e) {
            if (!handle.classList.contains('dragging')) { return; }
            var newH = Math.max(40, dragStartH + (e.clientY - dragStartY));
            canvas.style.height = newH + 'px';
            canvas.style.minHeight = newH + 'px';
            if (heightInp) { heightInp.value = Math.round(newH); }
        });

        handle.addEventListener('pointerup', function () {
            handle.classList.remove('dragging');
        });

        handle.addEventListener('pointercancel', function () {
            handle.classList.remove('dragging');
        });
    }

    // ΓöÇΓöÇ Section L ΓÇö Keyboard shortcuts ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    function bindKeyboard() {
        document.addEventListener('keydown', function (e) {
            var ctrl = e.ctrlKey || e.metaKey;
            if (!ctrl) { return; }

            if (e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((e.key === 'z' && e.shiftKey) || e.key === 'y') {
                e.preventDefault();
                redo();
            }
        });
    }

    // ΓöÇΓöÇ Section M ΓÇö Public API ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
    // Expose serialiseCanvas for the Code panel inline script.
    window.serialiseCanvasPublic = serialiseCanvas;

})();
