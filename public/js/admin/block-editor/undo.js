/**
 * Cruinn Admin — Undo / Redo System
 *
 * 50-step undo/redo for settings changes, block reorders, and deletions.
 * Uses Cruinn.blockContext for shared state.
 * Depends on: utils.js, block-editor/core.js, block-editor/properties.js
 */
(function (Cruinn) {

    // ── Button state ───────────────────────────────────────────

    Cruinn.updateUndoButtons = function () {
        var ctx = Cruinn.blockContext;
        var undoBtn = document.querySelector('.btn-undo');
        var redoBtn = document.querySelector('.btn-redo');
        if (undoBtn) undoBtn.disabled = ctx.undoStack.length === 0;
        if (redoBtn) redoBtn.disabled = ctx.redoStack.length === 0;
    };

    // ── Push onto the undo stack ───────────────────────────────

    Cruinn.pushUndo = function (actionType, blockItem) {
        var ctx = Cruinn.blockContext;
        var entry = {
            type: actionType,
            blockId: blockItem ? blockItem.dataset.blockId : null,
            timestamp: Date.now(),
        };

        if (actionType === 'settings') {
            entry.prevSettings = blockItem.dataset.settings || '{}';
        } else if (actionType === 'content') {
            entry.prevContent = JSON.stringify(Cruinn.getBlockContent(blockItem));
            entry.prevSettings = blockItem.dataset.settings || '{}';
        } else if (actionType === 'reorder') {
            var list = blockItem.parentNode;
            entry.prevOrder = Array.from(list.querySelectorAll(':scope > .block-editor-item')).map(function (el) { return el.dataset.blockId; });
            entry.listParent = list;
        } else if (actionType === 'delete') {
            entry.outerHTML = blockItem.outerHTML;
            entry.parentNode = blockItem.parentNode;
            entry.nextSiblingId = blockItem.nextElementSibling
                ? blockItem.nextElementSibling.dataset.blockId
                : null;
        }

        ctx.undoStack.push(entry);
        if (ctx.undoStack.length > ctx.maxUndoSteps) ctx.undoStack.shift();
        ctx.redoStack = [];
        Cruinn.updateUndoButtons();
    };

    // ── Perform undo ───────────────────────────────────────────

    Cruinn.performUndo = function () {
        var ctx = Cruinn.blockContext;
        if (ctx.undoStack.length === 0) return;
        var entry = ctx.undoStack.pop();

        if (entry.type === 'settings') {
            var block = document.querySelector('.block-editor-item[data-block-id="' + entry.blockId + '"]');
            if (block) {
                ctx.redoStack.push({ type: 'settings', blockId: entry.blockId, prevSettings: block.dataset.settings });
                block.dataset.settings = entry.prevSettings;
                var settings = {};
                try { settings = JSON.parse(entry.prevSettings); } catch (e) { }
                Cruinn.applyBlockSettings(block, settings);
                if (ctx.propsTargetBlock && ctx.propsTargetBlock.dataset.blockId === entry.blockId) {
                    Cruinn.selectBlock(block);
                }
                Cruinn.autoSaveBlock(block);
            }

        } else if (entry.type === 'content') {
            var block = document.querySelector('.block-editor-item[data-block-id="' + entry.blockId + '"]');
            if (block) {
                ctx.redoStack.push({
                    type: 'content',
                    blockId: entry.blockId,
                    prevContent: JSON.stringify(Cruinn.getBlockContent(block)),
                    prevSettings: block.dataset.settings,
                });
                block.dataset.settings = entry.prevSettings;
                Cruinn.autoSaveBlock(block);
                if (window.Cruinn && Cruinn.suppressBeforeUnload) Cruinn.suppressBeforeUnload();
                location.reload();
            }

        } else if (entry.type === 'reorder') {
            var list = entry.listParent;
            if (list) {
                ctx.redoStack.push({
                    type: 'reorder',
                    blockId: entry.blockId,
                    prevOrder: Array.from(list.querySelectorAll(':scope > .block-editor-item')).map(function (el) { return el.dataset.blockId; }),
                    listParent: list,
                });
                entry.prevOrder.forEach(function (id) {
                    var el = list.querySelector('.block-editor-item[data-block-id="' + id + '"]');
                    if (el) list.appendChild(el);
                });
                fetch('/admin/blocks/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: JSON.stringify({ order: entry.prevOrder.map(Number), _csrf_token: ctx.csrfToken }),
                }).catch(function () { });
                if (Cruinn.updateBlockSelector) Cruinn.updateBlockSelector();
            }

        } else if (entry.type === 'delete') {
            ctx.redoStack.push({ type: 'delete-redo', blockId: entry.blockId });
            var temp = document.createElement('div');
            temp.innerHTML = entry.outerHTML;
            var restored = temp.firstElementChild;
            if (entry.parentNode) {
                var nextSib = entry.nextSiblingId
                    ? entry.parentNode.querySelector('.block-editor-item[data-block-id="' + entry.nextSiblingId + '"]')
                    : null;
                if (nextSib) entry.parentNode.insertBefore(restored, nextSib);
                else entry.parentNode.appendChild(restored);

                var blockSettings = Cruinn.parseSettings(restored.dataset.settings);
                fetch('/admin/blocks/' + entry.blockId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: new URLSearchParams({
                        content: JSON.stringify(Cruinn.getBlockContent(restored)),
                        settings: JSON.stringify(blockSettings),
                        _csrf_token: ctx.csrfToken,
                    }),
                }).catch(function () { if (window.Cruinn && Cruinn.suppressBeforeUnload) Cruinn.suppressBeforeUnload(); location.reload(); });

                Cruinn.bindBlockEvents(restored);
                if (Cruinn.updateBlockSelector) Cruinn.updateBlockSelector();
            }
        }

        Cruinn.updateUndoButtons();
    };

    // ── Perform redo ───────────────────────────────────────────

    Cruinn.performRedo = function () {
        var ctx = Cruinn.blockContext;
        if (ctx.redoStack.length === 0) return;
        var entry = ctx.redoStack.pop();

        if (entry.type === 'settings') {
            var block = document.querySelector('.block-editor-item[data-block-id="' + entry.blockId + '"]');
            if (block) {
                ctx.undoStack.push({ type: 'settings', blockId: entry.blockId, prevSettings: block.dataset.settings });
                block.dataset.settings = entry.prevSettings;
                var settings = {};
                try { settings = JSON.parse(entry.prevSettings); } catch (e) { }
                Cruinn.applyBlockSettings(block, settings);
                if (ctx.propsTargetBlock && ctx.propsTargetBlock.dataset.blockId === entry.blockId) {
                    Cruinn.selectBlock(block);
                }
                Cruinn.autoSaveBlock(block);
            }

        } else if (entry.type === 'reorder') {
            var list = entry.listParent;
            if (list) {
                ctx.undoStack.push({
                    type: 'reorder',
                    blockId: entry.blockId,
                    prevOrder: Array.from(list.querySelectorAll(':scope > .block-editor-item')).map(function (el) { return el.dataset.blockId; }),
                    listParent: list,
                });
                entry.prevOrder.forEach(function (id) {
                    var el = list.querySelector('.block-editor-item[data-block-id="' + id + '"]');
                    if (el) list.appendChild(el);
                });
                fetch('/admin/blocks/reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ctx.csrfToken },
                    body: JSON.stringify({ order: entry.prevOrder.map(Number), _csrf_token: ctx.csrfToken }),
                }).catch(function () { });
                if (Cruinn.updateBlockSelector) Cruinn.updateBlockSelector();
            }

        } else if (entry.type === 'delete-redo') {
            var block = document.querySelector('.block-editor-item[data-block-id="' + entry.blockId + '"]');
            if (block) {
                ctx.undoStack.push({
                    type: 'delete',
                    blockId: entry.blockId,
                    outerHTML: block.outerHTML,
                    parentNode: block.parentNode,
                    nextSiblingId: block.nextElementSibling ? block.nextElementSibling.dataset.blockId : null,
                });
                Cruinn.deleteBlock(entry.blockId, block);
            }
        }

        Cruinn.updateUndoButtons();
    };

    // ── Initialise undo/redo system ────────────────────────────

    /**
     * Bind undo/redo buttons and keyboard shortcuts.
     * Call once after DOMContentLoaded.
     */
    Cruinn.initUndoSystem = function () {
        var undoBtn = document.querySelector('.btn-undo');
        var redoBtn = document.querySelector('.btn-redo');
        if (undoBtn) undoBtn.addEventListener('click', Cruinn.performUndo);
        if (redoBtn) redoBtn.addEventListener('click', Cruinn.performRedo);

        document.addEventListener('keydown', function (e) {
            if (!document.querySelector('.block-editor')) return;

            var tag = e.target.tagName;
            var isEditing = (
                tag === 'INPUT' || tag === 'TEXTAREA' ||
                e.target.isContentEditable ||
                e.target.closest('[contenteditable]')
            );

            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                if (!isEditing) { e.preventDefault(); Cruinn.performUndo(); }
            } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                if (!isEditing) { e.preventDefault(); Cruinn.performRedo(); }
            } else if (e.key === 'Delete' || e.key === 'Backspace') {
                var ctx = Cruinn.blockContext;
                if (!isEditing && ctx.propsTargetBlock) {
                    e.preventDefault();
                    Cruinn.pushUndo('delete', ctx.propsTargetBlock);
                    Cruinn.deleteBlock(ctx.propsTargetBlock.dataset.blockId, ctx.propsTargetBlock);
                }
            }
        });

        Cruinn.updateUndoButtons();
    };

})(window.Cruinn = window.Cruinn || {});
