(function () {
    var root = document.documentElement;
    var sidebar = document.getElementById('platform-sidebar');
    var backdrop = document.getElementById('platform-sidebar-backdrop');
    var sidebarBtn = document.getElementById('platform-sidebar-btn');
    var widthBtn = document.getElementById('platform-width-btn');
    var instanceMenuBtn = document.getElementById('platform-instance-menu-toggle');
    var instanceMenuBody = document.getElementById('platform-instance-menu-body');
    var DESKTOP_BP = 1024;
    var INSTANCE_MENU_KEY = 'platform-instance-menu-open-mobile';

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

    function setInstanceMenuState(open, persist) {
        if (!instanceMenuBtn || !instanceMenuBody) return;
        instanceMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        instanceMenuBody.hidden = !open;

        if (persist && !isDesktop()) {
            try {
                localStorage.setItem(INSTANCE_MENU_KEY, open ? '1' : '0');
            } catch (e) {
                // Ignore storage access failures.
            }
        }
    }

    function getPreferredMobileInstanceMenuState() {
        try {
            var stored = localStorage.getItem(INSTANCE_MENU_KEY);
            if (stored !== null) return stored === '1';
        } catch (e) {
            // Ignore storage access failures.
        }

        return !!(instanceMenuBody && instanceMenuBody.querySelector('.platform-nav-instance.active'));
    }

    function syncInstanceMenuForViewport(force) {
        if (!instanceMenuBtn || !instanceMenuBody) return;

        if (isDesktop()) {
            setInstanceMenuState(true, false);
            return;
        }

        if (force) {
            setInstanceMenuState(getPreferredMobileInstanceMenuState(), false);
        }
    }

    function syncWidthButtonState() {
        if (!widthBtn) return;
        var mode = currentWidthMode();
        var icons = { desktop: '\u229E', tablet: '\u25AD', mobile: '\u25AF' };
        widthBtn.textContent = icons[mode];
        widthBtn.title = 'Width: ' + mode.charAt(0).toUpperCase() + mode.slice(1);
    }

    function currentWidthMode() {
        if (root.classList.contains('platform-width-mobile')) return 'mobile';
        if (root.classList.contains('platform-width-tablet')) return 'tablet';
        return 'desktop';
    }

    function setWidthMode(mode) {
        root.classList.remove('platform-width-tablet', 'platform-width-mobile');
        if (mode === 'tablet' || mode === 'mobile') {
            root.classList.add('platform-width-' + mode);
        }
        try {
            localStorage.setItem('platform-width-mode', mode);
        } catch (e) {
            // Ignore storage access failures.
        }
        syncWidthButtonState();
    }

    if (widthBtn) {
        widthBtn.addEventListener('click', function () {
            var order = ['desktop', 'tablet', 'mobile'];
            var next = order[(order.indexOf(currentWidthMode()) + 1) % order.length];
            setWidthMode(next);
        });
    }

    var footerBtn = document.getElementById('platform-footer-btn');

    function syncFooterButtonState() {
        if (!footerBtn) return;
        var collapsed = root.classList.contains('platform-footer-collapsed');
        footerBtn.textContent = collapsed ? '\u25B4' : '\u25BE';
        footerBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    if (footerBtn) {
        footerBtn.addEventListener('click', function () {
            var collapsed = root.classList.toggle('platform-footer-collapsed');
            try {
                localStorage.setItem('platform-footer-collapsed', collapsed ? '1' : '0');
            } catch (e) {
                // Ignore storage access failures.
            }
            syncFooterButtonState();
        });
    }

    if (instanceMenuBtn && instanceMenuBody) {
        instanceMenuBtn.addEventListener('click', function () {
            var open = instanceMenuBtn.getAttribute('aria-expanded') === 'true';
            setInstanceMenuState(!open, true);
        });
        syncInstanceMenuForViewport(true);
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

    var lastDesktopState = isDesktop();

    window.addEventListener('resize', function () {
        var desktopNow = isDesktop();

        if (isDesktop()) {
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
        }

        if (desktopNow !== lastDesktopState) {
            syncInstanceMenuForViewport(true);
            lastDesktopState = desktopNow;
        }

        syncSidebarButtonState();
    });

    syncWidthButtonState();
    syncFooterButtonState();
    syncSidebarButtonState();
}());
