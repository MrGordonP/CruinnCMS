/**
 * Cruinn Admin — Drag-and-Drop Implementations
 *
 * Three separate drag systems unified in one file:
 *   1. Free-form canvas drag (absolute positioning)
 *   2. Structured mode reorder (sibling-based)
 *   3. Zone-aware drag (cross-zone, container drop targets)
 *
 * Depends on: utils.js, block-editor/core.js
 */
(function (Cruinn) {

    // ── 1. Free-form canvas drag ───────────────────────────────

    Cruinn.initFreeformDrag = function () {
        var ctx = Cruinn.blockContext;
        var canvas = document.getElementById('block-list');
        if (!canvas) return;

        canvas.querySelectorAll('.block-handle-move').forEach(function (handle) {
            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var item = this.closest('.block-editor-item');
                if (!item) return;

                var canvasRect = canvas.getBoundingClientRect();
                var itemRect = item.getBoundingClientRect();
                var offsetX = e.clientX - itemRect.left;
                var offsetY = e.clientY - itemRect.top;

                item.style.position = 'absolute';
                item.style.zIndex = '100';

                function onMove(ev) {
                    var x = ((ev.clientX - offsetX - canvasRect.left) / canvasRect.width) * 100;
                    var y = ev.clientY - offsetY - canvasRect.top;
                    item.style.left = Math.max(0, Math.min(95, x)) + '%';
                    item.style.top = Math.max(0, y) + 'px';
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);

                    var settings = Cruinn.parseSettings(item.dataset.settings);
                    settings.x = parseFloat(item.style.left) || 0;
                    settings.y = parseFloat(item.style.top) || 0;
                    item.dataset.settings = JSON.stringify(settings);

                    var blockId = item.dataset.blockId;
                    var content = Cruinn.getBlockContent(item);
                    fetch('/admin/blocks/' + blockId, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                        body: new URLSearchParams({
                            content: JSON.stringify(content),
                            settings: JSON.stringify(settings),
                            _csrf_token: ctx.csrfToken,
                        }),
                    });
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    };

    // ── 2. Structured mode reorder ────────────────────────────

    Cruinn.initStructuredDrag = function () {
        var ctx = Cruinn.blockContext;
        var blockList = document.getElementById('block-list');
        if (!blockList) return;

        var dragItem = null;
        var dragGhost = null;
        var dragPlaceholder = document.createElement('div');
        dragPlaceholder.className = 'block-drag-placeholder';
        var dragOffsetY = 0;

        blockList.querySelectorAll('.block-handle-move').forEach(function (handle) {
            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                dragItem = this.closest('.block-editor-item');
                if (!dragItem) return;

                // Only drag top-level blocks in structured mode
                if (dragItem.parentNode !== blockList) return;

                var rect = dragItem.getBoundingClientRect();
                dragOffsetY = e.clientY - rect.top;

                dragGhost = dragItem.cloneNode(true);
                dragGhost.className = 'block-editor-item block-drag-ghost';
                dragGhost.style.width = rect.width + 'px';
                dragGhost.style.top = rect.top + 'px';
                dragGhost.style.left = rect.left + 'px';
                document.body.appendChild(dragGhost);

                dragPlaceholder.style.height = rect.height + 'px';
                dragItem.parentNode.insertBefore(dragPlaceholder, dragItem);
                dragItem.classList.add('dragging');

                document.addEventListener('mousemove', onDragMove);
                document.addEventListener('mouseup', onDragEnd);
            });
        });

        function onDragMove(e) {
            if (!dragItem || !dragGhost) return;
            e.preventDefault();
            dragGhost.style.top = (e.clientY - dragOffsetY) + 'px';

            var siblings = [];
            for (var i = 0; i < blockList.children.length; i++) {
                var child = blockList.children[i];
                if (child.classList && child.classList.contains('block-editor-item') && !child.classList.contains('dragging')) {
                    siblings.push(child);
                }
            }

            var afterElement = null;
            siblings.forEach(function (item) {
                var rect = item.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) afterElement = item;
            });

            if (afterElement) {
                if (afterElement.nextSibling !== dragPlaceholder) afterElement.after(dragPlaceholder);
            } else if (siblings.length) {
                if (siblings[0].previousSibling !== dragPlaceholder) blockList.insertBefore(dragPlaceholder, siblings[0]);
            }
        }

        function onDragEnd() {
            if (!dragItem) return;
            document.removeEventListener('mousemove', onDragMove);
            document.removeEventListener('mouseup', onDragEnd);

            if (dragPlaceholder.parentNode) {
                blockList.insertBefore(dragItem, dragPlaceholder);
                dragPlaceholder.remove();
            }
            dragItem.classList.remove('dragging');
            if (dragGhost && dragGhost.parentNode) dragGhost.parentNode.removeChild(dragGhost);
            dragGhost = null;
            dragItem = null;

            var order = [];
            for (var i = 0; i < blockList.children.length; i++) {
                var child = blockList.children[i];
                if (child.dataset && child.dataset.blockId) order.push(parseInt(child.dataset.blockId));
            }

            fetch('/admin/blocks/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                body: JSON.stringify({ order: order, _csrf_token: ctx.csrfToken }),
            }).catch(function (err) {
                if (window.Cruinn && Cruinn.notify) Cruinn.notify('Reorder failed — ' + err.message, 'error');
            });
        }
    };

    // ── 3. Zone-aware drag-and-drop ───────────────────────────

    Cruinn.initZoneDrag = function () {
        var ctx = Cruinn.blockContext;
        var zoneCanvases = document.querySelectorAll('.se-zone-canvas');
        if (!zoneCanvases.length) return;

        var zDragItem = null;
        var zDragGhost = null;
        var zDragPlaceholder = document.createElement('div');
        zDragPlaceholder.className = 'block-drag-placeholder';
        var zDragCanvas = null;
        var zDragOffsetY = 0;
        var zDropTarget = null;

        // header/footer zone blocks are not valid container drop targets —
        // blocks dropped onto them should become siblings, not nested children
        var containerSelectors = '.block-container, .block-row-col';

        zoneCanvases.forEach(function (canvas) {
            canvas.querySelectorAll('.block-preview-handle').forEach(function (handle) {
                handle.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    zDragItem = this.closest('.block-editor-item');
                    if (!zDragItem) return;
                    zDragCanvas = zDragItem.parentNode;

                    var rect = zDragItem.getBoundingClientRect();
                    zDragOffsetY = e.clientY - rect.top;

                    zDragGhost = zDragItem.cloneNode(true);
                    zDragGhost.className = 'block-editor-item block-preview-wrap block-drag-ghost';
                    zDragGhost.style.width = rect.width + 'px';
                    zDragGhost.style.top = rect.top + 'px';
                    zDragGhost.style.left = rect.left + 'px';
                    zDragGhost.style.position = 'fixed';
                    zDragGhost.style.zIndex = '8000';
                    zDragGhost.style.pointerEvents = 'none';
                    zDragGhost.style.opacity = '0.85';
                    zDragGhost.style.boxShadow = '0 8px 24px rgba(0,0,0,0.18)';
                    zDragGhost.style.border = '2px solid var(--color-primary, #3b82f6)';
                    document.body.appendChild(zDragGhost);

                    zDragPlaceholder.style.height = rect.height + 'px';
                    zDragCanvas.insertBefore(zDragPlaceholder, zDragItem);
                    zDragItem.classList.add('dragging');

                    document.addEventListener('mousemove', onZoneDragMove);
                    document.addEventListener('mouseup', onZoneDragEnd);
                });
            });
        });

        function clearDropTargetHighlight() {
            document.querySelectorAll('.block-drop-target').forEach(function (el) {
                el.classList.remove('block-drop-target');
            });
            zDropTarget = null;
        }

        function onZoneDragMove(e) {
            if (!zDragItem || !zDragGhost) return;
            e.preventDefault();
            zDragGhost.style.top = (e.clientY - zDragOffsetY) + 'px';

            clearDropTargetHighlight();
            var elemUnder = document.elementFromPoint(e.clientX, e.clientY);
            if (elemUnder) {
                var container = elemUnder.closest(containerSelectors);
                if (container && !zDragItem.contains(container) && container !== zDragItem) {
                    var parentItem = container.closest('.block-editor-item');
                    if (parentItem && parentItem !== zDragItem) {
                        container.classList.add('block-drop-target');
                        zDropTarget = container;
                        return;
                    }
                }
            }

            // Normal sibling-based positioning
            var siblings = [];
            for (var i = 0; i < zDragCanvas.children.length; i++) {
                var child = zDragCanvas.children[i];
                if (child.classList && child.classList.contains('block-editor-item') && !child.classList.contains('dragging')) {
                    siblings.push(child);
                }
            }

            var afterElement = null;
            siblings.forEach(function (item) {
                var rect = item.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) afterElement = item;
            });

            if (afterElement) {
                if (afterElement.nextSibling !== zDragPlaceholder) afterElement.after(zDragPlaceholder);
            } else if (siblings.length) {
                if (siblings[0].previousSibling !== zDragPlaceholder) zDragCanvas.insertBefore(zDragPlaceholder, siblings[0]);
            }
        }

        function onZoneDragEnd() {
            if (!zDragItem) return;
            document.removeEventListener('mousemove', onZoneDragMove);
            document.removeEventListener('mouseup', onZoneDragEnd);

            var targetContainer = zDropTarget;
            var droppedInContainer = targetContainer !== null;
            clearDropTargetHighlight();

            if (droppedInContainer && targetContainer) {
                var parentBlockItem = targetContainer.closest('.block-editor-item');
                var parentBlockId = parentBlockItem ? parentBlockItem.dataset.blockId : null;
                var column = targetContainer.dataset.column || 0;

                if (zDragPlaceholder.parentNode) zDragPlaceholder.remove();
                zDragItem.classList.remove('dragging');
                if (zDragGhost && zDragGhost.parentNode) zDragGhost.parentNode.removeChild(zDragGhost);

                fetch('/admin/blocks/' + zDragItem.dataset.blockId + '/move', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: JSON.stringify({
                        parent_block_id: parentBlockId ? parseInt(parentBlockId) : null,
                        column: parseInt(column),
                        _csrf_token: ctx.csrfToken,
                    }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (window.Cruinn && Cruinn.suppressBeforeUnload) Cruinn.suppressBeforeUnload();
                            window.location.reload();
                        } else {
                            alert('Move failed: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(function (err) { alert('Move error: ' + err.message); });
            } else {
                if (zDragPlaceholder.parentNode) {
                    zDragCanvas.insertBefore(zDragItem, zDragPlaceholder);
                    zDragPlaceholder.remove();
                }
                zDragItem.classList.remove('dragging');
                if (zDragGhost && zDragGhost.parentNode) zDragGhost.parentNode.removeChild(zDragGhost);

                var order = [];
                for (var i = 0; i < zDragCanvas.children.length; i++) {
                    var child = zDragCanvas.children[i];
                    if (child.dataset && child.dataset.blockId) order.push(parseInt(child.dataset.blockId));
                }
                fetch('/admin/blocks/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: JSON.stringify({ order: order, _csrf_token: ctx.csrfToken }),
                }).catch(function (err) {
                    if (window.Cruinn && Cruinn.notify) Cruinn.notify('Reorder failed — ' + err.message, 'error');
                });
            }

            zDragGhost = null;
            zDragItem = null;
            zDragCanvas = null;
        }
    };

})(window.Cruinn = window.Cruinn || {});
