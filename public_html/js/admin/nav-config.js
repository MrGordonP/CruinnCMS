/**
 * Cruinn Admin — Navigation Configuration
 *
 * Add/remove top-level nav items and nested dropdown groups,
 * visibility toggle, and sort order updates.
 *
 * No external Cruinn dependencies.
 */
(function (Cruinn) {

    Cruinn.initNavConfig = function () {
        var navList = document.getElementById('nav-config-list');
        var navTemplate = document.getElementById('nav-item-template');
        if (!navList || !navTemplate) return;

        var navAddBtn = document.getElementById('nav-add-item');
        var navAddDropdownBtn = document.getElementById('nav-add-dropdown');
        var navCounter = 1000;

        // ── Add a new top-level item ────────────────────────────────

        if (navAddBtn) {
            navAddBtn.addEventListener('click', function () {
                var tempId = 'nav-new-' + (navCounter++);
                var html = navTemplate.innerHTML
                    .replace(/__TEMP_ID__/g, tempId)
                    .replace(/__PARENT_ID__/g, '');
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var newItem = wrapper.firstElementChild;
                navList.appendChild(newItem);
                newItem.querySelector('input[name="label[]"]').focus();
                updateNavSortOrders();
            });
        }

        // ── Add a dropdown (parent + one child) ────────────────────

        if (navAddDropdownBtn) {
            navAddDropdownBtn.addEventListener('click', function () {
                var parentTempId = 'nav-new-' + (navCounter++);
                var childTempId = 'nav-new-' + (navCounter++);

                var html = navTemplate.innerHTML
                    .replace(/__TEMP_ID__/g, parentTempId)
                    .replace(/__PARENT_ID__/g, '');
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var parentItem = wrapper.firstElementChild;
                navList.appendChild(parentItem);

                var urlInput = parentItem.querySelector('input[name="url[]"]');
                if (urlInput) urlInput.value = '#';

                var childContainer = document.createElement('div');
                childContainer.className = 'nav-config-children';
                childContainer.dataset.parent = parentTempId;
                navList.appendChild(childContainer);

                var childHtml = navTemplate.innerHTML
                    .replace(/__TEMP_ID__/g, childTempId)
                    .replace(/__PARENT_ID__/g, parentTempId);
                var childWrapper = document.createElement('div');
                childWrapper.innerHTML = childHtml.trim();
                childContainer.appendChild(childWrapper.firstElementChild);

                parentItem.querySelector('input[name="label[]"]').focus();
                updateNavSortOrders();
            });
        }

        // ── Remove item / toggle visibility (delegated) ────────────

        navList.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('.nav-remove-item');
            if (removeBtn) {
                var item = removeBtn.closest('.nav-config-item');
                if (item) {
                    var tempId = item.dataset.tempId;
                    var childContainer = navList.querySelector('.nav-config-children[data-parent="' + tempId + '"]');
                    if (childContainer) childContainer.remove();
                    item.remove();
                    updateNavSortOrders();
                }
                return;
            }

            var toggleBtn = e.target.closest('.nav-toggle-visibility');
            if (toggleBtn) {
                var item = toggleBtn.closest('.nav-config-item');
                if (item) {
                    var input = item.querySelector('.visibility-input');
                    var isVisible = input.value === '1';
                    input.value = isVisible ? '0' : '1';
                    item.classList.toggle('is-hidden', isVisible);
                    toggleBtn.textContent = isVisible ? '\uD83D\uDC41\u200D\uD83D\uDDE8' : '\uD83D\uDC41';
                }
            }
        });

        // ── Sort order update ───────────────────────────────────────

        function updateNavSortOrders() {
            var items = navList.querySelectorAll('.nav-config-item');
            items.forEach(function (item, index) {
                var input = item.querySelector('.sort-order-input');
                if (input) input.value = index;
            });
        }
    };

})(window.Cruinn = window.Cruinn || {});
