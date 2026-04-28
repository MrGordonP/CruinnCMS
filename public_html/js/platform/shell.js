(function () {
    var root = document.documentElement;
    var sidebar = document.getElementById('platform-sidebar');
    var backdrop = document.getElementById('platform-sidebar-backdrop');
    var sidebarBtn = document.getElementById('platform-sidebar-btn');
    var widthBtn = document.getElementById('platform-width-btn');
    var DESKTOP_BP = 1024;

    function isDesktop() {
        return window.innerWidth >= DESKTOP_BP;
    }

    function syncSidebarButtonState() {
        if (!sidebarBtn) return;
        if (isDesktop()) {
            var collapsed = root.classList.contains('platform-sidebar-collapsed');
            sidebarBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            return;
        }
        var open = !!(sidebar && sidebar.classList.contains('open'));
        sidebarBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
        syncSidebarButtonState();
    }

    function syncWidthButtonState() {
        if (!widthBtn) return;
        widthBtn.textContent = root.classList.contains('platform-layout-wide') ? '\u22A1' : '\u229E';
    }

    if (widthBtn) {
        widthBtn.addEventListener('click', function () {
            var wide = root.classList.toggle('platform-layout-wide');
            localStorage.setItem('platform-layout-wide', wide ? '1' : '0');
            syncWidthButtonState();
        });
    }

    if (sidebarBtn) {
        sidebarBtn.addEventListener('click', function () {
            if (isDesktop()) {
                var collapsed = root.classList.toggle('platform-sidebar-collapsed');
                localStorage.setItem('platform-sidebar-collapsed', collapsed ? '1' : '0');
                syncSidebarButtonState();
                return;
            }

            if (sidebar) {
                var open = sidebar.classList.toggle('open');
                if (backdrop) backdrop.classList.toggle('active', open);
            }
            syncSidebarButtonState();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMobileSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (!isDesktop()) closeMobileSidebar();
            });
        });
    }

    window.addEventListener('resize', function () {
        if (isDesktop()) {
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
        }
        syncSidebarButtonState();
    });

    syncWidthButtonState();
    syncSidebarButtonState();
}());
