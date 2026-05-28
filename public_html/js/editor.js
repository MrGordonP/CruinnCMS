/**
 * Cruinn CMS — Page Editor
 * Standalone IIFE. No external dependencies. No build step.
 * Sections: A Init, B IDs, C Selection, D contenteditable, E DnD,
 *           F Properties, G Palette, H Media, I Serialise, J Undo/Redo,
 *           K Publish, L Keyboard
 */
(function () {
    'use strict';

    // ── Section A — Init ────────────────────────────────────────────

    var wrap = document.getElementById('editor-wrap');
    var canvas = document.getElementById('editor-canvas');
    var panel = document.getElementById('editor-props');

    if (!wrap || !canvas || !panel) { return; }

    var PAGE_ID = wrap.dataset.pageId;
    var HAS_PAGE = wrap.dataset.hasPage === '1' && !!PAGE_ID;
    var IS_TEMPLATE_PAGE = wrap.dataset.isTemplatePage === '1';
    var CSRF = wrap.dataset.csrf;
    var API_BASE = wrap.dataset.apiBase || '/admin/editor';
    var liveStyles = document.getElementById('editor-live-styles');

    // -- Viewport (responsive breakpoint) state --------------------
    // 'desktop' | 'tablet' | 'mobile'
    var activeViewport = 'desktop';
    var VIEWPORT_WIDTHS = { desktop: null, tablet: 600, mobile: 360 };

    // Template zones available for this page (empty on template canvas / zone pages)
    var TEMPLATE_ZONES = (function () {
        try { return JSON.parse(wrap.dataset.templateZones || '[]'); } catch (e) { return []; }
    }());

    // The page's own zone (e.g. 'main'). Used as default for the zone assignment picker.
    var PAGE_ZONE = wrap.dataset.pageZone || 'main';

    // Context fields for content templates: [{key, label, type}]
    // Non-empty only when editing a content template canvas with a context_source assigned.
    var CONTEXT_FIELDS = (function () {
        try { return JSON.parse(wrap.dataset.contextFields || '[]'); } catch (e) { return []; }
    }());

    var MODULE_WIDGETS = (function () {
        try { return JSON.parse(wrap.dataset.moduleWidgets || '[]'); } catch (e) { return []; }
    }());

    var MODULE_CONTENT_PROVIDERS = (function () {
        try { return JSON.parse(wrap.dataset.moduleContentProviders || '[]'); } catch (e) { return []; }
    }());

    var BLOG_PROFILES = (function () {
        try { return JSON.parse(wrap.dataset.blogProfiles || '[]'); } catch (e) { return []; }
    }());

    var EVENT_PROFILES = (function () {
        try { return JSON.parse(wrap.dataset.eventProfiles || '[]'); } catch (e) { return []; }
    }());

    var CORE_FRAGMENT_CATALOG = {
        account: [
            { key: 'account_details_form', label: 'Account Details Form' },
            { key: 'account_password_form', label: 'Account Password Form' },
            { key: 'account_information', label: 'Account Information' }
        ]
    };

    var inlineFocusOverlay = null;
    var inlineFocusFrame = null;
    var inlineFocusTitle = null;
    var inlineFocusOpenFull = null;

    document.addEventListener('DOMContentLoaded', function () {
        initInlineCanvasFocus();
        restoreCssProps();
        reInitAll();
        bindPalette();
        bindToolbar();
        bindAccordions();
        bindKeyboard();
        initCanvasResize();
        bindDocPanel();
        bindPageSettings();
        bindTemplateLayout();
        bindTemplatePageSettings();
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
        decorateAssignedCanvasInlineEdit();
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

    function editorHrefForCanvas(pageId) {
        var id = parseInt(pageId, 10);
        if (!id) { return '#'; }
        if ((API_BASE || '').indexOf('/cms/editor') === 0) {
            var qp = new URLSearchParams(window.location.search || '');
            var instance = qp.get('instance') || '__platform__';
            var from = HAS_PAGE ? ('&from=' + encodeURIComponent(PAGE_ID)) : '';
            return '/cms/editor?instance=' + encodeURIComponent(instance) + '&page=' + id + from;
        }
        var fromQs = HAS_PAGE ? ('?from=' + encodeURIComponent(PAGE_ID)) : '';
        return '/admin/editor/' + id + '/edit' + fromQs;
    }

    function initInlineCanvasFocus() {
        inlineFocusOverlay = document.getElementById('editor-inline-focus-overlay');
        inlineFocusFrame = document.getElementById('editor-inline-focus-frame');
        inlineFocusTitle = document.getElementById('editor-inline-focus-title');
        inlineFocusOpenFull = document.getElementById('editor-inline-focus-open-full');

        if (!inlineFocusOverlay || !inlineFocusFrame) { return; }

        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-inline-canvas-edit]');
            if (!link) { return; }

            var href = link.getAttribute('href');
            if (!href || href === '#') {
                var canvasId = parseInt(link.getAttribute('data-inline-canvas-id') || '0', 10);
                href = editorHrefForCanvas(canvasId);
            }
            if (!href || href === '#') { return; }

            e.preventDefault();
            var label = (link.textContent || '').trim() || 'Canvas';
            openInlineCanvasFocus(href, label);
        });

        inlineFocusOverlay.querySelectorAll('[data-inline-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeInlineCanvasFocus();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && inlineFocusOverlay.classList.contains('is-open')) {
                closeInlineCanvasFocus();
            }
        });
    }

    function openInlineCanvasFocus(href, label) {
        if (!inlineFocusOverlay || !inlineFocusFrame) { return; }
        if (!href || href === '#') { return; }
        inlineFocusOverlay.classList.add('is-open');
        inlineFocusOverlay.setAttribute('aria-hidden', 'false');
        if (inlineFocusTitle) {
            inlineFocusTitle.textContent = 'Editing: ' + (label || 'Canvas');
        }
        if (inlineFocusOpenFull) {
            inlineFocusOpenFull.href = href;
        }
        // TODO(engine): replace iframe transport with same-shell context swap.
        inlineFocusFrame.src = href;
    }

    function closeInlineCanvasFocus() {
        if (!inlineFocusOverlay || !inlineFocusFrame) { return; }
        inlineFocusOverlay.classList.remove('is-open');
        inlineFocusOverlay.setAttribute('aria-hidden', 'true');
        inlineFocusFrame.src = 'about:blank';
    }

    function decorateAssignedCanvasInlineEdit() {
        canvas.querySelectorAll('.editor-zone-assigned-canvas[data-assigned-canvas-id]').forEach(function (assignedWrap) {
            if (assignedWrap.querySelector('.editor-inline-zone-edit')) { return; }

            var canvasId = parseInt(assignedWrap.getAttribute('data-assigned-canvas-id') || '0', 10);
            if (!canvasId) { return; }

            var action = document.createElement('a');
            action.className = 'editor-inline-zone-edit btn btn-small btn-outline';
            action.textContent = 'Click to edit canvas';
            action.href = editorHrefForCanvas(canvasId);
            action.setAttribute('data-inline-canvas-edit', '1');
            action.setAttribute('data-inline-canvas-id', String(canvasId));
            assignedWrap.insertBefore(action, assignedWrap.firstChild);
        });
    }

    /**
     * On editor load, copy data-css-props onto each block's inline style
     * so writeProps / rebuildLiveStyles have a consistent baseline.
     */
    function restoreCssProps() {
        canvas.querySelectorAll('[data-block]').forEach(function (block) {
            // Desktop props ? inline styles (base)
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

    // �� Viewport switching ��������������������������
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

    // ── Section B — Block ID generation ────────────────────────────

    function newId() {
        return 'b-' + Math.random().toString(36).slice(2, 10);
    }

    // ── Section C — Block selection ─────────────────────────────────

    var activeBlock = null;
    var selectedPphiBlock = null;
    var selectedPphiElement = null;

    var PHPI_PANEL_FIELDS = [
        { label: 'Color', prop: 'color', type: 'text', placeholder: 'e.g. #333 or inherit' },
        { label: 'Font size', prop: 'font-size', type: 'text', placeholder: 'e.g. 1rem or 16px' },
        { label: 'Font weight', prop: 'font-weight', type: 'text', placeholder: 'e.g. 400 or bold' },
        { label: 'Line height', prop: 'line-height', type: 'text', placeholder: 'e.g. 1.5' },
        { label: 'Text align', prop: 'text-align', type: 'select', options: ['', 'left', 'center', 'right', 'justify'] },
        { label: 'Background', prop: 'background-color', type: 'text', placeholder: 'e.g. #fff or transparent' },
        { label: 'Padding', prop: 'padding', type: 'text', placeholder: 'e.g. 1rem or 8px 16px' },
        { label: 'Margin', prop: 'margin', type: 'text', placeholder: 'e.g. 0 auto' },
        { label: 'Display', prop: 'display', type: 'select', options: ['', 'block', 'flex', 'grid', 'inline', 'inline-block', 'none'] },
        { label: 'Gap', prop: 'gap', type: 'text', placeholder: 'e.g. 1rem (flex/grid)' },
        { label: 'Grid columns', prop: 'grid-template-columns', type: 'text', placeholder: 'e.g. 1fr 2fr' },
        { label: 'Border', prop: 'border', type: 'text', placeholder: 'e.g. 1px solid #ccc' },
        { label: 'Border radius', prop: 'border-radius', type: 'text', placeholder: 'e.g. 4px' },
        { label: 'Width', prop: 'width', type: 'text', placeholder: 'e.g. 100% or 300px' },
        { label: 'Max width', prop: 'max-width', type: 'text', placeholder: 'e.g. 600px' },
    ];

    var PHPI_PANEL_PROP_SET = PHPI_PANEL_FIELDS.reduce(function (acc, field) {
        acc[field.prop] = true;
        return acc;
    }, {});

    var PHPI_TYPOGRAPHY_PRESETS = {
        body: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-body)',
            'font-size': '1rem',
            'font-weight': '400',
            'line-height': '1.7',
        },
        h1: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '2rem',
            'font-weight': '700',
            'line-height': '1.3',
        },
        h2: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '1.5rem',
            'font-weight': '700',
            'line-height': '1.3',
        },
        h3: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '1.25rem',
            'font-weight': '700',
            'line-height': '1.3',
        },
        h4: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '1rem',
            'font-weight': '700',
            'line-height': '1.3',
        },
        h5: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '0.875rem',
            'font-weight': '700',
            'line-height': '1.3',
        },
        h6: {
            'color': 'var(--color-text)',
            'font-family': 'var(--font-heading)',
            'font-size': '0.75rem',
            'font-weight': '700',
            'line-height': '1.3',
        }
    };

    var PHPI_TYPOGRAPHY_PROP_SET = {
        'color': true,
        'font-family': true,
        'font-size': true,
        'font-weight': true,
        'font-style': true,
        'line-height': true,
        'letter-spacing': true,
        'text-align': true,
        'text-transform': true,
        'text-decoration': true,
        'text-shadow': true,
    };

    var PHPI_TEXT_CASCADE_PROPS = {
        'color': true,
        'font-size': true,
        'font-family': true,
        'font-weight': true,
        'font-style': true,
        'line-height': true,
        'letter-spacing': true,
        'text-align': true,
        'text-transform': true,
        'text-decoration': true,
        'text-shadow': true,
    };

    function isPhpIncludeBlock(block) {
        if (!block) { return false; }
        var t = block.dataset.blockType;
        return t === 'php-include' || t === 'dynamic-include';
    }

    function clearPphiElementSelection() {
        canvas.querySelectorAll('[data-phpi-el].phpi-el-selected').forEach(function (x) {
            x.classList.remove('phpi-el-selected');
        });
        selectedPphiBlock = null;
        selectedPphiElement = null;
    }

    function buildPphiPanelFieldsHtml() {
        return PHPI_PANEL_FIELDS.map(function (field) {
            var input = '';
            if (field.type === 'select') {
                var options = (field.options || []).map(function (opt) {
                    var label = opt || '&mdash;';
                    return '<option value="' + opt + '">' + label + '</option>';
                }).join('');
                input = '<select class="editor-prop-input" data-phpi-prop="' + field.prop + '">' + options + '</select>';
            } else {
                input = '<input type="text" class="editor-prop-input" data-phpi-prop="' + field.prop + '" placeholder="' + (field.placeholder || '') + '">';
            }
            return '<div class="phpi-panel-row"><label>' + field.label + '</label>' + input + '</div>';
        }).join('');
    }

    function populatePphiPanelFields(rootEl, props) {
        rootEl.querySelectorAll('[data-phpi-prop]').forEach(function (inp) {
            inp.value = props[inp.dataset.phpiProp] || '';
        });
    }

    function syncPphiInspector(block, el) {
        var acc = panel.querySelector('[data-group="php-include-element"]');
        var titleEl = document.getElementById('prop-phpi-title');
        var classRow = document.getElementById('prop-phpi-class-row');
        var classSelect = document.getElementById('prop-phpi-class-select');
        var presetRow = document.getElementById('prop-phpi-preset-row');
        var presetSelect = document.getElementById('prop-phpi-preset');
        var emptyEl = document.getElementById('prop-phpi-empty');
        var fieldsEl = document.getElementById('prop-phpi-fields');

        if (!acc || !titleEl || !classRow || !classSelect || !presetRow || !presetSelect || !emptyEl || !fieldsEl) {
            return;
        }

        if (!isPhpIncludeBlock(block) || !el || !block.contains(el)) {
            acc.style.display = 'none';
            titleEl.textContent = 'None selected';
            classRow.style.display = 'none';
            classSelect.innerHTML = '';
            presetRow.style.display = 'none';
            presetSelect.value = '';
            emptyEl.style.display = '';
            fieldsEl.style.display = 'none';
            return;
        }

        var classes = (el.dataset.phpiClasses || '').trim();
        var pphiId = (el.dataset.phpiEl || '').trim();
        if (!pphiId) {
            acc.style.display = 'none';
            return;
        }

        if (!fieldsEl.dataset.initialized) {
            fieldsEl.innerHTML = buildPphiPanelFieldsHtml();
            fieldsEl.dataset.initialized = '1';
        }

        acc.style.display = '';
        titleEl.textContent = classes || ('[data-phpi-el="' + pphiId + '"]');
        emptyEl.style.display = 'none';
        fieldsEl.style.display = '';
        presetRow.style.display = '';

        var classList = classes.split(/\s+/).filter(Boolean);
        var elementSelector = '[data-phpi-el="' + pphiId + '"]';
        classSelect.innerHTML = '';
        var elementOpt = document.createElement('option');
        elementOpt.value = elementSelector;
        elementOpt.textContent = 'Selected element only';
        classSelect.appendChild(elementOpt);

        if (classList.length > 0) {
            classList.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = '.' + c;
                opt.textContent = '.' + c;
                classSelect.appendChild(opt);
            });
            if (classList.length > 1) {
                var compound = classList.map(function (c) { return '.' + c; }).join('');
                var compoundOpt = document.createElement('option');
                compoundOpt.value = compound;
                compoundOpt.textContent = compound + ' (all)';
                classSelect.appendChild(compoundOpt);
            }
        }
        classSelect.value = elementSelector;
        classRow.style.display = '';

        var cfg = {};
        try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
        var childStyles = (cfg.childStyles && typeof cfg.childStyles === 'object') ? cfg.childStyles : {};

        var activeSelector = function () {
            if (classSelect.value) {
                return classSelect.value;
            }
            return elementSelector;
        };

        var detectTypographyPreset = function (props) {
            var activeTypography = {};
            Object.keys(props || {}).forEach(function (key) {
                if (PHPI_TYPOGRAPHY_PROP_SET[key]) {
                    var value = (props[key] || '').toString().trim();
                    if (value !== '') {
                        activeTypography[key] = value;
                    }
                }
            });

            if (Object.keys(activeTypography).length === 0) {
                return '';
            }

            var presetKey = Object.keys(PHPI_TYPOGRAPHY_PRESETS).find(function (candidate) {
                var preset = PHPI_TYPOGRAPHY_PRESETS[candidate] || {};
                var presetKeys = Object.keys(preset);
                if (presetKeys.length !== Object.keys(activeTypography).length) {
                    return false;
                }
                return presetKeys.every(function (key) {
                    return (activeTypography[key] || '').toString().trim() === (preset[key] || '').toString().trim();
                });
            });

            return presetKey || '__custom__';
        };

        var saveChildSelectorProps = function (selector, props) {
            var nextCfg = {};
            try { nextCfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
            if (!nextCfg.childStyles || typeof nextCfg.childStyles !== 'object') {
                nextCfg.childStyles = {};
            }

            if (Object.keys(props).length > 0) {
                nextCfg.childStyles[selector] = props;
            } else {
                delete nextCfg.childStyles[selector];
            }

            if (Object.keys(nextCfg.childStyles).length === 0) {
                delete nextCfg.childStyles;
            }

            block.dataset.blockConfig = JSON.stringify(nextCfg);
            childStyles = nextCfg.childStyles || {};
            rebuildLiveStyles();
            debounceAction();
        };

        var loadSelectorIntoPanel = function (selector) {
            var selectorProps = childStyles[selector] || {};
            populatePphiPanelFields(fieldsEl, selectorProps);
            if (presetSelect) {
                presetSelect.value = detectTypographyPreset(selectorProps);
            }
        };

        loadSelectorIntoPanel(activeSelector());

        classSelect.onchange = function () {
            loadSelectorIntoPanel(activeSelector());
        };

        if (presetSelect) {
            presetSelect.onchange = function () {
                var presetKey = (presetSelect.value || '').toString();
                if (presetKey === '__custom__') {
                    return;
                }

                var selector = activeSelector();
                var existing = childStyles[selector] || {};
                var nextProps = {};

                Object.keys(existing).forEach(function (prop) {
                    if (!PHPI_TYPOGRAPHY_PROP_SET[prop]) {
                        nextProps[prop] = existing[prop];
                    }
                });

                if (presetKey && PHPI_TYPOGRAPHY_PRESETS[presetKey]) {
                    Object.keys(PHPI_TYPOGRAPHY_PRESETS[presetKey]).forEach(function (prop) {
                        nextProps[prop] = PHPI_TYPOGRAPHY_PRESETS[presetKey][prop];
                    });
                }

                saveChildSelectorProps(selector, nextProps);
                loadSelectorIntoPanel(selector);
            };
        }

        fieldsEl.querySelectorAll('[data-phpi-prop]').forEach(function (inp) {
            inp.oninput = function () {
                var selector = activeSelector();
                var existing = childStyles[selector] || {};
                var props = {};

                Object.keys(existing).forEach(function (prop) {
                    if (!PHPI_PANEL_PROP_SET[prop]) {
                        props[prop] = existing[prop];
                    }
                });

                fieldsEl.querySelectorAll('[data-phpi-prop]').forEach(function (x) {
                    var value = (x.value || '').trim();
                    if (value !== '') {
                        props[x.dataset.phpiProp] = value;
                    }
                });

                saveChildSelectorProps(selector, props);
                if (presetSelect) {
                    presetSelect.value = detectTypographyPreset(props);
                }
            };
        });
    }

    function selectPphiElement(block, el) {
        if (!isPhpIncludeBlock(block) || !el) { return; }

        var pphiId = (el.dataset.phpiEl || '').trim();
        if (!pphiId) { return; }

        clearPphiElementSelection();
        el.classList.add('phpi-el-selected');
        selectedPphiBlock = block;
        selectedPphiElement = el;
        syncPphiInspector(block, el);
    }

    // Intercept all interactive element clicks in the canvas — prevent navigation/submission.
    // Ctrl+click or Cmd+click opens anchor href in a new tab (for preview).
    canvas.addEventListener('click', function (e) {
        // Prevent anchor navigation
        var anchor = e.target.closest('a');
        if (anchor && canvas.contains(anchor)) {
            if (anchor.hasAttribute('data-inline-canvas-edit')) {
                e.preventDefault();
                e.stopPropagation();
                openInlineCanvasFocus(anchor.getAttribute('href') || '#', (anchor.textContent || '').trim() || 'Canvas');
                return;
            }
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

        var pphiEl = e.target.closest('[data-phpi-el]');
        if (pphiEl && b.contains(pphiEl) && isPhpIncludeBlock(b)) {
            select(b, { preservePphiSelection: true });
            selectPphiElement(b, pphiEl);
            return;
        }

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

    function select(block, options) {
        options = options || {};
        var preservePphiSelection = options.preservePphiSelection === true;

        if (activeBlock === block) {
            if (!preservePphiSelection) {
                clearPphiElementSelection();
                loadProps(block);
                updateBlockTree();
            }
            return;
        }

        if (!preservePphiSelection) {
            clearPphiElementSelection();
        }
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
        clearPphiElementSelection();
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

    // ── Section D — contenteditable + mini-toolbar ──────────────────

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

    // ── Section E — Drag and Drop ────────────────────────────────────

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

    // ── Section F — Properties panel ────────────────────────────────

    var LAYOUT_TYPES = ['element', 'section', 'columns', 'table', 'list', 'nav-menu'];
    var ZONE_TYPES = ['zone'];
    var IMAGE_TYPES = ['image', 'site-logo'];
    var TITLE_TYPES = ['site-title'];
    var DYNAMIC_TYPES = ['event-list', 'data-list', 'module-widget', 'module-content', 'dynamic-include'];
    var CONFIG_TYPES = ['event-list', 'nav-menu', 'php-include', 'dynamic-include', 'data-list', 'module-widget', 'module-content'];
    var PHP_CODE_TYPES = ['php-code'];
    // Block types whose inner_html slot is bindable
    var BIND_INNER_TYPES = ['text', 'html', 'heading', 'inline', 'anchor'];
    // Block types whose src slot is bindable
    var BIND_SRC_TYPES = ['image', 'site-logo'];
    // Block types whose href slot is bindable
    var BIND_HREF_TYPES = ['anchor'];

    function getAvailableZoneNames(currentName) {
        var seen = {};
        var names = [];

        function pushName(name) {
            var n = (name || '').toString().trim();
            if (!n || seen[n]) { return; }
            seen[n] = true;
            names.push(n);
        }

        var zoneSuggestions = (wrap.dataset.zoneSuggestions || 'main').split(',');
        zoneSuggestions.forEach(pushName);
        TEMPLATE_ZONES.forEach(pushName);

        canvas.querySelectorAll('[data-block-type="zone"]').forEach(function (zoneEl) {
            var cfg = {};
            try { cfg = JSON.parse(zoneEl.dataset.blockConfig || '{}'); } catch (e) { }
            pushName(cfg.zone_name || zoneEl.getAttribute('data-zone-name') || 'main');
        });

        pushName(currentName);
        return names;
    }

    function getModuleContentProvider(providerKey) {
        return MODULE_CONTENT_PROVIDERS.find(function (provider) {
            return (provider.key || '').toString() === (providerKey || '').toString();
        }) || null;
    }

    function getModuleContentDisplayModeMeta(providerKey) {
        var provider = getModuleContentProvider(providerKey);
        var editor = provider && provider.editor && typeof provider.editor === 'object'
            ? provider.editor
            : null;
        var displayMode = editor && editor.display_mode && typeof editor.display_mode === 'object'
            ? editor.display_mode
            : null;

        if (displayMode && Array.isArray(displayMode.options) && displayMode.options.length > 0) {
            return displayMode;
        }

        if (['blog:content', 'events:content'].indexOf((providerKey || '').toString()) !== -1) {
            return {
                label: 'Display Mode',
                default: 'both',
                options: [
                    { value: 'both', label: 'List and detail' },
                    { value: 'list', label: 'List only' },
                    { value: 'post', label: 'Post only' },
                    { value: 'detail', label: 'Detail only' }
                ]
            };
        }

        return null;
    }

    function syncModuleContentModeControl(providerKey, selectedValue) {
        var row = document.getElementById('prop-module-content-mode-row');
        var select = document.getElementById('prop-module-content-mode');
        var meta = getModuleContentDisplayModeMeta(providerKey);
        var label = row ? row.querySelector('label') : null;
        var resolvedValue = (selectedValue || '').toString();

        if (!row || !select) {
            return resolvedValue;
        }

        if (!meta) {
            row.style.display = 'none';
            if (label) {
                label.textContent = 'Display Mode';
            }
            return resolvedValue;
        }

        row.style.display = '';
        if (label) {
            label.textContent = (meta.label || 'Display Mode').toString();
        }

        select.innerHTML = '';
        meta.options.forEach(function (option) {
            var opt = document.createElement('option');
            opt.value = (option.value || '').toString();
            opt.textContent = (option.label || option.value || '').toString();
            select.appendChild(opt);
        });

        var hasSelectedValue = Array.from(select.options).some(function (option) {
            return option.value === resolvedValue;
        });

        if (!hasSelectedValue) {
            resolvedValue = meta.default !== undefined
                ? (meta.default || '').toString()
                : (meta.options[0].value || '').toString();
        }

        if (!Array.from(select.options).some(function (option) { return option.value === resolvedValue; })) {
            resolvedValue = (meta.options[0].value || '').toString();
        }

        select.value = resolvedValue;
        return resolvedValue;
    }

    function getModuleContentModeLabel(providerKey, modeValue) {
        var meta = getModuleContentDisplayModeMeta(providerKey);
        var resolvedMode = (modeValue || '').toString();

        if (!meta || !Array.isArray(meta.options)) {
            return resolvedMode;
        }

        var hit = meta.options.find(function (option) {
            return (option.value || '').toString() === resolvedMode;
        });

        if (hit) {
            return (hit.label || hit.value || '').toString();
        }

        if (meta.default !== undefined) {
            hit = meta.options.find(function (option) {
                return (option.value || '').toString() === (meta.default || '').toString();
            });
            if (hit) {
                return (hit.label || hit.value || '').toString();
            }
        }

        return resolvedMode;
    }

    function resolveDynamicIncludeSourceType(config) {
        var sourceType = (config.source_type || '').toString();
        if (!sourceType) {
            if ((config.template || '').toString() !== '') {
                sourceType = 'php_include';
            } else if ((config.widget_key || '').toString() !== '') {
                sourceType = 'module_widget';
            } else if ((config.provider_key || '').toString() !== '') {
                sourceType = 'module_content';
            } else if ((config.core_fragment_key || '').toString() !== '') {
                sourceType = 'core_fragment';
            } else {
                sourceType = 'php_include';
            }
        }
        return sourceType;
    }

    function findCoreFragmentModule(fragmentKey) {
        var key = (fragmentKey || '').toString();
        if (!key) { return ''; }

        var moduleKeys = Object.keys(CORE_FRAGMENT_CATALOG);
        for (var i = 0; i < moduleKeys.length; i++) {
            var moduleKey = moduleKeys[i];
            var list = CORE_FRAGMENT_CATALOG[moduleKey] || [];
            for (var j = 0; j < list.length; j++) {
                if ((list[j].key || '').toString() === key) {
                    return moduleKey;
                }
            }
        }

        return '';
    }

    function populateCoreFragmentOptions(moduleKey, selectedKey) {
        var keyRow = document.getElementById('prop-dyn-core-fragment-key-row');
        var fragmentSel = document.getElementById('prop-dyn-core-fragment');
        if (!keyRow || !fragmentSel) {
            return '';
        }

        var resolvedModule = (moduleKey || '').toString();
        var resolvedSelected = (selectedKey || '').toString();
        var options = CORE_FRAGMENT_CATALOG[resolvedModule] || [];

        fragmentSel.innerHTML = '<option value="">— Select fragment —</option>';
        options.forEach(function (entry) {
            var opt = document.createElement('option');
            opt.value = (entry.key || '').toString();
            opt.textContent = (entry.label || entry.key || '').toString();
            fragmentSel.appendChild(opt);
        });

        if (resolvedSelected && !Array.from(fragmentSel.options).some(function (opt) { return opt.value === resolvedSelected; })) {
            var stale = document.createElement('option');
            stale.value = resolvedSelected;
            stale.textContent = '(missing) ' + resolvedSelected;
            fragmentSel.appendChild(stale);
        }

        fragmentSel.value = resolvedSelected;
        keyRow.style.display = resolvedModule ? '' : 'none';
        return (fragmentSel.value || '').toString();
    }

    function syncDynamicIncludeContentUi(config) {
        var sourceType = resolveDynamicIncludeSourceType(config);
        var sourceRow = document.getElementById('prop-dyn-source-type-row');
        var sourceSel = document.getElementById('prop-dyn-source-type');
        var templateRow = document.getElementById('prop-dyn-template-row');
        var varsRow = document.getElementById('prop-dyn-vars-row');
        var editSourceRow = document.getElementById('prop-dyn-edit-source-row');
        var coreFragmentModuleRow = document.getElementById('prop-dyn-core-fragment-row');
        var coreFragmentModuleSel = document.getElementById('prop-dyn-core-fragment-module');
        var moduleWidgetGroup = panel.querySelector('.editor-content-group[data-content-type="module-widget"]');
        var moduleContentGroup = panel.querySelector('.editor-content-group[data-content-type="module-content"]');

        if (sourceRow) { sourceRow.style.display = ''; }
        if (sourceSel) { sourceSel.value = sourceType; }

        var isPhpInclude = sourceType === 'php_include';
        var isModuleWidget = sourceType === 'module_widget';
        var isModuleContent = sourceType === 'module_content';
        var isCoreFragment = sourceType === 'core_fragment';

        if (templateRow) { templateRow.style.display = isPhpInclude ? '' : 'none'; }
        if (varsRow) { varsRow.style.display = isPhpInclude ? '' : 'none'; }
        if (editSourceRow) { editSourceRow.style.display = isPhpInclude ? '' : 'none'; }
        if (coreFragmentModuleRow) { coreFragmentModuleRow.style.display = isCoreFragment ? '' : 'none'; }
        if (moduleWidgetGroup) { moduleWidgetGroup.style.display = isModuleWidget ? '' : 'none'; }
        if (moduleContentGroup) { moduleContentGroup.style.display = isModuleContent ? '' : 'none'; }

        if (isCoreFragment) {
            var selectedFragmentKey = (config.core_fragment_key || '').toString();
            var resolvedModule = '';
            if (coreFragmentModuleSel) {
                resolvedModule = (coreFragmentModuleSel.value || '').toString();
            }
            if (!resolvedModule) {
                resolvedModule = findCoreFragmentModule(selectedFragmentKey) || 'account';
            }
            if (coreFragmentModuleSel) {
                coreFragmentModuleSel.value = resolvedModule;
            }
            populateCoreFragmentOptions(resolvedModule, selectedFragmentKey);
        } else {
            populateCoreFragmentOptions('', '');
        }
    }

    function loadProps(block) {
        var type = block.dataset.blockType;
        var includeElementSelected = isPhpIncludeBlock(block)
            && selectedPphiBlock === block
            && !!selectedPphiElement
            && block.contains(selectedPphiElement);
        var styleTarget = includeElementSelected ? selectedPphiElement : block;
        var cs = getComputedStyle(styleTarget); // computed styles for reading actual CSS values

        function usesPagedContent(providerKey) {
            return ['blog:list', 'blog:content', 'events:list', 'events:content'].indexOf((providerKey || '').toString()) !== -1;
        }

        function usesBlogProfile(providerKey) {
            return ['blog:list', 'blog:content', 'blog:post'].indexOf((providerKey || '').toString()) !== -1;
        }

        function usesEventProfile(providerKey) {
            return ['events:list', 'events:content', 'events:detail'].indexOf((providerKey || '').toString()) !== -1;
        }

        // Show/hide groups
        panel.querySelector('.editor-props-empty').style.display = 'none';

        // Page Settings and Template Layout always visible (page/template-level, not block-level)
        var pageSettings = document.getElementById('editor-page-settings');
        if (pageSettings) { pageSettings.style.display = ''; }
        var templateLayout = document.getElementById('editor-template-layout');
        if (templateLayout) { templateLayout.style.display = ''; }

        panel.querySelectorAll('.editor-accordion').forEach(function (acc) {
            // Skip page-settings and template-layout, already handled above
            if (acc.id === 'editor-page-settings' || acc.id === 'editor-template-layout') { return; }
            acc.style.display = '';
        });

        // Show/hide the zone assignment row (root-level blocks only, when template has zones)
        var zoneAssignRow = document.getElementById('prop-zone-assign-row');
        var zoneAssignSel = document.getElementById('prop-zone-assign');
        if (zoneAssignRow && zoneAssignSel) {
            var parentBlockEl = block.parentElement ? block.parentElement.closest('[data-block]') : null;
            var isRootBlock = !parentBlockEl;
            if (TEMPLATE_ZONES.length > 0 && isRootBlock && ZONE_TYPES.indexOf(type) === -1) {
                zoneAssignSel.innerHTML = '';
                TEMPLATE_ZONES.forEach(function (zn) {
                    var opt = document.createElement('option');
                    opt.value = zn;
                    opt.textContent = zn;
                    zoneAssignSel.appendChild(opt);
                });
                var bCfg = {};
                try { bCfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                zoneAssignSel.value = bCfg.zone_name || PAGE_ZONE || TEMPLATE_ZONES[0] || 'main';
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

        // Bind group � only when this page has context fields and the block type has bindable slots
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
            var groupType = g.dataset.contentType;
            var show = groupType === type;
            if (type === 'dynamic-include' && (groupType === 'php-include' || groupType === 'module-widget' || groupType === 'module-content')) {
                show = true;
            }
            g.style.display = show ? '' : 'none';
        });

        // Identity
        var idInput = document.getElementById('prop-block-id');
        if (idInput) { idInput.value = block.id || ''; }
        var typeInput = document.getElementById('prop-block-type');
        if (typeInput) { typeInput.value = type || ''; }
        var classInput = panel.querySelector('[data-prop-class]');
        if (classInput) { classInput.value = block.className.replace(/\b(active|collapsed)\b/g, '').replace(/\s+/g, ' ').trim(); }
        var anchorHrefRow = document.getElementById('prop-anchor-href-row');
        var anchorHrefInput = document.getElementById('prop-anchor-href');

        // Collapsed checkbox
        var collapsedCb = document.getElementById('prop-collapsed');
        if (collapsedCb) { collapsedCb.checked = block.classList.contains('collapsed'); }

        // Block config (for dynamic/configurable blocks and UI behavior)
        var config = {};
        try {
            config = JSON.parse(block.dataset.blockConfig || '{}');
        } catch (e) { /* ignore */ }

        if (anchorHrefRow && anchorHrefInput) {
            if (type === 'anchor') {
                anchorHrefInput.value = (config.href || block.getAttribute('href') || '').toString();
                anchorHrefRow.style.display = '';
            } else {
                anchorHrefInput.value = '';
                anchorHrefRow.style.display = 'none';
            }
        }

        // Zone block naming: dropdown-first with optional custom value
        if (type === 'zone') {
            var zoneNameSel = document.getElementById('prop-zone-name');
            var zoneNameCustom = document.getElementById('prop-zone-name-custom');
            if (zoneNameSel && zoneNameCustom) {
                var currentZoneName = (config.zone_name || block.getAttribute('data-zone-name') || 'main').toString().trim() || 'main';
                var availableZoneNames = getAvailableZoneNames(currentZoneName);

                zoneNameSel.innerHTML = '';
                availableZoneNames.forEach(function (zn) {
                    var opt = document.createElement('option');
                    opt.value = zn;
                    opt.textContent = zn;
                    zoneNameSel.appendChild(opt);
                });
                var customOpt = document.createElement('option');
                customOpt.value = '__custom__';
                customOpt.textContent = 'Custom...';
                zoneNameSel.appendChild(customOpt);

                if (availableZoneNames.indexOf(currentZoneName) !== -1) {
                    zoneNameSel.value = currentZoneName;
                    zoneNameCustom.style.display = 'none';
                    zoneNameCustom.value = '';
                } else {
                    zoneNameSel.value = '__custom__';
                    zoneNameCustom.style.display = '';
                    zoneNameCustom.value = currentZoneName;
                }
            }
        }

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

        // CSS properties � read from active viewport overrides, fallback to computed desktop
        var vpPropsRaw = (!includeElementSelected && activeViewport === 'tablet') ? block.dataset.cssPropsTablet
            : (!includeElementSelected && activeViewport === 'mobile') ? block.dataset.cssPropsMobile
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

        // Numeric + unit props � on breakpoints, prefer stored override
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
        if (type === 'php-include' || type === 'dynamic-include') {
            var phpTplPicker = panel.querySelector('.php-include-tpl-picker');
            if (phpTplPicker) { phpTplPicker.value = config.template || ''; }
            var phpVarsContainer = panel.querySelector('.php-include-vars');
            if (phpVarsContainer) { buildPhpIncludeVarRows(phpVarsContainer, config, block); }
            if (type === 'dynamic-include') {
                syncDynamicIncludeContentUi(config);
            }
            syncPphiInspector(block, selectedPphiBlock === block ? selectedPphiElement : null);
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

        // Module widget: populate picker and ensure selected key exists in options.
        if (type === 'module-widget' || type === 'dynamic-include') {
            var mwSel = document.getElementById('prop-module-widget-key');
            if (mwSel) {
                var selectedKey = (config.widget_key || '').toString();
                mwSel.innerHTML = '<option value="">� Select widget �</option>';
                MODULE_WIDGETS.forEach(function (w) {
                    var key = (w.key || '').toString();
                    if (!key) { return; }
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = (w.module || 'module') + ' � ' + (w.title || key);
                    mwSel.appendChild(opt);
                });
                if (selectedKey && !Array.from(mwSel.options).some(function (o) { return o.value === selectedKey; })) {
                    var stale = document.createElement('option');
                    stale.value = selectedKey;
                    stale.textContent = '(missing) ' + selectedKey;
                    mwSel.appendChild(stale);
                }
                mwSel.value = selectedKey;
            }
            if (type === 'module-widget') {
                refreshModuleWidgetPreview(block);
            }
        }

        if (type === 'module-content' || type === 'dynamic-include') {
            var mcSel = document.getElementById('prop-module-content-provider');
            var mcModeRow = document.getElementById('prop-module-content-mode-row');
            var mcModeSel = document.getElementById('prop-module-content-mode');
            var mcPerPageRow = document.getElementById('prop-module-content-per-page-row');
            var mcPerPageInp = document.getElementById('prop-module-content-per-page');
            var mcBlogProfileRow = document.getElementById('prop-module-content-blog-profile-row');
            var mcBlogProfileSel = document.getElementById('prop-module-content-blog-profile');
            var mcEventProfileRow = document.getElementById('prop-module-content-event-profile-row');
            var mcEventProfileSel = document.getElementById('prop-module-content-event-profile');
            var mcSettings = document.getElementById('prop-module-content-settings');
            if (mcSel) {
                var selectedProvider = (config.provider_key || '').toString();
                mcSel.innerHTML = '<option value="">- Select provider -</option>';
                MODULE_CONTENT_PROVIDERS.forEach(function (p) {
                    var key = (p.key || '').toString();
                    if (!key) { return; }
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = (p.module || 'module') + ' - ' + (p.title || key);
                    mcSel.appendChild(opt);
                });
                if (selectedProvider && !Array.from(mcSel.options).some(function (o) { return o.value === selectedProvider; })) {
                    var staleProvider = document.createElement('option');
                    staleProvider.value = selectedProvider;
                    staleProvider.textContent = '(missing) ' + selectedProvider;
                    mcSel.appendChild(staleProvider);
                }
                mcSel.value = selectedProvider;
                var resolvedDisplayMode = syncModuleContentModeControl(selectedProvider, (config.display_mode || '').toString());
                if (mcPerPageRow) {
                    mcPerPageRow.style.display = usesPagedContent(selectedProvider) ? '' : 'none';
                }
                if (mcBlogProfileRow) {
                    mcBlogProfileRow.style.display = usesBlogProfile(selectedProvider) ? '' : 'none';
                }
                if (mcEventProfileRow) {
                    mcEventProfileRow.style.display = usesEventProfile(selectedProvider) ? '' : 'none';
                }
                if (resolvedDisplayMode !== (config.display_mode || '').toString()) {
                    config.display_mode = resolvedDisplayMode;
                    block.dataset.blockConfig = JSON.stringify(config);
                }
            }
            if (mcModeSel && mcModeRow && mcModeRow.style.display !== 'none') {
                mcModeSel.value = (config.display_mode || mcModeSel.value || '').toString();
            }
            if (mcPerPageInp) {
                mcPerPageInp.value = (config.per_page || 10).toString();
            }
            if (mcBlogProfileSel) {
                var selectedProfile = (config.blog_profile_id || '').toString();
                mcBlogProfileSel.innerHTML = '<option value="">- None -</option>';
                BLOG_PROFILES.forEach(function (profile) {
                    var profileId = (profile.id || '').toString();
                    if (!profileId) { return; }
                    var opt = document.createElement('option');
                    opt.value = profileId;
                    opt.textContent = profile.name || ('Profile ' + profileId);
                    mcBlogProfileSel.appendChild(opt);
                });
                if (selectedProfile && !Array.from(mcBlogProfileSel.options).some(function (o) { return o.value === selectedProfile; })) {
                    var staleProfile = document.createElement('option');
                    staleProfile.value = selectedProfile;
                    staleProfile.textContent = '(missing) Profile ' + selectedProfile;
                    mcBlogProfileSel.appendChild(staleProfile);
                }
                mcBlogProfileSel.value = selectedProfile;
            }
            if (mcEventProfileSel) {
                var selectedEventProfile = (config.event_profile_id || '').toString();
                mcEventProfileSel.innerHTML = '<option value="">- None -</option>';
                EVENT_PROFILES.forEach(function (profile) {
                    var profileId = (profile.id || '').toString();
                    if (!profileId) { return; }
                    var opt = document.createElement('option');
                    opt.value = profileId;
                    opt.textContent = profile.name || ('Profile ' + profileId);
                    mcEventProfileSel.appendChild(opt);
                });
                if (selectedEventProfile && !Array.from(mcEventProfileSel.options).some(function (o) { return o.value === selectedEventProfile; })) {
                    var staleEventProfile = document.createElement('option');
                    staleEventProfile.value = selectedEventProfile;
                    staleEventProfile.textContent = '(missing) Profile ' + selectedEventProfile;
                    mcEventProfileSel.appendChild(staleEventProfile);
                }
                mcEventProfileSel.value = selectedEventProfile;
            }
            if (mcSettings) {
                mcSettings.value = (config.settings_json || '').toString();
            }
            if (type === 'module-content') {
                refreshModuleContentPreview(block);
            }
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

        // Text shadow — parse back into sub-fields (from computed style)
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

        // Box shadow — parse back into sub-fields (from computed style)
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
            sel.innerHTML = '<option value="">� none �</option>';
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
            // Keep Page Settings and Template Layout always visible (page/template-level, not block-level)
            if (acc.id === 'editor-page-settings' || acc.id === 'editor-template-layout') { return; }
            acc.style.display = 'none';
        });
    }

    function bindPropInputs(block) {
        var anchorHrefInput = document.getElementById('prop-anchor-href');

        // Remove previous listeners by cloning nodes for [data-prop] inputs
        // We use a delegated approach on panel instead — re-bind on each select
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
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'widget_key' && (block.dataset.blockType === 'module-widget' || block.dataset.blockType === 'dynamic-include')) {
                inp.onchange = function () {
                    writeConfig(block, 'widget_key', inp.value);
                    refreshPhpIncludePreview(block);
                };
            }
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'provider_key' && (block.dataset.blockType === 'module-content' || block.dataset.blockType === 'dynamic-include')) {
                inp.onchange = function () {
                    var perPageRow = document.getElementById('prop-module-content-per-page-row');
                    var blogProfileRow = document.getElementById('prop-module-content-blog-profile-row');
                    var eventProfileRow = document.getElementById('prop-module-content-event-profile-row');
                    writeConfig(block, 'provider_key', inp.value);
                    var cfg = {};
                    try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                    var resolvedMode = syncModuleContentModeControl(inp.value, (cfg.display_mode || '').toString());
                    if (resolvedMode !== (cfg.display_mode || '').toString()) {
                        writeConfig(block, 'display_mode', resolvedMode);
                    }
                    if (perPageRow) {
                        perPageRow.style.display = (inp.value === 'blog:list' || inp.value === 'blog:content' || inp.value === 'events:list' || inp.value === 'events:content') ? '' : 'none';
                    }
                    if (blogProfileRow) {
                        blogProfileRow.style.display = (inp.value === 'blog:list' || inp.value === 'blog:content' || inp.value === 'blog:post') ? '' : 'none';
                    }
                    if (eventProfileRow) {
                        eventProfileRow.style.display = (inp.value === 'events:list' || inp.value === 'events:content' || inp.value === 'events:detail') ? '' : 'none';
                    }
                    refreshPhpIncludePreview(block);
                };
            }
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'display_mode' && (block.dataset.blockType === 'module-content' || block.dataset.blockType === 'dynamic-include')) {
                inp.onchange = function () {
                    writeConfig(block, 'display_mode', inp.value);
                    refreshPhpIncludePreview(block);
                };
            }
            if (inp.tagName === 'TEXTAREA' && inp.dataset.config === 'settings_json' && (block.dataset.blockType === 'module-content' || block.dataset.blockType === 'dynamic-include')) {
                inp.oninput = function () {
                    writeConfig(block, 'settings_json', inp.value);
                    refreshPhpIncludePreview(block);
                };
            }
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'blog_profile_id' && (block.dataset.blockType === 'module-content' || block.dataset.blockType === 'dynamic-include')) {
                inp.onchange = function () {
                    writeConfig(block, 'blog_profile_id', inp.value);
                    refreshPhpIncludePreview(block);
                };
            }
            if (inp.tagName === 'SELECT' && inp.dataset.config === 'event_profile_id' && (block.dataset.blockType === 'module-content' || block.dataset.blockType === 'dynamic-include')) {
                inp.onchange = function () {
                    writeConfig(block, 'event_profile_id', inp.value);
                    refreshPhpIncludePreview(block);
                };
            }
        });

        if (block.dataset.blockType === 'dynamic-include') {
            var dynSourceType = document.getElementById('prop-dyn-source-type');
            if (dynSourceType) {
                dynSourceType.onchange = function () {
                    writeConfig(block, 'source_type', dynSourceType.value);
                    var cfg = {};
                    try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                    syncDynamicIncludeContentUi(cfg);
                    block.innerHTML = '';
                    refreshPhpIncludePreview(block);
                };
            }

            var dynCoreFragment = document.getElementById('prop-dyn-core-fragment');
            var dynCoreFragmentModule = document.getElementById('prop-dyn-core-fragment-module');
            if (dynCoreFragmentModule) {
                dynCoreFragmentModule.onchange = function () {
                    var nextKey = populateCoreFragmentOptions(dynCoreFragmentModule.value, '');
                    writeConfig(block, 'core_fragment_key', nextKey);
                    refreshPhpIncludePreview(block);
                };
            }
            if (dynCoreFragment) {
                dynCoreFragment.onchange = function () {
                    writeConfig(block, 'core_fragment_key', dynCoreFragment.value);
                    refreshPhpIncludePreview(block);
                };
            }
        }

        if (anchorHrefInput && block.dataset.blockType === 'anchor') {
            anchorHrefInput.oninput = function () {
                var href = anchorHrefInput.value.trim();
                writeConfig(block, 'href', href);
                if (href) {
                    block.setAttribute('href', href);
                } else {
                    block.removeAttribute('href');
                }
            };
        }

        // Zone name selector (dropdown + optional custom): updates block_config.zone_name
        var zoneNameSel2 = document.getElementById('prop-zone-name');
        var zoneNameCustom2 = document.getElementById('prop-zone-name-custom');
        if (zoneNameSel2 && zoneNameCustom2 && block.dataset.blockType === 'zone') {
            var applyZoneName = function (name) {
                var resolved = (name || '').toString().trim() || 'main';
                writeConfig(block, 'zone_name', resolved);
                block.setAttribute('data-zone-name', resolved);
            };

            zoneNameSel2.onchange = function () {
                if (zoneNameSel2.value === '__custom__') {
                    zoneNameCustom2.style.display = '';
                    zoneNameCustom2.focus();
                    applyZoneName(zoneNameCustom2.value);
                    return;
                }
                zoneNameCustom2.style.display = 'none';
                zoneNameCustom2.value = '';
                applyZoneName(zoneNameSel2.value);
            };

            zoneNameCustom2.oninput = function () {
                if (zoneNameSel2.value === '__custom__') {
                    applyZoneName(zoneNameCustom2.value);
                }
            };
        }

        // Zone canvas assignment selector (template pages only): updates block_config.canvas_page_id
        var zoneCanvasSel = document.getElementById('prop-zone-canvas');
        if (zoneCanvasSel && block.dataset.blockType === 'zone') {
            // Populate dropdown with available zone canvases
            var wrap = document.getElementById('editor-wrap');
            var availableCanvases = [];
            try {
                availableCanvases = JSON.parse(wrap.dataset.availableZoneCanvases || '[]');
            } catch (e) { /* ignore */ }

            // Get current zone name and canvas assignment from block config
            var blockConfig = {};
            try { blockConfig = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
            var currentZoneName = blockConfig.zone_name || 'main';
            var currentCanvasPageId = blockConfig.canvas_page_id || null;

            // Filter canvases to this zone name
            var filtered = availableCanvases.filter(function (c) {
                return !c.zone_name || c.zone_name === currentZoneName;
            });

            // Clear and repopulate select
            zoneCanvasSel.innerHTML = '<option value="">� None �</option>';
            filtered.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.title + (c.zone_name ? ' (' + c.zone_name + ')' : '');
                if (c.id == currentCanvasPageId) {
                    opt.selected = true;
                }
                zoneCanvasSel.appendChild(opt);
            });
            var createOpt = document.createElement('option');
            createOpt.value = '__create_new__';
            createOpt.textContent = '+ Create New Canvas';
            zoneCanvasSel.appendChild(createOpt);

            // Handle canvas assignment changes
            zoneCanvasSel.onchange = function () {
                var cfg = {};
                try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
                var zoneName = (cfg.zone_name || block.getAttribute('data-zone-name') || '').toString();
                if (!zoneName) {
                    return;
                }

                if (this.value === '__create_new__') {
                    var selectEl = this;
                    selectEl.disabled = true;
                    fetch(API_BASE + '/' + PAGE_ID + '/zone-canvas/new', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': CSRF,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ zone_name: zoneName }),
                    })
                        .then(function (r) {
                            if (!r.ok) {
                                throw new Error('zone canvas create failed');
                            }
                            return r.json();
                        })
                        .then(function (data) {
                            if (data && data.success) {
                                window.location.reload();
                            } else {
                                selectEl.disabled = false;
                            }
                        })
                        .catch(function (err) {
                            console.error('[Cruinn] zone canvas create failed:', err);
                            selectEl.disabled = false;
                        });
                    return;
                }

                var val = this.value ? parseInt(this.value, 10) : null;
                fetch(API_BASE + '/' + PAGE_ID + '/metadata', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        zone_assignments: (function () {
                            var out = {};
                            out[zoneName] = val;
                            return out;
                        }()),
                    }),
                })
                    .then(function (r) {
                        if (!r.ok) {
                            throw new Error('metadata save failed');
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (data && data.success) {
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        console.error('[Cruinn] zone assignment save failed:', err);
                    });
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
        if (block.dataset.blockType === 'php-include' || block.dataset.blockType === 'dynamic-include') {
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
                                if (!(v in cfg) && v !== 'db' && v !== 'template' && v !== 'source_type') {
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

        // Colour swatches — pre-apply on click (solves first-pick = same colour),
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

        // PHP Code textarea — write back to block_config._php on change
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
            return k !== 'template'
                && k !== 'db'
                && k !== 'source_type'
                && k !== 'core_fragment_key'
                && k !== 'widget_key'
                && k !== 'provider_key'
                && k !== 'settings_json'
                && k !== 'display_mode'
                && k !== 'per_page'
                && k !== 'blog_profile_id'
                && k !== 'event_profile_id'
                && k !== 'childStyles';
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
        clearPphiElementSelection();
        syncPphiInspector(block, null);

        var cfg = {};
        try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }

        if (block.dataset.blockType === 'dynamic-include') {
            var sourceType = resolveDynamicIncludeSourceType(cfg);

            if (sourceType === 'module_widget') {
                if (!block.innerHTML.trim()) {
                    block.innerHTML = '<p class="editor-dynamic-placeholder">Dynamic Include: select a module widget.</p>';
                }
                return;
            }

            if (sourceType === 'module_content') {
                if (!block.innerHTML.trim()) {
                    block.innerHTML = '<p class="editor-dynamic-placeholder">Dynamic Include: select module content.</p>';
                }
                return;
            }

            if (sourceType === 'core_fragment') {
                if (!block.innerHTML.trim()) {
                    block.innerHTML = '<p class="editor-dynamic-placeholder">Dynamic Include: select a core fragment.</p>';
                }
                return;
            }
        }

        var rel = cfg.template || '';
        if (!rel) {
            block.innerHTML = '<p style="color:#9ca3af;font-size:0.8rem;padding:0.5rem">PHP Include — no template selected</p>';
            return;
        }
        var qs = 'template=' + encodeURIComponent(rel);
        Object.keys(cfg).forEach(function (k) {
            if (k !== 'template'
                && k !== 'db'
                && k !== 'source_type'
                && k !== 'core_fragment_key'
                && k !== 'widget_key'
                && k !== 'provider_key'
                && k !== 'settings_json'
                && k !== 'display_mode'
                && k !== 'per_page'
                && k !== 'blog_profile_id'
                && k !== 'event_profile_id'
                && k !== 'childStyles') {
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

    // ── Data List token hints ─────────────────────────────────────

    function updateDataListTokenHints(slug) {
        var hint = document.getElementById('prop-data-list-tokens');
        if (!hint) { return; }
        var sets = window.CONTENT_SETS || [];
        var set = null;
        for (var i = 0; i < sets.length; i++) { if (sets[i].slug === slug) { set = sets[i]; break; } }
        if (!set || !set.fields || !set.fields.length) {
            hint.textContent = set && set.type === 'query'
                ? 'Query set — tokens depend on selected fields.'
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
        var includeElementSelected = isPhpIncludeBlock(block)
            && selectedPphiBlock === block
            && !!selectedPphiElement
            && block.contains(selectedPphiElement);

        if (includeElementSelected) {
            var cfg = {};
            try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
            if (!cfg.childStyles || typeof cfg.childStyles !== 'object') {
                cfg.childStyles = {};
            }

            var pphiId = (selectedPphiElement.dataset.phpiEl || '').trim();
            if (!pphiId) {
                return;
            }

            var classSelect = document.getElementById('prop-phpi-class-select');
            var selector = (classSelect && classSelect.value)
                ? classSelect.value
                : ('[data-phpi-el="' + pphiId + '"]');

            if (!cfg.childStyles[selector] || typeof cfg.childStyles[selector] !== 'object') {
                cfg.childStyles[selector] = {};
            }

            var cssProp = String(prop || '').replace(/([A-Z])/g, '-$1').toLowerCase();
            if (value !== '' && value !== null && value !== undefined) {
                cfg.childStyles[selector][cssProp] = value;
            } else {
                delete cfg.childStyles[selector][cssProp];
            }

            if (Object.keys(cfg.childStyles[selector]).length === 0) {
                delete cfg.childStyles[selector];
            }
            if (Object.keys(cfg.childStyles).length === 0) {
                delete cfg.childStyles;
            }

            block.dataset.blockConfig = JSON.stringify(cfg);
            rebuildLiveStyles();
            recordAction();
            return;
        }

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

    function refreshModuleWidgetPreview(block) {
        if (!block || block.dataset.blockType !== 'module-widget') { return; }
        var cfg = {};
        try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
        var key = (cfg.widget_key || '').toString();
        if (!key) {
            block.innerHTML = '<p class="editor-dynamic-placeholder">Module Widget � select a widget in Content settings.</p>';
            return;
        }
        var hit = MODULE_WIDGETS.find(function (w) { return (w.key || '') === key; });
        if (!hit) {
            block.innerHTML = '<p class="editor-dynamic-placeholder">Module Widget � missing widget: ' + key + '</p>';
            return;
        }
        block.innerHTML = '<p class="editor-dynamic-placeholder">Module Widget: ' +
            (hit.module || 'module') + ' � ' + (hit.title || key) + '</p>';
    }

    function refreshModuleContentPreview(block) {
        if (!block || block.dataset.blockType !== 'module-content') { return; }
        var cfg = {};
        try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
        var key = (cfg.provider_key || '').toString();
        var mode = (cfg.display_mode || 'both').toString();
        if (!key) {
            block.innerHTML = '<p class="editor-dynamic-placeholder">Module Content - select a provider in Content settings.</p>';
            return;
        }
        var hit = MODULE_CONTENT_PROVIDERS.find(function (p) { return (p.key || '') === key; });
        if (!hit) {
            block.innerHTML = '<p class="editor-dynamic-placeholder">Module Content - missing provider: ' + key + '</p>';
            return;
        }
        var profileLabel = '';
        if (cfg.blog_profile_id) {
            var hitProfile = BLOG_PROFILES.find(function (p) { return String(p.id || '') === String(cfg.blog_profile_id || ''); });
            profileLabel = hitProfile ? (' - Profile: ' + (hitProfile.name || hitProfile.slug || cfg.blog_profile_id)) : (' - Profile #' + cfg.blog_profile_id);
        }
        if (cfg.event_profile_id) {
            var hitEventProfile = EVENT_PROFILES.find(function (p) { return String(p.id || '') === String(cfg.event_profile_id || ''); });
            profileLabel += hitEventProfile ? (' - Events Profile: ' + (hitEventProfile.name || hitEventProfile.slug || cfg.event_profile_id)) : (' - Events Profile #' + cfg.event_profile_id);
        }
        var label = 'Module Content: ' + (hit.module || 'module') + ' - ' + (hit.title || key);
        var modeLabel = getModuleContentModeLabel(key, mode);
        if (modeLabel) {
            label += ' (' + modeLabel + ')';
        }
        label += profileLabel;
        block.innerHTML = '<p class="editor-dynamic-placeholder">' + label + '</p>';
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

            var cfg = {};
            try { cfg = JSON.parse(block.dataset.blockConfig || '{}'); } catch (e) { }
            if (cfg.childStyles && typeof cfg.childStyles === 'object') {
                Object.keys(cfg.childStyles).forEach(function (selector) {
                    var props = cfg.childStyles[selector];
                    if (!props || typeof props !== 'object') { return; }
                    var rules = [];
                    var cascadeRules = [];
                    Object.keys(props).forEach(function (prop) {
                        var value = props[prop];
                        if (value === '' || value === null || value === undefined) { return; }
                        var rule = prop + ':' + value;
                        rules.push(rule);
                        if (PHPI_TEXT_CASCADE_PROPS[prop]) {
                            cascadeRules.push(rule);
                        }
                    });
                    if (rules.length > 0) {
                        css += '#' + block.id + ' ' + selector + ' {' + rules.join(';') + '}\n';
                    }
                    if (cascadeRules.length > 0) {
                        css += '#' + block.id + ' ' + selector + ', #' + block.id + ' ' + selector + ' * {' + cascadeRules.join(';') + '}\n';
                    }
                });
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

    // G��G�� Section G G�� Block palette + delete G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

    // Default CSS applied to new leaf blocks G�� portrait ISO proportions (G�� A4 at screen scale),
    // inline-block so that multiple blocks can sit side by side.
    var PORTRAIT_INIT = { display: 'inline-block', verticalAlign: 'top', width: '260px', boxSizing: 'border-box' };

    var BLOCK_DEFS = {
        'element': { tag: 'div', inner: '', isLayout: true, initCss: PORTRAIT_INIT },
        'text': { tag: 'div', inner: '<p>New text block.</p>', initCss: PORTRAIT_INIT },
        'heading': { tag: 'h2', inner: 'New Heading', initCss: PORTRAIT_INIT },
        'list': { tag: 'ul', inner: '<li>List item</li><li>List item</li>', isLayout: true, initCss: PORTRAIT_INIT },
        'image': { tag: 'figure', inner: '<img src="" alt=""><figcaption></figcaption>', initCss: PORTRAIT_INIT },
        'section': { tag: 'section', inner: '', isLayout: true, initCss: PORTRAIT_INIT },
        'columns': {
            tag: 'div', inner: function () {
                return '<section data-block data-block-type="section" id="' + newId() + '"></section>' +
                    '<section data-block data-block-type="section" id="' + newId() + '"></section>';
            }, isLayout: true, initCss: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }
        },
        'gallery': { tag: 'div', inner: '', initCss: PORTRAIT_INIT },
        'table': {
            tag: 'table',
            inner: '<tbody><tr><td>Cell</td><td>Cell</td></tr></tbody>',
            isLayout: true,
            initCss: { width: '100%', borderCollapse: 'collapse', boxSizing: 'border-box' }
        },
        'html': { tag: 'div', inner: '', initCss: PORTRAIT_INIT },
        'nav-menu': { tag: 'nav', inner: '', defaultConfig: { menu_id: '' }, initCss: PORTRAIT_INIT },
        'site-logo': { tag: 'div', inner: '<a href="/"><img src="" alt="Site Logo"></a>', initCss: PORTRAIT_INIT },
        'site-title': { tag: 'div', inner: '<h1 class="site-name">Site Name</h1><p class="site-tagline"></p>', initCss: PORTRAIT_INIT },
        'event-list': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Event list G�� visible on live page.</p>', dynamic: true, defaultConfig: { count: 5, filter: 'upcoming' }, initCss: PORTRAIT_INIT },
        'data-list': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Data List G�� visible on live page.</p>', dynamic: true, defaultConfig: { set_slug: '', view: 'continuous', card_html: '' }, initCss: PORTRAIT_INIT },
        'module-widget': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Module Widget G�� select widget in Content settings.</p>', dynamic: true, defaultConfig: { widget_key: '' }, initCss: PORTRAIT_INIT },
        'module-content': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Module Content - select provider in Content settings.</p>', dynamic: true, defaultConfig: { provider_key: '', display_mode: 'both', per_page: 10, blog_profile_id: '', event_profile_id: '', settings_json: '' }, initCss: PORTRAIT_INIT },
        'php-include': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">PHP Include G�� visible on live page.</p>', dynamic: true, defaultConfig: { template: '' }, initCss: PORTRAIT_INIT },
        'dynamic-include': { tag: 'div', inner: '<p class="editor-dynamic-placeholder">Dynamic Include G�� select a template in Content settings.</p>', dynamic: true, defaultConfig: { source_type: 'php_include', template: '' }, initCss: PORTRAIT_INIT },
        'zone': {
            tag: 'div', inner: '', isLayout: true, defaultConfig: { zone_name: 'main', zone_label: 'Main Content' },
            initCss: { display: 'block', width: '100%', boxSizing: 'border-box' }
        },
    };

    function bindPalette() {
        document.querySelectorAll('[data-add-block]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                addBlock(btn.dataset.addBlock, {
                    tag: btn.dataset.addTag || ''
                });
            });
        });
    }

    function isUnsafeInsertParent(node) {
        if (!node || !node.tagName) { return false; }
        return [
            'ul', 'ol',
            'table', 'thead', 'tbody', 'tfoot', 'tr',
            'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
        ].indexOf(node.tagName.toLowerCase()) !== -1;
    }

    function getSafeInsertAnchor(block) {
        var anchor = block;
        while (anchor && anchor.parentNode && anchor.parentNode !== canvas && isUnsafeInsertParent(anchor.parentNode)) {
            var parentBlock = anchor.parentNode.closest('[data-block]');
            if (!parentBlock || parentBlock === anchor) { break; }
            anchor = parentBlock;
        }
        return anchor;
    }

    function addBlock(type, options) {
        if (!HAS_PAGE) { return; }
        var def = BLOCK_DEFS[type];
        if (!def) { return; }
        options = options || {};

        var elementTag = (options.tag || '').trim() || def.tag;

        var el = document.createElement(elementTag);
        el.id = newId();
        el.setAttribute('data-block', '');
        el.setAttribute('data-block-type', type);

        var blockConfig = {};
        if (def.defaultConfig) {
            Object.assign(blockConfig, def.defaultConfig);
        }
        if (options.tag) {
            blockConfig._tag = elementTag;
        }
        if (Object.keys(blockConfig).length > 0) {
            el.dataset.blockConfig = JSON.stringify(blockConfig);
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

        if (type === 'module-widget') {
            refreshModuleWidgetPreview(el);
        }

        if (type === 'module-content') {
            refreshModuleContentPreview(el);
        }

        if (type === 'dynamic-include') {
            refreshPhpIncludePreview(el);
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
            var insertAnchor = getSafeInsertAnchor(activeBlock);
            var insertParent = insertAnchor && insertAnchor.parentNode ? insertAnchor.parentNode : canvas;
            if (!insertParent || !canvas.contains(insertParent)) {
                insertParent = canvas;
            }
            insertParent.insertBefore(el, insertAnchor ? insertAnchor.nextSibling : null);
        } else {
            canvas.appendChild(el);
        }

        // Browser DOM normalisation can reject invalid insertions inside list/table/text contexts.
        // If the new block did not land in the canvas tree as expected, fall back to canvas root.
        if (!canvas.contains(el)) {
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

    // G��G�� Block tree G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

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

    // G��G�� Accordion behaviour G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

    function bindAccordions() {
        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('.editor-accordion-toggle');
            if (!btn) { return; }
            var acc = btn.closest('.editor-accordion');
            if (acc) { acc.classList.toggle('collapsed'); }
        });
    }

    // G��G�� Section H G�� Media panel G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��
    // Delegated to Cruinn.openMediaBrowser (media-browser.js)

    function openMediaPanel(callback) {
        Cruinn.openMediaBrowser(callback);
    }

    // G��G�� Section I G�� Serialise + recordAction G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

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
            cloned.querySelectorAll('.editor-inline-zone-edit, .editor-zone-assigned-canvas, .editor-zone-assigned-empty').forEach(function (el) {
                el.remove();
            });
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

    // G��G�� Document panel (file-mode) G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��
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
        if (!HAS_PAGE) { return; }
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
    // G��G�� End Document panel G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

    function parseCssProps(style) {
        if (!style) { return null; }
        // Always return an object (possibly empty {}) G�� an empty object signals
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

    // -- Page Settings -----------------------------------------------------

    function bindPageSettings() {
        var templateSelect = document.getElementById('page-template-select');
        var zoneSelect = document.getElementById('page-zone-select');
        if (!templateSelect || !zoneSelect) { return; }

        // Populate zone selector based on current template
        function refreshZoneOptions() {
            var selectedOpt = templateSelect.options[templateSelect.selectedIndex];
            if (!selectedOpt) { return; }

            var zonesAttr = selectedOpt.dataset.zones || '["main"]';
            var zones = [];
            try { zones = JSON.parse(zonesAttr); } catch (e) { zones = ['main']; }

            // Use the currently selected value (when re-running after a template change),
            // falling back to the page's actual zone from the server on first load.
            var currentZone = zoneSelect.value || PAGE_ZONE;
            zoneSelect.innerHTML = '';

            zones.forEach(function (zone) {
                var opt = document.createElement('option');
                opt.value = zone;
                opt.textContent = zone.replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
                zoneSelect.appendChild(opt);
            });

            // Restore previous zone selection if still available
            if (currentZone && Array.from(zoneSelect.options).some(function (o) { return o.value === currentZone; })) {
                zoneSelect.value = currentZone;
            } else if (zoneSelect.options.length > 0) {
                zoneSelect.value = zoneSelect.options[0].value;
            }
        }

        // Initialize zone options on load
        refreshZoneOptions();

        // Handle template change
        templateSelect.addEventListener('change', function () {
            refreshZoneOptions();
            savePageMetadata(true);
        });

        // Handle zone change
        zoneSelect.addEventListener('change', function () {
            savePageMetadata(false);
        });

        function savePageMetadata(reloadAfterSave) {
            if (!HAS_PAGE) { return; }

            fetch(API_BASE + '/' + PAGE_ID + '/metadata', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    template: templateSelect.value,
                    page_zone: zoneSelect.value,
                }),
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('metadata save failed');
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (reloadAfterSave && data && data.success) {
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    console.error('[Cruinn] savePageMetadata failed:', err);
                });
        }
    }

    function bindTemplateLayout() {
        var maxWidthNum = document.getElementById('tpl-body-max-width-num');
        var maxWidthUnit = document.getElementById('tpl-body-max-width-unit');
        var padding = document.getElementById('tpl-body-padding');

        if (!maxWidthNum || !maxWidthUnit || !padding) { return; }

        // Load existing settings from data attribute
        var wrap = document.getElementById('editor-wrap');
        if (wrap && wrap.dataset.templateLayout) {
            try {
                var settings = JSON.parse(wrap.dataset.templateLayout);
                if (settings.maxWidth) {
                    maxWidthNum.value = settings.maxWidth;
                }
                if (settings.maxWidthUnit) {
                    maxWidthUnit.value = settings.maxWidthUnit;
                }
                if (settings.padding) {
                    padding.value = settings.padding;
                }
            } catch (e) {
                console.warn('[Cruinn] Failed to parse template layout settings:', e);
            }
        }

        // Debounced save
        var saveTimer = null;
        function saveTemplateLayout() {
            if (!HAS_PAGE) { return; }

            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                var maxWidth = maxWidthNum.value ? parseInt(maxWidthNum.value, 10) : null;
                var unit = maxWidthUnit.value;

                fetch(API_BASE + '/' + PAGE_ID + '/metadata', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        layout_settings: {
                            maxWidth: maxWidth,
                            maxWidthUnit: unit,
                            padding: padding.value.trim() || null,
                        },
                    }),
                }).catch(function (err) {
                    console.error('[Cruinn] saveTemplateLayout failed:', err);
                });
            }, 500);
        }

        maxWidthNum.addEventListener('input', saveTemplateLayout);
        maxWidthUnit.addEventListener('change', saveTemplateLayout);
        padding.addEventListener('input', saveTemplateLayout);
    }

    function bindTemplatePageSettings() {
        if (!IS_TEMPLATE_PAGE) { return; }

        var layoutSelect = document.getElementById('tpl-layout-page-select');
        var zoneSelects = Array.from(document.querySelectorAll('.template-zone-canvas-select'));

        function collectAssignments() {
            var assignments = {};
            zoneSelects.forEach(function (sel) {
                assignments[sel.dataset.zone] = sel.value ? parseInt(sel.value, 10) : null;
            });
            return assignments;
        }

        function saveTemplateSettings(reloadAfterSave) {
            fetch(API_BASE + '/' + PAGE_ID + '/metadata', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    layout_page_id: layoutSelect ? (layoutSelect.value ? parseInt(layoutSelect.value, 10) : null) : null,
                    zone_assignments: collectAssignments()
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        console.error('[Cruinn] saveTemplateSettings failed:', data && data.error ? data.error : data);
                        return;
                    }
                    if (reloadAfterSave) {
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    console.error('[Cruinn] saveTemplateSettings failed:', err);
                });
        }

        if (layoutSelect) {
            layoutSelect.addEventListener('change', function () {
                saveTemplateSettings(true);
            });
        }

        zoneSelects.forEach(function (sel) {
            sel.addEventListener('change', function () {
                saveTemplateSettings(true);
            });
        });
    }

    var _actionTimer = null;
    var _csrfRefreshPromise = null;
    var _csrfSaveLocked = false;

    function isCsrfExpiredResponse(status, bodyText) {
        return status === 403 && /csrf token expired/i.test((bodyText || '').toString());
    }

    function refreshCsrfTokenFromEditorPage() {
        if (_csrfRefreshPromise) {
            return _csrfRefreshPromise;
        }

        _csrfRefreshPromise = fetch(window.location.href, {
            method: 'GET',
            headers: { 'Accept': 'text/html' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('csrf refresh HTTP ' + r.status);
                }
                return r.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var freshWrap = doc.getElementById('editor-wrap');
                var freshToken = freshWrap && freshWrap.dataset
                    ? (freshWrap.dataset.csrf || '').toString()
                    : '';

                if (!freshToken) {
                    throw new Error('csrf token not found in refreshed editor html');
                }

                CSRF = freshToken;
                wrap.dataset.csrf = freshToken;
                return freshToken;
            })
            .finally(function () {
                _csrfRefreshPromise = null;
            });

        return _csrfRefreshPromise;
    }

    function postRecordAction(blocks, retriedAfterRefresh) {
        return fetch(API_BASE + '/' + PAGE_ID + '/action', {
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
                        if (isCsrfExpiredResponse(r.status, t) && !retriedAfterRefresh) {
                            return refreshCsrfTokenFromEditorPage().then(function () {
                                return postRecordAction(blocks, true);
                            });
                        }

                        if (isCsrfExpiredResponse(r.status, t)) {
                            _csrfSaveLocked = true;
                            showSaveError('Session token expired. Reload editor to continue saving.');
                            return null;
                        }

                        console.error('recordAction HTTP ' + r.status + ':', t);
                        showSaveError('Save failed (' + r.status + ') - check console');
                        return null;
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
                    return data;
                });
            });
    }

    function recordAction() {
        if (!HAS_PAGE) { return; }
        if (_htmlPageMode) { return; } // HTML pages: save only on publish
        if (_csrfSaveLocked) {
            showSaveError('Session token expired. Reload editor to continue saving.');
            return;
        }
        clearTimeout(_actionTimer);
        var blocks = serialiseCanvas();

        pushLocalUndo();

        postRecordAction(blocks, false)
            .catch(function (err) {
                console.error('recordAction failed:', err);
                if (!_csrfSaveLocked) {
                    showSaveError('Save failed - check console');
                }
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

    // G��G�� Section J G�� Undo / Redo G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

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
        if (!HAS_PAGE) { return; }
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
        if (!HAS_PAGE) { return; }
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

    // G��G�� Section K G�� Publish / Discard G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

    function bindToolbar() {
        if (!HAS_PAGE) { return; }
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
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok) {
                                throw new Error((data && data.error) ? data.error : ('HTTP ' + r.status));
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (data.success) {
                            if (data.reimported) {
                                alert('Published. Reloading editor G�� undo history has been reset.');
                                location.reload();
                            } else {
                                showDraftBadge(false);
                                localUndoStack.length = 0;
                                localRedoStack.length = 0;
                                setUndoRedoState(false, false);
                                publishBtn.disabled = false;
                                alert('Page published successfully.');
                            }
                        } else {
                            publishBtn.disabled = false;
                            var msg = (data && data.error) ? data.error : 'Publish failed.';
                            console.error('publish failed:', msg);
                            alert(msg);
                        }
                    })
                    .catch(function (err) {
                        console.error('publish failed:', err);
                        publishBtn.disabled = false;
                        alert(err && err.message ? err.message : 'Publish failed.');
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

        // G��G�� Code view toggle G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��
        var codeBtn = document.getElementById('editor-code-toggle-btn');
        if (codeBtn) { codeBtn.addEventListener('click', toggleCodeView); }
    }

    // G��G�� Section N G�� Code View G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

    var _inCodeView = false;
    var _codeArea = null;
    var _codeFileMode = null; // { rel } when editing a template file
    var _htmlPageMode = false; // true when page render_mode=html

    // Block type G�� HTML tag mapping (mirrors PHP BlockRegistry)
    var BLOCK_TAGS = {
        'text': 'div', 'heading': 'h2', 'image': 'figure', 'gallery': 'div',
        'html': 'div', 'section': 'section', 'columns': 'div', 'site-logo': 'div',
        'site-title': 'div', 'nav-menu': 'nav', 'map': 'div', 'event-list': 'div',
        'php-include': 'div', 'dynamic-include': 'div', 'anchor': 'a', 'document': 'span', 'element': 'div',
        'form': 'form', 'inline': 'span', 'list': 'ul', 'list-item': 'li',
        'table': 'table', 'php-code': 'div'
    };

    /**
     * Convert a block element (and children) to clean publishable HTML.
     */
    function blockToHtml(block, indent) {
        indent = indent || '';
        var type = block.dataset.blockType || 'text';
        var config = {};
        if (block.dataset.blockConfig) {
            try { config = JSON.parse(block.dataset.blockConfig); } catch (e) { }
        }

        var tag = config._tag || BLOCK_TAGS[type] || 'div';

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
            btn.textContent = _codeFileMode ? '+� Close File' : 'Blocks';
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
                    if (activeBlock && (activeBlock.dataset.blockType === 'php-include' || activeBlock.dataset.blockType === 'dynamic-include')) {
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
     * Very basic HTML formatter G�� adds newlines before block-level tags.
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
            if (!activeBlock || (activeBlock.dataset.blockType !== 'php-include' && activeBlock.dataset.blockType !== 'dynamic-include')) { return; }
            var cfg = {};
            try { cfg = JSON.parse(activeBlock.dataset.blockConfig || '{}'); } catch (e) { }
            var rel = cfg.template || '';
            if (!rel) {
                alert('Select a template file first.');
                return;
            }
            window.location.href = '/admin/editor?file=' + encodeURIComponent('templates/' + rel);
        });
    }());

    // G��G�� Section M G�� Canvas resize G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

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

    // G��G�� Section L G�� Keyboard shortcuts G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��

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

    // G��G�� Section M G�� Public API G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��G��
    // Expose serialiseCanvas for the Code panel inline script.
    window.serialiseCanvasPublic = serialiseCanvas;

})();
