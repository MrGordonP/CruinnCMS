/**
 * Cruinn Admin — Menu Editor
 *
 * Handles menu item CRUD, inline edit forms, and HTML5 drag-and-drop
 * tree reordering with nesting support.
 *
 * Depends on: utils.js (Cruinn.getCSRFToken)
 */
(function (Cruinn) {

    Cruinn.initMenuEditor = function () {
        var menuEditor = document.querySelector('.menu-item-editor');
        if (!menuEditor) return;

        var menuId = menuEditor.dataset.menuId;
        var csrfToken = Cruinn.getCSRFToken();

        // ── Sidebar panel accordion toggles ────────────────────────

        document.querySelectorAll('.menu-source-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = this.closest('.menu-source-panel');
                panel.classList.toggle('collapsed');
            });
        });

        // ── Select All checkboxes in a panel ───────────────────────

        document.querySelectorAll('.btn-select-all').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panelId = this.dataset.panel;
                var panel = document.getElementById('panel-' + panelId);
                var boxes = panel.querySelectorAll('.source-check');
                var allChecked = Array.prototype.every.call(boxes, function (cb) { return cb.checked; });
                boxes.forEach(function (cb) { cb.checked = !allChecked; });
            });
        });

        // ── Helper: POST item to menu and reload ───────────────────

        function addMenuItem(body) {
            body._csrf_token = csrfToken;
            fetch('/admin/menus/' + menuId + '/items', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new URLSearchParams(body),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown'));
                    }
                })
                .catch(function (err) { alert('Error adding item: ' + err.message); });
        }

        // ── Add checked items (pages / routes / subjects) ──────────

        document.querySelectorAll('.btn-add-checked').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var type = this.dataset.type;
                var panel = this.closest('.menu-source-panel');
                var checked = panel.querySelectorAll('.source-check:checked');
                if (!checked.length) { alert('Please select at least one item.'); return; }

                var queue = [];
                checked.forEach(function (cb) {
                    var body = { link_type: type, label: cb.dataset.label || '' };
                    if (type === 'page') body.page_id = cb.dataset.id;
                    else if (type === 'subject') body.subject_id = cb.dataset.id;
                    else if (type === 'route') body.route = cb.dataset.route;
                    queue.push(body);
                });

                function sendNext(i) {
                    if (i >= queue.length) { window.location.reload(); return; }
                    var b = queue[i];
                    b._csrf_token = csrfToken;
                    fetch('/admin/menus/' + menuId + '/items', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: new URLSearchParams(b),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.success) alert('Error: ' + (data.error || 'Unknown'));
                            sendNext(i + 1);
                        })
                        .catch(function (err) { alert('Error: ' + err.message); sendNext(i + 1); });
                }
                sendNext(0);
            });
        });

        // ── Add custom URL link ─────────────────────────────────────

        var customLinkBtn = document.querySelector('.btn-add-custom-link');
        if (customLinkBtn) {
            customLinkBtn.addEventListener('click', function () {
                var url = document.getElementById('custom-url').value.trim();
                var label = document.getElementById('custom-label').value.trim();
                if (!url) { alert('Please enter a URL.'); return; }
                addMenuItem({ link_type: 'url', url: url, label: label || url });
            });
        }

        // ── Add custom route ────────────────────────────────────────

        var customRouteBtn = document.querySelector('.btn-add-custom-route');
        if (customRouteBtn) {
            customRouteBtn.addEventListener('click', function () {
                var route = document.getElementById('custom-route').value.trim();
                var label = document.getElementById('custom-route-label').value.trim();
                if (!route) { alert('Please enter a route path.'); return; }
                addMenuItem({ link_type: 'route', route: route, label: label || route });
            });
        }

        // ── Edit Item (toggle form) ─────────────────────────────────

        document.querySelectorAll('.btn-edit-menu-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var itemId = this.dataset.itemId;
                var form = document.getElementById('edit-form-' + itemId);
                if (form) form.style.display = form.style.display === 'none' ? '' : 'none';
            });
        });

        // ── Cancel Edit ─────────────────────────────────────────────

        document.querySelectorAll('.btn-cancel-edit-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var itemId = this.dataset.itemId;
                var form = document.getElementById('edit-form-' + itemId);
                if (form) form.style.display = 'none';
            });
        });

        // ── Toggle type fields in edit forms ────────────────────────

        document.querySelectorAll('.mi-link-type').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var form = this.closest('.menu-tree-edit-form');
                form.querySelectorAll('.mi-field-route, .mi-field-url, .mi-field-page, .mi-field-subject').forEach(function (el) {
                    el.style.display = 'none';
                });
                var target = form.querySelector('.mi-field-' + this.value);
                if (target) target.style.display = '';
            });
        });

        // ── Save Item ───────────────────────────────────────────────

        document.querySelectorAll('.btn-save-menu-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var itemId = this.dataset.itemId;
                var form = document.getElementById('edit-form-' + itemId);
                var linkType = form.querySelector('.mi-link-type').value;

                var body = {
                    label: form.querySelector('.mi-label').value,
                    link_type: linkType,
                    parent_id: form.querySelector('.mi-parent-id').value,
                    css_class: form.querySelector('.mi-css-class').value,
                    open_new_tab: form.querySelector('.mi-new-tab').checked ? 1 : 0,
                    is_active: form.querySelector('.mi-active').checked ? 1 : 0,
                    visibility: form.querySelector('.mi-visibility') ? form.querySelector('.mi-visibility').value : 'always',
                    min_role: form.querySelector('.mi-min-role') ? form.querySelector('.mi-min-role').value : '',
                    _csrf_token: csrfToken,
                };

                if (linkType === 'route') body.route = form.querySelector('.mi-route').value;
                else if (linkType === 'page') body.page_id = form.querySelector('.mi-page-id').value;
                else if (linkType === 'subject') body.subject_id = form.querySelector('.mi-subject-id').value;
                else if (linkType === 'url') body.url = form.querySelector('.mi-url').value;

                fetch('/admin/menus/' + menuId + '/items/' + itemId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new URLSearchParams(body),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(function (err) { alert('Error saving item: ' + err.message); });
            });
        });

        // ── Delete Item ─────────────────────────────────────────────

        document.querySelectorAll('.btn-delete-menu-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var itemId = this.dataset.itemId;
                fetch('/admin/menus/' + menuId + '/items/' + itemId + '/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new URLSearchParams({ _csrf_token: csrfToken }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(function (err) { alert('Error deleting item: ' + err.message); });
            });
        });

        // ── Drag-and-Drop Reordering ────────────────────────────────

        (function initMenuDragDrop() {
            var treeRoot = document.querySelector('.menu-tree-root > .menu-tree');
            if (!treeRoot) return;

            var dragNode = null;
            var placeholder = document.createElement('li');
            placeholder.className = 'menu-tree-drop-placeholder';

            treeRoot.querySelectorAll('.menu-tree-node').forEach(function (node) {
                var handle = node.querySelector(':scope > .menu-tree-item > .menu-tree-handle');
                if (!handle) return;
                node.setAttribute('draggable', 'true');

                handle.addEventListener('mousedown', function () { node.dataset.handleGrabbed = '1'; });
                document.addEventListener('mouseup', function () { delete node.dataset.handleGrabbed; });

                node.addEventListener('dragstart', function (e) {
                    if (!node.dataset.handleGrabbed) { e.preventDefault(); return; }
                    dragNode = node;
                    node.classList.add('menu-tree-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', node.dataset.itemId);
                });

                node.addEventListener('dragend', function () {
                    if (dragNode) dragNode.classList.remove('menu-tree-dragging');
                    dragNode = null;
                    if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
                });
            });

            function getDropZone(e, node) {
                var rect = node.querySelector(':scope > .menu-tree-item').getBoundingClientRect();
                var y = e.clientY - rect.top;
                var h = rect.height;
                var x = e.clientX - rect.left;
                if (y < h * 0.25) return 'before';
                if (y > h * 0.75) return 'after';
                return x > rect.width * 0.4 ? 'inside' : 'after';
            }

            treeRoot.addEventListener('dragover', function (e) {
                if (!dragNode) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                var target = e.target.closest('.menu-tree-node');
                if (!target || target === dragNode || dragNode.contains(target)) return;

                var zone = getDropZone(e, target);
                treeRoot.querySelectorAll('.menu-tree-nest-target').forEach(function (el) {
                    el.classList.remove('menu-tree-nest-target');
                });

                if (zone === 'before') {
                    target.parentNode.insertBefore(placeholder, target);
                } else if (zone === 'after') {
                    target.parentNode.insertBefore(placeholder, target.nextElementSibling);
                } else {
                    target.classList.add('menu-tree-nest-target');
                    var children = target.querySelector(':scope > .menu-tree-children');
                    if (!children) {
                        children = document.createElement('ul');
                        children.className = 'menu-tree-children';
                        target.appendChild(children);
                    }
                    children.appendChild(placeholder);
                }
            });

            treeRoot.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragNode || !placeholder.parentNode) return;

                treeRoot.querySelectorAll('.menu-tree-nest-target').forEach(function (el) {
                    el.classList.remove('menu-tree-nest-target');
                });

                placeholder.parentNode.insertBefore(dragNode, placeholder);
                if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
                dragNode.classList.remove('menu-tree-dragging');

                persistMenuOrder();
            });

            function persistMenuOrder() {
                var items = [];
                function walk(parentEl, parentId) {
                    var nodes = parentEl.querySelectorAll(':scope > .menu-tree-node');
                    nodes.forEach(function (node, idx) {
                        items.push({
                            id: parseInt(node.dataset.itemId, 10),
                            parent_id: parentId,
                            sort_order: idx,
                        });
                        var childList = node.querySelector(':scope > .menu-tree-children');
                        if (childList) walk(childList, parseInt(node.dataset.itemId, 10));
                    });
                }
                walk(treeRoot, null);

                fetch('/admin/menus/' + menuId + '/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ items: items, _csrf_token: csrfToken }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            alert('Reorder failed: ' + (data.error || 'Unknown'));
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        alert('Reorder error: ' + err.message);
                        window.location.reload();
                    });
            }
        })();
    };

})(window.Cruinn = window.Cruinn || {});
