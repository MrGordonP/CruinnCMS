/**
 * php-include block type â€” editor-side definition.
 *
 * Properties panel:
 *   - Template picker (grouped by folder, fed from PHP_INCLUDE_TEMPLATES global)
 *   - Detected variable fields (auto-populated once a template is chosen)
 *   - Current config vars shown as editable key/value rows
 *
 * Canvas editing:
 *   - Elements with data-phpi-el are individually selectable inside the block.
 *   - Clicking a child element opens a floating mini-panel for that element's CSS class.
 *   - Style changes are stored in block_config.childStyles and auto-saved.
 *   - On publish, buildCss() emits #blockId .class-name { ... } for the live site.
 */
Cruinn.BlockTypes.register('php-include', {
    label: 'PHP Include',
    tag: 'div',
    isLayout: false,
    hasContent: true,

    /**
     * Read the current panel inputs and return a content object.
     * Called by getBlockContent() â†’ saved as block_config JSON.
     */
    getContent: function (blockItem) {
        // Content lives on the block's data-block-config attribute (dynamic block)
        var raw = blockItem.dataset.blockConfig;
        try { return JSON.parse(raw) || {}; } catch (e) { return {}; }
    },

    /**
     * Fill the Content accordion group with this block's current config.
     * Called by populateContent() in properties.js when this block is selected.
     */
    populatePanel: function (g, blockItem) {
        var section = g.querySelector('.editor-content-group[data-content-type="php-include"]');
        if (!section) return;

        var raw = blockItem.dataset.blockConfig;
        var cfg = {};
        try { cfg = JSON.parse(raw) || {}; } catch (e) { }

        // Populate template picker
        var picker = section.querySelector('.php-include-tpl-picker');
        if (picker) picker.value = cfg.template || '';

        // Render variable rows for the selected template
        Cruinn.BlockTypes._phpIncludeBuildVarRows(section, cfg);

        // Activate child-element editing on the canvas block
        Cruinn.BlockTypes._phpIncludeInitElements(blockItem);
    },

    /**
     * Hook called by the panel after the user changes the template picker.
     * Fetches the detected variable list and rebuilds the var rows.
     */
    _phpIncludeOnPickerChange: function (section, picker) {
        var rel = picker.value;
        var target = Cruinn.blockContext && Cruinn.blockContext.propsTargetBlock;

        // Update block_config with new template name, clear old vars
        var cfg = { template: rel };
        if (target) {
            try { var old = JSON.parse(target.dataset.blockConfig || '{}'); cfg = old; } catch (e) { }
            cfg.template = rel;
        }

        if (!rel) {
            Cruinn.BlockTypes._phpIncludeBuildVarRows(section, cfg);
            Cruinn.BlockTypes._phpIncludeSaveConfig(cfg, target, section);
            return;
        }

        // Fetch detected vars from the scan endpoint
        fetch('/admin/template-editor/vars?f=' + encodeURIComponent(rel))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // Merge: keep existing config values, add new detected vars as empty
                var vars = data.vars || [];
                vars.forEach(function (v) {
                    if (!(v in cfg) && v !== 'db' && v !== 'template' && v !== 'childStyles') {
                        cfg[v] = '';
                    }
                });
                Cruinn.BlockTypes._phpIncludeBuildVarRows(section, cfg);
                Cruinn.BlockTypes._phpIncludeSaveConfig(cfg, target, section);
            })
            .catch(function () {
                Cruinn.BlockTypes._phpIncludeBuildVarRows(section, cfg);
                Cruinn.BlockTypes._phpIncludeSaveConfig(cfg, target, section);
            });
    },

    /** Build/rebuild the editable variable keyâ†’value rows */
    _phpIncludeBuildVarRows: function (section, cfg) {
        var container = section.querySelector('.php-include-vars');
        if (!container) return;
        container.innerHTML = '';

        var varKeys = Object.keys(cfg).filter(function (k) {
            return k !== 'template' && k !== 'db' && k !== 'childStyles';
        });

        if (varKeys.length === 0) {
            container.innerHTML = '<p class="php-include-hint">Select a template to see its variables.</p>';
            return;
        }

        varKeys.forEach(function (k) {
            var row = document.createElement('div');
            row.className = 'editor-prop-row';
            row.innerHTML = '<label style="font-family:monospace;font-size:0.78rem">$' + k + '</label>'
                + '<input type="text" class="editor-prop-input php-include-var-input" data-var-key="' + k + '" value="' + (cfg[k] || '').replace(/"/g, '&quot;') + '">';
            container.appendChild(row);
        });

        // Wire up live change â†’ save
        container.querySelectorAll('.php-include-var-input').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var target = Cruinn.blockContext && Cruinn.blockContext.propsTargetBlock;
                var c = {};
                try { c = JSON.parse(target ? target.dataset.blockConfig || '{}' : '{}'); } catch (e) { }
                c[this.dataset.varKey] = this.value;
                Cruinn.BlockTypes._phpIncludeSaveConfig(c, target, section);
            });
        });
    },

    /** Persist config to the block's data attribute and trigger an auto-save */
    _phpIncludeSaveConfig: function (cfg, blockItem, section) {
        if (!blockItem) return;
        blockItem.dataset.blockConfig = JSON.stringify(cfg);

        // Refresh the canvas preview label
        var label = blockItem.querySelector('.php-include-label');
        if (label) label.textContent = cfg.template || '(none)';

        Cruinn.autoSaveBlock(blockItem);
    },

    // â”€â”€ Child-element editing â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Wire up click-to-edit on every data-phpi-el element inside a php-include block.
     * Called whenever the block is selected or the panel is populated.
     * Safe to call multiple times â€” re-entry is guarded by a flag.
     */
    _phpIncludeInitElements: function (blockItem) {
        if (blockItem._phpiInitDone) return;
        blockItem._phpiInitDone = true;

        blockItem.querySelectorAll('[data-phpi-el]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                // Don't let the click bubble up to block selection / container selection.
                e.stopPropagation();
                Cruinn.BlockTypes._phpIncludeSelectElement(blockItem, el);
            });
        });
    },

    /**
     * Show the child-element mini panel for the clicked element.
     * The panel floats near the element and contains a CSS property editor
     * scoped to that element's class(es).
     */
    _phpIncludeSelectElement: function (blockItem, el) {
        // Remove any existing panel
        var old = document.getElementById('phpi-element-panel');
        if (old) old.remove();

        var classes = (el.dataset.phpiClasses || '').trim();
        if (!classes) return;

        // Load current childStyles
        var cfg = {};
        try { cfg = JSON.parse(blockItem.dataset.blockConfig || '{}'); } catch (e) { }
        var childStyles = cfg.childStyles || {};

        // Build selector options from the element's classes
        var classList = classes.split(/\s+/).filter(Boolean);

        // Build the panel
        var panel = document.createElement('div');
        panel.id = 'phpi-element-panel';
        panel.className = 'phpi-element-panel';

        var selectorRow = '';
        if (classList.length > 1) {
            var opts = classList.map(function (c) {
                return '<option value=".' + c + '">.' + c + '</option>';
            }).join('');
            // Also offer the compound selector
            var compound = classList.map(function (c) { return '.' + c; }).join('');
            opts += '<option value="' + compound + '">' + compound + ' (all)</option>';
            selectorRow = '<div class="phpi-panel-row">'
                + '<label>Style class</label>'
                + '<select class="phpi-class-select editor-prop-input">' + opts + '</select>'
                + '</div>';
        }

        panel.innerHTML = '<div class="phpi-panel-header">'
            + '<span class="phpi-panel-title">' + classes + '</span>'
            + '<button class="phpi-panel-close" title="Close">&times;</button>'
            + '</div>'
            + '<div class="phpi-panel-body">'
            + selectorRow
            + Cruinn.BlockTypes._phpIncludePanelFields()
            + '</div>';

        // Position near the element
        var rect = el.getBoundingClientRect();
        var scrollX = window.scrollX || 0;
        var scrollY = window.scrollY || 0;
        panel.style.left = Math.min(rect.left + scrollX, window.innerWidth - 280) + 'px';
        panel.style.top  = (rect.bottom + scrollY + 6) + 'px';
        document.body.appendChild(panel);

        // Determine active selector (default: first class)
        var activeSelector = function () {
            var sel = panel.querySelector('.phpi-class-select');
            return sel ? sel.value : ('.' + classList[0]);
        };

        // Populate fields from existing childStyles for the default selector
        Cruinn.BlockTypes._phpIncludePopulateFields(panel, childStyles[activeSelector()] || {});

        // Selector change â†’ re-populate
        var selEl = panel.querySelector('.phpi-class-select');
        if (selEl) {
            selEl.addEventListener('change', function () {
                Cruinn.BlockTypes._phpIncludePopulateFields(panel, childStyles[activeSelector()] || {});
            });
        }

        // Field changes â†’ update childStyles â†’ apply inline â†’ save
        panel.querySelectorAll('[data-phpi-prop]').forEach(function (inp) {
            inp.addEventListener('input', function () {
                Cruinn.BlockTypes._phpIncludeApplyFromPanel(panel, blockItem, activeSelector, classList[0]);
            });
        });

        // Close button
        panel.querySelector('.phpi-panel-close').addEventListener('click', function () {
            panel.remove();
        });

        // Close on outside click
        function outsideClick(e) {
            if (!panel.contains(e.target) && e.target !== el) {
                panel.remove();
                document.removeEventListener('click', outsideClick, true);
            }
        }
        // Delay to avoid the current click closing the panel immediately
        setTimeout(function () {
            document.addEventListener('click', outsideClick, true);
        }, 0);

        // Highlight the selected element
        blockItem.querySelectorAll('[data-phpi-el].phpi-el-selected').forEach(function (x) {
            x.classList.remove('phpi-el-selected');
        });
        el.classList.add('phpi-el-selected');
    },

    /** Return the HTML for the CSS property input rows in the mini panel */
    _phpIncludePanelFields: function () {
        var fields = [
            { label: 'Color',        prop: 'color',            type: 'text',   placeholder: 'e.g. #333 or inherit' },
            { label: 'Font size',    prop: 'font-size',         type: 'text',   placeholder: 'e.g. 1rem or 16px' },
            { label: 'Font weight',  prop: 'font-weight',       type: 'text',   placeholder: 'e.g. 400 or bold' },
            { label: 'Line height',  prop: 'line-height',       type: 'text',   placeholder: 'e.g. 1.5' },
            { label: 'Text align',   prop: 'text-align',        type: 'select', options: ['', 'left', 'center', 'right', 'justify'] },
            { label: 'Background',   prop: 'background-color',  type: 'text',   placeholder: 'e.g. #fff or transparent' },
            { label: 'Padding',      prop: 'padding',           type: 'text',   placeholder: 'e.g. 1rem or 8px 16px' },
            { label: 'Margin',       prop: 'margin',            type: 'text',   placeholder: 'e.g. 0 auto' },
            { label: 'Display',      prop: 'display',           type: 'select', options: ['', 'block', 'flex', 'grid', 'inline', 'inline-block', 'none'] },
            { label: 'Gap',          prop: 'gap',               type: 'text',   placeholder: 'e.g. 1rem (flex/grid)' },
            { label: 'Grid columns', prop: 'grid-template-columns', type: 'text', placeholder: 'e.g. 1fr 2fr' },
            { label: 'Border',       prop: 'border',            type: 'text',   placeholder: 'e.g. 1px solid #ccc' },
            { label: 'Border radius',prop: 'border-radius',     type: 'text',   placeholder: 'e.g. 4px' },
            { label: 'Width',        prop: 'width',             type: 'text',   placeholder: 'e.g. 100% or 300px' },
            { label: 'Max width',    prop: 'max-width',         type: 'text',   placeholder: 'e.g. 600px' },
        ];

        return fields.map(function (f) {
            var input;
            if (f.type === 'select') {
                var opts = f.options.map(function (o) {
                    return '<option value="' + o + '">' + (o || 'â€”') + '</option>';
                }).join('');
                input = '<select class="editor-prop-input" data-phpi-prop="' + f.prop + '">' + opts + '</select>';
            } else {
                input = '<input type="text" class="editor-prop-input" data-phpi-prop="' + f.prop + '" placeholder="' + (f.placeholder || '') + '">';
            }
            return '<div class="phpi-panel-row"><label>' + f.label + '</label>' + input + '</div>';
        }).join('');
    },

    /** Populate panel fields from a props object */
    _phpIncludePopulateFields: function (panel, props) {
        panel.querySelectorAll('[data-phpi-prop]').forEach(function (inp) {
            inp.value = props[inp.dataset.phpiProp] || '';
        });
    },

    /** Read panel fields, update childStyles, apply inline styles, auto-save */
    _phpIncludeApplyFromPanel: function (panel, blockItem, getSelector, fallbackClass) {
        var selector = getSelector();

        // Collect non-empty props from the panel
        var props = {};
        panel.querySelectorAll('[data-phpi-prop]').forEach(function (inp) {
            if (inp.value.trim() !== '') {
                props[inp.dataset.phpiProp] = inp.value.trim();
            }
        });

        // Update block_config.childStyles
        var cfg = {};
        try { cfg = JSON.parse(blockItem.dataset.blockConfig || '{}'); } catch (e) { }
        if (!cfg.childStyles) cfg.childStyles = {};

        if (Object.keys(props).length > 0) {
            cfg.childStyles[selector] = props;
        } else {
            delete cfg.childStyles[selector];
        }
        blockItem.dataset.blockConfig = JSON.stringify(cfg);

        // Apply styles as a live <style> injection scoped to this block.
        // On publish, buildCss() will emit the same rules as proper CSS.
        var styleId = 'phpi-live-' + blockItem.id;
        var styleTag = document.getElementById(styleId);
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = styleId;
            document.head.appendChild(styleTag);
        }

        var css = '';
        var allChildStyles = cfg.childStyles || {};
        Object.keys(allChildStyles).forEach(function (sel) {
            var p = allChildStyles[sel];
            var rules = Object.keys(p).map(function (prop) {
                return prop + ':' + p[prop];
            }).join(';');
            if (rules) {
                css += '#' + blockItem.id + ' ' + sel + '{' + rules + '}\n';
            }
        });
        styleTag.textContent = css;

        // Debounced auto-save
        clearTimeout(blockItem._phpiSaveTimer);
        blockItem._phpiSaveTimer = setTimeout(function () {
            Cruinn.autoSaveBlock(blockItem);
        }, 600);
    },
});
