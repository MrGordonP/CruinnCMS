(function () {
    var root = document.documentElement;
    try {
        if (localStorage.getItem('admin-layout-wide') === '1') {
            root.classList.add('admin-layout-wide');
        }
        if (localStorage.getItem('admin-sidebar-collapsed') === '1') {
            root.classList.add('admin-sidebar-collapsed');
        }
    } catch (e) {
        // Ignore storage access failures.
    }

    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('admin-sidebar');
        var backdrop = document.getElementById('admin-sidebar-backdrop');
        var sidebarBtn = document.getElementById('admin-sidebar-btn');
        var DESKTOP_BP = 1024;

        function isDesktop() {
            return window.innerWidth >= DESKTOP_BP;
        }

        function syncSidebarButtonState() {
            if (!sidebarBtn) return;
            if (isDesktop()) {
                var collapsed = root.classList.contains('admin-sidebar-collapsed');
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

        if (sidebarBtn) {
            sidebarBtn.addEventListener('click', function () {
                if (isDesktop()) {
                    var collapsed = root.classList.toggle('admin-sidebar-collapsed');
                    try { localStorage.setItem('admin-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
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

        syncSidebarButtonState();
    });
}());
