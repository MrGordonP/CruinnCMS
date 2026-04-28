(function () {
    var root = document.documentElement;
    var widthBtn = document.getElementById('admin-width-btn');

    function syncWidthButton() {
        if (!widthBtn) return;
        widthBtn.textContent = root.classList.contains('admin-layout-wide') ? '\u22A1' : '\u229E';
    }

    if (widthBtn) {
        widthBtn.addEventListener('click', function () {
            var wide = root.classList.toggle('admin-layout-wide');
            try {
                localStorage.setItem('admin-layout-wide', wide ? '1' : '0');
            } catch (e) {
                // Ignore storage access failures.
            }
            syncWidthButton();
        });
    }

    var currentPath = window.location.pathname;
    document.querySelectorAll('.admin-sidebar-group').forEach(function (group) {
        var flyout = group.querySelector('.admin-sidebar-flyout');
        var parent = group.querySelector('.admin-sidebar-parent');
        if (!flyout || !parent) return;

        if (flyout.querySelector('a[href="' + currentPath + '"]')) {
            group.classList.add('open');
        }

        parent.addEventListener('click', function (event) {
            // Caret toggles the flyout; link text navigates normally
            if (event.target.closest('.sidebar-caret')) {
                event.preventDefault();
                group.classList.toggle('open');
            }
        });
    });

    syncWidthButton();

    // ── Document-level event delegations ──────────────────────────────────────

    // data-confirm on <form> → gate on submit event
    document.addEventListener('submit', function (event) {
        var msg = event.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { event.preventDefault(); }
    });

    document.addEventListener('click', function (event) {
        var el = event.target;

        // data-confirm on button / link → gate on click
        var confirmEl = el.closest('[data-confirm]');
        if (confirmEl && confirmEl.tagName !== 'FORM') {
            if (!window.confirm(confirmEl.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        }

        // data-show-id → show element by id, optionally hide the trigger itself
        var showEl = el.closest('[data-show-id]');
        if (showEl) {
            var showTarget = document.getElementById(showEl.getAttribute('data-show-id'));
            if (showTarget) { showTarget.style.display = 'block'; }
            if (showEl.hasAttribute('data-hide-self')) { showEl.style.display = 'none'; }
        }

        // data-hide-id → hide element by id
        var hideEl = el.closest('[data-hide-id]');
        if (hideEl) {
            var hideTarget = document.getElementById(hideEl.getAttribute('data-hide-id'));
            if (hideTarget) { hideTarget.style.display = 'none'; }
        }

        // data-close-panel="className" → hide nearest ancestor .className + restore install button
        var closePanelEl = el.closest('[data-close-panel]');
        if (closePanelEl) {
            var panelClass = closePanelEl.getAttribute('data-close-panel');
            var panel = closePanelEl.closest('.' + panelClass);
            if (panel) { panel.style.display = 'none'; }
            var card = closePanelEl.closest('.module-card');
            if (card) {
                var restoreBtn = card.querySelector('[data-show-id][data-hide-self]');
                if (restoreBtn) { restoreBtn.style.display = ''; }
            }
        }

        // data-action="window-close"
        var wcEl = el.closest('[data-action="window-close"]');
        if (wcEl) { event.preventDefault(); window.close(); }

        // data-stop-propagation
        if (el.closest('[data-stop-propagation]')) { event.stopPropagation(); }

        // data-media-input + data-media-preview → open Cruinn media browser
        var mediaEl = el.closest('[data-media-input]');
        if (mediaEl && window.Cruinn && Cruinn.openMediaBrowser) {
            var inputId   = mediaEl.getAttribute('data-media-input');
            var previewId = mediaEl.getAttribute('data-media-preview');
            Cruinn.openMediaBrowser(function (url) {
                var inp  = document.getElementById(inputId);
                var prev = document.getElementById(previewId);
                if (inp)  { inp.value = url; }
                if (prev) { prev.src = url; prev.style.display = 'block'; }
            });
        }
    });

    // data-toggle-target + data-toggle-class on checkbox → toggle class on target when unchecked
    document.addEventListener('change', function (event) {
        var cb = event.target;
        if (!cb.dataset || !cb.dataset.toggleTarget) { return; }
        var el = document.getElementById(cb.dataset.toggleTarget);
        if (el) { el.classList.toggle(cb.dataset.toggleClass, !cb.checked); }
    });

}());
