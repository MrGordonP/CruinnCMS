(function () {
    var root = document.documentElement;
    try {
        if (window.innerWidth >= 1024 && localStorage.getItem('platform-sidebar-collapsed') === '1') {
            root.classList.add('platform-sidebar-collapsed');
        }
        var width = localStorage.getItem('platform-width-mode');
        if (width === 'tablet' || width === 'mobile') {
            root.classList.add('platform-width-' + width);
        }
        if (localStorage.getItem('platform-footer-collapsed') === '1') {
            root.classList.add('platform-footer-collapsed');
        }
    } catch (e) {
        // Ignore storage access failures.
    }
}());
