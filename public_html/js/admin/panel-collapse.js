/**
 * PanelCollapse — shared collapsible panel logic for three-pane layouts.
 *
 * Works with the shared pl-panel-* classes defined in admin-panel-layout.css.
 * Used by: block editor, platform source editor, and any other three-pane layout.
 *
 * Usage:
 *   PanelCollapse.init([
 *     { panelId: 'editor-left',  toggleId: 'pl-panel-left-toggle',  storeKey: 'ed_left',  side: 'left'   },
 *     { panelId: 'editor-props', toggleId: 'pl-panel-right-toggle', storeKey: 'ed_right', side: 'right'  },
 *   ]);
 *
 * Panel config keys:
 *   panelId   — id of the element that gets .collapsed
 *   toggleId  — id of the toggle <button>
 *   storeKey  — localStorage key (null = no persistence)
 *   side      — 'left' | 'centre' | 'right'  (controls icon direction)
 *
 * Externally callable:
 *   PanelCollapse.expand(panelId)  — force-expand a panel by its DOM id
 */
var PanelCollapse = (function () {

    var ICONS = {
        left: { collapsed: '\u25B6', expanded: '\u25C0' },  // ▶ ◀
        centre: { collapsed: '\u25B6', expanded: '\u25C0' },  // ▶ ◀  (collapse left)
        right: { collapsed: '\u25C0', expanded: '\u25B6' },  // ◀ ▶
    };

    // registry: id → { panel, btn, storeKey, side }
    var _panels = {};

    function _syncLayoutWidth(panel, width) {
        if (!panel) return;
        panel.style.width = width + 'px';

        var layout = panel.closest('.panel-layout');
        if (!layout) return;

        if (panel.classList.contains('pl-panel-left')) {
            layout.style.setProperty('--pl-left-width', width + 'px');
        } else if (panel.classList.contains('pl-panel-right')) {
            layout.style.setProperty('--pl-right-width', width + 'px');
        }
    }

    function _apply(id, collapsed) {
        var entry = _panels[id];
        if (!entry) return;
        var panel = entry.panel;
        var btn = entry.btn;
        var icons = ICONS[entry.side] || ICONS.left;

        if (collapsed) {
            panel.classList.add('collapsed');
            if (btn) {
                btn.textContent = icons.collapsed;
                btn.title = 'Expand';
            }
        } else {
            panel.classList.remove('collapsed');
            if (btn) {
                btn.textContent = icons.expanded;
                btn.title = 'Collapse';
            }
        }
    }

    function _toggle(id) {
        var entry = _panels[id];
        if (!entry) return;
        var collapsed = !entry.panel.classList.contains('collapsed');
        _apply(id, collapsed);
        if (entry.storeKey) {
            try { localStorage.setItem(entry.storeKey, collapsed ? '1' : '0'); } catch (e) { /* ignore */ }
        }
    }

    function init(configs) {
        configs.forEach(function (cfg) {
            var panel = document.getElementById(cfg.panelId);
            var btn = cfg.toggleId ? document.getElementById(cfg.toggleId) : null;
            if (!panel) return;

            _panels[cfg.panelId] = {
                panel: panel,
                btn: btn,
                storeKey: cfg.storeKey || null,
                side: cfg.side || 'left',
            };

            // Restore persisted state
            var stored = cfg.storeKey ? localStorage.getItem(cfg.storeKey) : null;
            _apply(cfg.panelId, stored === '1');

            if (btn) {
                btn.addEventListener('click', function () {
                    _toggle(cfg.panelId);
                });
            }
        });
    }

    function expand(panelId) {
        _apply(panelId, false);
        var entry = _panels[panelId];
        if (entry && entry.storeKey) {
            try { localStorage.setItem(entry.storeKey, '0'); } catch (e) { /* ignore */ }
        }
    }

    // ── Drag-resize handles ─────────────────────────────────────────
    // Attach to any .pl-panel-resize element; resizes its parent panel.
    function initResize(handleId, panelId, storeKey) {
        var handle = document.getElementById(handleId);
        var panel = document.getElementById(panelId);
        if (!handle || !panel) return;

        // Restore persisted width
        if (storeKey) {
            var stored = localStorage.getItem(storeKey);
            if (stored) _syncLayoutWidth(panel, parseInt(stored, 10));
        }

        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            var startX = e.clientX;
            var startWidth = panel.getBoundingClientRect().width;
            handle.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMove(e) {
                var delta = e.clientX - startX;
                var newW = Math.max(140, Math.min(480, startWidth + delta));
                _syncLayoutWidth(panel, newW);
            }

            function onUp() {
                handle.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (storeKey) {
                    try { localStorage.setItem(storeKey, Math.round(panel.getBoundingClientRect().width)); } catch (e) { /* ignore */ }
                }
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    function _ensureToggleButton(panel, side) {
        var header = panel.querySelector('.pl-panel-header');
        if (!header) return null;

        var btn = header.querySelector('.pl-panel-toggle');
        if (btn) return btn;

        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pl-panel-toggle';
        btn.title = 'Collapse';

        if (side === 'right') {
            header.insertBefore(btn, header.firstChild);
        } else {
            header.appendChild(btn);
        }

        return btn;
    }

    function autoInitLayout(layout) {
        if (!layout) return;

        var panels = layout.querySelectorAll(':scope > .pl-panel, :scope > .pl-main');

        panels.forEach(function (panel) {
            if (!panel || _panels[panel.id]) {
                return;
            }

            var side = panel.classList.contains('pl-panel-right') ? 'right' : 'left';
            var panelId = panel.id;
            if (!panelId) {
                var layoutId = layout.id ? layout.id : 'panel-layout';
                panelId = layoutId + '-' + side;
                var suffix = 2;
                while (document.getElementById(panelId)) {
                    panelId = layoutId + '-' + side + '-' + suffix;
                    suffix++;
                }
                panel.id = panelId;
            }

            var btn = _ensureToggleButton(panel, side);
            if (!btn) return;

            if (!btn.id) {
                btn.id = panelId + '-toggle';
            }

            init([{
                panelId: panelId,
                toggleId: btn.id,
                storeKey: panelId + '-collapsed',
                side: side,
            }]);

            var resize = panel.querySelector('.pl-panel-resize');
            if (resize && panel.classList.contains('pl-panel-left')) {
                if (!resize.id) {
                    resize.id = panelId + '-resize';
                }
                initResize(resize.id, panelId, panelId + '_w');
            }
        });
    }

    function bootstrap() {
        document.querySelectorAll('.panel-layout').forEach(autoInitLayout);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    return { init: init, expand: expand, initResize: initResize };

}());
