/**
 * PanelCollapse — shared collapsible panel logic for three-pane layouts.
 *
 * Works with both:
 *   - admin panel-layout  (.pl-sidebar, .pl-main, .pl-detail)
 *   - platform source editor (.source-panel, .source-code-pane)
 *
 * Usage:
 *   PanelCollapse.init([
 *     { panelId: 'source-panel-left',   toggleId: 'source-panel-left-toggle',   storeKey: 'cms_src_left',   side: 'left'   },
 *     { panelId: 'source-code-pane',    toggleId: 'source-panel-centre-toggle', storeKey: 'cms_src_centre', side: 'centre' },
 *     { panelId: 'source-panel-right',  toggleId: 'source-panel-right-toggle',  storeKey: 'cms_src_right',  side: 'right'  },
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
    // Attach to any .source-panel-resize element; resizes its parent panel.
    function initResize(handleId, panelId, storeKey) {
        var handle = document.getElementById(handleId);
        var panel = document.getElementById(panelId);
        if (!handle || !panel) return;

        // Restore persisted width
        if (storeKey) {
            var stored = localStorage.getItem(storeKey);
            if (stored) panel.style.width = stored + 'px';
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
                panel.style.width = newW + 'px';
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

    return { init: init, expand: expand, initResize: initResize };

}());
