(function () {
    document.documentElement.classList.add('platform-layout-wide');
    if (window.innerWidth >= 1024 && localStorage.getItem('platform-sidebar-collapsed') === '1') {
        document.documentElement.classList.add('platform-sidebar-collapsed');
    }
}());
