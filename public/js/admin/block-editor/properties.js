/**
 * Cruinn Admin — Block/Zone Properties Panel — Phase 16 rebuild
 *
 * 7-group accordion: Identity | Layout | Size | Spacing | Typography | Background | Border
 *
 * Uses Cruinn.blockContext for shared state.
 * Depends on: utils.js, api.js, media-browser.js, block-editor/core.js, block-editor/undo.js
 */
(function (Cruinn) {

    // ── Group: Identity ────────────────────────────────────────

    function populateIdentity(panel, settings) {
        var g = panel.querySelector('[data-group="identity"]');
        if (!g) return;
        var nameInp = g.querySelector('[data-prop="blockName"]');
        if (nameInp) nameInp.value = settings.blockName || '';
        var idInp = g.querySelector('[data-prop="cssId"]');
        if (idInp) idInp.value = settings.cssId || '';
        var classInp = g.querySelector('[data-prop="cssClass"]');
        if (classInp) classInp.value = settings.cssClass || '';
        var roleInp = g.querySelector('[data-prop="role"]');
        if (roleInp) roleInp.value = settings.role || '';
    }

    function collectIdentity(panel, settings) {
        var g = panel.querySelector('[data-group="identity"]');
        if (!g) return;
        var nameInp = g.querySelector('[data-prop="blockName"]');
        if (nameInp && nameInp.value) settings.blockName = nameInp.value; else delete settings.blockName;
        var idInp = g.querySelector('[data-prop="cssId"]');
        if (idInp && idInp.value) settings.cssId = idInp.value; else delete settings.cssId;
        var classInp = g.querySelector('[data-prop="cssClass"]');
        if (classInp && classInp.value) settings.cssClass = classInp.value; else delete settings.cssClass;
        var roleInp = g.querySelector('[data-prop="role"]');
        if (roleInp && roleInp.value) settings.role = roleInp.value; else delete settings.role;
    }

    // ── Group: Layout ──────────────────────────────────────────

    function populateLayout(panel, settings) {
        var g = panel.querySelector('[data-group="layout"]');
        if (!g) return;
        var display = settings.display || 'block';
        var radio = g.querySelector('[data-prop="display"][value="' + display + '"]');
        if (radio) radio.checked = true;

        var gridSec = g.querySelector('.layout-grid-section');
        var flexSec = g.querySelector('.layout-flex-section');
        g.querySelectorAll('.layout-grid-section, .layout-flex-section').forEach(function (sec) {
            sec.style.display = 'none';
        });
        if (display === 'grid' && gridSec) gridSec.style.display = '';
        if (display === 'flex' && flexSec) flexSec.style.display = '';
        // shared align/justify sections (have both classes)
        g.querySelectorAll('.layout-grid-section.layout-flex-section').forEach(function (sec) {
            sec.style.display = (display === 'grid' || display === 'flex') ? '' : 'none';
        });

        var gridCols = g.querySelector('[data-prop="gridCols"]');
        if (gridCols) gridCols.value = settings.gridCols || '';
        var gridGap = g.querySelector('[data-prop="gridGap"]');
        if (gridGap) gridGap.value = settings.gridGap || '';

        // Highlight matching preset button
        var currentCols = settings.gridCols || '';
        g.querySelectorAll('.col-preset-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.cols === currentCols);
        });

        var flexDir = g.querySelector('[data-prop="flexDir"]');
        if (flexDir) flexDir.value = settings.flexDir || 'row';
        var flexWrap = g.querySelector('[data-prop="flexWrap"]');
        if (flexWrap) flexWrap.value = settings.flexWrap || 'nowrap';
        var flexGap = g.querySelector('[data-prop="flexGap"]');
        if (flexGap) flexGap.value = settings.flexGap || '';

        var alignItems = g.querySelector('[data-prop="alignItems"]');
        if (alignItems) alignItems.value = settings.alignItems || '';
        var justifyContent = g.querySelector('[data-prop="justifyContent"]');
        if (justifyContent) justifyContent.value = settings.justifyContent || '';
    }

    function collectLayout(panel, settings) {
        var g = panel.querySelector('[data-group="layout"]');
        if (!g) return;
        var displayRadio = g.querySelector('[data-prop="display"]:checked');
        if (displayRadio) settings.display = displayRadio.value; else delete settings.display;

        var gridCols = g.querySelector('[data-prop="gridCols"]');
        if (gridCols && gridCols.value) settings.gridCols = gridCols.value; else delete settings.gridCols;
        var gridGap = g.querySelector('[data-prop="gridGap"]');
        if (gridGap && gridGap.value) settings.gridGap = gridGap.value; else delete settings.gridGap;

        var flexDir = g.querySelector('[data-prop="flexDir"]');
        if (flexDir && flexDir.value) settings.flexDir = flexDir.value; else delete settings.flexDir;
        var flexWrap = g.querySelector('[data-prop="flexWrap"]');
        if (flexWrap && flexWrap.value) settings.flexWrap = flexWrap.value; else delete settings.flexWrap;
        var flexGap = g.querySelector('[data-prop="flexGap"]');
        if (flexGap && flexGap.value) settings.flexGap = flexGap.value; else delete settings.flexGap;

        var alignItems = g.querySelector('[data-prop="alignItems"]');
        if (alignItems && alignItems.value) settings.alignItems = alignItems.value; else delete settings.alignItems;
        var justifyContent = g.querySelector('[data-prop="justifyContent"]');
        if (justifyContent && justifyContent.value) settings.justifyContent = justifyContent.value; else delete settings.justifyContent;
    }

    // ── Group: Size ────────────────────────────────────────────

    function populateSize(panel, settings) {
        var g = panel.querySelector('[data-group="size"]');
        if (!g) return;
        var wInp = g.querySelector('[data-prop="width"]');
        if (wInp) wInp.value = settings.width || '';
        var wUnit = g.querySelector('[data-unit="widthUnit"]');
        if (wUnit) wUnit.value = settings.widthUnit || 'px';
        var hInp = g.querySelector('[data-prop="height"]');
        if (hInp) hInp.value = settings.height || '';
        var hUnit = g.querySelector('[data-unit="heightUnit"]');
        if (hUnit) hUnit.value = settings.heightUnit || 'px';
        var mwInp = g.querySelector('[data-prop="maxWidth"]');
        if (mwInp) mwInp.value = settings.maxWidth || '';
        var miwnInp = g.querySelector('[data-prop="minWidth"]');
        if (miwnInp) miwnInp.value = settings.minWidth || '';
        var mihInp = g.querySelector('[data-prop="minHeight"]');
        if (mihInp) mihInp.value = settings.minHeight || '';
        var mahInp = g.querySelector('[data-prop="maxHeight"]');
        if (mahInp) mahInp.value = settings.maxHeight || '';
    }

    function collectSize(panel, settings) {
        var g = panel.querySelector('[data-group="size"]');
        if (!g) return;
        var wInp = g.querySelector('[data-prop="width"]');
        var wUnit = g.querySelector('[data-unit="widthUnit"]');
        if (wInp && wInp.value) {
            settings.width = wInp.value;
            if (wUnit && wUnit.value !== 'px') settings.widthUnit = wUnit.value; else delete settings.widthUnit;
        } else { delete settings.width; delete settings.widthUnit; }

        var hInp = g.querySelector('[data-prop="height"]');
        var hUnit = g.querySelector('[data-unit="heightUnit"]');
        if (hInp && hInp.value) {
            settings.height = hInp.value;
            if (hUnit && hUnit.value !== 'px') settings.heightUnit = hUnit.value; else delete settings.heightUnit;
        } else { delete settings.height; delete settings.heightUnit; }

        var mwInp = g.querySelector('[data-prop="maxWidth"]');
        if (mwInp && mwInp.value) settings.maxWidth = mwInp.value; else delete settings.maxWidth;
        var miwnInp = g.querySelector('[data-prop="minWidth"]');
        if (miwnInp && miwnInp.value) settings.minWidth = miwnInp.value; else delete settings.minWidth;
        var mihInp = g.querySelector('[data-prop="minHeight"]');
        if (mihInp && mihInp.value) settings.minHeight = mihInp.value; else delete settings.minHeight;
        var mahInp = g.querySelector('[data-prop="maxHeight"]');
        if (mahInp && mahInp.value) settings.maxHeight = mahInp.value; else delete settings.maxHeight;
    }

    // ── Group: Spacing ─────────────────────────────────────────

    function populateSpacing(panel, settings) {
        var g = panel.querySelector('[data-group="spacing"]');
        if (!g) return;
        var mU = g.querySelector('[data-unit="marginUnit"]');
        if (mU) mU.value = settings.marginUnit || 'px';
        ['mt', 'mr', 'mb', 'ml'].forEach(function (p) {
            var inp = g.querySelector('[data-prop="' + p + '"]');
            var btn = g.querySelector('.prop-auto-btn[data-target="' + p + '"]');
            if (!inp) return;
            if (settings[p] === 'auto') {
                inp.value = ''; inp.disabled = true; inp.placeholder = 'auto';
                if (btn) btn.classList.add('active');
            } else {
                inp.value = settings[p] !== undefined ? settings[p] : '';
                inp.disabled = false; inp.placeholder = '0';
                if (btn) btn.classList.remove('active');
            }
        });
        var pU = g.querySelector('[data-unit="paddingUnit"]');
        if (pU) pU.value = settings.paddingUnit || 'px';
        ['pt', 'pr', 'pb', 'pl'].forEach(function (p) {
            var inp = g.querySelector('[data-prop="' + p + '"]');
            if (inp) inp.value = settings[p] !== undefined ? settings[p] : '';
        });
    }

    function collectSpacing(panel, settings) {
        var g = panel.querySelector('[data-group="spacing"]');
        if (!g) return;
        var mU = g.querySelector('[data-unit="marginUnit"]');
        if (mU && mU.value !== 'px') settings.marginUnit = mU.value; else delete settings.marginUnit;
        ['mt', 'mr', 'mb', 'ml'].forEach(function (p) {
            var btn = g.querySelector('.prop-auto-btn[data-target="' + p + '"]');
            if (btn && btn.classList.contains('active')) { settings[p] = 'auto'; return; }
            var inp = g.querySelector('[data-prop="' + p + '"]');
            if (inp && inp.value !== '') {
                var v = parseFloat(inp.value);
                if (!isNaN(v)) settings[p] = v; else delete settings[p];
            } else { delete settings[p]; }
        });
        var pU = g.querySelector('[data-unit="paddingUnit"]');
        if (pU && pU.value !== 'px') settings.paddingUnit = pU.value; else delete settings.paddingUnit;
        ['pt', 'pr', 'pb', 'pl'].forEach(function (p) {
            var inp = g.querySelector('[data-prop="' + p + '"]');
            if (inp && inp.value !== '') {
                var v = parseFloat(inp.value);
                if (!isNaN(v)) settings[p] = v; else delete settings[p];
            } else { delete settings[p]; }
        });
    }

    // ── Group: Typography ──────────────────────────────────────

    function populateTypography(panel, settings) {
        var g = panel.querySelector('[data-group="typography"]');
        if (!g) return;
        g.querySelectorAll('[data-prop="textColor"]').forEach(function (inp) {
            inp.value = settings.textColor || (inp.type === 'color' ? '#000000' : '');
        });
        var fsInp = g.querySelector('[data-prop="fontSize"]');
        if (fsInp) fsInp.value = settings.fontSize || '';
        var fsUnit = g.querySelector('[data-unit="fontSizeUnit"]');
        if (fsUnit) fsUnit.value = settings.fontSizeUnit || 'px';
        var fwInp = g.querySelector('[data-prop="fontWeight"]');
        if (fwInp) fwInp.value = settings.fontWeight || '';
        var taInp = g.querySelector('[data-prop="textAlign"]');
        if (taInp) taInp.value = settings.textAlign || '';
        var lhInp = g.querySelector('[data-prop="lineHeight"]');
        if (lhInp) lhInp.value = settings.lineHeight || '';
    }

    function collectTypography(panel, settings) {
        var g = panel.querySelector('[data-group="typography"]');
        if (!g) return;
        var tcInp = g.querySelector('input[type="text"][data-prop="textColor"]');
        if (tcInp && tcInp.value && tcInp.value !== '#000000') settings.textColor = tcInp.value;
        else delete settings.textColor;
        var fsInp = g.querySelector('[data-prop="fontSize"]');
        var fsUnit = g.querySelector('[data-unit="fontSizeUnit"]');
        if (fsInp && fsInp.value) {
            settings.fontSize = fsInp.value;
            if (fsUnit && fsUnit.value !== 'px') settings.fontSizeUnit = fsUnit.value;
            else delete settings.fontSizeUnit;
        } else { delete settings.fontSize; delete settings.fontSizeUnit; }
        var fwInp = g.querySelector('[data-prop="fontWeight"]');
        if (fwInp && fwInp.value) settings.fontWeight = fwInp.value; else delete settings.fontWeight;
        var taInp = g.querySelector('[data-prop="textAlign"]');
        if (taInp && taInp.value) settings.textAlign = taInp.value; else delete settings.textAlign;
        var lhInp = g.querySelector('[data-prop="lineHeight"]');
        if (lhInp && lhInp.value) settings.lineHeight = lhInp.value; else delete settings.lineHeight;
    }

    // ── Group: Background ──────────────────────────────────────

    function populateBackground(panel, settings) {
        var g = panel.querySelector('[data-group="background"]');
        if (!g) return;
        g.querySelectorAll('[data-prop="bgColor"]').forEach(function (inp) {
            inp.value = settings.bgColor || (inp.type === 'color' ? '#ffffff' : '');
        });
        var bgImgInp = g.querySelector('[data-prop="bgImage"]');
        if (bgImgInp) bgImgInp.value = settings.bgImage || '';
        var bgSizeInp = g.querySelector('[data-prop="bgSize"]');
        if (bgSizeInp) bgSizeInp.value = settings.bgSize || 'cover';
        var bgPosInp = g.querySelector('[data-prop="bgPos"]');
        if (bgPosInp) bgPosInp.value = settings.bgPos || 'center center';
        var bgRepInp = g.querySelector('[data-prop="bgRepeat"]');
        if (bgRepInp) bgRepInp.value = settings.bgRepeat || 'no-repeat';
    }

    function collectBackground(panel, settings) {
        var g = panel.querySelector('[data-group="background"]');
        if (!g) return;
        var bgInp = g.querySelector('input[type="text"][data-prop="bgColor"]');
        if (bgInp && bgInp.value && bgInp.value !== '#ffffff') settings.bgColor = bgInp.value;
        else delete settings.bgColor;
        var bgImgInp = g.querySelector('[data-prop="bgImage"]');
        if (bgImgInp && bgImgInp.value) {
            settings.bgImage = bgImgInp.value;
            var bgSizeInp = g.querySelector('[data-prop="bgSize"]');
            settings.bgSize = bgSizeInp ? bgSizeInp.value || 'cover' : 'cover';
            var bgPosInp = g.querySelector('[data-prop="bgPos"]');
            settings.bgPos = bgPosInp ? bgPosInp.value || 'center center' : 'center center';
            var bgRepInp = g.querySelector('[data-prop="bgRepeat"]');
            settings.bgRepeat = bgRepInp ? bgRepInp.value || 'no-repeat' : 'no-repeat';
        } else {
            delete settings.bgImage; delete settings.bgSize;
            delete settings.bgPos; delete settings.bgRepeat;
        }
    }

    // ── Group: Border ──────────────────────────────────────────

    function populateBorder(panel, settings) {
        var g = panel.querySelector('[data-group="border"]');
        if (!g) return;
        var bwInp = g.querySelector('[data-prop="borderWidth"]');
        if (bwInp) bwInp.value = settings.borderWidth || '';
        var bsInp = g.querySelector('[data-prop="borderStyle"]');
        if (bsInp) bsInp.value = settings.borderStyle || 'solid';
        var bcInp = g.querySelector('[data-prop="borderColor"]');
        if (bcInp) bcInp.value = settings.borderColor || '';
        var brInp = g.querySelector('[data-prop="borderRadius"]');
        if (brInp) brInp.value = settings.borderRadius || '';
        var brUnit = g.querySelector('[data-unit="borderRadiusUnit"]');
        if (brUnit) brUnit.value = settings.borderRadiusUnit || 'px';
    }

    function collectBorder(panel, settings) {
        var g = panel.querySelector('[data-group="border"]');
        if (!g) return;
        var bwInp = g.querySelector('[data-prop="borderWidth"]');
        var bsInp = g.querySelector('[data-prop="borderStyle"]');
        var bcInp = g.querySelector('[data-prop="borderColor"]');
        if (bwInp && bwInp.value && parseFloat(bwInp.value) > 0) {
            settings.borderWidth = parseFloat(bwInp.value);
            settings.borderStyle = bsInp ? bsInp.value || 'solid' : 'solid';
            settings.borderColor = bcInp ? bcInp.value || '#ccc' : '#ccc';
        } else {
            delete settings.borderWidth; delete settings.borderStyle; delete settings.borderColor;
        }
        var brInp = g.querySelector('[data-prop="borderRadius"]');
        var brUnit = g.querySelector('[data-unit="borderRadiusUnit"]');
        if (brInp && brInp.value && parseFloat(brInp.value) > 0) {
            settings.borderRadius = brInp.value;
            if (brUnit && brUnit.value !== 'px') settings.borderRadiusUnit = brUnit.value;
            else delete settings.borderRadiusUnit;
        } else {
            delete settings.borderRadius; delete settings.borderRadiusUnit;
        }
    }

    // ── Public: Apply visual CSS to a block element ────────────

    Cruinn.applyBlockSettings = function (blockItem, settings) {
        var target = blockItem.classList.contains('block-preview-wrap')
            ? (blockItem.querySelector('.content-block') || blockItem)
            : blockItem;
        var s = target.style;

        // Layout
        s.display = settings.display || '';
        if (settings.display === 'grid') {
            s.gridTemplateColumns = settings.gridCols || '';
            s.gap = settings.gridGap || '';
            s.flexDirection = ''; s.flexWrap = '';
        } else if (settings.display === 'flex') {
            s.flexDirection = settings.flexDir || '';
            s.flexWrap = settings.flexWrap || '';
            s.gap = settings.flexGap || '';
            s.gridTemplateColumns = '';
        } else {
            s.gridTemplateColumns = ''; s.flexDirection = ''; s.flexWrap = ''; s.gap = '';
        }
        s.alignItems = settings.alignItems || '';
        s.justifyContent = settings.justifyContent || '';

        // Size
        var wU = settings.widthUnit || 'px';
        s.width = settings.width ? settings.width + wU : '';
        var hU = settings.heightUnit || 'px';
        s.height = settings.height ? settings.height + hU : '';
        s.maxWidth = settings.maxWidth ? settings.maxWidth + 'px' : '';
        s.minWidth = settings.minWidth ? settings.minWidth + 'px' : '';
        s.minHeight = settings.minHeight ? settings.minHeight + 'px' : '';
        s.maxHeight = settings.maxHeight ? settings.maxHeight + 'px' : '';

        // Spacing
        var mU = settings.marginUnit || 'px';
        function mV(v) { return v === 'auto' ? 'auto' : (v !== undefined && v !== '' ? v + mU : ''); }
        s.marginTop = mV(settings.mt);
        s.marginRight = mV(settings.mr);
        s.marginBottom = mV(settings.mb);
        s.marginLeft = mV(settings.ml);
        var pU = settings.paddingUnit || 'px';
        s.paddingTop = settings.pt ? settings.pt + pU : '';
        s.paddingRight = settings.pr ? settings.pr + pU : '';
        s.paddingBottom = settings.pb ? settings.pb + pU : '';
        s.paddingLeft = settings.pl ? settings.pl + pU : '';

        // Typography
        s.color = settings.textColor || '';
        var fsU = settings.fontSizeUnit || 'px';
        s.fontSize = settings.fontSize ? settings.fontSize + fsU : '';
        s.fontWeight = settings.fontWeight || '';
        s.textAlign = settings.textAlign || '';
        s.lineHeight = settings.lineHeight || '';

        // Background
        s.backgroundColor = settings.bgColor || '';
        if (settings.bgImage) {
            s.backgroundImage = 'url(' + settings.bgImage + ')';
            s.backgroundSize = settings.bgSize || 'cover';
            s.backgroundPosition = settings.bgPos || 'center center';
            s.backgroundRepeat = settings.bgRepeat || 'no-repeat';
        } else {
            s.backgroundImage = ''; s.backgroundSize = '';
            s.backgroundPosition = ''; s.backgroundRepeat = '';
        }

        // Border
        s.border = (settings.borderWidth && parseFloat(settings.borderWidth) > 0)
            ? settings.borderWidth + 'px ' + (settings.borderStyle || 'solid') + ' ' + (settings.borderColor || '#ccc')
            : '';
        var brU = settings.borderRadiusUnit || 'px';
        s.borderRadius = settings.borderRadius ? settings.borderRadius + brU : '';
    };

    // ── Public: Apply visual CSS to a zone element ────────────

    Cruinn.applyZoneSettings = function (zoneEl, settings) {
        var canvas = zoneEl.querySelector('.se-zone-canvas');
        if (!canvas) return;
        var s = canvas.style;
        var mU = settings.marginUnit || 'px';
        function mV(v) { return v === 'auto' ? 'auto' : (v !== undefined && v !== '' ? v + mU : ''); }

        // maxWidth/minWidth are live-page layout concerns — never constrain the editor canvas
        s.maxWidth = '';
        s.minWidth = '';
        s.minHeight = settings.minHeight ? settings.minHeight + 'px' : '';
        s.maxHeight = settings.maxHeight ? settings.maxHeight + 'px' : '';
        s.marginLeft = mV(settings.ml);
        s.marginRight = mV(settings.mr);
        s.marginTop = mV(settings.mt);
        s.marginBottom = mV(settings.mb);

        var pU = settings.paddingUnit || 'px';
        s.paddingTop = settings.pt ? settings.pt + pU : '';
        s.paddingRight = settings.pr ? settings.pr + pU : '';
        s.paddingBottom = settings.pb ? settings.pb + pU : '';
        s.paddingLeft = settings.pl ? settings.pl + pU : '';

        s.backgroundColor = settings.bgColor || '';
        if (settings.bgImage) {
            s.backgroundImage = 'url(' + settings.bgImage + ')';
            s.backgroundSize = settings.bgSize || 'cover';
            s.backgroundPosition = settings.bgPos || 'center center';
            s.backgroundRepeat = settings.bgRepeat || 'no-repeat';
        } else {
            s.backgroundImage = ''; s.backgroundSize = '';
            s.backgroundPosition = ''; s.backgroundRepeat = '';
        }

        s.border = (settings.borderWidth && parseFloat(settings.borderWidth) > 0)
            ? settings.borderWidth + 'px ' + (settings.borderStyle || 'solid') + ' ' + (settings.borderColor || '#ccc')
            : '';
        s.borderRadius = settings.borderRadius ? settings.borderRadius + 'px' : '';
        s.color = settings.textColor || '';
        var fsU = settings.fontSizeUnit || 'px';
        s.fontSize = settings.fontSize ? settings.fontSize + fsU : '';
    };

    // ── Public: Update block selector dropdown ─────────────────

    Cruinn.updateBlockSelector = function () {
        var ctx = Cruinn.blockContext;
        var selector = document.getElementById('block-props-selector');
        if (!selector) return;
        selector.innerHTML = '<option value="">— Select block —</option>';

        document.querySelectorAll('.se-zone[data-zone]').forEach(function (z) {
            var opt = document.createElement('option');
            opt.value = 'zone:' + z.dataset.zone;
            opt.textContent = z.dataset.zone.toUpperCase() + ' Zone';
            if (ctx.propsTargetZone && ctx.propsTargetZone.dataset.zone === z.dataset.zone) opt.selected = true;
            selector.appendChild(opt);
        });

        document.querySelectorAll('.block-editor-item[data-block-id]').forEach(function (b) {
            var settings = Cruinn.parseSettings(b.dataset.settings);
            var name = settings.blockName || '';
            var type = (b.dataset.blockType || '').toUpperCase();
            var id = b.dataset.blockId || '?';
            var opt = document.createElement('option');
            opt.value = b.dataset.blockId;
            opt.textContent = type + ' #' + id + (name ? ' \u2014 ' + name : '');
            if (ctx.propsTargetBlock && ctx.propsTargetBlock.dataset.blockId === b.dataset.blockId) opt.selected = true;
            selector.appendChild(opt);
        });
    };

    // ── Group: Content (block-type specific) ───────────────────

    /**
     * Populate the Content group in the Properties panel from the block's
     * inline editor inputs (which are the authoritative source of content).
     * Delegates to the registered block type definition's populatePanel().
     */
    function populateContent(panel, blockItem, blockType) {
        var g = panel.querySelector('[data-group="content"]');
        if (!g) return;

        // Show only the content section matching this block type; hide others
        g.querySelectorAll('.editor-content-group').forEach(function (sec) {
            sec.style.display = sec.dataset.contentType === blockType ? '' : 'none';
        });
        // Legacy: also handle .content-section[data-for-type]
        g.querySelectorAll('.content-section').forEach(function (sec) {
            sec.style.display = sec.dataset.forType === blockType ? '' : 'none';
        });

        var def = Cruinn.BlockTypes && Cruinn.BlockTypes.get(blockType);
        if (def && typeof def.populatePanel === 'function') {
            def.populatePanel(g, blockItem);
        }
    }

    /** Convert a data-content-prop name to the corresponding inline <input name> */
    function contentPropToInputName(prop) {
        var direct = { menu_id: 'menu_id' };
        if (direct[prop] !== undefined) return direct[prop];
        // camelCase → snake_case with content_ prefix
        return 'content_' + prop.replace(/([A-Z])/g, function (m) { return '_' + m.toLowerCase(); });
    }

    // ── Public: Select a block ─────────────────────────────────

    Cruinn.selectBlock = function (blockItem) {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;
        if (!propsPanel || !blockItem) return;

        document.querySelectorAll('.block-props-editing').forEach(function (el) { el.classList.remove('block-props-editing'); });
        blockItem.classList.add('block-props-editing');
        ctx.propsTargetBlock = blockItem;

        var targetLabel = propsPanel.querySelector('.block-props-target-label');
        if (targetLabel) {
            var s0 = Cruinn.parseSettings(blockItem.dataset.settings);
            var name0 = s0.blockName || '';
            var type0 = (blockItem.dataset.blockType || '').toUpperCase();
            var id0 = blockItem.dataset.blockId || '?';
            targetLabel.textContent = ' \u2014 ' + type0 + ' #' + id0 + (name0 ? ' \u2014 ' + name0 : '');
        }

        var settings = Cruinn.parseSettings(blockItem.dataset.settings);
        var blockType = (blockItem.dataset.blockType || '').toLowerCase();

        // Show/hide Content group — driven by block type registry
        var contentGroup = propsPanel.querySelector('[data-group="content"]');
        if (contentGroup) {
            var def = Cruinn.BlockTypes && Cruinn.BlockTypes.get(blockType);
            var hasContent = !!(def && def.hasContent);
            contentGroup.style.display = hasContent ? '' : 'none';
            if (hasContent) populateContent(propsPanel, blockItem, blockType);
        }

        // Show/hide Layout group — driven by block type registry
        var layoutGroup = propsPanel.querySelector('[data-group="layout"]');
        if (layoutGroup) {
            var defLayout = Cruinn.BlockTypes && Cruinn.BlockTypes.get(blockType);
            layoutGroup.style.display = (defLayout && defLayout.isLayout) ? '' : 'none';
        }

        // Show/hide Save as Named Block button (section only)
        var saveNamedBtn = propsPanel.querySelector('.btn-save-named');
        if (saveNamedBtn) {
            saveNamedBtn.style.display = blockType === 'section' ? '' : 'none';
        }

        populateIdentity(propsPanel, settings);
        populateLayout(propsPanel, settings);
        populateSize(propsPanel, settings);
        populateSpacing(propsPanel, settings);
        populateTypography(propsPanel, settings);
        populateBackground(propsPanel, settings);
        populateBorder(propsPanel, settings);

        Cruinn.updateBlockSelector();
        propsPanel.style.display = '';
    };

    // ── Public: Select a zone ──────────────────────────────────

    Cruinn.selectZone = function (zoneEl) {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;
        if (!propsPanel || !zoneEl) return;

        document.querySelectorAll('.block-props-editing').forEach(function (el) { el.classList.remove('block-props-editing'); });
        ctx.propsTargetBlock = null;
        ctx.propsTargetZone = zoneEl;
        zoneEl.classList.add('block-props-editing');

        var targetLabel = propsPanel.querySelector('.block-props-target-label');
        if (targetLabel) targetLabel.textContent = ' \u2014 ' + zoneEl.dataset.zone.toUpperCase() + ' Zone';

        var settings = {};
        try { settings = JSON.parse(zoneEl.dataset.zoneSettings || '{}'); } catch (e) { }

        var layoutGroup = propsPanel.querySelector('[data-group="layout"]');
        if (layoutGroup) layoutGroup.style.display = 'none';
        var saveNamedBtn = propsPanel.querySelector('.btn-save-named');
        if (saveNamedBtn) saveNamedBtn.style.display = 'none';

        populateIdentity(propsPanel, settings);
        populateSize(propsPanel, settings);
        populateSpacing(propsPanel, settings);
        populateTypography(propsPanel, settings);
        populateBackground(propsPanel, settings);
        populateBorder(propsPanel, settings);

        Cruinn.updateBlockSelector();
        propsPanel.style.display = '';
    };

    // ── Public: Collect block settings ────────────────────────

    Cruinn.collectPropsSettings = function () {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;
        if (!ctx.propsTargetBlock) return null;

        var settings = Cruinn.parseSettings(ctx.propsTargetBlock.dataset.settings);

        collectIdentity(propsPanel, settings);
        collectLayout(propsPanel, settings);
        collectSize(propsPanel, settings);
        collectSpacing(propsPanel, settings);
        collectTypography(propsPanel, settings);
        collectBackground(propsPanel, settings);
        collectBorder(propsPanel, settings);

        return settings;
    };

    // ── Public: Collect zone settings ─────────────────────────

    Cruinn.collectZoneSettings = function () {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;
        if (!ctx.propsTargetZone) return null;

        var settings = {};
        try { settings = JSON.parse(ctx.propsTargetZone.dataset.zoneSettings || '{}'); } catch (e) { }

        collectIdentity(propsPanel, settings);
        collectSize(propsPanel, settings);
        collectSpacing(propsPanel, settings);
        collectTypography(propsPanel, settings);
        collectBackground(propsPanel, settings);
        collectBorder(propsPanel, settings);

        return settings;
    };

    // ── Public: Apply + save zone props ───────────────────────

    Cruinn.applyAndSaveZoneProps = function () {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;
        if (!ctx.propsTargetZone) return;

        var settings = Cruinn.collectZoneSettings();
        if (!settings) return;

        ctx.propsTargetZone.dataset.zoneSettings = JSON.stringify(settings);
        Cruinn.applyZoneSettings(ctx.propsTargetZone, settings);

        var indicator = propsPanel.querySelector('.block-props-save-indicator');
        var zoneName = ctx.propsTargetZone.dataset.zone;
        var entityId = ctx.parentId;

        clearTimeout(ctx.zoneSaveTimer);
        ctx.zoneSaveTimer = setTimeout(function () {
            fetch('/admin/templates/' + entityId + '/zone-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                body: new URLSearchParams({ zone: zoneName, settings: JSON.stringify(settings), _csrf_token: ctx.csrfToken }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && indicator) {
                        indicator.style.display = '';
                        setTimeout(function () { indicator.style.display = 'none'; }, 1500);
                    }
                })
                .catch(function (err) {
                    if (window.Cruinn && Cruinn.notify) Cruinn.notify('Zone save failed — ' + err.message, 'error');
                });
        }, 500);
    };

    // ── Public: Apply + save block props ──────────────────────

    Cruinn.applyAndSaveProps = function () {
        var ctx = Cruinn.blockContext;
        var propsPanel = ctx.propsPanel;

        if (ctx.propsTargetZone) { Cruinn.applyAndSaveZoneProps(); return; }
        if (!ctx.propsTargetBlock) return;

        var settings = Cruinn.collectPropsSettings();
        if (!settings) return;

        Cruinn.pushUndo('settings', ctx.propsTargetBlock);
        ctx.propsTargetBlock.dataset.settings = JSON.stringify(settings);

        // Update info bar
        var infoBar = ctx.propsTargetBlock.querySelector('.block-info-bar');
        if (infoBar) {
            var nameSpan = infoBar.querySelector('.block-info-name');
            if (settings.blockName) {
                if (!nameSpan) {
                    nameSpan = document.createElement('span');
                    nameSpan.className = 'block-info-name';
                    infoBar.appendChild(document.createTextNode(' '));
                    infoBar.appendChild(nameSpan);
                }
                nameSpan.textContent = settings.blockName;
            } else if (nameSpan) { nameSpan.remove(); }
            var existingBadge = infoBar.querySelector('.block-role-badge');
            if (settings.role) {
                var label = settings.role.replace(/-/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
                if (existingBadge) {
                    existingBadge.textContent = label;
                } else {
                    var badge = document.createElement('span');
                    badge.className = 'block-role-badge';
                    badge.textContent = label;
                    infoBar.appendChild(document.createTextNode(' '));
                    infoBar.appendChild(badge);
                }
            } else if (existingBadge) { existingBadge.remove(); }
        }

        Cruinn.applyBlockSettings(ctx.propsTargetBlock, settings);

        var indicator = propsPanel.querySelector('.block-props-save-indicator');

        clearTimeout(ctx.propsSaveTimer);
        ctx.propsSaveTimer = setTimeout(function () {
            var blockId = ctx.propsTargetBlock.dataset.blockId;
            var content = Cruinn.getBlockContent(ctx.propsTargetBlock);
            fetch('/admin/blocks/' + blockId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                body: new URLSearchParams({
                    content: JSON.stringify(content),
                    settings: JSON.stringify(settings),
                    _csrf_token: ctx.csrfToken,
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && indicator) {
                        indicator.style.display = '';
                        setTimeout(function () { indicator.style.display = 'none'; }, 1500);
                    }
                })
                .catch(function (err) {
                    if (window.Cruinn && Cruinn.notify) Cruinn.notify('Block save failed — ' + err.message, 'error');
                });
        }, 500);

        Cruinn.updateBlockSelector();
    };

    // ── Public: Initialise the properties panel ────────────────

    Cruinn.initPropertiesPanel = function () {
        var ctx = Cruinn.blockContext;
        var propsPanel = document.getElementById('block-props-panel');
        if (!propsPanel) return;
        ctx.propsPanel = propsPanel;

        // Accordion group toggles
        propsPanel.querySelectorAll('.props-group-header').forEach(function (header) {
            header.addEventListener('click', function () {
                var group = this.closest('.props-group');
                if (!group) return;
                var isOpen = group.classList.contains('open');
                group.classList.toggle('open', !isOpen);
                var chevron = this.querySelector('.pg-chevron');
                if (chevron) chevron.textContent = isOpen ? '\u25BC' : '\u25B2';
            });
        });

        // Display radio → show/hide grid/flex sub-sections
        propsPanel.querySelectorAll('[data-prop="display"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var g = this.closest('[data-group="layout"]');
                if (!g) return;
                var display = this.value;
                g.querySelectorAll('.layout-grid-section, .layout-flex-section').forEach(function (sec) {
                    sec.style.display = 'none';
                });
                if (display === 'grid') {
                    var gs = g.querySelector('.layout-grid-section:not(.layout-flex-section)');
                    if (gs) gs.style.display = '';
                } else if (display === 'flex') {
                    var fs = g.querySelector('.layout-flex-section:not(.layout-grid-section)');
                    if (fs) fs.style.display = '';
                }
                g.querySelectorAll('.layout-grid-section.layout-flex-section').forEach(function (sec) {
                    sec.style.display = (display === 'grid' || display === 'flex') ? '' : 'none';
                });
                Cruinn.applyAndSaveProps();
            });
        });

        // Column preset buttons
        propsPanel.addEventListener('click', function (e) {
            var btn = e.target.closest('.col-preset-btn');
            if (!btn) return;
            var g = btn.closest('[data-group="layout"]');
            if (!g) return;
            // Update gridCols input
            var gridColsInput = g.querySelector('[data-prop="gridCols"]');
            if (gridColsInput) gridColsInput.value = btn.dataset.cols;
            // Ensure grid display radio is checked
            var gridRadio = g.querySelector('[data-prop="display"][value="grid"]');
            if (gridRadio && !gridRadio.checked) {
                gridRadio.checked = true;
                gridRadio.dispatchEvent(new Event('change'));
            }
            // Highlight active preset
            g.querySelectorAll('.col-preset-btn').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            Cruinn.applyAndSaveProps();
        });

        // Properties toggle button
        var propsToggleBtn = document.querySelector('.btn-toggle-props');
        if (propsToggleBtn) {
            propsToggleBtn.addEventListener('click', function () {
                var isVisible = propsPanel.style.display !== 'none';
                propsPanel.style.display = isVisible ? 'none' : '';
                this.classList.toggle('active', !isVisible);
            });
        }

        // Close button
        var closePropsBtn = propsPanel.querySelector('.block-props-close');
        if (closePropsBtn) {
            closePropsBtn.addEventListener('click', function () {
                propsPanel.style.display = 'none';
                var toggleBtn = document.querySelector('.btn-toggle-props');
                if (toggleBtn) toggleBtn.classList.remove('active');
                if (ctx.propsTargetBlock) ctx.propsTargetBlock.classList.remove('block-props-editing');
                ctx.propsTargetBlock = null;
                document.querySelectorAll('.se-zone.block-props-editing').forEach(function (z) { z.classList.remove('block-props-editing'); });
                ctx.propsTargetZone = null;
            });
        }

        // Block selector dropdown
        var blockSelector = document.getElementById('block-props-selector');
        if (blockSelector) {
            blockSelector.addEventListener('change', function () {
                var val = this.value;
                if (!val) return;
                if (val.indexOf('zone:') === 0) {
                    var zoneName = val.substring(5);
                    var zoneEl = document.querySelector('.se-zone[data-zone="' + zoneName + '"]');
                    if (zoneEl) Cruinn.selectZone(zoneEl);
                    return;
                }
                var blockItem = document.querySelector('.block-editor-item[data-block-id="' + val + '"]');
                if (blockItem) {
                    ctx.propsTargetZone = null;
                    document.querySelectorAll('.se-zone.block-props-editing').forEach(function (z) { z.classList.remove('block-props-editing'); });
                    Cruinn.selectBlock(blockItem);
                    blockItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }

        // Auto-save on any panel input change
        propsPanel.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.id === 'block-props-selector') return;
            var evName = (el.type === 'radio' || el.type === 'range') ? 'change' : 'change';
            el.addEventListener(evName, Cruinn.applyAndSaveProps);
        });

        // Auto buttons for margin fields
        propsPanel.querySelectorAll('.prop-auto-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.dataset.target;
                var inp = propsPanel.querySelector('[data-prop="' + target + '"]');
                if (btn.classList.contains('active')) {
                    btn.classList.remove('active');
                    if (inp) { inp.disabled = false; inp.placeholder = '0'; inp.value = ''; }
                } else {
                    btn.classList.add('active');
                    if (inp) { inp.disabled = true; inp.value = ''; inp.placeholder = 'auto'; }
                }
                Cruinn.applyAndSaveProps();
            });
        });

        // Browse button for background image
        var propBrowseBg = propsPanel.querySelector('.prop-browse-bg');
        if (propBrowseBg) {
            propBrowseBg.addEventListener('click', function () {
                var urlInput = propsPanel.querySelector('[data-group="background"] [data-prop="bgImage"]');
                if (!urlInput) return;
                Cruinn.openMediaBrowser(function (url) { urlInput.value = url; Cruinn.applyAndSaveProps(); });
            });
        }

        // Browse button for site-logo in Content group
        var propBrowseLogo = propsPanel.querySelector('.prop-browse-logo');
        if (propBrowseLogo) {
            propBrowseLogo.addEventListener('click', function () {
                var srcInput = propsPanel.querySelector('[data-content-prop="src"]');
                if (!srcInput) return;
                Cruinn.openMediaBrowser(function (url) {
                    srcInput.value = url;
                    // Mirror to inline editor
                    var target = ctx.propsTargetBlock;
                    if (target) {
                        var inlineInp = target.querySelector('[name="content_src"]');
                        if (inlineInp) {
                            inlineInp.value = url;
                            // Update inline preview image
                            var preview = target.querySelector('.block-image-preview');
                            if (preview) preview.src = url;
                        }
                        Cruinn.autoSaveBlock(target);
                    }
                });
            });
        }

        // Content group inputs → sync to inline editor + auto-save
        propsPanel.querySelectorAll('[data-content-prop]').forEach(function (panelInp) {
            panelInp.addEventListener('change', function () {
                var target = ctx.propsTargetBlock;
                if (!target) return;
                var inputName = contentPropToInputName(this.dataset.contentProp);
                var inlineInp = target.querySelector('[name="' + inputName + '"]');
                if (inlineInp) inlineInp.value = this.value;
                Cruinn.autoSaveBlock(target);
            });
        });

        // Delete from panel
        var deleteFromPanel = propsPanel.querySelector('.btn-delete-from-panel');
        if (deleteFromPanel) {
            deleteFromPanel.addEventListener('click', function () {
                if (!ctx.propsTargetBlock) return;
                Cruinn.pushUndo('delete', ctx.propsTargetBlock);
                Cruinn.deleteBlock(ctx.propsTargetBlock.dataset.blockId, ctx.propsTargetBlock);
            });
        }

        // Save as Named Block button
        var saveNamedBtn = propsPanel.querySelector('.btn-save-named');
        if (saveNamedBtn) {
            saveNamedBtn.addEventListener('click', function () {
                if (!ctx.propsTargetBlock) return;
                var name = prompt('Named Block name:');
                if (!name) return;
                var description = prompt('Description (optional):') || '';
                Cruinn.saveAsNamedBlock(
                    ctx.propsTargetBlock.dataset.blockId,
                    name,
                    description
                );
            });
        }

        // Move block buttons
        propsPanel.querySelectorAll('.btn-move-block').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!ctx.propsTargetBlock) return;
                var dir = this.dataset.dir;
                var list = ctx.propsTargetBlock.parentNode;
                if (!list) return;
                var siblings = Array.from(list.querySelectorAll(':scope > .block-editor-item'));
                var idx = siblings.indexOf(ctx.propsTargetBlock);
                if (idx < 0) return;

                Cruinn.pushUndo('reorder', ctx.propsTargetBlock);

                if (dir === 'forward' && idx > 0) list.insertBefore(ctx.propsTargetBlock, siblings[idx - 1]);
                else if (dir === 'back' && idx < siblings.length - 1) siblings[idx + 1].after(ctx.propsTargetBlock);
                else if (dir === 'first' && idx > 0) list.insertBefore(ctx.propsTargetBlock, siblings[0]);
                else if (dir === 'last' && idx < siblings.length - 1) list.appendChild(ctx.propsTargetBlock);

                var order = Array.from(list.querySelectorAll(':scope > .block-editor-item'))
                    .map(function (el) { return parseInt(el.dataset.blockId); });
                fetch('/admin/blocks/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: JSON.stringify({ order: order, _csrf_token: ctx.csrfToken }),
                }).catch(function (err) {
                    if (window.Cruinn && Cruinn.notify) Cruinn.notify('Reorder failed — ' + err.message, 'error');
                });
                Cruinn.updateBlockSelector();
            });
        });

        // Colour picker ↔ text input sync (background)
        var bgColorPicker = propsPanel.querySelector('[data-group="background"] input[type="color"][data-prop="bgColor"]');
        var bgColorText = propsPanel.querySelector('[data-group="background"] input[type="text"][data-prop="bgColor"]');
        if (bgColorPicker && bgColorText) {
            bgColorPicker.addEventListener('input', function () { bgColorText.value = this.value; });
            bgColorText.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(this.value)) bgColorPicker.value = this.value;
            });
        }

        // Colour picker ↔ text input sync (text colour)
        var tcPicker = propsPanel.querySelector('[data-group="typography"] input[type="color"][data-prop="textColor"]');
        var tcText = propsPanel.querySelector('[data-group="typography"] input[type="text"][data-prop="textColor"]');
        if (tcPicker && tcText) {
            tcPicker.addEventListener('input', function () { tcText.value = this.value; });
            tcText.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(this.value)) tcPicker.value = this.value;
            });
        }

        // Block item click → select block
        document.querySelectorAll('.block-editor-item').forEach(function (blockItem) {
            blockItem.addEventListener('click', function (e) {
                var tag = e.target.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON' || tag === 'OPTION' ||
                    e.target.isContentEditable || e.target.closest('[contenteditable]') || e.target.closest('button')) {
                    return;
                }
                e.stopPropagation();
                ctx.propsTargetZone = null;
                document.querySelectorAll('.se-zone.block-props-editing').forEach(function (z) { z.classList.remove('block-props-editing'); });
                Cruinn.selectBlock(this);
            });
        });

        // Zone label click → select zone
        document.querySelectorAll('.se-zone-label').forEach(function (label) {
            label.addEventListener('click', function (e) {
                e.stopPropagation();
                var zoneEl = this.closest('.se-zone');
                if (zoneEl) Cruinn.selectZone(zoneEl);
            });
        });

        // Apply saved zone settings on page load
        document.querySelectorAll('.se-zone[data-zone-settings]').forEach(function (zoneEl) {
            var s = {};
            try { s = JSON.parse(zoneEl.dataset.zoneSettings || '{}'); } catch (e) { }
            if (Object.keys(s).length > 0) Cruinn.applyZoneSettings(zoneEl, s);
        });

        // Apply saved block settings on page load
        document.querySelectorAll('.block-editor-item[data-settings]').forEach(function (item) {
            var s = Cruinn.parseSettings(item.dataset.settings);
            if (Object.keys(s).length > 0) Cruinn.applyBlockSettings(item, s);
        });

        // php-include: wire template picker → auto-detect variables
        var phpIncludeSection = propsPanel.querySelector('.editor-content-group[data-content-type="php-include"]');
        if (phpIncludeSection) {
            var phpPicker = phpIncludeSection.querySelector('.php-include-tpl-picker');
            if (phpPicker) {
                phpPicker.addEventListener('change', function () {
                    Cruinn.BlockTypes._phpIncludeOnPickerChange(phpIncludeSection, phpPicker);
                });
            }
        }

        Cruinn.updateBlockSelector();
    };

})(window.Cruinn = window.Cruinn || {});
