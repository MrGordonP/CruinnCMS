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
            event.preventDefault();
            group.classList.toggle('open');
        });
    });

    syncWidthButton();
}());
