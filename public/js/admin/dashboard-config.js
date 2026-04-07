/**
 * Cruinn Admin — Dashboard Configuration
 *
 * Widget drag-and-drop reorder, sort order inputs, visibility toggles,
 * and live preview panel.
 *
 * No external Cruinn dependencies.
 */
(function (Cruinn) {

    Cruinn.initDashboardConfig = function () {
        var widgetList = document.getElementById('widget-config-list');
        if (!widgetList) return;

        var dragItem = null;
        var placeholder = document.createElement('div');
        placeholder.className = 'widget-config-placeholder';

        // ── Drag start via handle ───────────────────────────────────

        widgetList.addEventListener('mousedown', function (e) {
            var handle = e.target.closest('.widget-config-drag-handle');
            if (!handle) return;

            e.preventDefault();
            dragItem = handle.closest('.widget-config-item');
            dragItem.classList.add('is-dragging');

            var rect = dragItem.getBoundingClientRect();
            var offsetX = e.clientX - rect.left;
            var offsetY = e.clientY - rect.top;

            placeholder.style.height = rect.height + 'px';
            dragItem.parentNode.insertBefore(placeholder, dragItem.nextSibling);

            dragItem.style.position = 'fixed';
            dragItem.style.width = rect.width + 'px';
            dragItem.style.left = rect.left + 'px';
            dragItem.style.top = rect.top + 'px';
            dragItem.style.zIndex = '1000';

            function onMouseMove(ev) {
                dragItem.style.left = (ev.clientX - offsetX) + 'px';
                dragItem.style.top = (ev.clientY - offsetY) + 'px';

                var items = widgetList.querySelectorAll('.widget-config-item:not(.is-dragging)');
                var insertBefore = null;
                for (var i = 0; i < items.length; i++) {
                    var r = items[i].getBoundingClientRect();
                    if (ev.clientY < r.top + r.height / 2) {
                        insertBefore = items[i];
                        break;
                    }
                }
                if (insertBefore) {
                    widgetList.insertBefore(placeholder, insertBefore);
                } else {
                    widgetList.appendChild(placeholder);
                }
            }

            function onMouseUp() {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                dragItem.classList.remove('is-dragging');
                dragItem.style.position = '';
                dragItem.style.width = '';
                dragItem.style.left = '';
                dragItem.style.top = '';
                dragItem.style.zIndex = '';

                widgetList.insertBefore(dragItem, placeholder);
                if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
                dragItem = null;

                updateSortOrders();
                updatePreview();
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        // ── Update sort order hidden inputs ─────────────────────────

        function updateSortOrders() {
            var items = widgetList.querySelectorAll('.widget-config-item');
            items.forEach(function (item, index) {
                var input = item.querySelector('.sort-order-input');
                if (input) input.value = index;
            });
        }

        // ── Visibility toggles ──────────────────────────────────────

        widgetList.addEventListener('change', function (e) {
            if (e.target.classList.contains('widget-visibility-toggle')) {
                var item = e.target.closest('.widget-config-item');
                if (item) {
                    item.classList.toggle('is-active', e.target.checked);
                }
                updatePreview();
            }
            if (e.target.tagName === 'SELECT') {
                updatePreview();
            }
        });

        // ── Live preview update ─────────────────────────────────────

        function updatePreview() {
            var preview = document.getElementById('dashboard-preview');
            if (!preview) return;

            preview.innerHTML = '';
            var items = widgetList.querySelectorAll('.widget-config-item');
            var anyVisible = false;

            items.forEach(function (item) {
                var checkbox = item.querySelector('.widget-visibility-toggle');
                if (!checkbox || !checkbox.checked) return;

                anyVisible = true;
                var name = item.querySelector('.widget-config-name').textContent;
                var widthSelect = item.querySelector('select');
                var width = widthSelect ? widthSelect.value : 'full';

                var div = document.createElement('div');
                div.className = 'preview-widget preview-' + width;
                var label = document.createElement('div');
                label.className = 'preview-widget-label';
                label.textContent = name;
                div.appendChild(label);
                preview.appendChild(div);
            });

            if (!anyVisible) {
                var p = document.createElement('p');
                p.className = 'text-muted';
                p.style.textAlign = 'center';
                p.style.padding = 'var(--space-lg)';
                p.textContent = 'No widgets enabled. Toggle some widgets on above.';
                preview.appendChild(p);
            }
        }
    };

})(window.Cruinn = window.Cruinn || {});
